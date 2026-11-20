<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\world;

use PickaxeMP\protocol\packet\packets\LevelChunkPacket;
use PickaxeMP\world\chunk\ChunkPosition;
use PickaxeMP\world\chunk\ChunkSerializer;
use PickaxeMP\world\ChunkStreamer;
use PickaxeMP\world\generator\FlatGenerator;
use PickaxeMP\world\block\BlockRegistry;
use PickaxeMP\world\storage\FileWorldStorage;
use PickaxeMP\world\World;
use PickaxeMP\world\WorldException;
use PickaxeMP\world\WorldProperties;
use PHPUnit\Framework\TestCase;

/**
 * `ChunkStreamer` sözleşmesi: yarıçap kırpma, tekrar gönderim engeli,
 * kare pencere boyutu ve giriş chunk'ının işaretlenmesi.
 */
final class ChunkStreamerTest extends TestCase
{
    private string $root;

    private World $world;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pickaxe_streamer_' . bin2hex(random_bytes(6));
        mkdir($this->root, 0755, true);

        $registry = BlockRegistry::withPhase4Blocks();

        $storage = new FileWorldStorage(
            $this->root,
            $registry->state('minecraft:air')->getNetworkId()
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
            new FlatGenerator($registry)
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
                continue;
            }

            @unlink($full);
        }

        @rmdir($path);
    }

    // --- Yapılandırma ---------------------------------------------------

    public function testDefaultRadiusIsTwo(): void
    {
        self::assertSame(ChunkStreamer::DEFAULT_RADIUS, (new ChunkStreamer())->viewDistance());
    }

    public function testRejectsRadiusBelowMinimum(): void
    {
        $this->expectException(WorldException::class);

        new ChunkStreamer(0);
    }

    public function testRejectsRadiusAboveMaximum(): void
    {
        $this->expectException(WorldException::class);

        new ChunkStreamer(ChunkStreamer::MAX_RADIUS + 1);
    }

    // --- Yarıçap kırpma -------------------------------------------------

    public function testClampKeepsValueInsideBounds(): void
    {
        $streamer = new ChunkStreamer(3);

        self::assertSame(3, $streamer->clampRadius(3));
        self::assertSame(1, $streamer->clampRadius(0));
        self::assertSame(1, $streamer->clampRadius(-10));
        self::assertSame(3, $streamer->clampRadius(99));
    }

    public function testClampRadiusUsesViewDistanceAsCeiling(): void
    {
        $streamer = new ChunkStreamer(2);

        self::assertSame(2, $streamer->clampRadius(8));
        self::assertSame(2, $streamer->clampRadius(2));
        self::assertSame(1, $streamer->clampRadius(1));
    }

    // --- sync() davranışı -----------------------------------------------

    public function testSyncReturnsSquareWindow(): void
    {
        $streamer = new ChunkStreamer(1);

        $packets = $streamer->sync($this->world, 's1', 8.5, 8.5);

        // (2*1+1)^2 = 9
        self::assertCount(9, $packets);
    }

    public function testSyncUsesRequestedRadiusWhenSmaller(): void
    {
        $streamer = new ChunkStreamer(4);

        // İstenen 1 -> (3)^2 = 9 paket; 9x9 değil.
        $packets = $streamer->sync($this->world, 's1', 8.5, 8.5, 1);

        self::assertCount(9, $packets);
    }

    public function testSyncSecondCallReturnsNothingNew(): void
    {
        $streamer = new ChunkStreamer(1);

        $first = $streamer->sync($this->world, 's1', 8.5, 8.5);
        $second = $streamer->sync($this->world, 's1', 8.5, 8.5);

        self::assertCount(9, $first);
        self::assertCount(0, $second);
        self::assertSame(9, $streamer->sentCount('s1'));
    }

    public function testSyncAfterMovementReturnsOnlyNewChunks(): void
    {
        $streamer = new ChunkStreamer(1);

        $streamer->sync($this->world, 's1', 8.5, 8.5);

        // 16 blok sağa: 1 chunk kayma -> sadece yeni sütun (3 chunk).
        $moved = $streamer->sync($this->world, 's1', 24.5, 8.5);

        self::assertCount(3, $moved);
        self::assertSame(12, $streamer->sentCount('s1'));
    }

    public function testSessionsAreIsolated(): void
    {
        $streamer = new ChunkStreamer(1);

        $streamer->sync($this->world, 's1', 8.5, 8.5);
        $packets = $streamer->sync($this->world, 's2', 8.5, 8.5);

        // İkinci oturum aynı chunk'ları **alır**: tekrar gönderim
        // oturum başına değil, bağlantı başına engellenir.
        self::assertCount(9, $packets);
    }

    public function testMarkSentPreventsDuplicateOfLoginChunk(): void
    {
        $streamer = new ChunkStreamer(1);

        // Giriş paketleri spawn chunk'ını zaten yolladı.
        $streamer->markSent('s1', ChunkPosition::fromBlock(8, 8));

        $packets = $streamer->sync($this->world, 's1', 8.5, 8.5);

        // 9'un 1'i zaten gitti -> 8 yeni.
        self::assertCount(8, $packets);
    }

    public function testHasSentReportsMarkedChunk(): void
    {
        $streamer = new ChunkStreamer(1);
        $position = new ChunkPosition(0, 0);

        self::assertFalse($streamer->hasSent('s1', $position));

        $streamer->markSent('s1', $position);

        self::assertTrue($streamer->hasSent('s1', $position));
    }

    public function testResetForgetsSession(): void
    {
        $streamer = new ChunkStreamer(1);
        $position = new ChunkPosition(0, 0);

        $streamer->markSent('s1', $position);
        $streamer->reset('s1');

        self::assertFalse($streamer->hasSent('s1', $position));
        self::assertSame(0, $streamer->sentCount('s1'));
    }

    // --- Defter temizliği (Phase 9) -------------------------------------

    public function testEvictOutsideDropsDistantLedgerEntries(): void
    {
        $streamer = new ChunkStreamer(1);
        $streamer->sync($this->world, 's1', 8.5, 8.5);

        self::assertSame(9, $streamer->sentCount('s1'));

        // 5 chunk sağa: eski pencere tamamen payın dışında kalır.
        $removed = $streamer->evictOutside('s1', 8.5 + 5 * 16, 8.5);

        self::assertSame(9, $removed);
        self::assertSame(0, $streamer->sentCount('s1'));
    }

    public function testEvictOutsideKeepsChunksInsideMargin(): void
    {
        $streamer = new ChunkStreamer(1);
        $streamer->sync($this->world, 's1', 8.5, 8.5);

        // 3 chunk sağa: yarıçap 1 + pay 2 = 3. Merkez 3; x=-1 dışarıda.
        $removed = $streamer->evictOutside('s1', 8.5 + 3 * 16, 8.5);

        self::assertSame(3, $removed);
        self::assertSame(6, $streamer->sentCount('s1'));
    }

    public function testEvictedChunksAreResentWhenPlayerReturns(): void
    {
        $streamer = new ChunkStreamer(1);
        $streamer->sync($this->world, 's1', 8.5, 8.5);
        $streamer->evictOutside('s1', 8.5 + 5 * 16, 8.5);

        $again = $streamer->sync($this->world, 's1', 8.5, 8.5);

        self::assertCount(9, $again, 'Defterden dusen chunk geri donunce yeniden gonderilmeli.');
    }

    public function testEvictOutsideUnknownSessionIsANoop(): void
    {
        self::assertSame(0, (new ChunkStreamer(1))->evictOutside('missing', 0.0, 0.0));
    }

    public function testSyncIncludesEmptyChunks(): void
    {
        // Boş chunk'lar da gönderilmezse istemci orayı "yüklenmedi" sayar
        // ve oyuncu düşer. FlatGenerator boş olmayan zemini üretir; burada
        // sayının pencere boyutuna eşit olması yeterli.
        $streamer = new ChunkStreamer(1);

        $packets = $streamer->sync($this->world, 's1', 0.5, 0.5, 1);

        self::assertCount(9, $packets);

        foreach ($packets as $packet) {
            self::assertInstanceOf(LevelChunkPacket::class, $packet);
        }
    }

    public function testPacketCarriesRequestedChunkCoordinates(): void
    {
        $streamer = new ChunkStreamer(1, new ChunkSerializer());

        // (8.5, 8.5) -> chunk (0, 0); pencere -1..1
        $packets = $streamer->sync($this->world, 's1', 8.5, 8.5, 1);

        $coords = [];

        foreach ($packets as $packet) {
            $coords[$packet->chunkX . ',' . $packet->chunkZ] = true;
        }

        self::assertArrayHasKey('-1,-1', $coords);
        self::assertArrayHasKey('1,1', $coords);
        self::assertCount(9, $coords);
    }

    public function testPacketForSerializesSingleChunk(): void
    {
        $streamer = new ChunkStreamer(1);

        $packet = $streamer->packetFor($this->world, new ChunkPosition(0, 0));

        self::assertSame(0, $packet->chunkX);
        self::assertSame(0, $packet->chunkZ);
        self::assertGreaterThan(0, $packet->subChunkCount);        self::assertNotSame('', $packet->serializedChunkData);
    }

    public function testSyncLeavesNoChunkBehindOnWideRadius(): void
    {
        $streamer = new ChunkStreamer(3);

        $packets = $streamer->sync($this->world, 's1', 8.5, 8.5);

        self::assertCount(49, $packets);
        self::assertSame(49, $streamer->sentCount('s1'));
    }
}
