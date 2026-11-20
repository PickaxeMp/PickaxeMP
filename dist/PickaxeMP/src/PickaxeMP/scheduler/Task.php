<?php

declare(strict_types=1);

namespace PickaxeMP\scheduler;

/**
 * Zamanlayıcıdaki tek bir görev.
 *
 * Görev nesneleri dışarıya verilmez; Scheduler bunları kendi içinde tutar.
 */
final class Task
{
    public function __construct(
        public readonly string $id,
        public readonly mixed $callback,
        public int $nextTick,
        public readonly ?int $periodTicks,
        public readonly int $sequence
    ) {
    }

    public function isPeriodic(): bool
    {
        return $this->periodTicks !== null;
    }
}
