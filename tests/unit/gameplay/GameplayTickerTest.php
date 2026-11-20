<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\gameplay;

use PHPUnit\Framework\TestCase;
use PickaxeMP\auth\IdentityType;
use PickaxeMP\auth\PlayerIdentity;
use PickaxeMP\entity\EntityManager;
use PickaxeMP\entity\ItemActorManager;
use PickaxeMP\gameplay\GameplayTicker;
use PickaxeMP\item\ItemRegistry;
use PickaxeMP\item\ItemStack;
use PickaxeMP\player\Player;
use PickaxeMP\player\PlayerAttributes;
use PickaxeMP\player\PlayerInventory;
use PickaxeMP\player\PlayerState;
use PickaxeMP\utils\Logger;
use PickaxeMP\world\block\BlockRegistry;
use PickaxeMP\world\chunk\ChunkPosition;
use PickaxeMP\world\generator\FlatGenerator;
use PickaxeMP\world\storage\FileWorldStorage;
use PickaxeMP\world\World;
use PickaxeMP\world\WorldProperties;

/**
 * Oyun döngüsü sözleşmesi: dünya saati, periyodik `SetTime`, hareket
 * tabanlı açlık ve oyuncu temizliği.
 */
final class GameplayTickerTest extends TestCase
{
    private string $root;

    private World $world;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pickaxe_ticker_' . bin2hex(random_bytes(6));
        mkdir($this->root, 0755, true);

        $registry = BlockRegistry::withPhase4Blocks();

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
            new FileWorldStorage($this->root, $registry->state('minecraft:air')->getNetworkId()),
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

    private function player(string $name = 'Steve'): Player
    {
        $player = new Player(
            identity: new PlayerIdentity(
                xuid: 'xuid-' . $name,
                uuid: '00000000-0000-0000-0000-00000000000' . strlen($name),
                displayName: $name,
                type: IdentityType::Offline,
                verifiedAt: time()
            ),
            sessionId: 'session-' . $name,
            connectionId: 'connection-' . $name,
            entityId: 1,
            runtimeId: 1,
            world: $this->world,
            x: 8.5,
            y: -57.0,
            z: 8.5,
            inventory: new PlayerInventory('', '00000000-0000-0000-0000-000000000001')
        );

        $player->markSpawned();

        return $player;
    }

    public function testTimeAdvancesOneTickPerCall(): void
    {
        $ticker = new GameplayTicker($this->world);
        $start = $this->world->getTime();

        $result = $ticker->tick(1, []);

        self::assertSame($start + 1, $result->time);
        self::assertSame($start + 1, $this->world->getTime());
    }

    public function testTimeWrapsAtDayLength(): void
    {
        $ticker = new GameplayTicker($this->world);

        // 23999 -> 0 sarma noktası.
        $this->world->advanceTime(WorldProperties::DAY_LENGTH - 1 - $this->world->getTime());

        self::assertSame(WorldProperties::DAY_LENGTH - 1, $this->world->getTime());

        $result = $ticker->tick(1, []);

        self::assertSame(0, $result->time);
    }

    public function testTimeIsBroadcastOnlyOnSyncInterval(): void
    {
        $ticker = new GameplayTicker($this->world, null, 10);

        self::assertNull($ticker->tick(1, [])->timeToBroadcast);
        self::assertNull($ticker->tick(9, [])->timeToBroadcast);

        $result = $ticker->tick(10, []);

        self::assertNotNull($result->timeToBroadcast);
        self::assertSame($this->world->getTime(), $result->timeToBroadcast);
    }

    public function testZeroTickNumberNeverBroadcastsTime(): void
    {
        $ticker = new GameplayTicker($this->world, null, 1);

        self::assertNull($ticker->tick(0, [])->timeToBroadcast);
    }

