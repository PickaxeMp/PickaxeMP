<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\protocol;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketDirection;
use PickaxeMP\protocol\packet\PacketRegistry;
use PickaxeMP\protocol\packet\packets\ChunkRadiusUpdatedPacket;
use PickaxeMP\protocol\packet\packets\NetworkChunkPublisherUpdatePacket;
use PickaxeMP\protocol\packet\packets\RequestChunkRadiusPacket;
use PickaxeMP\protocol\packet\packets\SetLocalPlayerAsInitializedPacket;
use PickaxeMP\protocol\packet\packets\SetSpawnPositionPacket;
use PickaxeMP\protocol\packet\packets\SetTimePacket;
use PickaxeMP\protocol\packet\types\BlockPos;
use PickaxeMP\protocol\packet\types\ChunkPos;
use PickaxeMP\protocol\packet\types\SpawnPositionType;
use PickaxeMP\protocol\ProtocolException;
use PHPUnit\Framework\TestCase;

/**
 * Phase 8 dünya akışı paketlerinin tel biçimi sözleşmesi.
 *
 * Her paket için üç şey doğrulanır:
 *  1. Altın baytlar (şemadan türetilmiş beklenen çıktı)
 *  2. Kodla → çöz → aynı değer (round-trip)
 *  3. Registry'de doğru yön ile kayıtlı olması
 */
final class WorldStreamPacketTest extends TestCase
{
    private function registry(): PacketRegistry
    {
        return PacketRegistry::withSupportedPackets();
    }

    // --- SetTimePacket (10) ---------------------------------------------

    public function testSetTimeEncodesZigZagVarInt(): void
    {
        $writer = new BinaryWriter();
        (new SetTimePacket(6000))->encode($writer);

        // 6000 -> ZigZag 12000 = 0b10_1110_1110_0000
        // varint: 12000 & 0x7F = 0x60 -> 0xE0 (devam), 12000 >> 7 = 93 = 0x5D
        self::assertSame("\xE0\x5D", $writer->bytes());
    }

    public function testSetTimeRoundTrips(): void
    {
        foreach ([0, 1, 6000, 12000, 18000, 23999] as $time) {
            $writer = new BinaryWriter();
            (new SetTimePacket($time))->encode($writer);

            $decoded = SetTimePacket::decode(new BinaryReader($writer->bytes()));

            self::assertSame($time, $decoded->time, "time={$time}");
        }
    }

    public function testSetTimeDefaultIsNoon(): void
    {
        self::assertSame(6000, SetTimePacket::DEFAULT_TIME);
        self::assertSame(6000, (new SetTimePacket())->time);
    }

    public function testSetTimeIsRegisteredServerToClient(): void
    {
        $registry = $this->registry();

        self::assertSame(SetTimePacket::class, $registry->classFor(10));
        self::assertSame(PacketDirection::ServerToClient, $registry->directionFor(10));
    }

    // --- RequestChunkRadiusPacket (69) ----------------------------------

    public function testRequestChunkRadiusEncodesRadiusThenMaxRadius(): void
    {
        $writer = new BinaryWriter();
        (new RequestChunkRadiusPacket(8, 32))->encode($writer);

        // 8 -> ZigZag 16 = 0x10; 32 = 0x20
        self::assertSame("\x10\x20", $writer->bytes());
    }

    public function testRequestChunkRadiusRoundTrips(): void
    {
        $writer = new BinaryWriter();
        (new RequestChunkRadiusPacket(5, 12))->encode($writer);

        $decoded = RequestChunkRadiusPacket::decode(new BinaryReader($writer->bytes()));

        self::assertSame(5, $decoded->chunkRadius);
        self::assertSame(12, $decoded->maxChunkRadius);
    }

    public function testRequestChunkRadiusMaxRadiusDefaultsToZero(): void
    {
        self::assertSame(0, (new RequestChunkRadiusPacket(4))->maxChunkRadius);
    }

