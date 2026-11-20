<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\types\BlockPos;
use PickaxeMP\protocol\packet\types\PlayerActionType;

/**
 * `PlayerActionPacket` (id **36**, istemci -> sunucu).
 *
 * Sema (Mojang v1.26.65, 2225) - `PlayerActionPacketPayload`, ordinal sirasi:
 *
 * ```
 * 0 Player Runtime ID  uint64 (varuint64)
 * 1 Action             int32  (Compression -> ZigZag varint32)
 * 2 Block Position     BlockPos
 * 3 Result Pos         BlockPos
 * 4 Face               int32  (Compression -> ZigZag varint32)
 * ```
 *
 * Dikkat: ordinal sirasi "dogal" alan sirasindan farklidir; `Player Runtime ID`
 * ilk sirada gelir.
 */
final class PlayerActionPacket implements PacketInterface
{
    public function __construct(
        public readonly int $playerRuntimeId,
        public readonly PlayerActionType $action,
        public readonly BlockPos $blockPosition,
        public readonly BlockPos $resultPosition,
        public readonly int $face
    ) {
    }

    public function getId(): int
    {
        return PacketId::PLAYER_ACTION;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeVarULong($this->playerRuntimeId);
        $writer->writeVarInt($this->action->value);
        $this->blockPosition->encode($writer);
        $this->resultPosition->encode($writer);
        $writer->writeVarInt($this->face);
    }

    public static function decode(BinaryReader $reader): static
    {
        return new static(
            playerRuntimeId: $reader->readVarULong(),
            action: PlayerActionType::fromWire($reader->readVarInt()),
            blockPosition: BlockPos::decode($reader),
            resultPosition: BlockPos::decode($reader),
            face: $reader->readVarInt()
        );
    }
}
