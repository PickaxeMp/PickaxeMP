<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\protocol;

use PHPUnit\Framework\TestCase;
use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\packets\ItemStackRequestPacket;
use PickaxeMP\protocol\packet\packets\ItemStackResponsePacket;
use PickaxeMP\protocol\packet\types\FullContainerName;
use PickaxeMP\protocol\packet\types\ItemStackNetResult;
use PickaxeMP\protocol\packet\types\ItemStackRequest;
use PickaxeMP\protocol\packet\types\ItemStackRequestAction;
use PickaxeMP\protocol\packet\types\ItemStackRequestSlotInfo;
use PickaxeMP\protocol\packet\types\ItemStackResponseContainerInfo;
use PickaxeMP\protocol\packet\types\ItemStackResponseInfo;
use PickaxeMP\protocol\packet\types\ItemStackResponseSlotInfo;
use PickaxeMP\protocol\ProtocolException;
use PickaxeMP\protocol\UnsupportedPacketFieldException;

/**
 * Phase 10.3 — `ItemStackRequest` (147) / `ItemStackResponse` (148) tel biçimi.
 *
 * Tel biçimi Mojang `v1.26.65` şeması (2225) ve gophertunnel `master`
 * uyarlamasıyla eşleşir. Kritik ayrıntı: her aksiyon **önce** `varuint32`
 * varyant indeksini, **sonra** `uint8` tür kimliğini taşır. Varyant listesi
 * 7 ve 8 (`PlaceInContainer` / `TakeOutContainer`) atladığı için tür >= 9
 * olduğunda varyant `tür - 2`'dir.
 */
final class ItemStackRequestTest extends TestCase
{
    private function bytes(object $packet): string
    {
        $writer = new BinaryWriter();
        $packet->encode($writer);

        return $writer->bytes();
    }

    private function hex(string $bytes): string
    {
        return implode(' ', array_map(
            static fn (string $byte): string => sprintf('%02X', ord($byte)),
            str_split($bytes)
        ));
    }

    private function slot(int $container, int $slot, int $netId = 0): ItemStackRequestSlotInfo
    {
        return new ItemStackRequestSlotInfo(
            new FullContainerName($container),
            $slot,
            $netId
        );
    }

    public function testTakeActionWireLayout(): void
    {
        // Elle kurulmus beklenen baytlar (yorum: alan alan).
        $expected = implode('', [
            "\x01",             // Requests sayisi (varuint32)
            "\x05",             // Client Request Id = -3 (zigzag varint32)
            "\x01",             // Actions sayisi (varuint32)
            "\x00",             // varyant (varuint32) = Take
            "\x00",             // tur (uint8) = Take
            "\x03",             // miktar (uint8)
            "\x3B\x00",         // source: Cursor (59), dinamik kimlik yok
            "\x00",             // source slot
            "\x00\x00\x00\x00", // source net id (sabit int32 LE)
            "\x0C\x00",         // destination: CombinedHotbarAndInventory (12)
            "\x05",             // destination slot
            "\x00\x00\x00\x00", // destination net id (sabit int32 LE)
            "\x00",             // filtre metni sayisi (varuint32)
            "\x00\x00\x00\x00", // filtre kaynagi (sabit int32 LE)
        ]);

        $packet = new ItemStackRequestPacket([
            new ItemStackRequest(
                requestId: -3,
                actions: [ItemStackRequestAction::take(3, $this->slot(59, 0), $this->slot(12, 5))],
                filterStrings: [],
                filterStringOrigin: 0
            ),
        ]);

        self::assertSame($this->hex($expected), $this->hex($this->bytes($packet)));
    }

