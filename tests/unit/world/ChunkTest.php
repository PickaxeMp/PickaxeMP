<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\world;

use PHPUnit\Framework\TestCase;
use PickaxeMP\world\BlockPosition;
use PickaxeMP\world\chunk\Chunk;
use PickaxeMP\world\chunk\ChunkPosition;
use PickaxeMP\world\chunk\SubChunk;
use PickaxeMP\world\chunk\SubChunkSerializer;
use PickaxeMP\world\WorldException;

final class ChunkTest extends TestCase
{
    public function testChunkPositionFromBlockHandlesNegatives(): void
    {
        self::assertSame(0, ChunkPosition::fromBlock(0, 0)->x);
        self::assertSame(0, ChunkPosition::fromBlock(15, 15)->x);
        self::assertSame(1, ChunkPosition::fromBlock(16, 16)->x);
        self::assertSame(-1, ChunkPosition::fromBlock(-1, -1)->x);
        self::assertSame(-1, ChunkPosition::fromBlock(-16, -16)->z);
        self::assertSame(-2, ChunkPosition::fromBlock(-17, -17)->z);
    }

    public function testBlockPositionLocalCoordinates(): void
    {
        self::assertSame(0, (new BlockPosition(0, 0, 0))->localX());
        self::assertSame(15, (new BlockPosition(15, 0, 15))->localZ());
        self::assertSame(15, (new BlockPosition(-1, 0, -1))->localX());
        self::assertSame(-1, (new BlockPosition(-1, 0, -1))->chunkX());
        self::assertSame(0, (new BlockPosition(-16, 0, 0))->localX());
        self::assertSame(-1, (new BlockPosition(-16, 0, 0))->chunkX());
    }

    public function testSubChunkIndexForNegativeY(): void
    {
        self::assertSame(-4, Chunk::subChunkIndexFor(-64));
        self::assertSame(-1, Chunk::subChunkIndexFor(-1));
        self::assertSame(0, Chunk::subChunkIndexFor(0));
        self::assertSame(19, Chunk::subChunkIndexFor(319));
    }

    public function testYRangeIsTwentyFourSubChunks(): void
    {
        self::assertSame(24, Chunk::SUBCHUNK_COUNT);
        self::assertSame(19 - (-4) + 1, Chunk::SUBCHUNK_COUNT);
        self::assertTrue(Chunk::isYInRange(-64));
        self::assertTrue(Chunk::isYInRange(319));
        self::assertFalse(Chunk::isYInRange(-65));
        self::assertFalse(Chunk::isYInRange(320));
    }

    public function testSubChunkIndexOrdering(): void
    {
        // Bedrock: X en hızlı, sonra Z, sonra Y.
        self::assertSame(0, SubChunk::index(0, 0, 0));
        self::assertSame(1, SubChunk::index(1, 0, 0));
        self::assertSame(16, SubChunk::index(0, 0, 1));
        self::assertSame(256, SubChunk::index(0, 1, 0));
        self::assertSame(4095, SubChunk::index(15, 15, 15));
    }

    public function testSubChunkRejectsOutOfRange(): void
    {
        $this->expectException(WorldException::class);
        SubChunk::index(16, 0, 0);
    }

    public function testSubChunkGetSet(): void
    {
        $subChunk = new SubChunk();

        $subChunk->set(3, 4, 5, 12345);

        self::assertSame(12345, $subChunk->get(3, 4, 5));
        self::assertSame(0, $subChunk->get(0, 0, 0));
    }

    public function testSubChunkUniformDetection(): void
    {
        $air = new SubChunk();
        self::assertTrue($air->isUniform());
        self::assertTrue($air->isUniformWith(0));
        self::assertTrue($air->isEmpty(0));

        $air->set(0, 0, 0, 7);
        self::assertFalse($air->isUniform());
        self::assertFalse($air->isEmpty(0));
    }

    public function testSubChunkDirtyTracking(): void
    {
        $subChunk = new SubChunk();
        self::assertFalse($subChunk->isDirty());

        $subChunk->set(1, 1, 1, 5);
        self::assertTrue($subChunk->isDirty());

        // Aynı değeri yazmak tekrar kirli yapmaz.
        $subChunk->markClean();
        $subChunk->set(1, 1, 1, 5);
        self::assertFalse($subChunk->isDirty());
    }

    public function testSubChunkUniqueIds(): void
    {
        $subChunk = new SubChunk();
        $subChunk->set(0, 0, 0, 10);
        $subChunk->set(1, 0, 0, 20);
        $subChunk->set(2, 0, 0, 10);

        self::assertSame([10, 20, 0], $subChunk->uniqueIds());
    }

    public function testChunkSetAndGetAcrossSubChunks(): void
    {
        $chunk = new Chunk(new ChunkPosition(0, 0));

        $chunk->setBlock(-64, 0, 0, 1);
        $chunk->setBlock(0, 5, 5, 2);
        $chunk->setBlock(319, 15, 15, 3);

        self::assertSame(1, $chunk->getBlock(-64, 0, 0));
        self::assertSame(2, $chunk->getBlock(0, 5, 5));
        self::assertSame(3, $chunk->getBlock(319, 15, 15));
        self::assertSame(0, $chunk->getBlock(100, 0, 0));
    }

