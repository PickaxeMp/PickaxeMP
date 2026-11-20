<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\types\FullContainerName;
use PickaxeMP\protocol\packet\types\NetworkItemStackDescriptor;

/**
 * `InventorySlotPacket` (id **50**, sunucu → istemci).
 *
 * Sema (Mojang v1.26.65, 2225; `InventorySlotPacketPayload.json`):
 * Container Id uint8, Slot **varuint32**, Full Container Name (opsiyonel),
 * Storage Item (opsiyonel), Item.
 */
final class InventorySlotPacket implements PacketInterface
{
    public function __construct(
        public readonly int $containerId,
        public readonly int $slot,
        public readonly NetworkItemStackDescriptor $item,
        public readonly ?FullContainerName $fullContainerName = null,
        public readonly ?NetworkItemStackDescriptor $storageItem = null
    ) {
    }

    public function getId(): int
    {
        return PacketId::INVENTORY_SLOT;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeByte($this->containerId);
        $writer->writeVarUInt($this->slot);

        $writer->writeOptional($this->fullContainerName !== null, function (BinaryWriter $w): void {
            $this->fullContainerName?->encode($w);
        });

        $writer->writeOptional($this->storageItem !== null, function (BinaryWriter $w): void {
            $this->storageItem?->encode($w);
        });

        $this->item->encode($writer);
    }

    public static function decode(BinaryReader $reader): static
    {
        $containerId = $reader->readByte();
        $slot = $reader->readVarUInt();

        $fullContainerName = $reader->readBool() ? FullContainerName::decode($reader) : null;
        $storageItem = $reader->readBool() ? NetworkItemStackDescriptor::decode($reader) : null;
        $item = NetworkItemStackDescriptor::decode($reader);

        return new static($containerId, $slot, $item, $fullContainerName, $storageItem);
    }
}
