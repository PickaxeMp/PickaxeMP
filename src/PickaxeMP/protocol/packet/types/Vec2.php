<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;

/**
 * `Vec2` — iki `float` (little-endian).
 *
 * Mojang semasi: `Vec2.json` (X, Y; `x-underlying-type` = float).
 */
final class Vec2
{
    public function __construct(
        public readonly float $x,
        public readonly float $y
    ) {
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeFloat($this->x);
        $writer->writeFloat($this->y);
    }

    public static function decode(BinaryReader $reader): self
    {
        return new self($reader->readFloat(), $reader->readFloat());
    }
}
