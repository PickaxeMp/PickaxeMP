<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\ProtocolException;

/**
 * `ItemUseInventoryTransaction` - `InventoryTransaction` varyantı 2.
 *
 * Mojang `v1.26.65` ordinal sırası (0..12):
 *
 * ```
 *  0 Actions                    InventoryTransaction (varuint sayı + InventoryAction)
 *  1 Action Type                int32 (Compression, Enum-as-Value)
 *  2 Trigger Type               uint8 (Enum-as-Value)
 *  3 Position                   BlockPos
 *  4 Face                       uint8 (Compression)
 *  5 Slot                       int32 (Compression → ZigZag varint32)
 *  6 Hand                       uint8 (Enum-as-Value)
 *  7 Item                       NetworkItemStackDescriptor
 *  8 From Position              Vec3
 *  9 Click Position             Vec3
 * 10 Target Block Id            uint32 (Compression → varuint32)
 * 11 Client Interact Prediction uint8
 * 12 Client Cooldown State      uint8
 * ```
 *
 * Blok yerleştirmenin gerçek girdisi budur; kırma `PlayerAction`/blok
 * aksiyonlarından gelir.
 */
final class ItemUseInventoryTransaction
{
    public const MAX_ACTIONS = 100;

    /**
     * @param list<InventoryAction> $actions
     */
    public function __construct(
        public readonly array $actions,
        public readonly ItemUseActionType $actionType,
        public readonly int $triggerType,
        public readonly BlockPos $position,
        public readonly int $face,
        public readonly int $slot,
        public readonly HandSlot $hand,
        public readonly NetworkItemStackDescriptor $item,
        public readonly Vec3 $fromPosition,
        public readonly Vec3 $clickPosition,
        public readonly int $targetBlockId,
        public readonly int $clientInteractPrediction,
        public readonly int $clientCooldownState
    ) {
        if (count($actions) > self::MAX_ACTIONS) {
            throw ProtocolException::lengthTooLarge(
                'item use inventory actions',
                count($actions),
                self::MAX_ACTIONS
            );
        }

        if ($face < 0 || $face > 0xFF) {
            throw ProtocolException::varIntOverflow('item use face (uint8)');
        }

        if ($triggerType < 0 || $triggerType > 0xFF) {
            throw ProtocolException::varIntOverflow('item use trigger type (uint8)');
        }

        if ($clientInteractPrediction < 0 || $clientInteractPrediction > 0xFF) {
            throw ProtocolException::varIntOverflow('item use prediction (uint8)');
        }

        if ($clientCooldownState < 0 || $clientCooldownState > 0xFF) {
            throw ProtocolException::varIntOverflow('item use cooldown state (uint8)');
        }

        if ($targetBlockId < 0 || $targetBlockId > 0xFFFFFFFF) {
            throw ProtocolException::varIntOverflow('item use target block id (uint32)');
        }
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeVarUInt(count($this->actions));

        foreach ($this->actions as $action) {
            $action->encode($writer);
        }

        $writer->writeVarInt($this->actionType->value);
        $writer->writeByte($this->triggerType);
        $this->position->encode($writer);
        $writer->writeByte($this->face);
        $writer->writeVarInt($this->slot);
        $writer->writeByte($this->hand->value);
        $this->item->encode($writer);
        $this->fromPosition->encode($writer);
        $this->clickPosition->encode($writer);
        $writer->writeVarUInt($this->targetBlockId);
        $writer->writeByte($this->clientInteractPrediction);
        $writer->writeByte($this->clientCooldownState);
    }

    public static function decode(BinaryReader $reader): self
    {
        $actionCount = $reader->readVarUInt();

        if ($actionCount > self::MAX_ACTIONS) {
            throw ProtocolException::lengthTooLarge(
                'item use inventory actions',
                $actionCount,
                self::MAX_ACTIONS
            );
        }

        $actions = [];

        for ($i = 0; $i < $actionCount; $i++) {
            $actions[] = InventoryAction::decode($reader);
        }

        $actionType = ItemUseActionType::fromWire($reader->readVarInt());
        $triggerType = $reader->readByte();
        $position = BlockPos::decode($reader);
        $face = $reader->readByte();
        $slot = $reader->readVarInt();
        $hand = HandSlot::fromWire($reader->readByte());
        $item = NetworkItemStackDescriptor::decode($reader);
        $fromPosition = Vec3::decode($reader);
        $clickPosition = Vec3::decode($reader);
        $targetBlockId = $reader->readVarUInt();
        $clientInteractPrediction = $reader->readByte();
        $clientCooldownState = $reader->readByte();

        return new self(
            actions: $actions,
            actionType: $actionType,
            triggerType: $triggerType,
            position: $position,
            face: $face,
            slot: $slot,
            hand: $hand,
            item: $item,
            fromPosition: $fromPosition,
            clickPosition: $clickPosition,
            targetBlockId: $targetBlockId,
            clientInteractPrediction: $clientInteractPrediction,
            clientCooldownState: $clientCooldownState
        );
    }
}
