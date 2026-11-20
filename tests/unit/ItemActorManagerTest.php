<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit;

use PHPUnit\Framework\TestCase;
use PickaxeMP\auth\IdentityType;
use PickaxeMP\auth\PlayerIdentity;
use PickaxeMP\entity\EntityManager;
use PickaxeMP\entity\ItemActorManager;
use PickaxeMP\item\ItemRegistry;
use PickaxeMP\item\ItemStack;
use PickaxeMP\player\Player;
use PickaxeMP\player\PlayerInventory;
use PickaxeMP\protocol\packet\packets\MoveActorDeltaPacket;
use PickaxeMP\utils\Logger;
use PickaxeMP\world\World;
use PickaxeMP\world\WorldManager;
use PickaxeMP\world\block\BlockRegistry;
use PickaxeMP\world\chunk\ChunkPosition;
use PickaxeMP\world\generator\FlatGenerator;
use PickaxeMP\world\storage\FileWorldStorage;

/**
 * Yerdeki eşya varlıkları: üretim, toplama, envanter sözleşmesi.
 *
 * Kritik kurallar:
 *  - İstemcinin bildirdiği miktar **doğrulanır** (eşya çoğaltma savunması).
 *  - Envanterde yer yoksa eşya yerde kalır (kısmi toplama yok).
 *  - Menzil dışındaki istek yok sayılır.
 *  - `RemoveActor` **ActorUniqueID** taşır, runtime kimlik değil.
 */