    public function testRequestChunkRadiusTruncatesMaxRadiusToSingleByte(): void
    {
        // uint8 alanı: 300 -> 300 & 0xFF = 44
        $writer = new BinaryWriter();
        (new RequestChunkRadiusPacket(1, 300))->encode($writer);

        $decoded = RequestChunkRadiusPacket::decode(new BinaryReader($writer->bytes()));

        self::assertSame(44, $decoded->maxChunkRadius);
    }

    public function testRequestChunkRadiusIsRegisteredClientToServer(): void
    {
        $registry = $this->registry();

        self::assertSame(RequestChunkRadiusPacket::class, $registry->classFor(69));
        self::assertSame(PacketDirection::ClientToServer, $registry->directionFor(69));
    }

    // --- ChunkRadiusUpdatedPacket (70) ----------------------------------

    public function testChunkRadiusUpdatedRoundTrips(): void
    {
        foreach ([1, 2, 8, 32] as $radius) {
            $writer = new BinaryWriter();
            (new ChunkRadiusUpdatedPacket($radius))->encode($writer);

            $decoded = ChunkRadiusUpdatedPacket::decode(new BinaryReader($writer->bytes()));

            self::assertSame($radius, $decoded->chunkRadius);
        }
    }

    public function testChunkRadiusUpdatedGoldenBytes(): void
    {
        $writer = new BinaryWriter();
        (new ChunkRadiusUpdatedPacket(2))->encode($writer);

        // 2 -> ZigZag 4 = 0x04
        self::assertSame("\x04", $writer->bytes());
    }

    public function testChunkRadiusUpdatedIsRegisteredServerToClient(): void
    {
        $registry = $this->registry();

        self::assertSame(ChunkRadiusUpdatedPacket::class, $registry->classFor(70));
        self::assertSame(PacketDirection::ServerToClient, $registry->directionFor(70));
    }

    // --- SetLocalPlayerAsInitializedPacket (113) ------------------------

    public function testSetLocalPlayerAsInitializedUsesVarUInt64(): void
    {
        $writer = new BinaryWriter();
        (new SetLocalPlayerAsInitializedPacket(1))->encode($writer);

        // varuint64, 1 -> 0x01
        self::assertSame("\x01", $writer->bytes());
    }

    public function testSetLocalPlayerAsInitializedRoundTripsLargeId(): void
    {
        $writer = new BinaryWriter();
        (new SetLocalPlayerAsInitializedPacket(123456789))->encode($writer);

        $decoded = SetLocalPlayerAsInitializedPacket::decode(new BinaryReader($writer->bytes()));

        self::assertSame(123456789, $decoded->playerRuntimeId);
    }

    public function testSetLocalPlayerAsInitializedIsRegisteredClientToServer(): void
    {
        $registry = $this->registry();

        self::assertSame(SetLocalPlayerAsInitializedPacket::class, $registry->classFor(113));
        self::assertSame(PacketDirection::ClientToServer, $registry->directionFor(113));
    }

    // --- Registry bütünlüğü ---------------------------------------------

    public function testRegistryKnowsAllFourNewPackets(): void
    {
        $registry = $this->registry();

        self::assertTrue($registry->has(10));
        self::assertTrue($registry->has(69));
        self::assertTrue($registry->has(70));
        self::assertTrue($registry->has(113));
    }

    // --- NetworkChunkPublisherUpdatePacket (121) ------------------------

    public function testNetworkChunkPublisherUpdateEncodesPositionRadiusAndSavedChunks(): void
    {
        $writer = new BinaryWriter();
        (new NetworkChunkPublisherUpdatePacket(
            position: new BlockPos(0, 64, 0),
            radius: 48
        ))->encode($writer);

        // BlockPos: ZigZag varint 0 / 64 / 0 -> 00 80 01 00
        // Radius: varuint32 48 -> 30
        // Saved chunks: uint32 0 -> 00 00 00 00
        self::assertSame("\x00\x80\x01\x00\x30\x00\x00\x00\x00", $writer->bytes());
    }

