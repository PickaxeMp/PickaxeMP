<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;

/**
 * `ExperimentToggle` (Mojang şeması, 2225): Name (string) → Enabled (bool).
 */
final class ExperimentToggle
{
    public function __construct(
        public readonly string $name,
        public readonly bool $enabled = false
    ) {
    }

    public static function decode(BinaryReader $reader): self
    {
        return new self($reader->readString(), $reader->readBool());
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeString($this->name);
        $writer->writeBool($this->enabled);
    }
}
