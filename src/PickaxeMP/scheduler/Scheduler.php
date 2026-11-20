<?php

declare(strict_types=1);

namespace PickaxeMP\scheduler;

use PickaxeMP\exception\SchedulerException;
use PickaxeMP\utils\Logger;

/**
 * Tick tabanlı görev zamanlayıcısı.
 *
 * Tasarım kararları:
 *  - Gerçek zaman yerine tick sayısı kullanılır; testler deterministiktir.
 *  - Gecikme en az 1 tick'tir. Bu, bir görevin kendi içinde 0 gecikmeyle
 *    yeni görev üretip sonsuz döngü oluşturmasını engeller.
 *  - Bir görev istisna fırlatırsa yalnızca o görev raporlanır; zamanlayıcı
 *    ve diğer görevler çalışmaya devam eder. Tek bir hatalı eklenti sunucuyu
 *    düşürmemelidir.
 *  - Çalıştırma sırasında iptal edilen görev, o tick içinde çalıştırılmaz.
 */
final class Scheduler
{
    /** Bir run() çağrısında çalıştırılabilecek en fazla görev sayısı. */
    private const MAX_EXECUTIONS_PER_RUN = 10000;

    /** @var array<string, Task> */
    private array $tasks = [];

    private int $sequence = 0;

    private int $autoIdCounter = 0;

    public function __construct(private readonly ?Logger $logger = null)
    {
    }

    /**
     * Yeni görev ekler.
     *
     * @param callable $callback
     * @param int $delayTicks İlk çalışma gecikmesi (>= 1)
     * @param int|null $periodTicks null ise tek seferlik görev
     * @param string|null $id Verilmezse otomatik üretilir
     * @return string Görev kimliği
     */
    public function schedule(callable $callback, int $delayTicks, ?int $periodTicks = null, ?string $id = null): string
    {
        if ($delayTicks < 1) {
            throw SchedulerException::invalidDelay($delayTicks);
        }

        if ($periodTicks !== null && $periodTicks < 1) {
            throw SchedulerException::invalidPeriod($periodTicks);
        }

        $id ??= 'task-' . (++$this->autoIdCounter);

        if (isset($this->tasks[$id])) {
            throw SchedulerException::duplicateId($id);
        }

        $this->tasks[$id] = new Task(
            id: $id,
            callback: $callback,
            nextTick: $delayTicks,
            periodTicks: $periodTicks,
            sequence: $this->sequence++
        );

        return $id;
    }

    public function cancel(string $id): bool
    {
        if (!isset($this->tasks[$id])) {
            return false;
        }

        unset($this->tasks[$id]);

        return true;
    }

    public function cancelAll(): void
    {
        $this->tasks = [];
    }

    public function has(string $id): bool
    {
        return isset($this->tasks[$id]);
    }

    public function count(): int
    {
        return count($this->tasks);
    }

    /**
     * @return list<string>
     */
    public function pendingIds(): array
    {
        return array_keys($this->tasks);
    }

    /**
     * Verilen tick'e kadar zamanı gelmiş görevleri çalıştırır.
     *
     * @param int $nowTick Geçerli tick numarası (mutlak, artan)
     * @return int Çalıştırılan görev sayısı
     */
    public function run(int $nowTick): int
    {
        if ($nowTick < 0) {
            throw SchedulerException::invalidDelay($nowTick);
        }

        $due = [];

        foreach ($this->tasks as $task) {
            if ($task->nextTick <= $nowTick) {
                $due[] = $task;
            }
        }

        if ($due === []) {
            return 0;
        }

        usort(
            $due,
            static fn (Task $a, Task $b): int => [$a->nextTick, $a->sequence] <=> [$b->nextTick, $b->sequence]
        );

        $executed = 0;

        foreach ($due as $task) {
            if ($executed >= self::MAX_EXECUTIONS_PER_RUN) {
                $this->logger?->error('Zamanlayici tek tick icinde cok fazla gorev calistirdi, kalanlar ertelendi.', [
                    'executed' => $executed,
                ]);

                break;
            }

            // Çalıştırma sırasında iptal edilmiş olabilir.
            if (!isset($this->tasks[$task->id])) {
                continue;
            }

            $this->invoke($task);
            $executed++;

            if ($task->isPeriodic()) {
                $period = (int) $task->periodTicks;

                // Geride kalmış tikleri topluca yakmamak için bir sonraki
                // çalışmayı şimdiden sonraya sabitliyoruz.
                $task->nextTick = max($task->nextTick + $period, $nowTick + $period);
                continue;
            }

            unset($this->tasks[$task->id]);
        }

        return $executed;
    }

    private function invoke(Task $task): void
    {
        try {
            ($task->callback)($this, $task);
        } catch (\Throwable $throwable) {
            $this->logger?->error('Zamanlanmis gorev hata verdi.', [
                'task' => $task->id,
                'error' => $throwable->getMessage(),
                'type' => $throwable::class,
            ]);
        }
    }
}
