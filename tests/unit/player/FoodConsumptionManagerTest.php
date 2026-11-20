<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\player;

use PHPUnit\Framework\TestCase;
use PickaxeMP\auth\IdentityType;
use PickaxeMP\auth\PlayerIdentity;
use PickaxeMP\item\ItemRegistry;
use PickaxeMP\item\ItemStack;
use PickaxeMP\player\FoodConsumptionManager;
use PickaxeMP\player\FoodTable;
use PickaxeMP\player\Player;
use PickaxeMP\player\PlayerInventory;
use PickaxeMP\protocol\packet\types\BlockPos;
use PickaxeMP\protocol\packet\types\HandSlot;
use PickaxeMP\protocol\packet\types\ItemUseActionType;
use PickaxeMP\protocol\packet\types\ItemUseInventoryTransaction;
use PickaxeMP\protocol\packet\types\NetworkItemStackDescriptor;
use PickaxeMP\protocol\packet\types\Vec3;
use PickaxeMP\utils\Logger;
use PickaxeMP\world\World;
use PickaxeMP\world\WorldManager;
use PickaxeMP\world\block\BlockRegistry;
use PickaxeMP\world\generator\FlatGenerator;
use PickaxeMP\world\storage\FileWorldStorage;

/**
 * Yemek tüketimi sözleşmesi.
 *
 * Kritik kurallar:
 *  - Yalnızca seçili hotbar yuvasındaki, ana eldeki yemek yenir.
 *  - Eşya **sunucu envanterinden** okunur; istemcinin tanımlayıcısına
 *    güvenilmez.
 *  - Ağ kimliği kayıtlı olmayan eşya tüketilmez (istemci ayrışması olur).
 *  - Tok oyuncu yiyemez; ölü/`Joining` oyuncu yiyemez.
 *  - Tüketimler arasında `EAT_COOLDOWN_MS` vardır; oturum başına izlenir.
 */