final class ItemActorManagerTest extends TestCase
{
    private string $root;
    private Logger $logger;
    private World $world;
    private ItemRegistry $registry;
    private ItemActorManager $manager;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'pickaxe-item-actor-' . bin2hex(random_bytes(4));
        mkdir($this->root, 0755, true);

        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);
        $this->logger = new Logger('item-actor-test', $stream, Logger::DEBUG);

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

        $this->registry = new ItemRegistry();
        $this->registry->register('minecraft:stone', 1);
        $this->registry->register('minecraft:dirt', 2);

        $this->manager = new ItemActorManager(
            new EntityManager(1, 1000, 1000),
            $this->registry,
            $this->logger
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testSpawnRegistersActorAndReturnsAuthoritativeIds(): void
    {
        $packet = $this->manager->spawn($this->world, new ItemStack('minecraft:stone', 4), 1.0, 2.0, 3.0, 'session-a');

        self::assertNotNull($packet);
        self::assertSame(1000, $packet->targetActorId);
        self::assertSame(1000, $packet->targetRuntimeId);
        self::assertSame(1, $packet->item->id);
        self::assertSame(4, $packet->item->count);
        self::assertSame(1, $this->manager->count());

        $actor = $this->manager->get(1000);
        self::assertNotNull($actor);
        self::assertSame('session-a', $actor->ownerSessionId);
        self::assertSame('minecraft:stone', $actor->stack->type());
    }

    public function testSpawnStoresACopyOfTheStack(): void
    {
        $stack = new ItemStack('minecraft:stone', 4);
        $this->manager->spawn($this->world, $stack, 0.0, 0.0, 0.0);

        $stack->remove(4);

        self::assertSame(4, $this->manager->get(1000)?->stack->count());
    }

    public function testSpawnRejectsUnknownItem(): void
    {
        self::assertNull($this->manager->spawn($this->world, new ItemStack('minecraft:diamond', 1), 0.0, 0.0, 0.0));
        self::assertSame(0, $this->manager->count());
    }

    public function testSpawnRejectsEmptyStack(): void
    {
        self::assertNull($this->manager->spawn($this->world, ItemStack::empty(), 0.0, 0.0, 0.0));
    }

    public function testConsumeRejectsWhenInventoryLacksTheItems(): void
    {
        $inventory = $this->inventory();
        $inventory->setSlot(0, new ItemStack('minecraft:stone', 2));

        self::assertNull($this->manager->consume($inventory, new ItemStack('minecraft:stone', 3)));
        self::assertSame(2, $inventory->getSlot(0)?->count(), 'Basarisiz istekte hicbir yuva degismemeli.');
    }

    public function testConsumeSpansMultipleSlots(): void
    {
        $inventory = $this->inventory();
        $inventory->setSlot(0, new ItemStack('minecraft:stone', 2));
        $inventory->setSlot(5, new ItemStack('minecraft:stone', 3));

        $updates = $this->manager->consume($inventory, new ItemStack('minecraft:stone', 4));

        self::assertNotNull($updates);
        self::assertNull($inventory->getSlot(0));
        self::assertSame(1, $inventory->getSlot(5)?->count());
        self::assertSame([0, 5], array_keys($updates));
    }

    public function testInsertMergesThenFillsEmptySlots(): void
    {
        $inventory = $this->inventory();
        $inventory->setSlot(3, new ItemStack('minecraft:stone', 62));

        $updates = $this->manager->insert($inventory, new ItemStack('minecraft:stone', 5));

        self::assertNotNull($updates);
        self::assertSame(64, $inventory->getSlot(3)?->count());
        self::assertSame(3, $inventory->getSlot(0)?->count());
    }

    public function testInsertRejectsWhenInventoryIsFull(): void
    {
        $inventory = $this->inventory();

        for ($slot = 0; $slot < PlayerInventory::SIZE; $slot++) {
            $inventory->setSlot($slot, new ItemStack('minecraft:dirt', 64));
        }

        self::assertNull($this->manager->insert($inventory, new ItemStack('minecraft:stone', 1)));
    }

    public function testPickupMovesStackIntoInventoryAndRemovesActor(): void
    {
        $player = $this->player(9, 0.0, 0.0, 0.0);
        $player->markSpawned();

        $spawn = $this->manager->spawn($this->world, new ItemStack('minecraft:stone', 3), 0.5, 0.0, 0.5);
        self::assertNotNull($spawn);

        $result = $this->manager->pickup($player, $spawn->targetRuntimeId, $this->world);

        self::assertNotNull($result);
        self::assertSame(3, $player->inventory()->getSlot(0)?->count());
        self::assertSame(0, $this->manager->count(), 'Toplanan varlik kayittan dusmeli.');

        // `RemoveActor` alani ActorUniqueID tasir.
        self::assertSame($spawn->targetActorId, $result->removeActor->targetActorId);
        self::assertSame([0], array_keys($result->slotUpdates));
    }

    public function testPickupRejectsOutOfRangeRequest(): void
    {
        $player = $this->player(9, 10.0, 0.0, 0.0);
        $player->markSpawned();

        $spawn = $this->manager->spawn($this->world, new ItemStack('minecraft:stone', 3), 0.0, 0.0, 0.0);
        self::assertNotNull($spawn);

        self::assertNull($this->manager->pickup($player, $spawn->targetRuntimeId, $this->world));
        self::assertSame(1, $this->manager->count(), 'Esya yerde kalmali.');
        self::assertNull($player->inventory()->getSlot(0));
    }

    public function testPickupLeavesItemWhenInventoryIsFull(): void
    {
        $player = $this->player(9, 0.0, 0.0, 0.0);
        $player->markSpawned();

        for ($slot = 0; $slot < PlayerInventory::SIZE; $slot++) {
            $player->inventory()->setSlot($slot, new ItemStack('minecraft:dirt', 64));
        }

        $spawn = $this->manager->spawn($this->world, new ItemStack('minecraft:stone', 3), 0.0, 0.0, 0.0);
        self::assertNotNull($spawn);

        self::assertNull($this->manager->pickup($player, $spawn->targetRuntimeId, $this->world));
        self::assertSame(1, $this->manager->count(), 'Yer yoksa esya yerde kalmali.');
    }

    public function testPickupRejectsUnknownActor(): void
    {
        $player = $this->player(9, 0.0, 0.0, 0.0);
        $player->markSpawned();

        self::assertNull($this->manager->pickup($player, 424242, $this->world));
    }

    public function testDespawnRemovesActorWithoutPacket(): void
    {
        $spawn = $this->manager->spawn($this->world, new ItemStack('minecraft:stone', 1), 0.0, 0.0, 0.0);
        self::assertNotNull($spawn);

        self::assertTrue($this->manager->despawn($spawn->targetRuntimeId));
        self::assertSame(0, $this->manager->count());
        self::assertFalse($this->manager->despawn($spawn->targetRuntimeId));
    }

    public function testTickKeepsFreshItems(): void
    {
        $spawn = $this->manager->spawn($this->world, new ItemStack('minecraft:stone', 1), 0.0, 0.0, 0.0);
        self::assertNotNull($spawn);

        self::assertSame([], $this->manager->tick(time()));
        self::assertSame(1, $this->manager->count());
    }

    public function testTickRemovesExpiredItemsAndReturnsRemoveActor(): void
    {
        $spawn = $this->manager->spawn($this->world, new ItemStack('minecraft:stone', 1), 0.0, 0.0, 0.0);
        self::assertNotNull($spawn);

        $expiredAt = time() + ItemActorManager::LIFETIME_SECONDS + 1;
        $packets = $this->manager->tick($expiredAt);

        self::assertCount(1, $packets);
        self::assertSame($spawn->targetActorId, $packets[0]->targetActorId);
        self::assertSame(0, $this->manager->count());
    }

    public function testTickPurgesAlreadyDespawnedActorsSilently(): void
    {
        $spawn = $this->manager->spawn($this->world, new ItemStack('minecraft:stone', 1), 0.0, 0.0, 0.0);
        self::assertNotNull($spawn);
        $actor = $this->manager->get($spawn->targetRuntimeId);
        self::assertNotNull($actor);

        $actor->entity->despawn();

        $packets = $this->manager->tick(time());

        self::assertSame([], $packets, 'Elle despawn edilen varlik icin ikinci paket uretilmez');
        self::assertSame(0, $this->manager->count());
    }

    // --- Yerçekimi (Phase 9) --------------------------------------------

    public function testMotionFallsToTheGroundAndEmitsMoveActorDelta(): void
    {
        // Düz dünyanın üst yüzeyi -58'deki grass bloğunun tepesi: -57.
        $this->world->getChunk(new ChunkPosition(0, 0));

        $spawn = $this->manager->spawn(
            $this->world,
            new ItemStack('minecraft:stone', 1),
            0.5,
            -55.0,
            0.5
        );
        self::assertNotNull($spawn);

        $moves = [];

        for ($tick = 0; $tick < 40; $tick++) {
            $moves = array_merge($moves, $this->manager->tickMotion());
        }

        self::assertNotEmpty($moves);
        self::assertInstanceOf(MoveActorDeltaPacket::class, $moves[0]);
        self::assertSame($spawn->targetRuntimeId, $moves[0]->data->actorRuntimeId);

        $actor = $this->manager->get($spawn->targetRuntimeId);
        self::assertNotNull($actor);
        self::assertEqualsWithDelta(-57.0, $actor->position->y, 1e-6);
        self::assertTrue($actor->onGround, 'Esya zeminde dinlenmeli.');

        // Sunucu konumu Entity ile de senkron olmali; ikinci kaynak yok.
        self::assertEqualsWithDelta(-57.0, $actor->entity->y(), 1e-6);
    }

    public function testMotionStopsProducingPacketsWhenItemRests(): void
    {
        $this->world->getChunk(new ChunkPosition(0, 0));

        $spawn = $this->manager->spawn(
            $this->world,
            new ItemStack('minecraft:stone', 1),
            0.5,
            -55.0,
            0.5
        );
        self::assertNotNull($spawn);

        for ($tick = 0; $tick < 40; $tick++) {
            $this->manager->tickMotion();
        }

        // Dinlenen esya icin her tick yeniden paket uretmemeli: bant
        // genisligi ve istemcide titreme sorunu olurdu.
        self::assertSame([], $this->manager->tickMotion());
    }

    public function testMotionPushesItemOutOfASolidBlock(): void
    {
        $this->world->getChunk(new ChunkPosition(0, 0));

        // -58 grass blogunun *icinde* dogan esya yukari itilmeli.
        $spawn = $this->manager->spawn(
            $this->world,
            new ItemStack('minecraft:stone', 1),
            0.5,
            -58.0,
            0.5
        );
        self::assertNotNull($spawn);

        $moves = $this->manager->tickMotion();

        self::assertCount(1, $moves);

        $actor = $this->manager->get($spawn->targetRuntimeId);
        self::assertNotNull($actor);
        self::assertEqualsWithDelta(-57.0, $actor->position->y, 1e-6);
        self::assertTrue($actor->onGround);
    }

    private function inventory(): PlayerInventory
    {
        return new PlayerInventory(
            $this->root,
            '11111111-2222-3333-4444-555555555555',
            $this->logger
        );
    }

    private function player(int $entityId, float $x, float $y, float $z): Player
    {
        return new Player(
            identity: new PlayerIdentity(
                xuid: 'xuid-' . $entityId,
                uuid: '11111111-2222-3333-4444-555555555555',
                displayName: 'Tester',
                type: IdentityType::Offline,
                verifiedAt: time()
            ),
            sessionId: 'session-' . $entityId,
            connectionId: 'connection-' . $entityId,
            entityId: $entityId,
            runtimeId: $entityId,
            world: $this->world,
            x: $x,
            y: $y,
            z: $z,
            inventory: $this->inventory()
        );
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

                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
