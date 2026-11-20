<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\compression;

use PickaxeMP\protocol\ProtocolException;

/**
 * Sıkıştırma ayarları.
 *
 * `threshold` semantiği Mojang şemasından doğrulanmıştır:
 *   - 0 → sıkıştırma KAPALI
 *   - 1 → 1 bayt ve üzeri her şey sıkıştırılır
 *   - N → yalnızca N bayttan büyük yükler sıkıştırılır
 *
 * DÜRÜST NOT: Threshold'un "paket gövdesi" mi yoksa "paket kimliği dahil"
 * mi ölçüldüğü resmî şemada belirtilmemiştir. Uygulama **kimlik dahil tam
 * yükü** ölçer ve bu tercih dokümante edilir.
 */
final class CompressionSettings
{
    public const DEFAULT_THRESHOLD = 256;
    public const MIN_THRESHOLD = 0;
    public const MAX_THRESHOLD = 65535;

    public function __construct(
        public readonly CompressionAlgorithm $algorithm = CompressionAlgorithm::Zlib,
        public readonly int $threshold = self::DEFAULT_THRESHOLD
    ) {
        if ($threshold < self::MIN_THRESHOLD || $threshold > self::MAX_THRESHOLD) {
            throw new ProtocolException(
                "Gecersiz sikistirma esigi: {$threshold} (0..65535)"
            );
        }
    }

    public static function disabled(): self
    {
        return new self(CompressionAlgorithm::None, 0);
    }

    public function isEnabled(): bool
    {
        return $this->algorithm !== CompressionAlgorithm::None && $this->threshold > 0;
    }

    /**
     * Verilen boyuttaki yük sıkıştırılmalı mı?
     */
    public function shouldCompress(int $size): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        return $size >= $this->threshold;
    }
}
