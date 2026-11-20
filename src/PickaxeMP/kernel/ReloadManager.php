<?php

declare(strict_types=1);

namespace PickaxeMP\kernel;

use PickaxeMP\utils\Logger;

/**
 * Merkezi reload orkestratörü.
 *
 * Akış: sırala → hepsini hazırla → hepsini doğrula → hepsini uygula.
 * Herhangi bir adımda hata çıkarsa, o ana kadar hazırlanmış TÜM modüller ters
 * sırayla geri alınır ve eski çalışan durum korunur.
 *
 * DÜRÜST SINIRLAR:
 *  - Atomiklik kısmidir. rollbackReload() telafi edicidir, ACID değildir.
 *  - Zaman aşımı işbirliklidir: adımlar arasında ölçülür, çalışan bir PHP
 *    fonksiyonu zorla kesilemez.
 *  - PHP sınıf kodu yenilenmez. Yalnızca yapılandırma/veri/menü yenilenir.
 */
final class ReloadManager
{
    /** @var resource|null */
    private $lockHandle = null;

    private bool $running = false;

    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly Logger $logger,
        private readonly ?string $lockFile = null,
        private readonly int $timeoutMs = 5000
    ) {
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Belirtilen kapsamdaki tüm yenilenebilir modülleri yeniler.
     */
    public function reload(ReloadRequest $request = new ReloadRequest()): ReloadReport
    {
        $startedAt = microtime(true);

        if ($this->running) {
            return new ReloadReport(
                request: $request,
                results: [],
                success: false,
                failureReason: 'Zaten bir reload islemi calisiyor.',
                durationMs: $this->elapsedMs($startedAt)
            );
        }

        if (!$this->acquireLock()) {
            return new ReloadReport(
                request: $request,
                results: [],
                success: false,
                failureReason: 'Reload kilidi alinamadi (baska bir surec reload yapiyor).',
                durationMs: $this->elapsedMs($startedAt)
            );
        }

        $this->running = true;

        try {
            return $this->execute($request, $startedAt);
        } finally {
            $this->running = false;
            $this->releaseLock();
        }
    }

    private function execute(ReloadRequest $request, float $startedAt): ReloadReport
    {
        $targets = $this->selectTargets();

        if ($targets === []) {
            return new ReloadReport(
                request: $request,
                results: [],
                success: true,
                failureReason: '',
                durationMs: $this->elapsedMs($startedAt)
            );
        }

        $this->logger->info('Reload baslatiliyor.', ['scope' => $request->scope, 'modules' => count($targets)]);

        /** @var array<string, ReloadPlan> $plans */
        $plans = [];
        /** @var list<ReloadResult> $results */
        $results = [];

        // 1) Hazırla + doğrula
        foreach ($targets as $module) {
            $id = $module->getId();
            $moduleStart = microtime(true);

            if ($this->timedOut($startedAt)) {
                return $this->abort($request, $results, $plans, $startedAt, 'Reload zaman asimi (prepare).');
            }

            try {
                $plan = $module->prepareReload($request);
                $module->validateReload($plan);
                $plans[$id] = $plan;
            } catch (\Throwable $throwable) {
                $results[] = new ReloadResult(
                    moduleId: $id,
                    status: ReloadStatus::Failed,
                    message: $throwable->getMessage(),
                    durationMs: $this->elapsedMs($moduleStart)
                );

                return $this->abort($request, $results, $plans, $startedAt, "Hazirlama basarisiz: {$id}");
            }
        }

        // 2) Uygula
        /** @var list<string> $committed */
        $committed = [];

        foreach ($targets as $module) {
            $id = $module->getId();
            $moduleStart = microtime(true);

            if ($this->timedOut($startedAt)) {
                return $this->abort($request, $results, $plans, $startedAt, 'Reload zaman asimi (commit).', $committed);
            }

            try {
                $module->commitReload($plans[$id]);
                $committed[] = $id;

                $results[] = new ReloadResult(
                    moduleId: $id,
                    status: ReloadStatus::Committed,
                    message: '',
                    durationMs: $this->elapsedMs($moduleStart)
                );
            } catch (\Throwable $throwable) {
                $results[] = new ReloadResult(
                    moduleId: $id,
                    status: ReloadStatus::Failed,
                    message: $throwable->getMessage(),
                    durationMs: $this->elapsedMs($moduleStart)
                );

                return $this->abort(
                    $request,
                    $results,
                    $plans,
                    $startedAt,
                    "Uygulama basarisiz: {$id}",
                    $committed
                );
            }
        }

        $durationMs = $this->elapsedMs($startedAt);
        $this->logger->info('Reload tamamlandi.', ['modules' => count($committed), 'ms' => round($durationMs, 2)]);

        return new ReloadReport($request, $results, true, '', $durationMs);
    }

    /**
     * @param list<ReloadResult> $results
     * @param array<string, ReloadPlan> $plans
     * @param list<string> $committed
     */
    private function abort(
        ReloadRequest $request,
        array $results,
        array $plans,
        float $startedAt,
        string $reason,
        array $committed = []
    ): ReloadReport {
        $this->logger->error('Reload basarisiz, geri alma uygulaniyor.', ['reason' => $reason]);

        $rollbackOrder = array_reverse($committed);

        // Commit edilmiş olanlar gerçekten geri alınmalı.
        foreach ($rollbackOrder as $id) {
            $module = $this->registry->get($id);

            if (!$module instanceof ReloadableInterface) {
                continue;
            }

            try {
                $module->rollbackReload($plans[$id]);
                $results[] = new ReloadResult($id, ReloadStatus::RolledBack, 'Onceki durum korundu.');
            } catch (\Throwable $throwable) {
                $results[] = new ReloadResult(
                    $id,
                    ReloadStatus::Failed,
                    'Geri alma basarisiz: ' . $throwable->getMessage()
                );
            }
        }

        // Hazırlanmış ama commit edilmemiş planlar da serbest bırakılmalı.
        foreach ($plans as $id => $plan) {
            if (in_array($id, $committed, true)) {
                continue;
            }

            $module = $this->registry->get($id);

            if (!$module instanceof ReloadableInterface) {
                continue;
            }

            try {
                $module->rollbackReload($plan);
            } catch (\Throwable) {
                // Hazırlık aşamasındaki planın geri alınamaması kritik değildir.
            }
        }

        return new ReloadReport(
            request: $request,
            results: $results,
            success: false,
            failureReason: $reason,
            durationMs: $this->elapsedMs($startedAt)
        );
    }

    /**
     * @return list<ReloadableInterface>
     */
    private function selectTargets(): array
    {
        $targets = [];

        foreach ($this->registry->inBootOrder() as $module) {
            if ($module instanceof ReloadableInterface && $module->getLifecyclePolicy()->reloadable) {
                $targets[] = $module;
            }
        }

        return $targets;
    }

    private function timedOut(float $startedAt): bool
    {
        return $this->timeoutMs > 0 && $this->elapsedMs($startedAt) > $this->timeoutMs;
    }

    private function elapsedMs(float $startedAt): float
    {
        return (microtime(true) - $startedAt) * 1000.0;
    }

    private function acquireLock(): bool
    {
        if ($this->lockFile === null) {
            return true;
        }

        $handle = @fopen($this->lockFile, 'c');

        if ($handle === false) {
            $this->logger->error('Reload kilit dosyasi acilamadi.', ['file' => $this->lockFile]);

            return false;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return false;
        }

        $this->lockHandle = $handle;

        return true;
    }

    private function releaseLock(): void
    {
        if ($this->lockHandle === null) {
            return;
        }

        flock($this->lockHandle, LOCK_UN);
        fclose($this->lockHandle);
        $this->lockHandle = null;
    }
}