    public function testRequestRoundTripsWithAllSupportedActions(): void
    {
        $packet = new ItemStackRequestPacket([
            new ItemStackRequest(
                requestId: -7,
                actions: [
                    ItemStackRequestAction::take(2, $this->slot(12, 3), $this->slot(59, 0)),
                    ItemStackRequestAction::place(2, $this->slot(59, 0), $this->slot(12, 9)),
                    ItemStackRequestAction::swap($this->slot(12, 9), $this->slot(12, 4)),
                ],
                filterStrings: ['Tester'],
                filterStringOrigin: 3
            ),
        ]);

        $decoded = ItemStackRequestPacket::decode(new BinaryReader($this->bytes($packet)));

        self::assertCount(1, $decoded->requests);

        $request = $decoded->requests[0];

        self::assertSame(-7, $request->requestId);
        self::assertTrue($request->hasValidRequestId());
        self::assertSame(['Tester'], $request->filterStrings);
        self::assertSame(3, $request->filterStringOrigin);
        self::assertCount(3, $request->actions);

        [$take, $place, $swap] = $request->actions;

        self::assertSame(ItemStackRequestAction::TAKE, $take->type);
        self::assertSame(2, $take->amount);
        self::assertSame(12, $take->source?->container->containerName);
        self::assertSame(3, $take->source?->slot);
        self::assertSame(59, $take->destination?->container->containerName);

        self::assertSame(ItemStackRequestAction::PLACE, $place->type);
        self::assertSame(9, $place->destination?->slot);

        self::assertSame(ItemStackRequestAction::SWAP, $swap->type);
        self::assertSame(0, $swap->amount);
        self::assertSame(9, $swap->source?->slot);
        self::assertSame(4, $swap->destination?->slot);
    }

    public function testVariantPrefixUsesTypeMinusTwoAboveContainerActions(): void
    {
        // CraftRecipe (12) -> varyant 10, sonra tur 12.
        $action = new ItemStackRequestAction(
            type: ItemStackRequestAction::CRAFT_RECIPE,
            recipeNetworkId: 4
        );

        self::assertSame(10, $action->variant());

        $encoded = new BinaryWriter();
        $action->encode($encoded);

        $bytes = $encoded->bytes();

        self::assertSame("\x0A\x0C", substr($bytes, 0, 2));
    }

    public function testMismatchedVariantAndTypeIsRejected(): void
    {
        $writer = new BinaryWriter();
        $writer->writeVarUInt(1);   // requests
        $writer->writeVarInt(-3);   // request id
        $writer->writeVarUInt(1);   // actions
        $writer->writeVarUInt(7);   // varyant: CraftNonImplemented beklenir
        $writer->writeByte(12);     // tur: CraftRecipe

        $this->expectException(ProtocolException::class);

        ItemStackRequestPacket::decode(new BinaryReader($writer->bytes()));
    }

    public function testUnsupportedActionPayloadRaisesRecoverableException(): void
    {
        $writer = new BinaryWriter();
        $writer->writeVarUInt(1);
        $writer->writeVarInt(-5);
        $writer->writeVarUInt(1);
        // CraftRecipeAuto (13) -> varyant 11; gövdesi eşya tanımlayıcı listesi
        // taşır ve Phase 10.3 kapsamında çözülmez.
        $writer->writeVarUInt(11);
        $writer->writeByte(13);

        $this->expectException(UnsupportedPacketFieldException::class);

        ItemStackRequestPacket::decode(new BinaryReader($writer->bytes()));
    }

    public function testTooManyActionsIsRejected(): void
    {
        $writer = new BinaryWriter();
        $writer->writeVarUInt(1);
        $writer->writeVarInt(-3);
        $writer->writeVarUInt(101);

        $this->expectException(ProtocolException::class);

        ItemStackRequestPacket::decode(new BinaryReader($writer->bytes()));
    }

    public function testTooManyRequestsIsRejected(): void
    {
        $writer = new BinaryWriter();
        $writer->writeVarUInt(101);

        $this->expectException(ProtocolException::class);

        ItemStackRequestPacket::decode(new BinaryReader($writer->bytes()));
    }

    public function testRequestIdMustBeNegativeAndOdd(): void
    {
        $request = new ItemStackRequest(-3, [ItemStackRequestAction::take(1, $this->slot(12, 0), $this->slot(59, 0))]);

        self::assertTrue($request->hasValidRequestId());
        self::assertFalse((new ItemStackRequest(-4, []))->hasValidRequestId());
        self::assertFalse((new ItemStackRequest(3, []))->hasValidRequestId());
        self::assertFalse((new ItemStackRequest(1, []))->hasValidRequestId());
    }

