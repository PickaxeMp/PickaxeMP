<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\player;

use PHPUnit\Framework\TestCase;
use PickaxeMP\auth\PlayerIdentityFactory;
use PickaxeMP\item\ItemStack;
use PickaxeMP\player\BlockActionManager;
use PickaxeMP\player\GameMode;
use PickaxeMP\player\Player;
use PickaxeMP\player\PlayerInventory;
use PickaxeMP\protocol\packet\types\BlockPos;
use PickaxeMP\protocol\packet\types\HandSlot;
use PickaxeMP\protocol\packet\types\ItemUseActionType;
use PickaxeMP\protocol\packet\types\ItemUseInventoryTransaction;
use PickaxeMP\protocol\packet\types\NetworkItemStackDescriptor;
use PickaxeMP\protocol\packet\types\Vec3;
use PickaxeMP\utils\Logger;
use PickaxeMP\world\block\BlockRegistry;
use PickaxeMP\world\chunk\ChunkPosition;
use PickaxeMP\world\generator\FlatGenerator;
use PickaxeMP\world\storage\FileWorldStorage;
use PickaxeMP\world\World;
use PickaxeMP\world\WorldProperties;

/**
 * Blok kırma/yerleştirme **erişim mesafesi**.
 *
 * ## Neden bu testler var?
 *
 * `BlockActionManager` istemcinin gönderdiği koordinatlara güvenir. Mesafe
 * denetimi olmadan bir istemci dünyanın herhangi bir yerindeki bloğu kırabilir
 * veya herhangi bir konuma blok yerleştirebilir. Bu, sunucu yetkisinin
 * (authority) tamamen kaybı demektir.
 *
 * ## Ölçü
 *
 * Oyuncunun **göz** konumu (ayak + 1.62) ile bloğun **merkezi**
 * (`x+0.5, y+0.5, z+0.5`) arasındaki Öklid mesafesi kullanılır. Eksen başına
 * kontrol yanlıştır: `(5, 0, 5)` köşegeni eksen başına 5'tir ama gerçek
 * mesafe 7.07'dir.
 */
final class BlockActionManagerTest extends TestCase
{
    private const EYE_HEIGHT = 1.62;

    private string $root;

    private World $world;

    private BlockRegistry $registry;

    private BlockActionManager $manager;

    private Player $player;

    /** @var resource */
    private $logStream;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'pickaxe-reach-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0755, true);

        $this->registry = BlockRegistry::withPhase4Blocks();
        $this->world = new World(
            new WorldProperties(
                name: 'test',
                displayName: 'Test World',
                seed: 1,
                generator: 'flat',
                dimension: 'overworld',
                spawnX: 8.5,
                spawnY: -57.0,
                spawnZ: 8.5
            ),
            new FileWorldStorage($this->root, $this->registry->state('minecraft:air')->getNetworkId()),
            new FlatGenerator($this->registry)
        );

        // (8, -57, 8) civarındaki chunk'ları belleğe al. Menzil dışı testleri
        // komşu chunk'a düştüğü için onlar da yüklü olmalı; aksi hâlde test
        // "chunk yüklü değil" yüzünden değil, mesafe yüzünden reddedilsin.
        $this->world->getChunk(new ChunkPosition(0, 0));
        $this->world->getChunk(new ChunkPosition(1, 0));
        $this->world->getChunk(new ChunkPosition(0, 1));

        $this->logStream = fopen('php://memory', 'wb');

        $this->manager = new BlockActionManager(
            new Logger('reach-test', $this->logStream, Logger::DEBUG),
            $this->registry
        );

        $identity = (new PlayerIdentityFactory())->offline('Steve');

        $this->player = new Player(
            identity: $identity,
            sessionId: 's-1',
            connectionId: 'c-1',
            entityId: 1,
            runtimeId: 1,
            world: $this->world,
            x: 8.5,
            y: -57.0,
            z: 8.5,
            inventory: new PlayerInventory($this->root, $identity->uuid)
        );
        $this->player->setGameMode(GameMode::Survival);
        $this->player->markSpawned();