    public function testNetworkChunkPublisherUpdateRoundTripsSavedChunks(): void
    {
        $packet = new NetworkChunkPublisherUpdatePacket(
            position: new BlockPos(-16, -20, 32),
            radius: 96,
            savedChunks: [new ChunkPos(0, 0), new ChunkPos(-1, 2)]
        );

        $writer = new BinaryWriter();
        $packet->encode($writer);

        $decoded = NetworkChunkPublisherUpdatePacket::decode(new BinaryReader($writer->bytes()));

        self::assertSame(-16, $decoded->position->x);
        self::assertSame(-20, $decoded->position->y);
        self::assertSame(32, $decoded->position->z);
        self::assertSame(96, $decoded->radius);
        self::assertCount(2, $decoded->savedChunks);
        self::assertSame(0, $decoded->savedChunks[0]->x);
        self::assertSame(-1, $decoded->savedChunks[1]->x);
        self::assertSame(2, $decoded->savedChunks[1]->z);
    }

    public function testNetworkChunkPublisherUpdateRejectsTooManySavedChunks(): void
    {
        $chunks = [];

        for ($i = 0; $i <= NetworkChunkPublisherUpdatePacket::MAX_SAVED_CHUNKS; $i++) {
            $chunks[] = new ChunkPos($i, $i);
        }

        $this->expectException(ProtocolException::class);

        new NetworkChunkPublisherUpdatePacket(new BlockPos(0, 0, 0), 16, $chunks);
    }

    public function testNetworkChunkPublisherUpdateIsRegisteredServerToClient(): void
    {
        $registry = $this->registry();

        self::assertSame(NetworkChunkPublisherUpdatePacket::class, $registry->classFor(121));
        self::assertSame(PacketDirection::ServerToClient, $registry->directionFor(121));
    }

    public function testChunkPosEncodesFixedInt32Pair(): void
    {
        $writer = new BinaryWriter();
        (new ChunkPos(1, -2))->encode($writer);

        // Sabit little-endian int32: 1 -> 01 00 00 00, -2 -> FE FF FF FF.
        self::assertSame("\x01\x00\x00\x00\xFE\xFF\xFF\xFF", $writer->bytes());
    }

    // --- SetSpawnPositionPacket (43) ------------------------------------

    public function testSetSpawnPositionEncodesSchemaOrdinalOrder(): void
    {
        $writer = new BinaryWriter();
        SetSpawnPositionPacket::worldSpawn(new BlockPos(0, -57, 0))->encode($writer);

        // Spawn type WorldSpawn -> ZigZag 1 -> 02
        // BlockPos 0 / -57 / 0: 0 -> 00, -57 -> ZigZag 113 -> 71, 0 -> 00
        // Dimension 0 -> 00
        // Spawn block pos ayni: 00 71 00
        self::assertSame(
            "\x02\x00\x71\x00\x00\x00\x71\x00",
            $writer->bytes()
        );
    }

    public function testSetSpawnPositionRoundTripsPlayerSpawn(): void
    {
        $packet = SetSpawnPositionPacket::playerRespawn(
            new BlockPos(10, 64, -20),
            dimension: 0
        );

        $writer = new BinaryWriter();
        $packet->encode($writer);

        $decoded = SetSpawnPositionPacket::decode(new BinaryReader($writer->bytes()));

        self::assertSame(SpawnPositionType::PlayerRespawn, $decoded->spawnType);
        self::assertSame(10, $decoded->blockPosition->x);
        self::assertSame(64, $decoded->blockPosition->y);
        self::assertSame(-20, $decoded->blockPosition->z);
        self::assertSame(0, $decoded->dimension);

        // Dunya spawn'i verilmediginde sentinel yazilir: istemci dunya
        // spawn'ini degistirmez.
        self::assertSame(
            SetSpawnPositionPacket::UNSET_SPAWN,
            $decoded->spawnBlockPos->x
        );
    }

    public function testSetSpawnPositionIsRegisteredServerToClient(): void
    {
        $registry = $this->registry();

        self::assertSame(SetSpawnPositionPacket::class, $registry->classFor(43));
        self::assertSame(PacketDirection::ServerToClient, $registry->directionFor(43));
    }

    public function testUnknownPacketIdStillThrows(): void
    {
        $this->expectException(\PickaxeMP\protocol\ProtocolException::class);

        $this->registry()->classFor(9999);
    }
}
