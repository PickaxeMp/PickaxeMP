<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\ProtocolException;

/**
 * `SyncedAttribute` — bir varlik ozelliginin min/mevcut/max degerleri.
 *
 * Tel bicimi (Mojang `SyncedAttribute.json` ordinal 0,1,2,3):
 * `Attribute Name` string + `Min Value` float + `Current Value` float +
 * `Max Value` float.
 */
final class SyncedAttribute
{
    public function __construct(
        public readonly string $name,
        public readonly float $min,
        public readonly float $current,
        public readonly float $max
    ) {
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeString($this->name, 256);
        $writer->writeFloat($this->min);
        $writer->writeFloat($this->current);
        $writer->writeFloat($this->max);
    }

    public static function decode(BinaryReader $reader): self
    {
        return new self(
            $reader->readString(256),
            $reader->readFloat(),
            $reader->readFloat(),
            $reader->readFloat()
        );
    }
}