final class FoodConsumptionManagerTest extends TestCase
{
    private string $root;
    private Logger $logger;
    private World $world;
    private ItemRegistry $items;
    private int $now = 0;
    private FoodConsumptionManager $manager;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'pickaxe-food-' . bin2hex(random_bytes(4));
        mkdir($this->root, 0755, true);

        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);
        $this->logger = new Logger('food-test', $stream, Logger::DEBUG);

        $blocks = BlockRegistry::withPhase4Blocks();
        $airNetworkId = $blocks->state('minecraft:air')->getNetworkId();
        $worlds = new WorldManager(
            $this->logger,
            $this->root,
            $airNetworkId,
            new FileWorldStorage($this->root, $airNetworkId),
            new FlatGenerator($blocks)
        );
        $this->world = $worlds->loadOrCreate('world', 1, 'Test World');

        $this->items = new ItemRegistry();
        $this->items->register('minecraft:apple', 1);
        $this->items->register('minecraft:bread', 2);
        $this->items->register('minecraft:stone', 3);

        $this->manager = new FoodConsumptionManager(
            $this->logger,
            FoodTable::withDefaultFoods(),
            $this->items,
            fn (): int => $this->now
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testConsumesHeldFoodAndAppliesNutrition(): void
    {
        $player = $this->player();
        $player->attributes()->consume(40.0);
        $player->inventory()->setSlot(0, new ItemStack('minecraft:bread', 2));

        $result = $this->manager->consume($player, $this->use());

        self::assertNotNull($result);
        self::assertSame(0, $result->slot);
        self::assertSame(1, $result->remaining?->count());
        self::assertSame(5, $result->nutrition->hunger);
        // 40 tukenme = 10 puan: 5 doygunluk + 5 aclik -> aclik 15; ekmek +5 = 20.
        self::assertSame(20, $player->attributes()->hunger());
        self::assertEqualsWithDelta(6.0, $player->attributes()->saturation(), 1e-9);
        self::assertSame(1, $player->inventory()->getSlot(0)?->count());
    }

    public function testConsumingLastItemEmptiesSlot(): void
    {
        $player = $this->player();
        $player->attributes()->consume(40.0);
        $player->inventory()->setSlot(0, new ItemStack('minecraft:apple', 1));

        $result = $this->manager->consume($player, $this->use());

        self::assertNotNull($result);
        self::assertNull($result->remaining);
        self::assertNull($player->inventory()->getSlot(0));
        // 40 tukenme = 10 puan: 5 doygunluk + 5 aclik -> aclik 15; elma +4 = 19.
        self::assertSame(19, $player->attributes()->hunger());
    }

    public function testRejectsFoodWhenHungerIsFull(): void
    {
        $player = $this->player();
        $player->inventory()->setSlot(0, new ItemStack('minecraft:bread', 2));

        self::assertNull($this->manager->consume($player, $this->use()));
        self::assertSame(2, $player->inventory()->getSlot(0)?->count());
        self::assertSame(20, $player->attributes()->hunger());
    }

    public function testRejectsNonFoodItem(): void
    {
        $player = $this->player();
        $player->attributes()->consume(24.0);
        $player->inventory()->setSlot(0, new ItemStack('minecraft:stone', 2));

        self::assertNull($this->manager->consume($player, $this->use()));
        self::assertSame(2, $player->inventory()->getSlot(0)?->count());
    }

    public function testRejectsFoodWithoutNetworkId(): void
    {
        $manager = new FoodConsumptionManager(
            $this->logger,
            FoodTable::withDefaultFoods(),
            new ItemRegistry(),
            fn (): int => $this->now
        );

        $player = $this->player();
        $player->attributes()->consume(24.0);
        $player->inventory()->setSlot(0, new ItemStack('minecraft:apple', 2));

        self::assertNull($manager->consume($player, $this->use()));
        self::assertSame(2, $player->inventory()->getSlot(0)?->count());
    }

    public function testRejectsFoodOutsideSelectedHotbarSlot(): void
    {
        $player = $this->player();
        $player->attributes()->consume(24.0);
        $player->inventory()->setSlot(1, new ItemStack('minecraft:apple', 2));

        self::assertNull($this->manager->consume($player, $this->use(slot: 1)));
        self::assertSame(2, $player->inventory()->getSlot(1)?->count());
    }

    public function testRejectsOffHand(): void
    {
        $player = $this->player();
        $player->attributes()->consume(24.0);
        $player->inventory()->setSlot(0, new ItemStack('minecraft:apple', 2));

        self::assertNull($this->manager->consume($player, $this->use(hand: HandSlot::OffHand)));
        self::assertSame(2, $player->inventory()->getSlot(0)?->count());
    }

    public function testRejectsDeadPlayer(): void
    {
        $player = $this->player();
        $player->inventory()->setSlot(0, new ItemStack('minecraft:apple', 2));
        $player->damage(Player::MAX_HEALTH);

        self::assertTrue($player->isDead());
        self::assertNull($this->manager->consume($player, $this->use()));
        self::assertSame(2, $player->inventory()->getSlot(0)?->count());
    }

    public function testRejectsJoiningPlayer(): void
    {
        $player = $this->player(spawned: false);
        $player->inventory()->setSlot(0, new ItemStack('minecraft:apple', 2));

        self::assertNull($this->manager->consume($player, $this->use()));
        self::assertSame(2, $player->inventory()->getSlot(0)?->count());
    }

    public function testRejectsEmptySlot(): void
    {
        $player = $this->player();
        $player->attributes()->consume(24.0);

        self::assertNull($this->manager->consume($player, $this->use()));
    }

    public function testCooldownBlocksRapidSecondConsumption(): void
    {
        $player = $this->player();
        $player->attributes()->consume(60.0);
        $player->inventory()->setSlot(0, new ItemStack('minecraft:bread', 3));

        self::assertNotNull($this->manager->consume($player, $this->use()));
        self::assertSame(2, $player->inventory()->getSlot(0)?->count());

        $this->now = 1000;

        self::assertNull($this->manager->consume($player, $this->use()));
        self::assertSame(2, $player->inventory()->getSlot(0)?->count());

        $this->now = FoodConsumptionManager::EAT_COOLDOWN_MS;

        self::assertNotNull($this->manager->consume($player, $this->use()));
        self::assertSame(1, $player->inventory()->getSlot(0)?->count());
    }

    public function testForgetClearsCooldown(): void
    {
        $player = $this->player();
        $player->attributes()->consume(60.0);
        $player->inventory()->setSlot(0, new ItemStack('minecraft:bread', 2));

        self::assertNotNull($this->manager->consume($player, $this->use()));

        $this->manager->forget($player->sessionId);

        self::assertNotNull($this->manager->consume($player, $this->use()));
        self::assertNull($player->inventory()->getSlot(0));
    }

    public function testCooldownIsPerPlayer(): void
    {
        $first = $this->player(session: 'session-a', uuid: '11111111-2222-3333-4444-555555555555');
        $second = $this->player(session: 'session-b', uuid: '66666666-7777-8888-9999-aaaaaaaaaaaa');

        foreach ([$first, $second] as $player) {
            $player->attributes()->consume(24.0);
            $player->inventory()->setSlot(0, new ItemStack('minecraft:apple', 2));
        }

        self::assertNotNull($this->manager->consume($first, $this->use()));
        self::assertNotNull($this->manager->consume($second, $this->use()));
    }

    private function use(
        int $slot = 0,
        HandSlot $hand = HandSlot::MainHand
    ): ItemUseInventoryTransaction {
        return new ItemUseInventoryTransaction(
            actions: [],
            actionType: ItemUseActionType::Use,
            triggerType: 0,
            position: new BlockPos(0, -58, 0),
            face: 1,
            slot: $slot,
            hand: $hand,
            item: NetworkItemStackDescriptor::empty(),
            fromPosition: new Vec3(0.5, -57.0, 0.5),
            clickPosition: new Vec3(0.5, 1.0, 0.5),
            targetBlockId: 0,
            clientInteractPrediction: 0,
            clientCooldownState: 0
        );
    }

    private function player(
        string $session = 'session-a',
        string $uuid = '11111111-2222-3333-4444-555555555555',
        bool $spawned = true
    ): Player {
        $player = new Player(
            identity: new PlayerIdentity(
                xuid: 'xuid-' . $session,
                uuid: $uuid,
                displayName: 'Tester',
                type: IdentityType::Offline,
                verifiedAt: time()
            ),
            sessionId: $session,
            connectionId: 'connection-' . $session,
            entityId: 1,
            runtimeId: 1,
            world: $this->world,
            x: 0.5,
            y: -57.0,
            z: 0.5,
            inventory: new PlayerInventory($this->root, $uuid, $this->logger)
        );

        if ($spawned) {
            $player->markSpawned();
        }

        return $player;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);

        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
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
