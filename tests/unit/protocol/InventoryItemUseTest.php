<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\protocol;

use PHPUnit\Framework\TestCase;
use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\packets\InventoryTransactionPacket;
use PickaxeMP\protocol\packet\packets\PlayerAuthInputPacket;
use PickaxeMP\protocol\packet\types\BlockPos;
use PickaxeMP\protocol\packet\types\HandSlot;
use PickaxeMP\protocol\packet\types\ItemUseActionType;
use PickaxeMP\protocol\packet\types\ItemUseInventoryTransaction;
use PickaxeMP\protocol\packet\types\NetworkItemStackDescriptor;
use PickaxeMP\protocol\packet\types\PlayerAuthInputFlags;
use PickaxeMP\protocol\packet\types\Vec3;
use PickaxeMP\protocol\ProtocolException;

/**
 * Phase 7 - `ItemUseInventoryTransaction` (varyant 2) ve
 * `PlayerAuthInput` bit 32/34 davranisi.
 *
 * Bit 34 (`Item Stack Request`) Phase 10.3'te desteklenir: alan tel uzerinde
 * `ItemStackRequest` olarak cozulur ve gidis-donus yapar.
 */
final class InventoryItemUseTest extends TestCase
{
    private function bytes(object $packet): string
    {
        $writer = new BinaryWriter();
        $packet->encode($writer);

        return $writer->bytes();
    }

    private function itemUse(): ItemUseInventoryTransaction
    {
        return new ItemUseInventoryTransaction(
            actions: [],
            actionType: ItemUseActionType::Place,
            triggerType: 0,
            position: new BlockPos(3, -58, 5),
            face: 1,
            slot: 4,
            hand: HandSlot::MainHand,
            item: NetworkItemStackDescriptor::empty(),
            fromPosition: new Vec3(8.5, -57.0, 8.5),
            clickPosition: new Vec3(0.5, 1.0, 0.5),
            targetBlockId: 12345,
            clientInteractPrediction: 1,
            clientCooldownState: 0
        );
    }

    public function testItemUseVariantRoundTripsInsideInventoryTransaction(): void
    {
        $packet = new InventoryTransactionPacket(itemUse: $this->itemUse());

        self::assertSame(InventoryTransactionPacket::TYPE_ITEM_USE, $packet->type());

        $decoded = InventoryTransactionPacket::decode(new BinaryReader($this->bytes($packet)));

        self::assertSame(InventoryTransactionPacket::TYPE_ITEM_USE, $decoded->type());
        self::assertSame([], $decoded->legacySetItemSlots);

        $use = $decoded->itemUse;
        self::assertNotNull($use);
        self::assertSame(ItemUseActionType::Place, $use->actionType);
        self::assertSame(1, $use->face);
        self::assertSame(4, $use->slot);
        self::assertSame(HandSlot::MainHand, $use->hand);
        self::assertSame(3, $use->position->x);
        self::assertSame(-58, $use->position->y);
        self::assertSame(5, $use->position->z);
        self::assertSame(12345, $use->targetBlockId);
        self::assertSame(1, $use->clientInteractPrediction);
        self::assertTrue($use->item->isEmpty());
    }

    public function testItemUseVariantWritesTransactionTypeTwo(): void
    {
        // legacyRequestId = 0 -> varint 0x00 ; legacy slots yok -> 0x00 ;
        // transactionType = 2 -> varuint 0x02
        $bytes = $this->bytes(new InventoryTransactionPacket(itemUse: $this->itemUse()));

        self::assertSame("\x00\x00\x02", substr($bytes, 0, 3));
    }

    public function testNormalAndItemUseVariantsAreMutuallyExclusive(): void
    {
        $action = new \PickaxeMP\protocol\packet\types\InventoryAction(
            source: new \PickaxeMP\protocol\packet\types\InventorySource(
                \PickaxeMP\protocol\packet\types\InventorySource::TYPE_CONTAINER,
                containerId: 0
            ),
            slot: 0,
            fromItem: NetworkItemStackDescriptor::empty(),
            toItem: NetworkItemStackDescriptor::empty()
        );

        $this->expectException(\PickaxeMP\protocol\ProtocolException::class);

        new InventoryTransactionPacket(actions: [$action], itemUse: $this->itemUse());
    }

    public function testNormalVariantStillEncodesTypeZero(): void
    {
        $bytes = $this->bytes(new InventoryTransactionPacket());

        self::assertSame("\x00\x00\x00\x00", $bytes);
    }

