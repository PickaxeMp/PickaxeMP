<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\types\Vec3;

/**
 * `MoveActorAbsolutePacket` (id **18**, sunucu → istemci).
 *
 * Sema (Mojang v1.26.65, 2225; `MoveActorAbsoluteData.json`):
 * ActorRuntimeID varuint64, Header uint8, Position Vec3,
 * Rotation X uint8, Rotation Y uint8, Rotation Y Head uint8.
 */
final class MoveActorAbsolutePacket implements PacketInterface
{
    public function __construct(
        public readonly int $targetRuntimeId,
        public readonly int $flags,
        public readonly Vec3 $position,
        public readonly int $rotationX,
        public readonly int $rotationY,
        public readonly int $rotationYHead
    ) {
    }

    public function getId(): int
    {
        return PacketId::MOVE_ACTOR_ABSOLUTE;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeVarULong($this->targetRuntimeId);
        $writer->writeByte($this->flags);
        $this->position->encode($writer);
        $writer->writeByte($this->rotationX);
        $writer->writeByte($this->rotationY);
        $writer->writeByte($this->rotationYHead);
    }

    public static function decode(BinaryReader $reader): static
    {
        return new static(
            $reader->readVarULong(),
            $reader->readByte(),
            Vec3::decode($reader),
            $reader->readByte(),
            $reader->readByte(),
            $reader->readByte()
        );
    }
}
