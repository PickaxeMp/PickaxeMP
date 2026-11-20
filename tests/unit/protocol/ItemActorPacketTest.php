<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\protocol;

use PHPUnit\Framework\TestCase;
use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\compression\CompressionManager;
use PickaxeMP\protocol\packet\PacketContext;
use PickaxeMP\protocol\packet\PacketDirection;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketRegistry;
use PickaxeMP\protocol\packet\packets\AddItemActorPacket;
use PickaxeMP\protocol\packet\packets\TakeItemActorPacket;
use PickaxeMP\protocol\packet\types\BlockPos;
use PickaxeMP\protocol\packet\types\NetworkItemStackDescriptor;
use PickaxeMP\protocol\packet\types\SynchedActorData;
use PickaxeMP\protocol\packet\types\SynchedActorDataEntry;
use PickaxeMP\protocol\packet\types\Vec3;
use PickaxeMP\protocol\ProtocolEngine;
use PickaxeMP\protocol\ProtocolException;

/**
 * `AddItemActor` (15) + `TakeItemActor` (17) + `SynchedActorData` kodlama
 * sözleşmesi.
 *
 * Şema: `docs/protocol/schemas/v1.26.65/AddItemActorPacket*.json`,
 * `TakeItemActorPacket*.json`, `DataItemEntry.json`.
 *
 * Altın baytlar elle hesaplanmıştır; kodlayıcı sessizce kayarsa test kırılır.
 */
final class ItemActorPacketTest extends TestCase
{
    private function engine(): ProtocolEngine
    {
        return new ProtocolEngine(PacketRegistry::withSupportedPackets(), new CompressionManager());
    }

    private function s2c(): PacketContext
    {
        return new PacketContext('test', PacketDirection::ServerToClient);
    }

    private function c2s(): PacketContext
    {
        return new PacketContext('test', PacketDirection::ClientToServer);
    }

    /**
     * Paket gövdesini (başlık olmadan) ham baytlara çevirir.
     */
    private function payload(AddItemActorPacket|TakeItemActorPacket $packet): string
    {
        $writer = new BinaryWriter();
        $packet->encode($writer);

        return $writer->bytes();
    }

    public function testPacketIdsMatchOfficialSchema(): void
    {
        self::assertSame(15, PacketId::ADD_ITEM_ACTOR);
        self::assertSame(17, PacketId::TAKE_ITEM_ACTOR);
        self::assertSame('AddItemActor', PacketId::name(15));
        self::assertSame('TakeItemActor', PacketId::name(17));
    }

    public function testRegistryDirections(): void
    {
        $registry = PacketRegistry::withSupportedPackets();

        self::assertSame(
            PacketDirection::ServerToClient,
            $registry->directionFor(PacketId::ADD_ITEM_ACTOR)
        );
        self::assertSame(
            PacketDirection::ClientToServer,
            $registry->directionFor(PacketId::TAKE_ITEM_ACTOR)
        );
    }

    public function testAddItemActorGoldenBytesWithoutMetadata(): void
    {
        $packet = new AddItemActorPacket(
            targetActorId: 1,
            targetRuntimeId: 2,
            item: new NetworkItemStackDescriptor(id: 1, count: 3),
            position: Vec3::zero(),
            velocity: Vec3::zero()
        );

        $expected = "\x02"                    // ActorUniqueID (varlong, zigzag 1)
            . "\x02"                          // ActorRuntimeID (varuint64)
            . "\x01\x00"                      // Id int16 LE
            . "\x03\x00"                      // Stack size uint16 LE
            . "\x00"                          // Aux value
            . "\x00"                          // Net Id Variant yok
            . "\x00"                          // Block Runtime Id
            . "\x04\x00\x00\x00\x00"          // User Data Buffer (4 bayt)
            . str_repeat("\x00", 24)          // Position + Velocity (6 float)
            . "\x00"                          // Entity Data (0 girdi)
            . "\x00";                         // Is From Fishing

        self::assertSame($expected, $this->payload($packet));
    }

    public function testAddItemActorRoundTripCarriesAllScalarFields(): void
    {
        $packet = new AddItemActorPacket(
            targetActorId: 1000,
            targetRuntimeId: 1000,
            item: new NetworkItemStackDescriptor(id: 264, count: 12),
            position: new Vec3(8.5, -57.25, -8.75),
            velocity: new Vec3(0.1, 0.2, -0.3),
            entityData: new SynchedActorData([
                SynchedActorDataEntry::int64(0, 424242),
                SynchedActorDataEntry::byte(2, -5),
            ]),
            isFromFishing: true
        );

        $engine = $this->engine();
        $decoded = $engine->decode($engine->encode($packet), $this->s2c());

        self::assertInstanceOf(AddItemActorPacket::class, $decoded);
        self::assertSame(1000, $decoded->targetActorId);
        self::assertSame(1000, $decoded->targetRuntimeId);
        self::assertSame(264, $decoded->item->id);
        self::assertSame(12, $decoded->item->count);
        self::assertSame(8.5, $decoded->position->x);
        self::assertSame(-57.25, $decoded->position->y);
        self::assertSame(-8.75, $decoded->position->z);
        self::assertEqualsWithDelta(0.1, $decoded->velocity->x, 1e-6);
        self::assertTrue($decoded->isFromFishing);
        self::assertSame(2, $decoded->entityData->count());
        self::assertSame(424242, $decoded->entityData->get(0)?->value);
        self::assertSame(-5, $decoded->entityData->get(2)?->value);
    }

