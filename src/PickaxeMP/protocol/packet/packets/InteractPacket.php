<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\types\InteractAction;
use PickaxeMP\protocol\packet\types\Vec3;

/**
 * `InteractPacket` (id **33**, istemci -> sunucu).
 *
 * Sema (Mojang v1.26.65, 2225) - `InteractPacketPayload`:
 *
 * ```
 * 0 Action            uint8 (Enum-as-Value: 0,3,4,5,6)
 * 1 Target Runtime ID varuint64
 * 2 Position          Vec3 - YALNIZCA Action == 4 (InteractUpdate)
 * ```
 *
 * `Position` semada `required` degil; kosullu varligi gophertunnel/pmmp
 * davranisindan alinmistir. **Varsayim** - gercek istemciyle dogrulanmadi.
 */
final class InteractPacket implements PacketInterface
{
    public function __construct(
        public readonly InteractAction $action,
        public readonly int $targetRuntimeId,
        public readonly ?Vec3 $position = null
    ) {
    }

    public function getId(): int
    {
        return PacketId::INTERACT;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeByte($this->action->value);
        $writer->writeVarULong($this->targetRuntimeId);

        if ($this->action->carriesPosition()) {
            ($this->position ?? Vec3::zero())->encode($writer);
        }
    }

    public static function decode(BinaryReader $reader): static
    {
        $action = InteractAction::fromWire($reader->readByte());
        $targetRuntimeId = $reader->readVarULong();

        $position = $action->carriesPosition() ? Vec3::decode($reader) : null;

        return new static(
            action: $action,
            targetRuntimeId: $targetRuntimeId,
            position: $position
        );
    }
}
