<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\compression;

use PickaxeMP\protocol\ProtocolException;

/**
 * Bedrock sıkıştırma algoritması.
 *
 * Doğrulanmış değerler (Mojang `PacketCompressionAlgorithm.json`, 2225):
 *   ZLib = 0, Snappy = 1, None = 65535
 *
 * DÜRÜST NOT: Bu PHP derlemesinde `snappy` eklentisi yoktur. Bu yüzden sunucu
 * **her zaman ZLib** önerir. Bir istemci Snappy talep ederse açık hata
 * üretilir; sessizce yanlış veri gönderilmez.
 */
enum CompressionAlgorithm: int
{
    case Zlib = 0;
    case Snappy = 1;
    case None = 65535;

    public static function fromWire(int $value): self
    {
        return match ($value) {
            0 => self::Zlib,
            1 => self::Snappy,
            65535 => self::None,
            default => throw new ProtocolException("Bilinmeyen sikistirma algoritmasi: {$value}"),
        };
    }

    public function isSupported(): bool
    {
        return match ($this) {
            self::Zlib, self::None => true,
            self::Snappy => extension_loaded('snappy'),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Zlib => 'zlib',
            self::Snappy => 'snappy',
            self::None => 'none',
        };
    }
}
