<?php

declare(strict_types=1);

namespace PickaxeMP\kernel;

use PickaxeMP\exception\LifecycleException;
use PickaxeMP\scheduler\Scheduler;
use PickaxeMP\utils\Config;
use PickaxeMP\utils\Logger;
use PickaxeMP\utils\ServerConfig;

/**
 * Sunucunun çekirdeği: modül yaşam döngüsü, tick döngüsü ve kapanış sırası.
 *
 * Kurallar:
 *  - boot() iki kez çağrılamaz.
 *  - tick() ve shutdown() yalnızca boot edilmişken çalışır.
 *  - boot sırasında bir modül patlarsa, o ana kadar başlamış modüller ters
 *    sırayla kapatılır; yarım kalmış çekirdek bırakılmaz.
 *  - shutdown() idempotenttir: iki kez çağrılması hata değildir.
 *  - Kapanıştan önce SaveableInterface uygulayan modüllerin bekleyen yazıları
 *    boşaltılır.
 */
final class ServerKernel
{
    private bool $booted = false;

    private bool $shutdownDone = false;

    /** @var list<ModuleInterface> */
    private array $bootSequence = [];

    public function __construct(
        private readonly Logger $logger,
        private readonly Config $config,
        private readonly ServerConfig $settings,
        private readonly Scheduler $scheduler,
        private readonly ModuleRegistry $modules
    ) {
    }

    public function getLogger(): Logger
    {
        return $this->logger;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getSettings(): ServerConfig
    {
        return $this->settings;
    }

    public function getScheduler(): Scheduler
    {
        return $this->scheduler;
    }

    public function getModules(): ModuleRegistry
    {
        return $this->modules;
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    public function boot(): void
    {
        if ($this->booted) {
            throw LifecycleException::alreadyBooted();
        }

        $this->booted = true;
        $this->shutdownDone = false;
        $this->bootSequence = [];

        foreach ($this->modules->inBootOrder() as $module) {
            try {
                $module->boot();
            } catch (\Throwable $throwable) {
                $this->logger->error('Modul baslatilamadi, geri aliniyor.', [
                    'module' => $module->getId(),
                    'error' => $throwable->getMessage(),
                ]);

                $this->rollbackBoot();

                throw new LifecycleException(
                    "Modul baslatilamadi: {$module->getId()} — {$throwable->getMessage()}",
                    0,
                    $throwable
                );
            }

            $this->bootSequence[] = $module;
        }

        $this->logger->info('Cekirdek baslatildi.', [
            'modules' => count($this->bootSequence),
        ]);
    }

    /**
     * Tek bir tick çalıştırır.
     *
     * @return int Bu tick'te çalıştırılan zamanlanmış görev sayısı
     */
    public function tick(int $tickNumber): int
    {
        if (!$this->booted) {
            throw LifecycleException::notBooted();
        }

        if ($tickNumber < 0) {
            throw LifecycleException::notBooted();
        }

        return $this->scheduler->run($tickNumber);
    }

    public function shutdown(): void
    {
        if (!$this->booted || $this->shutdownDone) {
            return;
        }

        $this->shutdownDone = true;

        $this->flushSaveable();

        foreach (array_reverse($this->bootSequence) as $module) {
            try {
                $module->shutdown();
            } catch (\Throwable $throwable) {
                $this->logger->error('Modul kapatilirken hata verdi.', [
                    'module' => $module->getId(),
                    'error' => $throwable->getMessage(),
                ]);
            }
        }

        $this->bootSequence = [];
        $this->booted = false;

        $this->logger->info('Cekirdek kapatildi.');
    }

    private function rollbackBoot(): void
    {
        foreach (array_reverse($this->bootSequence) as $module) {
            try {
                $module->shutdown();
            } catch (\Throwable) {
                // Geri alma sırasında ikincil hatalar yutulur; asıl hata raporlanır.
            }
        }

        $this->bootSequence = [];
        $this->booted = false;
    }

    private function flushSaveable(): void
    {
        foreach ($this->modules->saveableInBootOrder() as $module) {
            try {
                if (!$module->hasPendingWrites()) {
                    continue;
                }

                $module->flush();

                $this->logger->info('Bekleyen veri yazildi.', ['module' => $module->getId()]);
            } catch (\Throwable $throwable) {
                $this->logger->error('Bekleyen veri yazilamadi.', [
                    'module' => $module->getId(),
                    'error' => $throwable->getMessage(),
                ]);
            }
        }
    }
}
