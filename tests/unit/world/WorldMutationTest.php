<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\world;

use PHPUnit\Framework\TestCase;
use PickaxeMP\world\block\BlockRegistry;
use PickaxeMP\world\chunk\ChunkPosition;
use PickaxeMP\world\generator\FlatGenerator;
use PickaxeMP\world\storage\FileWorldStorage;
use PickaxeMP\world\World;
use PickaxeMP\world\WorldException;
use PickaxeMP\world\WorldProperties;

/**
 * Phase 7 - dunya mutasyonu (`World::getBlock` / `World::setBlock`).
 */
final class WorldMutationTest extends TestCase
{
    private string $root;

    private BlockRegistry $registry;

    private World $world;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pickaxe-mut-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0755, true);

        $this->registry = BlockRegistry::withPhase4Blocks();

        $storage = new FileWorldStorage(
            $this->root,
            $this->registry->state('minecraft:air')->getNetworkId()
        );

        $this->world = new World(
            new WorldProperties(
                name: 'test',
                displayName: 'Test World',
                seed: 424242,
                generator: 'flat',
                dimension: 'overworld',
                spawnX: 8.5,
                spawnY: -57.0,
                spawnZ: 8.5
            ),
            $storage,
            new FlatGenerator($this->registry)
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $path . DIRECTORY_SEPARATOR . $entry;

            if (is_dir($full)) {
                $this->removeDirectory($full);
            } else {
                @unlink($full);
            }
        }

        @rmdir($path);
    }

    private function stone(): int
    {
        return $this->registry->state('minecraft:stone')->getNetworkId();
    }

    private function air(): int
    {
        return $this->registry->state('minecraft:air')->getNetworkId();
    }

    public function testUnloadedChunkIsNotReadable(): void
    {
        self::assertFalse($this->world->blockPositionLoaded(100, -57, 100));
        self::assertNull($this->world->getBlock(100, -57, 100));
        self::assertFalse($this->world->setBlock(100, -57, 100, $this->stone()));
    }

    public function testLoadedChunkIsReadableAndMatchesChunk(): void
    {
        $position = new ChunkPosition(0, 0);
        $chunk = $this->world->getChunk($position);

        self::assertTrue($this->world->blockPositionLoaded(3, -58, 5));
        self::assertSame($chunk->getBlock(-58, 3, 5), $this->world->getBlock(3, -58, 5));
    }

    public function testSetBlockMutatesAndMarksChunkDirty(): void
    {
        $position = new ChunkPosition(0, 0);
        $chunk = $this->world->getChunk($position);
        $chunk->markClean();

        self::assertTrue($this->world->setBlock(3, -57, 5, $this->stone()));
        self::assertSame($this->stone(), $this->world->getBlock(3, -57, 5));
        self::assertTrue($chunk->isDirty());
    }

    public function testSetBlockWithSameValueReportsNoChange(): void
    {
        $this->world->getChunk(new ChunkPosition(0, 0));

        self::assertTrue($this->world->setBlock(3, -57, 5, $this->stone()));
        self::assertFalse($this->world->setBlock(3, -57, 5, $this->stone()));
    }

    public function testSetBlockAcceptsAirAndKeepsChunkReadable(): void
    {
        $this->world->getChunk(new ChunkPosition(0, 0));

        self::assertTrue($this->world->setBlock(3, -58, 5, $this->air()));
        self::assertSame($this->air(), $this->world->getBlock(3, -58, 5));
    }

    public function testNegativeCoordinatesMapToNegativeChunks(): void
    {
        $this->world->getChunk(new ChunkPosition(-1, -1));

        self::assertTrue($this->world->setBlock(-1, -57, -1, $this->stone()));
        self::assertSame($this->stone(), $this->world->getBlock(-1, -57, -1));

        $chunk = $this->world->getChunk(new ChunkPosition(-1, -1));
        self::assertSame($this->stone(), $chunk->getBlock(-57, 15, 15));
    }

    public function testSetBlockOutsideWorldHeightThrows(): void
    {
        $this->world->getChunk(new ChunkPosition(0, 0));

        try {
            $this->world->setBlock(0, 320, 0, $this->stone());
            self::fail('Y = 320 kabul edildi.');
        } catch (WorldException) {
            // beklenen
        }

        $this->expectException(WorldException::class);
        $this->world->setBlock(0, -65, 0, $this->stone());
    }

    public function testGetBlockOutsideWorldHeightReturnsNull(): void
    {
        $this->world->getChunk(new ChunkPosition(0, 0));

        self::assertNull($this->world->getBlock(0, 400, 0));
        self::assertFalse($this->world->blockPositionLoaded(0, -1000, 0));
    }

    public function testClosedWorldRejectsMutation(): void
    {
        $this->world->getChunk(new ChunkPosition(0, 0));
        $this->world->close();

        self::assertFalse($this->world->setBlock(3, -57, 5, $this->stone()));
        self::assertFalse($this->world->blockPositionLoaded(3, -57, 5));
        self::assertNull($this->world->getBlock(3, -57, 5));
    }

    public function testRegistryResolvesNetworkIdBackToState(): void
    {
        $stone = $this->registry->fromNetworkId($this->stone());

        self::assertNotNull($stone);
        self::assertSame('minecraft:stone', $stone->getName());

        self::assertNull($this->registry->fromNetworkId(0xDEADBEEF));
    }

    public function testRegistryResolvesNonDefaultState(): void
    {
        $snowy = $this->registry->state('minecraft:grass_block', ['snowy' => true]);

        $resolved = $this->registry->fromNetworkId($snowy->getNetworkId());

        self::assertNotNull($resolved);
        self::assertSame('minecraft:grass_block', $resolved->getName());
        self::assertTrue($resolved->getProperties()['snowy']);
    }
}
