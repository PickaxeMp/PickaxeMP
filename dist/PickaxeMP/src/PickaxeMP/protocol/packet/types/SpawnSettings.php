<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;

/**
 * `SpawnSettings` (Mojang şeması, 2225) — LevelSettings ordinal 1.
 *
 * DÜRÜST NOT: Şema bu alanı `$ref` ile gösterir ama alt şeması bu depoda
 * yayımlanmamıştır. Bedrock tarihsel olarak burada üç int32 taşır:
 * `spawnType`, `spawnX`, `spawnY`, `spawnZ` (4 alan).
 *
 * Bu uygulama **spawnType = 0** (oyuncu spawn'ı) ve verilen konumu yazar.
 * Alan sayısı gerçek istemciyle doğrulanmalıdır; yanlışsa StartGame
 * ayrıştırması kayar ve istemci bağlantıyı düşürür.
 */
final class SpawnSettings
{
    public function __construct(
        public readonly int $spawnType = 0,
        public readonly int $spawnX = 0,
        public readonly int $spawnY = 64,
        public readonly int $spawnZ = 0
    ) {
    }

    public static function at(int $x, int $y, int $z): self
    {
        return new self(0, $x, $y, $z);
    }

    public static function decode(BinaryReader $reader): self
    {
        return new self(
            $reader->readInt32(),
            $reader->readInt32(),
            $reader->readInt32(),
            $reader->readInt32()
        );
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeInt32($this->spawnType);
        $writer->writeInt32($this->spawnX);
        $writer->writeInt32($this->spawnY);
        $writer->writeInt32($this->spawnZ);
    }
}
