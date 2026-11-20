<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\world;

use PHPUnit\Framework\TestCase;
use PickaxeMP\world\block\BlockRegistry;
use PickaxeMP\world\chunk\Chunk;
use PickaxeMP\world\chunk\ChunkPosition;
use PickaxeMP\world\generator\FlatGenerator;
use PickaxeMP\world\storage\FileWorldStorage;
use PickaxeMP\world\WorldException;
use PickaxeMP\world\WorldProperties;

final class WorldStorageTest extends TestCase
{
    private string $root;

    private BlockRegistry $registry;

    private FileWorldStorage $storage;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pickaxe-world-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0755, true);

        $this->registry = BlockRegistry::withPhase4Blocks();
        $this->storage = new FileWorldStorage(
            $this->root,
            $this->registry->state('minecraft:air')->getNetworkId()
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

    private function properties(string $name = 'test'): WorldProperties
    {
        return new WorldProperties(
            name: $name,
            displayName: 'Test World',
            seed: 424242,
            generator: 'flat',
            dimension: 'overworld',
            spawnX: 8.5,
            spawnY: -57.0,
            spawnZ: 8.5,
            createdAt: 1000,
            updatedAt: 1000
        );
    }

    public function testSaveAndLoadProperties(): void
    {
        $this->storage->saveProperties($this->properties());

        self::assertTrue($this->storage->propertiesExist('test'));

        $loaded = $this->storage->loadProperties('test');

        self::assertSame('test', $loaded->name);
        self::assertSame(424242, $loaded->seed);
        self::assertSame(-57.0, $loaded->spawnY);
    }

    public function testMissingPropertiesThrows(): void
    {
        $this->expectException(WorldException::class);
        $this->storage->loadProperties('yok');
    }

    public function testChunkRoundTrip(): void
    {
        $generator = new FlatGenerator($this->registry);
        $chunk = $generator->generate(new ChunkPosition(2, -3));

        $this->storage->saveChunk('test', $chunk);

        self::assertTrue($this->storage->hasChunk('test', 2, -3));
        self::assertFalse($this->storage->hasChunk('test', 0, 0));

        $loaded = $this->storage->loadChunk('test', 2, -3);

        self::assertNotNull($loaded);
        self::assertSame(2, $loaded->getPosition()->x);
        self::assertSame(-3, $loaded->getPosition()->z);

        $grass = $this->registry->state('minecraft:grass_block', ['snowy' => false])->getNetworkId();
        self::assertSame($grass, $loaded->getBlock(-58, 0, 0));
    }

    public function testChunkRoundTripPreservesAllBlocks(): void
    {
        $generator = new FlatGenerator($this->registry);
        $chunk = $generator->generate(new ChunkPosition(0, 0));
        $buddy = $this->registry->state('minecraft:stone')->getNetworkId();

        // Birkaç blok değiştir.
        $chunk->setBlock(10, 3, 4, $buddy);
        $chunk->setBlock(-30, 15, 15, $buddy);

        $this->storage->saveChunk('test', $chunk);
        $loaded = $this->storage->loadChunk('test', 0, 0);

        self::assertNotNull($loaded);
        self::assertSame($buddy, $loaded->getBlock(10, 3, 4));
        self::assertSame($buddy, $loaded->getBlock(-30, 15, 15));
    }

    public function testSaveMarksChunkClean(): void
    {
        $generator = new FlatGenerator($this->registry);
        $chunk = $generator->generate(new ChunkPosition(0, 0));
        $chunk->setBlock(0, 0, 0, 999);
        self::assertTrue($chunk->isDirty());

        $this->storage->saveChunk('test', $chunk);

        self::assertFalse($chunk->isDirty());
    }

    public function testCorruptChunkIsRejected(): void
    {
        $generator = new FlatGenerator($this->registry);
        $chunk = $generator->generate(new ChunkPosition(0, 0));
        $this->storage->saveChunk('test', $chunk);

        $path = $this->storage->worldDirectory('test')
            . DIRECTORY_SEPARATOR . 'chunks' . DIRECTORY_SEPARATOR . '0_0.chunk';

        // Son baytı (CRC) boz.
        $contents = (string) file_get_contents($path);
        $contents[strlen($contents) - 1] = chr((ord($contents[strlen($contents) - 1]) + 1) % 256);
        file_put_contents($path, $contents);

        $this->expectException(WorldException::class);
        $this->storage->loadChunk('test', 0, 0);
    }

    public function testTruncatedChunkIsRejected(): void
    {
        $generator = new FlatGenerator($this->registry);
        $chunk = $generator->generate(new ChunkPosition(0, 0));
        $this->storage->saveChunk('test', $chunk);

        $path = $this->storage->worldDirectory('test')
            . DIRECTORY_SEPARATOR . 'chunks' . DIRECTORY_SEPARATOR . '0_0.chunk';

        $contents = (string) file_get_contents($path);
        file_put_contents($path, substr($contents, 0, 10));

        $this->expectException(WorldException::class);
        $this->storage->loadChunk('test', 0, 0);
    }

    public function testBadMagicIsRejected(): void
    {
        $generator = new FlatGenerator($this->registry);
        $chunk = $generator->generate(new ChunkPosition(0, 0));
        $this->storage->saveChunk('test', $chunk);

        $path = $this->storage->worldDirectory('test')
            . DIRECTORY_SEPARATOR . 'chunks' . DIRECTORY_SEPARATOR . '0_0.chunk';

        $contents = (string) file_get_contents($path);
        $contents[0] = 'X';
        // CRC'yi yeniden hesapla ki magic kontrolüne ulaşabilelim.
        $body = substr($contents, 0, -4);
        $contents = $body . pack('V', crc32($body) & 0xFFFFFFFF);
        file_put_contents($path, $contents);

        $this->expectException(WorldException::class);
        $this->storage->loadChunk('test', 0, 0);
    }

    public function testMissingChunkReturnsNull(): void
    {
        self::assertNull($this->storage->loadChunk('test', 99, 99));
    }

    public function testListChunks(): void
    {
        $generator = new FlatGenerator($this->registry);

        $this->storage->saveChunk('test', $generator->generate(new ChunkPosition(0, 0)));
        $this->storage->saveChunk('test', $generator->generate(new ChunkPosition(-5, 3)));

        $list = $this->storage->listChunks('test');

        self::assertCount(2, $list);
        self::assertContains('-5,3', $list);
        self::assertContains('0,0', $list);
    }

    public function testRejectsPathTraversalInWorldName(): void
    {
        $this->expectException(WorldException::class);
        $this->storage->saveProperties($this->properties('../escape'));
    }

    public function testRejectsInvalidPropertyJson(): void
    {
        $directory = $this->storage->worldDirectory('bozuk');
        mkdir($directory, 0755, true);
        file_put_contents($directory . DIRECTORY_SEPARATOR . 'world.json', '{bozuk json');

        $this->expectException(WorldException::class);
        $this->storage->loadProperties('bozuk');
    }

    public function testAtomicWriteLeavesNoTempFile(): void
    {
        $this->storage->saveProperties($this->properties());

        $files = scandir($this->storage->worldDirectory('test')) ?: [];

        self::assertNotContains('world.json.tmp', $files);
        self::assertContains('world.json', $files);
    }
}
