<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\support;

use PickaxeMP\kernel\LifecyclePolicy;
use PickaxeMP\kernel\ModuleInterface;

/**
 * Yalnızca ModuleInterface uygulayan, kaydedilebilir olmayan modül.
 *
 * SaveableInterface ayrımını test edebilmek için gereklidir.
 */
final class PlainModule implements ModuleInterface
{
    public bool $booted = false;

    public function __construct(public readonly string $id, private array $dependencies = [])
    {
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
        return LifecyclePolicy::standard();
    }

    public function boot(): void
    {
        $this->booted = true;
    }

    public function shutdown(): void
    {
        $this->booted = false;
    }
}
