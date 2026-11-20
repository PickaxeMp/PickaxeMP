<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\player;

use PHPUnit\Framework\TestCase;
use PickaxeMP\auth\IdentityType;
use PickaxeMP\auth\PlayerIdentity;
use PickaxeMP\item\ItemStack;
use PickaxeMP\player\BlockActionManager;
use PickaxeMP\player\Player;
use PickaxeMP\player\PlayerInventory;
use PickaxeMP\player\PlayerState;
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
 * Phase 7 - oynanis dongusu: saglik, olum, yeniden dogma ve blok eylemleri.
 *
 * Testler `ConnectionPipeline`'a girmez: mutasyon mantigi tasiyicidan
 * bagimsizdir ve burada dogrudan sinanir.
 */
final class GameplayLoopTest extends TestCase
{
    private string $root;

    private Logger $logger;

    private BlockRegistry $registry;

    private World $world;

    private BlockActionManager $blocks;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pickaxe-gameplay-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0755, true);

        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);
        $this->logger = new Logger('gameplay-test', $stream, Logger::DEBUG);

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
            new FileWorldStorage($this->root, $this->airId()),
            new FlatGenerator($this->registry)
        );

        $this->blocks = new BlockActionManager($this->logger, $this->registry);

        // Chunk (0,0) ve (1,0) yuklenir: `World::setBlock` yuklu olmayan
        // chunk'a yazmaz.
        $this->world->getChunk(new ChunkPosition(0, 0));
        $this->world->getChunk(new ChunkPosition(1, 0));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    // --- Yardimcilar ------------------------------------------------------

    private function airId(): int
    {
        return $this->registry->state('minecraft:air')->getNetworkId();
    }

    private function stoneId(): int
    {
        return $this->registry->state('minecraft:stone')->getNetworkId();
    }

    private function player(string $name = 'Steve', int $entityId = 1, int $runtimeId = 1): Player
    {
        return new Player(
            identity: new PlayerIdentity(
                xuid: 'xuid-' . $name,
                uuid: '00000000-0000-0000-0000-00000000000' . strlen($name),
                displayName: $name,
                type: IdentityType::Offline,
                verifiedAt: time()
            ),
            sessionId: 'session-' . $name,
            connectionId: 'connection-' . $name,
            entityId: $entityId,
            runtimeId: $runtimeId,
            world: $this->world,
            x: 8.5,
            y: -57.0,
            z: 8.5,
            inventory: new PlayerInventory('', str_pad((string) $entityId, 8, '0', STR_PAD_LEFT) . '-0000-0000-0000-000000000000')
        );
    }

    private function spawnedPlayer(string $name = 'Steve', int $entityId = 1): Player
    {
        $player = $this->player($name, $entityId);
        $player->markSpawned();

        return $player;
    }

    private function itemUse(int $x, int $y, int $z, int $face = 1, int $slot = 0): ItemUseInventoryTransaction
    {
        return new ItemUseInventoryTransaction(
            actions: [],
            actionType: ItemUseActionType::Place,
            triggerType: 0,
            position: new BlockPos($x, $y, $z),
            face: $face,
            slot: $slot,
            hand: HandSlot::MainHand,
            item: NetworkItemStackDescriptor::empty(),
            fromPosition: new Vec3(8.5, -57.0, 8.5),
            clickPosition: new Vec3(0.5, 1.0, 0.5),
            targetBlockId: $this->stoneId(),
            clientInteractPrediction: 0,
            clientCooldownState: 0
        );
    }

    // --- Saglik -----------------------------------------------------------

    public function testHealthIsPerPlayer(): void
    {
        $first = $this->spawnedPlayer('Alex', 1);
        $second = $this->spawnedPlayer('Steve', 2);

        self::assertSame(Player::MAX_HEALTH, $first->health());
        self::assertSame(5, $first->damage(5));

        self::assertSame(15, $first->health());
        self::assertSame(Player::MAX_HEALTH, $second->health(), 'Saglik paylasilmamali.');
    }

    public function testLethalDamageMarksPlayerDeadAndBlocksInteraction(): void
    {
        $player = $this->spawnedPlayer();

        self::assertSame(Player::MAX_HEALTH, $player->damage(Player::MAX_HEALTH));

        self::assertSame(0, $player->health());
        self::assertTrue($player->isDead());
        self::assertSame(PlayerState::Dead, $player->state());
        self::assertFalse($player->canInteract());
        self::assertFalse($player->state()->canInteract());
    }

    public function testDamageOnDeadPlayerIsIgnored(): void
    {
        $player = $this->spawnedPlayer();
        $player->damage(Player::MAX_HEALTH);

        self::assertSame(0, $player->damage(3));
        self::assertSame(0, $player->health());
    }

    public function testHealIsCappedAndDoesNotRevive(): void
    {
        $player = $this->spawnedPlayer();

        $player->damage(Player::MAX_HEALTH);
        self::assertSame(0, $player->heal(5));
        self::assertTrue($player->isDead());

        $player->markRespawned(8.5, -57.0, 8.5);
        self::assertSame(Player::MAX_HEALTH, $player->health());

        $player->damage(4);
        self::assertSame(4, $player->heal(10));
        self::assertSame(Player::MAX_HEALTH, $player->health());
        self::assertSame(0, $player->heal(1));
    }

    public function testRespawnRestoresStateHealthAndPosition(): void
    {
        $player = $this->spawnedPlayer();
        $player->damage(Player::MAX_HEALTH);
        $player->setPosition(1.0, -50.0, 1.0);

        $player->markRespawned(8.5, -57.0, 8.5);

        self::assertSame(PlayerState::Spawned, $player->state());
        self::assertSame(Player::MAX_HEALTH, $player->health());
        self::assertTrue($player->canInteract());
        self::assertSame(8.5, $player->x());
        self::assertSame(-57.0, $player->y());
    }

    public function testAlivePlayerCannotRespawn(): void
    {
        $player = $this->spawnedPlayer();

        $this->expectException(\PickaxeMP\player\PlayerException::class);
        $player->markRespawned(0.0, -57.0, 0.0);
    }

    public function testHotbarSelectionIsBounded(): void
    {
        $player = $this->spawnedPlayer();

        $player->setSelectedHotbarSlot(8);
        self::assertSame(8, $player->selectedHotbarSlot());

        $this->expectException(\PickaxeMP\player\PlayerException::class);
        $player->setSelectedHotbarSlot(9);
    }

    // --- Blok kirma -------------------------------------------------------

    public function testBreakReplacesBlockWithAirAndReportsChange(): void
    {
        $player = $this->spawnedPlayer();

        $result = $this->blocks->breakBlock($player, 3, -58, 5, $this->world);

        self::assertFalse($result->isEmpty());
        self::assertCount(1, $result->changes);
        self::assertSame($this->airId(), $result->changes[0]->networkId);
        self::assertSame($this->airId(), $this->world->getBlock(3, -58, 5));
    }

    public function testBreakReportsBreakSoundWithBlockName(): void
    {
        $player = $this->spawnedPlayer();

        $result = $this->blocks->breakBlock($player, 3, -58, 5, $this->world);

        $sound = $result->sound();
        self::assertNotNull($sound);
        self::assertSame('break', $sound->event);
        self::assertSame('minecraft:grass_block', $sound->block);
        self::assertSame([3, -58, 5], [$sound->x, $sound->y, $sound->z]);

        $packet = $sound->toPacket();
        self::assertSame('break', $packet->soundEvent);
        self::assertSame('minecraft:grass_block', $packet->actorIdentifier);
        self::assertFalse($packet->isGlobal, 'Blok sesi tum sunucuya gitmemeli');
        self::assertSame(3.5, $packet->position->x);
        self::assertSame(-57.5, $packet->position->y);
        self::assertSame(5.5, $packet->position->z);
    }

    public function testFailedBreakReportsNoSound(): void
    {
        $player = $this->spawnedPlayer();

        self::assertNotSame([], $this->blocks->breakBlock($player, 3, -58, 5, $this->world)->sounds);

        // Ayni blok artik hava: kirilma yok, ses de olmamali. Aksi hâlde
        // istemci olmayan bir kirilma duyar.
        self::assertSame([], $this->blocks->breakBlock($player, 3, -58, 5, $this->world)->sounds);
    }

    public function testBreakOnUnloadedChunkDoesNothing(): void
    {
        $player = $this->spawnedPlayer();

        $result = $this->blocks->breakBlock($player, 500, -58, 500, $this->world);

        self::assertTrue($result->isEmpty());
    }

    public function testBreakOutsideWorldHeightDoesNothing(): void
    {
        $player = $this->spawnedPlayer();

        self::assertTrue($this->blocks->breakBlock($player, 3, 400, 5, $this->world)->isEmpty());
    }

    public function testDeadPlayerCannotBreakOrPlace(): void
    {
        $player = $this->spawnedPlayer();
        $player->damage(Player::MAX_HEALTH);

        self::assertTrue($this->blocks->breakBlock($player, 3, -58, 5, $this->world)->isEmpty());

        $player->inventory()->setSlot(0, new ItemStack('minecraft:stone', 4));
        self::assertTrue($this->blocks->place($player, $this->itemUse(3, -58, 5), $this->world)->isEmpty());
    }

    public function testMutationRateLimitIsEnforced(): void
    {
        $player = $this->spawnedPlayer();

        // 30 mutasyon kabul edilir; bloklar oyuncunun (8.5, -57, 8.5)
        // konumundan menzil içindeki farklı sütunlarda.
        $positions = [];

        for ($x = 4; $x <= 8; $x++) {
            for ($z = 4; $z <= 9; $z++) {
                $positions[] = [$x, $z];
            }
        }

        self::assertGreaterThanOrEqual(BlockActionManager::MAX_MUTATIONS_PER_SECOND, count($positions));

        for ($i = 0; $i < BlockActionManager::MAX_MUTATIONS_PER_SECOND; $i++) {
            [$x, $z] = $positions[$i];
            $this->world->setBlock($x, -57, $z, $this->stoneId());
            self::assertFalse(
                $this->blocks->breakBlock($player, $x, -57, $z, $this->world)->isEmpty(),
                "Mutasyon {$i} reddedilmemeliydi."
            );
        }

        // Menzil içinde ama hız sınırı aşılmış bir blok.
        $this->world->setBlock(8, -57, 4, $this->stoneId());
        self::assertTrue(
            $this->blocks->breakBlock($player, 8, -57, 4, $this->world)->isEmpty(),
            'Sinir asildiginda mutasyon yapilmamali.'
        );
    }

    // --- Blok yerlestirme -------------------------------------------------

    public function testPlaceConsumesOneItemAndReportsSlot(): void
    {
        $player = $this->spawnedPlayer();
        $player->inventory()->setSlot(0, new ItemStack('minecraft:stone', 3));

        $result = $this->blocks->place($player, $this->itemUse(3, -58, 5, 1, 0), $this->world);

        self::assertFalse($result->isEmpty());
        self::assertSame(0, $result->inventorySlot);
        self::assertSame(2, $result->inventoryStack?->count());
        self::assertSame(2, $player->inventory()->getSlot(0)?->count());

        $placed = $this->world->getBlock(3, -57, 5);
        self::assertSame($this->registry->defaultState('minecraft:stone')->getNetworkId(), $placed);

        $sound = $result->sound();
        self::assertNotNull($sound);
        self::assertSame('place', $sound->event);
        self::assertSame('minecraft:stone', $sound->block);
        self::assertSame([3, -57, 5], [$sound->x, $sound->y, $sound->z]);    }

    public function testPlaceOnOccupiedTargetIsRejected(): void
    {
        $player = $this->spawnedPlayer();
        $player->inventory()->setSlot(0, new ItemStack('minecraft:stone', 3));

        // Hedef (-57) zaten hava degil: grass blogunun kendisine yerlestir.
        $result = $this->blocks->place($player, $this->itemUse(3, -58, 5, 0, 0), $this->world);

        // Face 0 = alt; hedef (3, -59, 5) dolu (dirt) oldugu icin reddedilir.
        self::assertTrue($result->isEmpty());
        self::assertSame(3, $player->inventory()->getSlot(0)?->count());
    }

    public function testPlaceWithoutHeldBlockIsRejected(): void
    {
        $player = $this->spawnedPlayer();
        $player->inventory()->setSlot(0, new ItemStack('minecraft:diamond_sword', 1));

        $result = $this->blocks->place($player, $this->itemUse(3, -58, 5), $this->world);

        self::assertTrue($result->isEmpty());
        self::assertSame(1, $player->inventory()->getSlot(0)?->count());
    }

    public function testPlaceLastItemClearsSlot(): void
    {
        $player = $this->spawnedPlayer();
        $player->inventory()->setSlot(0, new ItemStack('minecraft:stone', 1));

        $result = $this->blocks->place($player, $this->itemUse(3, -58, 5, 1, 0), $this->world);

        self::assertSame(0, $result->inventorySlot);
        self::assertNull($result->inventoryStack);
        self::assertNull($player->inventory()->getSlot(0));
    }

    public function testNonPlaceItemUseIsIgnored(): void
    {
        $player = $this->spawnedPlayer();
        $player->inventory()->setSlot(0, new ItemStack('minecraft:stone', 3));

        $use = new ItemUseInventoryTransaction(
            actions: [],
            actionType: ItemUseActionType::Use,
            triggerType: 0,
            position: new BlockPos(3, -58, 5),
            face: 1,
            slot: 0,
            hand: HandSlot::MainHand,
            item: NetworkItemStackDescriptor::empty(),
            fromPosition: new Vec3(8.5, -57.0, 8.5),
            clickPosition: new Vec3(0.5, 1.0, 0.5),
            targetBlockId: 0,
            clientInteractPrediction: 0,
            clientCooldownState: 0
        );

        self::assertTrue($this->blocks->place($player, $use, $this->world)->isEmpty());
        self::assertSame(3, $player->inventory()->getSlot(0)?->count());
    }

    public function testLoadedChunkIsRequired(): void
    {
        self::assertFalse($this->world->blockPositionLoaded(100, -58, 100));
        $this->world->getChunk(new ChunkPosition(0, 0));
        self::assertTrue($this->world->blockPositionLoaded(3, -58, 5));
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
}
