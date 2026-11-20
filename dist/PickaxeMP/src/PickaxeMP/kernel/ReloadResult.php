<?php

declare(strict_types=1);

namespace PickaxeMP\kernel;

/**
 * Reload raporundaki tek bir modül satırı.
 */
final class ReloadResult
{
    public function __construct(
        public readonly string $moduleId,
        public readonly ReloadStatus $status,
        public readonly string $message = '',
        public readonly float $durationMs = 0.0
    ) {
    }
}
