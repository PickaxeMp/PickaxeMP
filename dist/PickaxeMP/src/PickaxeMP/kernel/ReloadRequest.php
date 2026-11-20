<?php

declare(strict_types=1);

namespace PickaxeMP\kernel;

/**
 * Reload isteği. Hangi kapsamın istendiğini ve kimin tetiklediğini taşır.
 */
final class ReloadRequest
{
    public function __construct(
        public readonly string $scope = 'all',
        public readonly string $origin = 'console'
    ) {
    }

    public static function all(string $origin = 'console'): self
    {
        return new self('all', $origin);
    }
}
