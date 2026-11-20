<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\ProtocolException;

/**
 * `InventoryAction` — tek bir slot degisimi.
 *
 * Tel bicimi (Mojang `InventoryAction.json`): `Source` + `Slot` varuint32 +
 * `From Item` + `To Item` tanimlayicilari.
 */
final class InventoryAction
{
    public function __construct(
        public readonly InventorySource $source,
        public readonly int $slot,
        public readonly NetworkItemStackDescriptor $fromItem,
        public readonly NetworkItemStackDescriptor $toItem
    ) {
        if ($slot < 0 || $slot > 0xFFFFFFFF) {
            throw ProtocolException::varIntOverflow('inventory action slot (uint32)');
        }
    }

    public function encode(BinaryWriter $writer): void
    {
        $this->source->encode($writer);
        $writer->writeVarUInt($this->slot);
        $this->fromItem->encode($writer);
        $this->toItem->encode($writer);
    }

    public static function decode(BinaryReader $reader): self
    {
        $source = InventorySource::decode($reader);
        $slot = $reader->readVarUInt();
        $fromItem = NetworkItemStackDescriptor::decode($reader);
        $toItem = NetworkItemStackDescriptor::decode($reader);

        return new self($source, $slot, $fromItem, $toItem);
    }
}