    public function testPlayerAuthInputCarriesItemUseTransaction(): void
    {
        $packet = new PlayerAuthInputPacket(
            pitch: 0.0,
            yaw: 0.0,
            x: 1.0,
            y: 2.0,
            z: 3.0,
            moveX: 0.0,
            moveZ: 0.0,
            headYaw: 0.0,
            inputData: 1 << PlayerAuthInputFlags::PERFORM_ITEM_INTERACTION,
            inputMode: 0,
            playMode: 0,
            interactionModel: 0,
            interactPitch: 0.0,
            interactYaw: 0.0,
            clientTick: 7,
            itemUseTransaction: $this->itemUse()
        );

        $decoded = PlayerAuthInputPacket::decode(new BinaryReader($this->bytes($packet)));

        self::assertTrue($decoded->hasFlag(PlayerAuthInputFlags::PERFORM_ITEM_INTERACTION));
        self::assertNotNull($decoded->itemUseTransaction);
        self::assertSame(ItemUseActionType::Place, $decoded->itemUseTransaction->actionType);
        self::assertSame(4, $decoded->itemUseTransaction->slot);
        self::assertSame(7, $decoded->clientTick);
    }

    public function testPlayerAuthInputWithoutItemUseFlagLeavesTransactionNull(): void
    {
        $packet = new PlayerAuthInputPacket(
            pitch: 0.0,
            yaw: 0.0,
            x: 1.0,
            y: 2.0,
            z: 3.0,
            moveX: 0.0,
            moveZ: 0.0,
            headYaw: 0.0,
            inputData: 0,
            inputMode: 0,
            playMode: 0,
            interactionModel: 0,
            interactPitch: 0.0,
            interactYaw: 0.0,
            clientTick: 1
        );

        $decoded = PlayerAuthInputPacket::decode(new BinaryReader($this->bytes($packet)));

        self::assertNull($decoded->itemUseTransaction);
    }

    public function testItemStackRequestFlagWithoutDataIsProtocolError(): void
    {
        $packet = new PlayerAuthInputPacket(
            pitch: 0.0,
            yaw: 0.0,
            x: 1.0,
            y: 2.0,
            z: 3.0,
            moveX: 0.0,
            moveZ: 0.0,
            headYaw: 0.0,
            inputData: 1 << PlayerAuthInputFlags::PERFORM_ITEM_STACK_REQUEST,
            inputMode: 0,
            playMode: 0,
            interactionModel: 0,
            interactPitch: 0.0,
            interactYaw: 0.0,
            clientTick: 1
        );

        $this->expectException(\PickaxeMP\protocol\ProtocolException::class);

        $packet->encode(new BinaryWriter());
    }

    public function testItemStackRequestRoundTripsInsideAuthInput(): void
    {
        $request = new \PickaxeMP\protocol\packet\types\ItemStackRequest(
            requestId: -3,
            actions: [
                \PickaxeMP\protocol\packet\types\ItemStackRequestAction::take(
                    2,
                    new \PickaxeMP\protocol\packet\types\ItemStackRequestSlotInfo(
                        new \PickaxeMP\protocol\packet\types\FullContainerName(12),
                        4
                    ),
                    new \PickaxeMP\protocol\packet\types\ItemStackRequestSlotInfo(
                        new \PickaxeMP\protocol\packet\types\FullContainerName(59),
                        0
                    )
                ),
            ]
        );

        $packet = new PlayerAuthInputPacket(
            pitch: 0.0,
            yaw: 0.0,
            x: 1.0,
            y: 2.0,
            z: 3.0,
            moveX: 0.0,
            moveZ: 0.0,
            headYaw: 0.0,
            inputData: 1 << PlayerAuthInputFlags::PERFORM_ITEM_STACK_REQUEST,
            inputMode: 0,
            playMode: 0,
            interactionModel: 0,
            interactPitch: 0.0,
            interactYaw: 0.0,
            clientTick: 1,
            itemStackRequest: $request
        );

        $decoded = PlayerAuthInputPacket::decode(new BinaryReader($this->bytes($packet)));

        self::assertNotNull($decoded->itemStackRequest);
        self::assertSame(-3, $decoded->itemStackRequest->requestId);
        self::assertCount(1, $decoded->itemStackRequest->actions);
        self::assertSame(2, $decoded->itemStackRequest->actions[0]->amount);
    }


    public function testItemUseFlagWithoutDataIsProtocolError(): void
    {
        $packet = new PlayerAuthInputPacket(
            pitch: 0.0,
            yaw: 0.0,
            x: 1.0,
            y: 2.0,
            z: 3.0,
            moveX: 0.0,
            moveZ: 0.0,
            headYaw: 0.0,
            inputData: 1 << PlayerAuthInputFlags::PERFORM_ITEM_INTERACTION,
            inputMode: 0,
            playMode: 0,
            interactionModel: 0,
            interactPitch: 0.0,
            interactYaw: 0.0,
            clientTick: 1
        );

        $this->expectException(\PickaxeMP\protocol\ProtocolException::class);
        $packet->encode(new BinaryWriter());
    }
}