    public function testChunkRejectsOutOfRangeY(): void
    {
        $this->expectException(WorldException::class);
        (new Chunk(new ChunkPosition(0, 0)))->setBlock(-65, 0, 0, 1);
    }

    public function testWritingAirDoesNotCreateSubChunk(): void
    {
        $chunk = new Chunk(new ChunkPosition(0, 0), 0);

        $chunk->setBlock(0, 0, 0, 0);

        self::assertSame([], $chunk->subChunkIndices());
    }

    public function testChunkDirtyTracking(): void
    {
        $chunk = new Chunk(new ChunkPosition(0, 0));
        $chunk->markClean();
        self::assertFalse($chunk->isDirty());

        $chunk->setBlock(0, 0, 0, 1);
        self::assertTrue($chunk->isDirty());

        $chunk->markClean();
        self::assertFalse($chunk->isDirty());
    }

    public function testNonEmptySubChunkIndices(): void
    {
        $chunk = new Chunk(new ChunkPosition(0, 0));
        $chunk->setBlock(-64, 0, 0, 1);
        $chunk->setBlock(0, 0, 0, 2);
        $chunk->setBlock(16, 0, 0, 3);

        self::assertSame([-4, 0, 1], $chunk->nonEmptySubChunkIndices());
    }

    public function testEmptyChunk(): void
    {
        self::assertTrue((new Chunk(new ChunkPosition(0, 0)))->isEmpty());
    }

    public function testSubChunkRoundTripUniform(): void
    {
        $serializer = new SubChunkSerializer();
        $subChunk = new SubChunk();

        $data = $serializer->serialize($subChunk, 0, 0);
        $decoded = $serializer->deserialize($data);

        self::assertInstanceOf(SubChunk::class, $decoded['subChunk']);
        self::assertSame(0, $decoded['index']);
        self::assertTrue($decoded['subChunk']->isUniformWith(0));
    }

    public function testSubChunkRoundTripWithBlocks(): void
    {
        $serializer = new SubChunkSerializer();
        $subChunk = new SubChunk();

        // Üç farklı blok + hava.
        for ($x = 0; $x < 16; $x++) {
            for ($z = 0; $z < 16; $z++) {
                $value = match ($x % 3) {
                    0 => 100,
                    1 => 200,
                    default => 300,
                };
                $subChunk->set($x, 0, $z, $value);
            }
        }

        $data = $serializer->serialize($subChunk, -4, 0);
        $decoded = $serializer->deserialize($data);

        self::assertSame(-4, $decoded['index']);

        for ($x = 0; $x < 16; $x++) {
            for ($z = 0; $z < 16; $z++) {
                $expected = match ($x % 3) {
                    0 => 100,
                    1 => 200,
                    default => 300,
                };
                self::assertSame(
                    $expected,
                    $decoded['subChunk']->get($x, 0, $z),
                    "blok ({$x},0,{$z})"
                );
            }
        }
    }

    public function testSubChunkRoundTripRandomized(): void
    {
        mt_srand(424242);
        $serializer = new SubChunkSerializer();

        $subChunk = new SubChunk();
        $expected = [];

        for ($index = 0; $index < SubChunk::BLOCK_COUNT; $index++) {
            $value = mt_rand(0, 1) === 0 ? 0 : mt_rand(1, 500);
            $expected[$index] = $value;
            $subChunk->setIndex($index, $value);
        }

        $data = $serializer->serialize($subChunk, 1, 0);
        $decoded = $serializer->deserialize($data);

        for ($index = 0; $index < SubChunk::BLOCK_COUNT; $index++) {
            self::assertSame($expected[$index], $decoded['subChunk']->getIndex($index), "indeks {$index}");
        }
    }

    public function testSerializerRejectsBadVersion(): void
    {
        $serializer = new SubChunkSerializer();

        $this->expectException(WorldException::class);
        $serializer->deserialize("\x08\x01\x00\x00");
    }

    public function testSerializerRejectsTruncatedData(): void
    {
        $serializer = new SubChunkSerializer();

        $this->expectException(WorldException::class);
        $serializer->deserialize("\x09\x01\x00");
    }

    public function testSerializerRejectsEmptyData(): void
    {
        $this->expectException(WorldException::class);
        (new SubChunkSerializer())->deserialize('');
    }

    public function testSerializedSizeIsReasonable(): void
    {
        $serializer = new SubChunkSerializer();
        $subChunk = new SubChunk();

        // Tek blok → 2 palet girişi, 1 bit indeks = 4096/32 = 128 kelime.
        $subChunk->set(0, 0, 0, 42);
        $data = $serializer->serialize($subChunk, 0, 0);

        // 4 başlık + 1 palet sayım + 2 varint + 512 bayt indeks civarı.
        self::assertGreaterThan(500, strlen($data));
        self::assertLessThan(600, strlen($data));
    }
}
