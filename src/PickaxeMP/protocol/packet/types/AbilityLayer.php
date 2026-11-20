<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\ProtocolException;

/**
 * `SerializedAbilitiesDataSerializedLayer` — tek bir yetenek katmani.
 *
 * Tel bicimi (Mojang `SerializedAbilitiesDataSerializedLayer.json` ordinal 0..5):
 * Seriellesmis katman uint16, yetenek kumesi uint32, yetenek degerleri uint32,
 * FlySpeed float, VerticalFlySpeed float, WalkSpeed float.
 */
final class AbilityLayer
{
    public function __construct(
        public readonly int $layer,
        public readonly int $abilitiesSet,
        public readonly int $abilityValues,
        public readonly float $flySpeed,
        public readonly float $verticalFlySpeed,
        public readonly float $walkSpeed
    ) {
        if ($layer < 0 || $layer > 0xFFFF) {
            throw ProtocolException::varIntOverflow('ability layer (uint16)');
        }

        if ($abilitiesSet < 0 || $abilitiesSet > 0xFFFFFFFF) {
            throw ProtocolException::varIntOverflow('ability set (uint32)');
        }

        if ($abilityValues < 0 || $abilityValues > 0xFFFFFFFF) {
            throw ProtocolException::varIntOverflow('ability values (uint32)');
        }
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeUint16($this->layer);
        $writer->writeUint32($this->abilitiesSet);
        $writer->writeUint32($this->abilityValues);
        $writer->writeFloat($this->flySpeed);
        $writer->writeFloat($this->verticalFlySpeed);
        $writer->writeFloat($this->walkSpeed);
    }

    public static function decode(BinaryReader $reader): self
    {
        return new self(
            $reader->readUint16(),
            $reader->readUint32(),
            $reader->readUint32(),
            $reader->readFloat(),
            $reader->readFloat(),
            $reader->readFloat()
        );
    }
}
