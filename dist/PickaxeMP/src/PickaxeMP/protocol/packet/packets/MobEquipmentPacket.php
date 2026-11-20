<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\types\NetworkItemStackDescriptor;

/**
 * `MobEquipmentPacket` (id **31**, sunucu → istemci).
 *
 * Sema (Mojang v1.26.65, 2225; `MobEquipmentPacketPayload.json`):
 * runtime ID varuint64, Item `NetworkItemStackDescriptor`, Slot uint8,
 * Selected Slot uint8, Container ID uint8.
 */
final class MobEquipmentPacket implements PacketInterface
{
    public function __construct(
        public readonly int $targetRuntimeId,
        public readonly NetworkItemStackDescriptor $item,
        public readonly int $slot,
        public readonly int $selectedSlot,
        public readonly int $containerId
    ) {
    }

    public function getId(): int
    {
        return PacketId::MOB_EQUIPMENT;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeVarULong($this->targetRuntimeId);
        $this->item->encode($writer);
        $writer->writeByte($this->slot);
        $writer->writeByte($this->selectedSlot);
        $writer->writeByte($this->containerId);
    }

    public static function decode(BinaryReader $reader): static
    {
        return new static(
            $reader->readVarULong(),
            NetworkItemStackDescriptor::decode($reader),
            $reader->readByte(),
            $reader->readByte(),
            $reader->readByte()
        );
    }
}
