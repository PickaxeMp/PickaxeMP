<?php

declare(strict_types=1);

namespace PickaxeMP\world;

use PickaxeMP\exception\PickaxeException;

/**
 * Dünya katmanı hatası.
 */
final class WorldException extends PickaxeException
{
    public static function invalidChunkPosition(int $x, int $z): self
    {
        return new self("Gecersiz chunk konumu: ({$x}, {$z})");
    }

    public static function invalidSubChunkIndex(int $index, int $min, int $max): self
    {
        return new self("Gecersiz subchunk indeksi: {$index} ({$min}..{$max})");
    }

    public static function invalidViewDistance(int $value, int $min, int $max): self
    {
        return new self("Gecersiz gorunum mesafesi: {$value} ({$min}..{$max})");
    }

    public static function invalidTimeStep(int $ticks): self
    {
        return new self("Gecersiz zaman adimi: {$ticks} (negatif olamaz)");
    }

    public static function paletteTooLarge(int $count, int $limit): self
    {
        return new self("Palet cok buyuk: {$count} (sinir: {$limit})");
    }

    public static function corruptWorldFile(string $path, string $reason): self
    {
        return new self("Bozuk dunya dosyasi ({$path}): {$reason}");
    }

    public static function saveFailed(string $path, string $reason): self
    {
        return new self("Dunya kaydedilemedi ({$path}): {$reason}");
    }

    public static function unknownWorld(string $name): self
    {
        return new self("Dunya bulunamadi: {$name}");
    }

    public static function outOfBounds(int $x, int $y, int $z): self
    {
        return new self("Konum dunya sinirlari disinda: ({$x}, {$y}, {$z})");
    }

    public static function checksumMismatch(int $expected, int $actual): self
    {
        return new self("Saglamlik ozeti uyusmuyor: beklenen {$expected}, alinan {$actual}");
    }
}