    public function testMetadataRoundTripCoversEverySupportedType(): void
    {
        $data = new SynchedActorData([
            SynchedActorDataEntry::byte(1, 7),
            SynchedActorDataEntry::short(2, -300),
            SynchedActorDataEntry::int(3, -70000),
            SynchedActorDataEntry::float(4, 1.5),
            SynchedActorDataEntry::string(5, 'etiket'),
            SynchedActorDataEntry::pos(6, new BlockPos(1, -2, 3)),
            SynchedActorDataEntry::int64(7, -9007199254740991),
            SynchedActorDataEntry::vec3(8, new Vec3(0.25, -0.5, 2.0)),
        ]);

        $writer = new BinaryWriter();
        $data->encode($writer);

        $decoded = SynchedActorData::decode(new BinaryReader($writer->bytes()));

        self::assertSame(8, $decoded->count());
        self::assertSame(7, $decoded->get(1)?->value);
        self::assertSame(-300, $decoded->get(2)?->value);
        self::assertSame(-70000, $decoded->get(3)?->value);
        self::assertSame(1.5, $decoded->get(4)?->value);
        self::assertSame('etiket', $decoded->get(5)?->value);
        self::assertInstanceOf(BlockPos::class, $decoded->get(6)?->value);
        self::assertSame(-2, $decoded->get(6)?->value->y);
        self::assertSame(-9007199254740991, $decoded->get(7)?->value);
        self::assertInstanceOf(Vec3::class, $decoded->get(8)?->value);
        self::assertSame(2.0, $decoded->get(8)?->value->z);
    }

    public function testMetadataIsEncodedInAscendingIdOrder(): void
    {
        $data = new SynchedActorData([
            SynchedActorDataEntry::byte(9, 1),
            SynchedActorDataEntry::byte(3, 2),
        ]);

        $writer = new BinaryWriter();
        $data->encode($writer);

        // Sayi + (id 3, tip 0, legacy 0, deger 2) + (id 9, tip 0, legacy 0, deger 1)
        self::assertSame("\x02\x03\x00\x00\x02\x09\x00\x00\x01", $writer->bytes());
    }

    public function testSelectorAndLegacyTypeMismatchIsRejected(): void
    {
        // id=1, selector=2 (int), legacy=0 (byte) → uyusmuyor.
        $bytes = "\x01" . "\x02" . "\x00" . "\x01";

        $this->expectException(ProtocolException::class);
        SynchedActorDataEntry::decode(new BinaryReader($bytes));
    }

    public function testCompoundTagDecodeIsExplicitlyUnsupported(): void
    {
        // id=1, selector=5, legacy=5 → NBT gövdesi yok; açık hata beklenir.
        $bytes = "\x01" . "\x05" . "\x05";

        $this->expectException(ProtocolException::class);
        SynchedActorDataEntry::decode(new BinaryReader($bytes));
    }

    public function testDuplicateMetadataIdIsRejected(): void
    {
        $this->expectException(ProtocolException::class);
        new SynchedActorData([
            SynchedActorDataEntry::byte(4, 1),
            SynchedActorDataEntry::byte(4, 2),
        ]);
    }

    public function testMetadataCountIsCapped(): void
    {
        // 4097 girdi bildiren sayaç: gövde hiç okunmadan reddedilmeli.
        $writer = new BinaryWriter();
        $writer->writeVarUInt(4097);

        $this->expectException(ProtocolException::class);
        SynchedActorData::decode(new BinaryReader($writer->bytes()));
    }

    public function testTakeItemActorGoldenBytes(): void
    {
        $packet = new TakeItemActorPacket(itemRuntimeId: 5, actorRuntimeId: 9);

        self::assertSame("\x05\x09", $this->payload($packet));
    }

    public function testTakeItemActorRoundTrip(): void
    {
        $engine = $this->engine();
        $decoded = $engine->decode(
            $engine->encode(new TakeItemActorPacket(itemRuntimeId: 65537, actorRuntimeId: 3)),
            $this->c2s()
        );

        self::assertInstanceOf(TakeItemActorPacket::class, $decoded);
        self::assertSame(65537, $decoded->itemRuntimeId);
        self::assertSame(3, $decoded->actorRuntimeId);
    }

    public function testAddItemActorRejectsTrailingBytesInStrictMode(): void
    {
        $engine = $this->engine();
        $bytes = $engine->encode(new AddItemActorPacket(
            targetActorId: 1,
            targetRuntimeId: 1,
            item: new NetworkItemStackDescriptor(id: 1, count: 1),
            position: Vec3::zero(),
            velocity: Vec3::zero()
        )) . "\xFF";

        $this->expectException(ProtocolException::class);
        $engine->decode($bytes, $this->s2c());
    }
}