        $this->player->inventory()->setSlot(0, new ItemStack('minecraft:stone', 8));
    }

    protected function tearDown(): void
    {
        if (is_resource($this->logStream)) {
            fclose($this->logStream);
        }

        $this->removeDirectory($this->root);
    }

    public function testReachConstantIsSixBlocks(): void
    {
        self::assertSame(6.0, BlockActionManager::MAX_REACH);
    }

    public function testBreakWithinReachSucceeds(): void
    {
        // Oyuncunun ayağının hemen yanındaki blok.
        $x = 10;
        $y = -57;
        $z = 8;
        $this->placeBlock($x, $y, $z, 'minecraft:stone');

        $result = $this->manager->breakBlock($this->player, $x, $y, $z, $this->world);

        self::assertCount(1, $result->changes);
        self::assertSame($this->airNetworkId(), $this->world->getBlock($x, $y, $z));
    }

    public function testBreakBeyondReachIsRejected(): void
    {
        // 12 blok ileride: meshafesi sınırın çok üstünde.
        $x = 20;
        $y = -57;
        $z = 8;
        $this->placeBlock($x, $y, $z, 'minecraft:stone');
        $before = $this->world->getBlock($x, $y, $z);

        $result = $this->manager->breakBlock($this->player, $x, $y, $z, $this->world);

        self::assertTrue($result->isEmpty(), 'Menzil dışındaki blok kırılmamalı.');
        self::assertSame($before, $this->world->getBlock($x, $y, $z));
    }

    public function testDiagonalBeyondReachIsRejectedEvenIfEachAxisIsWithinLimit(): void
    {
        // Eksen başına ~4.5 blok, gerçek mesafe ~6.4: eksen başına kontrol
        // yapan hatalı bir uygulama bu testi geçemez.
        $x = 13;
        $y = -57;
        $z = 13;
        $this->placeBlock($x, $y, $z, 'minecraft:stone');

        $result = $this->manager->breakBlock($this->player, $x, $y, $z, $this->world);

        self::assertTrue($result->isEmpty());
    }

    public function testHandleActionsSkipsOutOfRangeBreak(): void
    {
        $x = 24;
        $y = -57;
        $z = 8;
        $this->placeBlock($x, $y, $z, 'minecraft:stone');

        $result = $this->manager->handleActions(
            $this->player,
            [new \PickaxeMP\protocol\packet\types\PlayerBlockAction(0, $x, $y, $z, 0)],
            $this->world
        );

        self::assertTrue($result->isEmpty());
        self::assertNotSame($this->airNetworkId(), $this->world->getBlock($x, $y, $z));
    }

    public function testPlaceWithinReachSucceeds(): void
    {
        $use = $this->useTransaction(9, -57, 8, face: 1);

        $result = $this->manager->place($this->player, $use, $this->world);

        self::assertCount(1, $result->changes);
        self::assertSame(
            $this->registry->defaultState('minecraft:stone')->getNetworkId(),
            $this->world->getBlock(9, -56, 8)
        );
        self::assertSame(7, $this->player->inventory()->getSlot(0)?->count());
    }

    public function testPlaceBeyondReachIsRejected(): void
    {
        $use = $this->useTransaction(20, -58, 8, face: 1);

        $result = $this->manager->place($this->player, $use, $this->world);

        self::assertTrue($result->isEmpty(), 'Menzil dışına yerleştirme yapılmamalı.');
        self::assertSame(8, $this->player->inventory()->getSlot(0)?->count());
        self::assertSame($this->airNetworkId(), $this->world->getBlock(20, -57, 8));
    }

    public function testCombatModeReachIsTheOnlyLimit(): void
    {
        // Yaratıcı mod daha uzun menzil vermemeli: tek bir sabit kullanılır.
        $this->player->setGameMode(GameMode::Creative);
        $x = 20;
        $y = -57;
        $z = 8;
        $this->placeBlock($x, $y, $z, 'minecraft:stone');

        self::assertTrue(
            $this->manager->breakBlock($this->player, $x, $y, $z, $this->world)->isEmpty()
        );
    }

    // --- yardımcılar ---

    private function useTransaction(int $x, int $y, int $z, int $face): ItemUseInventoryTransaction
    {
        return new ItemUseInventoryTransaction(
            actions: [],
            actionType: ItemUseActionType::Place,
            triggerType: 0,
            position: new BlockPos($x, $y, $z),
            face: $face,
            slot: 0,
            hand: HandSlot::MainHand,
            item: NetworkItemStackDescriptor::empty(),
            fromPosition: new Vec3(8.5, -57.0 + self::EYE_HEIGHT, 8.5),
            clickPosition: new Vec3(0.5, 1.0, 0.5),
            targetBlockId: 0,
            clientInteractPrediction: 0,
            clientCooldownState: 0
        );
    }

    private function placeBlock(int $x, int $y, int $z, string $name): void
    {
        $ok = $this->world->setBlock($x, $y, $z, $this->registry->defaultState($name)->getNetworkId());

        self::assertTrue($ok, "Test hazırlığı: ({$x},{$y},{$z}) bloğu yazılamadı");
    }

    private function airNetworkId(): int
    {
        return $this->registry->defaultState('minecraft:air')->getNetworkId();
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