    public function testMovingPlayerConsumesHungerAndProducesAttributeUpdate(): void
    {
        $ticker = new GameplayTicker($this->world);
        $player = $this->player();

        // İlk tick konumu kaydeder (hareket sayılmaz).
        $ticker->tick(1, [$player]);

        $updates = [];

        // Tükenme 4.0'a ulaşana kadar yürü: her tick 0.01 → ~400 tick.
        for ($tick = 2; $tick <= 500; $tick++) {
            $player->setPosition(8.5 + ($tick * 0.5), -57.0, 8.5);
            $result = $ticker->tick($tick, [$player]);

            if (isset($result->attributeUpdates[$player->sessionId])) {
                $updates[] = $result->attributeUpdates[$player->sessionId];
            }
        }

        self::assertNotSame([], $updates, 'Yuruyen oyuncunun aciligi bir noktada guncellenmeli');

        $saturation = null;

        foreach ($updates[0] as $attribute) {
            if ($attribute->name === \PickaxeMP\protocol\packet\types\Attribute::SATURATION) {
                $saturation = $attribute->current;
            }
        }

        // İlk harcanan puan doygunluktan düşer (5.0 → 4.0), açlık 20 kalır.
        self::assertSame(4.0, $saturation);
    }

    public function testStationaryPlayerDoesNotProduceAttributeUpdate(): void
    {
        $ticker = new GameplayTicker($this->world);
        $player = $this->player();

        $ticker->tick(1, [$player]);
        $result = $ticker->tick(2, [$player]);

        self::assertArrayNotHasKey($player->sessionId, $result->attributeUpdates);
    }

    public function testDisconnectedPlayerIsIgnoredAndForgotten(): void
    {
        $ticker = new GameplayTicker($this->world);
        $player = $this->player();
        $player->markDisconnected();

        $result = $ticker->tick(1, [$player]);

        self::assertArrayNotHasKey($player->sessionId, $result->attributeUpdates);
        self::assertSame([], $result->attributeUpdates);
    }

    public function testForgetDropsSessionState(): void
    {
        $ticker = new GameplayTicker($this->world);
        $player = $this->player();

        $ticker->tick(1, [$player]);
        $ticker->forget($player->sessionId);

        // Konum kaydı silindiği için ilk tick tekrar "ilk" sayılır.
        $player->setPosition(20.0, -57.0, 20.0);
        $result = $ticker->tick(2, [$player]);

        self::assertArrayNotHasKey($player->sessionId, $result->attributeUpdates);
    }