    public function testSuccessResponseWireLayout(): void
    {
        $expected = implode('', [
            "\x01",                     // Responses sayisi
            "\x00",                     // Result = Success
            "\x05",                     // Client Request Id = -3 (zigzag)
            "\x01",                     // konteyner bilgisi var
            "\x01",                     // konteyner sayisi
            "\x0C\x00",                 // CombinedHotbarAndInventory (12)
            "\x01",                     // yuva bilgisi sayisi
            "\x05",                     // Requested Slot
            "\x05",                     // Slot
            "\x02",                     // Amount
            "\x00",                     // net id yok (varlik bayti)
            "\x00",                     // Custom Name = "" (uzunluk 0)
            "\x00",                     // Filtered Name yok
            "\x00",                     // Durability Correction = 0 (zigzag)
        ]);

        $packet = new ItemStackResponsePacket([
            ItemStackResponseInfo::success(-3, [
                new ItemStackResponseContainerInfo(
                    new FullContainerName(12),
                    [new ItemStackResponseSlotInfo(requestedSlot: 5, slot: 5, amount: 2)]
                ),
            ]),
        ]);

        self::assertSame($this->hex($expected), $this->hex($this->bytes($packet)));
    }

    public function testErrorResponseOmitsContainers(): void
    {
        $packet = new ItemStackResponsePacket([
            ItemStackResponseInfo::error(-3, ItemStackNetResult::INVALID_REMOVED_AMOUNT),
        ]);

        $decoded = ItemStackResponsePacket::decode(new BinaryReader($this->bytes($packet)));

        self::assertCount(1, $decoded->responses);

        $response = $decoded->responses[0];

        self::assertSame(ItemStackNetResult::INVALID_REMOVED_AMOUNT, $response->result);
        self::assertSame(-3, $response->requestId);
        self::assertSame([], $response->containers);
        self::assertFalse($response->isSuccess());
    }

    public function testResponseRoundTripsContainerSlots(): void
    {
        $packet = new ItemStackResponsePacket([
            ItemStackResponseInfo::success(-9, [
                new ItemStackResponseContainerInfo(
                    new FullContainerName(12),
                    [
                        new ItemStackResponseSlotInfo(requestedSlot: 1, slot: 1, amount: 0),
                        new ItemStackResponseSlotInfo(
                            requestedSlot: 2,
                            slot: 2,
                            amount: 5,
                            netId: 0,
                            customName: 'Elmas',
                            durabilityCorrection: 12
                        ),
                    ]
                ),
                new ItemStackResponseContainerInfo(
                    new FullContainerName(59),
                    [new ItemStackResponseSlotInfo(requestedSlot: 0, slot: 0, amount: 3)]
                ),
            ]),
        ]);

        $decoded = ItemStackResponsePacket::decode(new BinaryReader($this->bytes($packet)));
        $response = $decoded->responses[0];

        self::assertTrue($response->isSuccess());
        self::assertSame(-9, $response->requestId);
        self::assertCount(2, $response->containers);

        $slots = $response->containers[0]->slots;

        self::assertCount(2, $slots);
        self::assertSame(0, $slots[0]->amount);
        self::assertSame(5, $slots[1]->amount);
        self::assertSame('Elmas', $slots[1]->customName);
        self::assertSame(12, $slots[1]->durabilityCorrection);
        self::assertSame(59, $response->containers[1]->container->containerName);
    }

    public function testSlotInfoContainerAndNetIdRoundTrip(): void
    {
        $slot = new ItemStackRequestSlotInfo(new FullContainerName(12, 77), 35, 1234);

        $writer = new BinaryWriter();
        $slot->encode($writer);

        $decoded = ItemStackRequestSlotInfo::decode(new BinaryReader($writer->bytes()));

        self::assertSame(12, $decoded->container->containerName);
        self::assertSame(77, $decoded->container->dynamicId);
        self::assertSame(35, $decoded->slot);
        self::assertSame(1234, $decoded->netId);
    }

    public function testMovingActionRequiresAmountInsideSchemaRange(): void
    {
        $this->expectException(ProtocolException::class);

        ItemStackRequestAction::take(0, $this->slot(12, 0), $this->slot(59, 0));
    }
}
