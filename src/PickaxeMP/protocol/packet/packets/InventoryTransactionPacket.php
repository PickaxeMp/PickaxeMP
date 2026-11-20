<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\types\InventoryAction;
use PickaxeMP\protocol\packet\types\ItemUseInventoryTransaction;
use PickaxeMP\protocol\packet\types\LegacySetSlot;
use PickaxeMP\protocol\ProtocolException;

/**
 * `InventoryTransactionPacket` (id **30**, istemci → sunucu).
 *
 * Sema (Mojang v1.26.65, 2225; `InventoryTransactionPacketPayload.json`):
 *
 * ```
 *  0 Legacy Request ID      varint32
 *  1 Legacy Set Item Slots  istege bagli: varlik bayti + varuint32 sayi +
 *                           LegacySetSlot listesi
 *  2 Transaction            varuint32 kontrol degeri:
 *                           0 = Normal   -> Actions dizisi
 *                           2 = ItemUse  -> ItemUseInventoryTransaction
 *                           digerleri    -> ProtocolException
 * ```
 *
 * Phase 7 `Normal` (0) ve `ItemUse` (2) varyantlarini kabul eder. `ItemUse`
 * blok yerleştirmenin **gercek** girdisidir; sunucu bunu `Normal` gibi
 * yorumlamaz.
 */
final class InventoryTransactionPacket implements PacketInterface
{
    public const TYPE_NORMAL = 0;
    public const TYPE_ITEM_USE = 2;

    public const MAX_ACTIONS = 100;

    /**
     * @param list<LegacySetSlot> $legacySetItemSlots
     * @param list<InventoryAction> $actions Normal varyanti aksiyonlari
     */
    public function __construct(
        public readonly int $legacyRequestId = 0,
        public readonly array $legacySetItemSlots = [],
        public readonly array $actions = [],
        public readonly ?ItemUseInventoryTransaction $itemUse = null
    ) {
        if (count($actions) > self::MAX_ACTIONS) {
            throw ProtocolException::lengthTooLarge(
                'inventory transaction actions',
                count($actions),
                self::MAX_ACTIONS
            );
        }

        if ($itemUse !== null && $actions !== []) {
            throw new ProtocolException(
                'InventoryTransaction: Normal ve ItemUse varyantlari ayni pakette olamaz'
            );
        }
    }

    public function getId(): int
    {
        return PacketId::INVENTORY_TRANSACTION;
    }

    public function type(): int
    {
        return $this->itemUse !== null ? self::TYPE_ITEM_USE : self::TYPE_NORMAL;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeVarInt($this->legacyRequestId);

        $writer->writeOptional($this->legacySetItemSlots !== [], function (BinaryWriter $w): void {
            $w->writeVarUInt(count($this->legacySetItemSlots));

            foreach ($this->legacySetItemSlots as $slot) {
                $slot->encode($w);
            }
        });

        if ($this->itemUse !== null) {
            $writer->writeVarUInt(self::TYPE_ITEM_USE);
            $this->itemUse->encode($writer);

            return;
        }

        $writer->writeVarUInt(self::TYPE_NORMAL);
        $writer->writeVarUInt(count($this->actions));

        foreach ($this->actions as $action) {
            $action->encode($writer);
        }
    }

    public static function decode(BinaryReader $reader): static
    {
        $legacyRequestId = $reader->readVarInt();

        $legacySetItemSlots = [];

        if ($reader->readBool()) {
            $count = $reader->readVarUInt();

            if ($count > LegacySetSlot::MAX_SLOTS) {
                throw ProtocolException::lengthTooLarge(
                    'legacy set item slots',
                    $count,
                    LegacySetSlot::MAX_SLOTS
                );
            }

            for ($i = 0; $i < $count; $i++) {
                $legacySetItemSlots[] = LegacySetSlot::decode($reader);
            }
        }

        $type = $reader->readVarUInt();

        if ($type === self::TYPE_ITEM_USE) {
            return new static(
                $legacyRequestId,
                $legacySetItemSlots,
                [],
                ItemUseInventoryTransaction::decode($reader)
            );
        }

        if ($type !== self::TYPE_NORMAL) {
            throw new ProtocolException(
                "InventoryTransaction: yalnizca Normal (0) ve ItemUse (2) destekleniyor, alinan: {$type}"
            );
        }

        $actionCount = $reader->readVarUInt();

        if ($actionCount > self::MAX_ACTIONS) {
            throw ProtocolException::lengthTooLarge(
                'inventory transaction actions',
                $actionCount,
                self::MAX_ACTIONS
            );
        }

        $actions = [];

        for ($i = 0; $i < $actionCount; $i++) {
            $actions[] = InventoryAction::decode($reader);
        }

        return new static($legacyRequestId, $legacySetItemSlots, $actions);
    }
}
