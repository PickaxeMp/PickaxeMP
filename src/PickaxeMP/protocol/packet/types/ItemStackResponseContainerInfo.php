<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\ProtocolException;

/**
 * `ItemStackResponseContainerInfo` — yanıtta tek konteynerin yuvaları.
 *
 * Tel biçimi (Mojang `ItemStackResponseContainerInfo.json`, 2225):
 *
 * ```
 *  0 Full Container Name  uint8 + istege bagli uint32
 *  1 Slots                varuint32 sayi + ItemStackResponseSlotInfo listesi
 * ```
 */
final class ItemStackResponseContainerInfo
{
    public const MAX_SLOTS = 256;

    /**
     * @param list<ItemStackResponseSlotInfo> $slots
     */
    public function __construct(
        public readonly FullContainerName $container,
        public readonly array $slots
    ) {
        if (count($slots) > self::MAX_SLOTS) {
            throw ProtocolException::lengthTooLarge(
                'item stack response container slots',
                count($slots),
                self::MAX_SLOTS
            );
        }
    }

    public function encode(BinaryWriter $writer): void
    {
        $this->container->encode($writer);
        $writer->writeVarUInt(count($this->slots));

        foreach ($this->slots as $slot) {
            $slot->encode($writer);
        }
    }

    public static function decode(BinaryReader $reader): self
    {
        $container = FullContainerName::decode($reader);
        $count = $reader->readVarUInt();

        if ($count > self::MAX_SLOTS) {
            throw ProtocolException::lengthTooLarge(
                'item stack response container slots',
                $count,
                self::MAX_SLOTS
            );
        }

        $slots = [];

        for ($i = 0; $i < $count; $i++) {
            $slots[] = ItemStackResponseSlotInfo::decode($reader);
        }

        return new self($container, $slots);
    }
}
