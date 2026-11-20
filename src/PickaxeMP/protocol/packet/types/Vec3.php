<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;

/**
 * `Vec3` — uc `float` (little-endian).
 *
 * Mojang semasi: `Vec3.json` (X, Y, Z; `x-underlying-type` = float).
 */
final class Vec3
{
    public function __construct(
        public readonly float $x,
        public readonly float $y,
        public readonly float $z
    ) {
    }

    public static function zero(): self
    {
        return new self(0.0, 0.0, 0.0);
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeFloat($this->x);
        $writer->writeFloat($this->y);
        $writer->writeFloat($this->z);
    }

    public static function decode(BinaryReader $reader): self
    {
        return new self(
            $reader->readFloat(),
            $reader->readFloat(),
            $reader->readFloat()
        );
    }
}
