<?php

declare(strict_types=1);

namespace PickaxeMP\kernel;

/**
 * prepareReload() çıktısı.
 *
 * Plan, henüz UYGULANMAMIŞ yeni durumu taşır. commitReload() bu planı devreye
 * alır, rollbackReload() ise planı çöpe atıp eski durumu korur.
 */
final class ReloadPlan
{
    /**
     * @param array<string, mixed> $state Modüle özgü hazırlanmış durum
     */
    public function __construct(
        public readonly array $state = [],
        public readonly ReloadRequest $request = new ReloadRequest()
    ) {
    }

    public static function empty(ReloadRequest $request = new ReloadRequest()): self
    {
        return new self([], $request);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->state[$key] ?? $default;
    }
}
