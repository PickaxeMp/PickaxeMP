<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\types\RespawnState;
use PickaxeMP\protocol\packet\types\Vec3;

/**
 * `RespawnPacket` (id **45**, sunucu -> istemci).
 *
 * Sema (Mojang v1.26.65, 2225) - `RespawnPacketPayload`, ordinal sirasi:
 *
 * ```
 * 0 Position          Vec3 (float x 3)
 * 1 State             uint8 (Enum-as-Value: 0,1,2)
 * 2 Player Runtime Id uint64 (varuint64)
 * ```
 */
final class RespawnPacket implements PacketInterface
{
    public function __construct(
        public readonly Vec3 $position,
        public readonly RespawnState $state,
        public readonly int $playerRuntimeId
    ) {
    }

    public function getId(): int
    {
        return PacketId::RESPAWN;
    }

    public function encode(BinaryWriter $writer): void
    {
        $this->position->encode($writer);
        $writer->writeByte($this->state->value);
        $writer->writeVarULong($this->playerRuntimeId);
    }

    public static function decode(BinaryReader $reader): static
    {
        return new static(
            position: Vec3::decode($reader),
            state: RespawnState::fromWire($reader->readByte()),
            playerRuntimeId: $reader->readVarULong()
        );
    }
}
