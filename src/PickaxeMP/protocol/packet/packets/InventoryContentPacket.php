<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\types\FullContainerName;
use PickaxeMP\protocol\packet\types\NetworkItemStackDescriptor;
use PickaxeMP\protocol\ProtocolException;

/**
 * `InventoryContentPacket` (id **49**, sunucu → istemci).
 *
 * Sema (Mojang v1.26.65, 2225; `InventoryContentPacketPayload.json`):
 * Container Id **varuint32**, Slots (varuint32 sayi + tanimlayicilar),
 * Full Container Name, Storage Item.
 */
final class InventoryContentPacket implements PacketInterface
{
    public const MAX_SLOTS = 256;

    /**
     * @param list<NetworkItemStackDescriptor> $slots
     */
    public function __construct(
        public readonly int $containerId,
        public readonly array $slots,
        public readonly FullContainerName $fullContainerName,
        public readonly NetworkItemStackDescriptor $storageItem
    ) {
        if (count($slots) > self::MAX_SLOTS) {
            throw ProtocolException::lengthTooLarge('inventory content slots', count($slots), self::MAX_SLOTS);
        }
    }

    public function getId(): int
    {
        return PacketId::INVENTORY_CONTENT;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeVarUInt($this->containerId);
        $writer->writeVarUInt(count($this->slots));

        foreach ($this->slots as $slot) {
            $slot->encode($writer);
        }

        $this->fullContainerName->encode($writer);
        $this->storageItem->encode($writer);
    }

    public static function decode(BinaryReader $reader): static
    {
        $containerId = $reader->readVarUInt();
        $count = $reader->readVarUInt();

        if ($count > self::MAX_SLOTS) {
            throw ProtocolException::lengthTooLarge('inventory content slots', $count, self::MAX_SLOTS);
        }

        $slots = [];

        for ($i = 0; $i < $count; $i++) {
            $slots[] = NetworkItemStackDescriptor::decode($reader);
        }

        return new static(
            $containerId,
            $slots,
            FullContainerName::decode($reader),
            NetworkItemStackDescriptor::decode($reader)
        );
    }
}
