<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;

/**
 * `ChunkPos` - iki **sabit** `int32` (little-endian) chunk koordinatı.
 *
 * Dikkat: `Vec2` iki `float` taşır; bu tür chunk koordinatları için ayrıdır
 * ve `NetworkChunkPublisherUpdate` içinde kullanılır. gophertunnel
 * `protocol.ChunkPos` ile aynı biçim.
 */
final class ChunkPos
{
    public function __construct(
        public readonly int $x,
        public readonly int $z
    ) {
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeInt32($this->x);
        $writer->writeInt32($this->z);
    }

    public static function decode(BinaryReader $reader): self
    {
        return new self($reader->readInt32(), $reader->readInt32());
    }
}
