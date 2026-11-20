<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\world;

use PHPUnit\Framework\TestCase;
use PickaxeMP\world\block\BlockRegistry;
use PickaxeMP\world\chunk\Chunk;
use PickaxeMP\world\chunk\ChunkPosition;
use PickaxeMP\world\generator\FlatGenerator;
use PickaxeMP\world\WorldException;
use PickaxeMP\world\WorldProperties;

final class FlatGeneratorTest extends TestCase
{
    private FlatGenerator $generator;

    private BlockRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = BlockRegistry::withPhase4Blocks();
        $this->generator = new FlatGenerator($this->registry);
    }

    private function id(string $name, array $properties = []): int
    {
        return $this->registry->state($name, $properties)->getNetworkId();
    }

    public function testLayersAreCorrect(): void
    {
        $chunk = $this->generator->generate(new ChunkPosition(0, 0));

        $bedrock = $this->id('minecraft:bedrock');
        $stone = $this->id('minecraft:stone');
        $dirt = $this->id('minecraft:dirt');
        $grass = $this->id('minecraft:grass_block', ['snowy' => false]);
        $air = $this->id('minecraft:air');

        // Bedrock katmanı.
        self::assertSame($bedrock, $chunk->getBlock(-64, 0, 0));

        // Stone.
        self::assertSame($stone, $chunk->getBlock(-63, 0, 0));
        self::assertSame($stone, $chunk->getBlock(-61, 0, 0));

        // Dirt.
        self::assertSame($dirt, $chunk->getBlock(-60, 0, 0));
        self::assertSame($dirt, $chunk->getBlock(-59, 0, 0));

        // Grass (üst yüzey).
        self::assertSame($grass, $chunk->getBlock(-58, 0, 0));

        // Üstü hava.
        self::assertSame($air, $chunk->getBlock(-57, 0, 0));
        self::assertSame($air, $chunk->getBlock(100, 8, 8));
    }

    public function testAllColumnsAreFilled(): void
    {
        $chunk = $this->generator->generate(new ChunkPosition(3, -2));
        $grass = $this->id('minecraft:grass_block', ['snowy' => false]);

        for ($x = 0; $x < 16; $x++) {
            for ($z = 0; $z < 16; $z++) {
                self::assertSame($grass, $chunk->getBlock(-58, $x, $z), "kolon ({$x},{$z})");
            }
        }
    }

    public function testDeterministicForSamePosition(): void
    {
        $a = $this->generator->generate(new ChunkPosition(5, 7));
        $b = $this->generator->generate(new ChunkPosition(5, 7));

        for ($index = 0; $index < Chunk::SUBCHUNK_COUNT; $index++) {
            $subA = $a->getSubChunk($index - 4);
            $subB = $b->getSubChunk($index - 4);

            if ($subA === null || $subB === null) {
                self::assertSame($subA, $subB);
                continue;
            }

            self::assertSame($subA->blocks(), $subB->blocks(), "subchunk {$index}");
        }
    }

    public function testDifferentPositionsProduceSameFlatTerrain(): void
    {
        // Düz dünya konumdan bağımsızdır (bilinçli tasarım).
        $a = $this->generator->generate(new ChunkPosition(0, 0));
        $b = $this->generator->generate(new ChunkPosition(-100, 250));

        $grass = $this->id('minecraft:grass_block', ['snowy' => false]);

        self::assertSame($grass, $a->getBlock(-58, 0, 0));
        self::assertSame($grass, $b->getBlock(-58, 0, 0));
    }

    public function testGeneratedChunkIsClean(): void
    {
        $chunk = $this->generator->generate(new ChunkPosition(0, 0));

        self::assertFalse($chunk->isDirty(), 'Uretilen chunk temiz olmali');
    }

    public function testGeneratedChunkHasExpectedSubChunks(): void
    {
        $chunk = $this->generator->generate(new ChunkPosition(0, 0));

        // -58 → indeks -4 (y=-58 >> 4 = -4). Bloklar -64..-58 aralığında.
        self::assertContains(-4, $chunk->nonEmptySubChunkIndices());
        self::assertSame([-4], $chunk->nonEmptySubChunkIndices());
    }

    public function testSpawnIsAboveGrass(): void
    {
        $spawn = $this->generator->spawnPoint();

        self::assertSame(8.5, $spawn['x']);
        self::assertSame(8.5, $spawn['z']);
        self::assertSame((float) FlatGenerator::SPAWN_Y, $spawn['y']);
        self::assertGreaterThan(FlatGenerator::GRASS_Y, $spawn['y']);
    }

    public function testAirNetworkIdIsStable(): void
    {
        self::assertSame(
            $this->id('minecraft:air'),
            $this->generator->airNetworkId()
        );
    }
}