    public function testNegativeTickNumberIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new GameplayTicker($this->world))->tick(-1, []);
    }

    public function testInvalidSyncIntervalIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new GameplayTicker($this->world, null, 0);
    }

    public function testHasWorkReflectsResultContents(): void
    {
        $ticker = new GameplayTicker($this->world, null, 10);

        self::assertFalse($ticker->tick(1, [])->hasWork());
        self::assertTrue($ticker->tick(10, [])->hasWork());
    }

    public function testDeadPlayerDoesNotAccumulateHunger(): void
    {
        $ticker = new GameplayTicker($this->world);
        $player = $this->player();

        $ticker->tick(1, [$player]);
        $player->damage(Player::MAX_HEALTH);

        $player->setPosition(9.5, -57.0, 8.5);
        $result = $ticker->tick(2, [$player]);

        self::assertArrayNotHasKey($player->sessionId, $result->attributeUpdates);
        self::assertSame(PlayerState::Dead, $player->state());
    }

    /**
     * Ölü oyuncu hareket etse bile açlık zinciri işlemez.
     *
     * Phase 9 testi yalnızca tek tick'i kapsıyordu; 400 tick'lik hareket
     * tükenmeyi 4.0'a çıkarıp doygunluğu harcıyordu.
     */
    public function testDeadPlayerDoesNotConsumeHungerWhileMoving(): void
    {
        $ticker = new GameplayTicker($this->world);
        $player = $this->player();

        $ticker->tick(1, [$player]);
        $player->damage(Player::MAX_HEALTH);

        self::assertSame(PlayerState::Dead, $player->state());
        self::assertSame(
            PlayerAttributes::DEFAULT_SATURATION,
            $player->attributes()->saturation()
        );

        for ($tick = 2; $tick <= 401; $tick++) {
            $player->setPosition(8.5 + ($tick - 1) * 0.5, -57.0, 8.5);
            $ticker->tick($tick, [$player]);
        }

        self::assertSame(0.0, $player->attributes()->exhaustion());
        self::assertSame(
            PlayerAttributes::DEFAULT_SATURATION,
            $player->attributes()->saturation()
        );
        self::assertSame(
            PlayerAttributes::DEFAULT_HUNGER,
            $player->attributes()->hunger()
        );
    }

    public function testStarvationIsReportedAsHealthUpdate(): void
    {
        $ticker = new GameplayTicker($this->world);
        $player = $this->player();
        $player->attributes()->consume(100.0);

        self::assertSame(0, $player->attributes()->hunger());

        $result = null;

        for ($tick = 1; $tick <= 81; $tick++) {
            $result = $ticker->tick($tick, [$player]);
        }

        self::assertNotNull($result);
        self::assertSame(Player::MAX_HEALTH - 1, $player->health());
        self::assertSame([$player->sessionId => Player::MAX_HEALTH - 1], $result->healthUpdates);
        self::assertTrue($result->hasWork());
    }

    public function testRegenerationIsReportedAsHealthUpdate(): void
    {
        $ticker = new GameplayTicker($this->world);
        $player = $this->player();
        $player->damage(10);

        $result = null;

        for ($tick = 1; $tick <= 81; $tick++) {
            $result = $ticker->tick($tick, [$player]);
        }

        self::assertNotNull($result);
        self::assertSame(11, $player->health());
        self::assertSame([$player->sessionId => 11], $result->healthUpdates);
    }

    public function testForgetResetsSurvivalTimers(): void
    {
        $ticker = new GameplayTicker($this->world);
        $player = $this->player();
        $player->attributes()->consume(100.0);

        // 79 uygun tick birikir, sonra oturum unutulur.
        for ($tick = 1; $tick <= 80; $tick++) {
            $ticker->tick($tick, [$player]);
        }

        $ticker->forget($player->sessionId);

        for ($tick = 81; $tick <= 160; $tick++) {
            $result = $ticker->tick($tick, [$player]);
        }

        self::assertSame(Player::MAX_HEALTH, $player->health());

        $result = $ticker->tick(161, [$player]);

        self::assertSame(Player::MAX_HEALTH - 1, $player->health());
        self::assertSame([$player->sessionId => Player::MAX_HEALTH - 1], $result->healthUpdates);
    }

    public function testStarvationDeathIsReportedOnce(): void
    {
        $ticker = new GameplayTicker($this->world);
        $player = $this->player();
        $player->attributes()->consume(100.0);
        $player->damage(Player::MAX_HEALTH - 1);

        self::assertSame(1, $player->health());

        $deaths = [];
        $result = null;

        for ($tick = 1; $tick <= 160; $tick++) {
            $result = $ticker->tick($tick, [$player]);

            if ($result->deaths !== []) {
                $deaths[] = $result->deaths;
            }
        }

        self::assertNotNull($result);
        self::assertSame(0, $player->health());
        self::assertCount(1, $deaths, 'Olum olayi yalnizca bir kez uretilmeli');
        self::assertSame([$player->sessionId => $player->entityId], $deaths[0]);
        self::assertFalse($result->hasWork());
    }

    public function testLivingPlayerProducesNoDeathEvent(): void
    {
        $ticker = new GameplayTicker($this->world);
        $player = $this->player();

        for ($tick = 1; $tick <= 5; $tick++) {
            self::assertSame([], $ticker->tick($tick, [$player])->deaths);
        }
    }

    public function testItemMotionIsIncludedInTickResult(): void
    {
        $this->world->getChunk(new ChunkPosition(0, 0));

        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);
        $logger = new Logger('ticker-items', $stream, Logger::DEBUG);

        $registry = new ItemRegistry();
        $registry->register('minecraft:stone', 1);

        $items = new ItemActorManager(
            new EntityManager(1, 1000, 1000),
            $registry,
            $logger
        );
        self::assertNotNull(
            $items->spawn($this->world, new ItemStack('minecraft:stone', 1), 8.5, -52.0, 8.5)
        );

        $result = (new GameplayTicker($this->world, $items))->tick(1, []);

        self::assertNotEmpty($result->movedItems);
        self::assertTrue($result->hasWork());
    }
}
