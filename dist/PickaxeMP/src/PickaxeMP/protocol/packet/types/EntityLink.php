<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\ProtocolException;

/**
 * `ActorLink` — iki varlik arasindaki bag (binme/arac iliskisi).
 *
 * Tel bicimi (Mojang `ActorLink.json` ordinal 0..5):
 * Target A int64 (Compression → varlong), Target B int64 (varlong),
 * Type uint8, Immediate bool, Passenger Initiated bool,
 * Vehicle Angular Velocity float.
 */
final class EntityLink
{
    public const TYPE_RIDING = 0;
    public const TYPE_PASSENGER = 1;

    public function __construct(
        public readonly int $targetA,
        public readonly int $targetB,
        public readonly int $type,
        public readonly bool $immediate,
        public readonly bool $passengerInitiated,
        public readonly float $vehicleAngularVelocity
    ) {
        if ($type < 0 || $type > 0xFF) {
            throw ProtocolException::varIntOverflow('actor link type (uint8)');
        }
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeVarLong($this->targetA);
        $writer->writeVarLong($this->targetB);
        $writer->writeByte($this->type);
        $writer->writeBool($this->immediate);
        $writer->writeBool($this->passengerInitiated);
        $writer->writeFloat($this->vehicleAngularVelocity);
    }

    public static function decode(BinaryReader $reader): self
    {
        return new self(
            $reader->readVarLong(),
            $reader->readVarLong(),
            $reader->readByte(),
            $reader->readBool(),
            $reader->readBool(),
            $reader->readFloat()
        );
    }
}
