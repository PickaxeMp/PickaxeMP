<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;

/**
 * `BlockPos` — uc `int32`, "Compression" secenegi nedeniyle ZigZag `varint32`.
 *
 * Mojang semasi: `BlockPos.json` (X, Y, Z; `x-serialization-options` = Compression).
 */
final class BlockPos
{
    public function __construct(
        public readonly int $x,
        public readonly int $y,
        public readonly int $z
    ) {
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeVarInt($this->x);
        $writer->writeVarInt($this->y);
        $writer->writeVarInt($this->z);
    }

    public static function decode(BinaryReader $reader): self
    {
        return new self(
            $reader->readVarInt(),
            $reader->readVarInt(),
            $reader->readVarInt()
        );
    }
}
