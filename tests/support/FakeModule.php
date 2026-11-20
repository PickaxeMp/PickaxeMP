<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\support;

use PickaxeMP\kernel\LifecyclePolicy;
use PickaxeMP\kernel\ModuleInterface;
use PickaxeMP\kernel\ReloadPlan;
use PickaxeMP\kernel\ReloadRequest;
use PickaxeMP\kernel\ReloadableInterface;
use PickaxeMP\kernel\SaveableInterface;

/**
 * Testlerde kullanılan yapılandırılabilir sahte modül.
 *
 * Gerçek sistemlerin yerine geçmez; yalnızca yaşam döngüsü sözleşmesinin
 * doğru sırada ve doğru hata davranışıyla çalıştığını kanıtlar.
 */
class FakeModule implements ReloadableInterface, SaveableInterface
{
    public bool $booted = false;

    public bool $shutdownDone = false;

    public bool $pendingWrites = false;

    public int $flushCount = 0;

    public int $prepareCount = 0;

    public int $validateCount = 0;

    public int $commitCount = 0;

    public int $rollbackCount = 0;

    public bool $failBoot = false;

    public bool $failPrepare = false;

    public bool $failValidate = false;

    public bool $failCommit = false;

    public bool $failRollback = false;

    /** @var list<string> */
    public array $events = [];

    /** @var list<string> */
    private array $dependencies;

    private bool $reloadable;

    private bool $saveOnShutdown;

    /**
     * @param list<string> $dependencies
     */
    public function __construct(
        public readonly string $id,
        array $dependencies = [],
        bool $reloadable = true,
        bool $saveOnShutdown = false
    ) {
        $this->dependencies = $dependencies;
        $this->reloadable = $reloadable;
        $this->saveOnShutdown = $saveOnShutdown;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function getLifecyclePolicy(): LifecyclePolicy
    {
        return new LifecyclePolicy(
            reloadable: $this->reloadable,
            saveOnShutdown: $this->saveOnShutdown,
            critical: $this->saveOnShutdown
        );
    }

    public function boot(): void
    {
        if ($this->failBoot) {
            throw new \RuntimeException("boot failed: {$this->id}");
        }

        $this->booted = true;
        $this->record("boot:{$this->id}");
    }

    public function shutdown(): void
    {
        if ($this->shutdownDone) {
            return;
        }

        $this->shutdownDone = true;
        $this->record("shutdown:{$this->id}");
    }

    public function prepareReload(ReloadRequest $request): ReloadPlan
    {
        $this->prepareCount++;

        if ($this->failPrepare) {
            throw new \RuntimeException("prepare failed: {$this->id}");
        }

        return new ReloadPlan(['module' => $this->id], $request);
    }

    public function validateReload(ReloadPlan $plan): void
    {
        $this->validateCount++;

        if ($this->failValidate) {
            throw new \RuntimeException("validate failed: {$this->id}");
        }
    }

    public function commitReload(ReloadPlan $plan): void
    {
        $this->commitCount++;

        if ($this->failCommit) {
            throw new \RuntimeException("commit failed: {$this->id}");
        }

        $this->record("commit:{$this->id}");
    }

    public function rollbackReload(ReloadPlan $plan): void
    {
        $this->rollbackCount++;

        if ($this->failRollback) {
            throw new \RuntimeException("rollback failed: {$this->id}");
        }

        $this->record("rollback:{$this->id}");
    }

    public function hasPendingWrites(): bool
    {
        return $this->pendingWrites;
    }

    public function flush(): void
    {
        $this->flushCount++;
        $this->pendingWrites = false;
        $this->record("flush:{$this->id}");
    }

    private function record(string $event): void
    {
        $this->events[] = $event;
    }
}
