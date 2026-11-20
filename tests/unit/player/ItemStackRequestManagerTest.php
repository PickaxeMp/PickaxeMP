<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\player;

use PHPUnit\Framework\TestCase;
use PickaxeMP\auth\IdentityType;
use PickaxeMP\auth\PlayerIdentity;
use PickaxeMP\item\ItemStack;
use PickaxeMP\player\ItemStackRequestManager;
use PickaxeMP\player\Player;
use PickaxeMP\player\PlayerInventory;
use PickaxeMP\protocol\packet\types\FullContainerName;
use PickaxeMP\protocol\packet\types\ItemStackNetResult;
use PickaxeMP\protocol\packet\types\ItemStackRequest;
use PickaxeMP\protocol\packet\types\ItemStackRequestAction;
use PickaxeMP\protocol\packet\types\ItemStackRequestSlotInfo;
use PickaxeMP\utils\Logger;
use PickaxeMP\world\World;
use PickaxeMP\world\WorldManager;
use PickaxeMP\world\block\BlockRegistry;
use PickaxeMP\world\generator\FlatGenerator;
use PickaxeMP\world\storage\FileWorldStorage;

/**
 * Phase 10.3 — sunucu yetkili yığın isteği doğrulaması.
 *
 * Kritik kurallar:
 *  - Yalnızca `Take` / `Place` / `Swap` uygulanır; diğerleri **reddedilir**.
 *  - Yalnızca `CombinedHotbarAndInventory` (12, yuva 0-35) ve `Cursor`
 *    (59, yuva 0) kabul edilir.
 *  - İşlem **atomiktir**: herhangi bir aksiyon hata verirse envanter ve
 *    imleç hiç değişmez.
 *  - Hata yanıtı konteyner bilgisi taşımaz.
 *  - İmleç oturum başına izlenir; `forget()` siler.
 */
final class ItemStackRequestManagerTest extends TestCase
{
    private string $root;
    private Logger $logger;
    private World $world;
    private ItemStackRequestManager $manager;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'pickaxe-stackreq-' . bin2hex(random_bytes(4));
        mkdir($this->root, 0755, true);

        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);
        $this->logger = new Logger('stackreq-test', $stream, Logger::DEBUG);

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

        $this->manager = new ItemStackRequestManager($this->logger);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    private function slot(int $container, int $slot): ItemStackRequestSlotInfo
    {
        return new ItemStackRequestSlotInfo(new FullContainerName($container), $slot);
    }

    private function request(int $id, ItemStackRequestAction ...$actions): ItemStackRequest
    {
        return new ItemStackRequest($id, array_values($actions));
    }

    private function inventorySlot(int $slot): ItemStackRequestSlotInfo
    {
        return $this->slot(ItemStackRequestManager::CONTAINER_INVENTORY, $slot);
    }

    private function cursorSlot(): ItemStackRequestSlotInfo
    {
        return $this->slot(ItemStackRequestManager::CONTAINER_CURSOR, 0);
    }

    public function testTakeMovesAmountFromInventoryToCursor(): void
    {
        $player = $this->player();
        $player->inventory()->setSlot(5, new ItemStack('minecraft:bread', 5));

        $response = $this->manager->handle(
            $player,
            $this->request(-3, ItemStackRequestAction::take(3, $this->inventorySlot(5), $this->cursorSlot()))
        );

        self::assertTrue($response->isSuccess());
        self::assertSame(-3, $response->requestId);
        self::assertSame(2, $player->inventory()->getSlot(5)?->count());
        self::assertSame(3, $this->manager->cursor($player->sessionId)?->count());

        self::assertCount(2, $response->containers);
        self::assertSame(ItemStackRequestManager::CONTAINER_INVENTORY, $response->containers[0]->container->containerName);
        self::assertSame(5, $response->containers[0]->slots[0]->slot);
        self::assertSame(2, $response->containers[0]->slots[0]->amount);
        self::assertSame(ItemStackRequestManager::CONTAINER_CURSOR, $response->containers[1]->container->containerName);
        self::assertSame(3, $response->containers[1]->slots[0]->amount);
    }

    public function testPlaceMergesIntoExistingStack(): void
    {
        $player = $this->player();
        $player->inventory()->setSlot(2, new ItemStack('minecraft:bread', 10));
        $this->manager->handle(
            $player,
            $this->request(-3, ItemStackRequestAction::take(4, $this->inventorySlot(2), $this->cursorSlot()))
        );

        $response = $this->manager->handle(
            $player,
            $this->request(-5, ItemStackRequestAction::place(4, $this->cursorSlot(), $this->inventorySlot(2)))
        );

        self::assertTrue($response->isSuccess());
        self::assertSame(10, $player->inventory()->getSlot(2)?->count());
        self::assertNull($this->manager->cursor($player->sessionId));
        self::assertSame(10, $response->containers[0]->slots[0]->amount);
        self::assertSame(0, $response->containers[1]->slots[0]->amount);
    }

    public function testSwapExchangesCursorAndSlot(): void
    {
        $player = $this->player();
        $player->inventory()->setSlot(8, new ItemStack('minecraft:stone', 7));
        $player->inventory()->setSlot(9, new ItemStack('minecraft:bread', 3));
        $this->manager->handle(
            $player,
            $this->request(-3, ItemStackRequestAction::take(2, $this->inventorySlot(8), $this->cursorSlot()))
        );

        $response = $this->manager->handle(
            $player,
            $this->request(-7, ItemStackRequestAction::swap($this->cursorSlot(), $this->inventorySlot(9)))
        );

        self::assertTrue($response->isSuccess());
        self::assertSame(2, $player->inventory()->getSlot(9)?->count());
        self::assertSame('minecraft:stone', $player->inventory()->getSlot(9)?->type());
        self::assertSame('minecraft:bread', $this->manager->cursor($player->sessionId)?->type());
        self::assertSame(3, $this->manager->cursor($player->sessionId)?->count());
    }

    public function testInsufficientSourceRejectsWithoutMutation(): void
    {
        $player = $this->player();
        $player->inventory()->setSlot(4, new ItemStack('minecraft:bread', 2));

        $response = $this->manager->handle(
            $player,
            $this->request(-3, ItemStackRequestAction::take(5, $this->inventorySlot(4), $this->cursorSlot()))
        );

        self::assertSame(ItemStackNetResult::INVALID_REMOVED_AMOUNT, $response->result);
        self::assertSame([], $response->containers);
        self::assertSame(2, $player->inventory()->getSlot(4)?->count());
        self::assertNull($this->manager->cursor($player->sessionId));
    }

    public function testMismatchedDestinationTypeRejects(): void
    {
        $player = $this->player();
        $player->inventory()->setSlot(1, new ItemStack('minecraft:bread', 4));
        $player->inventory()->setSlot(2, new ItemStack('minecraft:stone', 4));

        $response = $this->manager->handle(
            $player,
            $this->request(-3, ItemStackRequestAction::take(2, $this->inventorySlot(1), $this->inventorySlot(2)))
        );

        self::assertSame(ItemStackNetResult::CANNOT_PLACE_ITEM, $response->result);
        self::assertSame(4, $player->inventory()->getSlot(1)?->count());
        self::assertSame(4, $player->inventory()->getSlot(2)?->count());
    }

    public function testDestinationOverflowRejects(): void
    {
        $player = $this->player();
        $player->inventory()->setSlot(1, new ItemStack('minecraft:bread', 64));
        $player->inventory()->setSlot(2, new ItemStack('minecraft:bread', 62));

        $response = $this->manager->handle(
            $player,
            $this->request(-3, ItemStackRequestAction::take(4, $this->inventorySlot(1), $this->inventorySlot(2)))
        );

        self::assertSame(ItemStackNetResult::INVALID_ADJUSTED_AMOUNT, $response->result);
        self::assertSame(64, $player->inventory()->getSlot(1)?->count());
        self::assertSame(62, $player->inventory()->getSlot(2)?->count());
    }

    public function testUnsupportedActionIsRejectedAtomically(): void
    {
        $player = $this->player();
        $player->inventory()->setSlot(0, new ItemStack('minecraft:bread', 3));

        $response = $this->manager->handle(
            $player,
            $this->request(
                -3,
                ItemStackRequestAction::take(2, $this->inventorySlot(0), $this->cursorSlot()),
                new ItemStackRequestAction(
                    type: ItemStackRequestAction::DROP,
                    amount: 1,
                    source: $this->inventorySlot(0)
                )
            )
        );

        self::assertSame(ItemStackNetResult::ACTION_REQUEST_NOT_ALLOWED, $response->result);
        self::assertSame(3, $player->inventory()->getSlot(0)?->count());
        self::assertNull($this->manager->cursor($player->sessionId));
    }

    public function testUnsupportedContainerIsRejected(): void
    {
        $player = $this->player();
        $player->inventory()->setSlot(0, new ItemStack('minecraft:bread', 3));

        $response = $this->manager->handle(
            $player,
            $this->request(-3, ItemStackRequestAction::take(1, $this->slot(28, 0), $this->cursorSlot()))
        );

        self::assertSame(ItemStackNetResult::INVALID_SOURCE_CONTAINER, $response->result);
        self::assertSame(3, $player->inventory()->getSlot(0)?->count());
    }

    public function testCursorSlotMustBeZero(): void
    {
        $player = $this->player();

        $response = $this->manager->handle(
            $player,
            $this->request(-3, ItemStackRequestAction::take(1, $this->slot(59, 1), $this->cursorSlot()))
        );

        self::assertSame(ItemStackNetResult::INVALID_SOURCE_CONTAINER, $response->result);
    }

    public function testInvalidRequestIdIsRejected(): void
    {
        $player = $this->player();
        $player->inventory()->setSlot(0, new ItemStack('minecraft:bread', 3));

        $response = $this->manager->handle(
            $player,
            $this->request(-4, ItemStackRequestAction::take(1, $this->inventorySlot(0), $this->cursorSlot()))
        );

        self::assertSame(ItemStackNetResult::ERROR, $response->result);
        self::assertSame(3, $player->inventory()->getSlot(0)?->count());
    }

    public function testDeadPlayerCannotRequest(): void
    {
        $player = $this->player();
        $player->inventory()->setSlot(0, new ItemStack('minecraft:bread', 3));
        $player->damage(Player::MAX_HEALTH);

        $response = $this->manager->handle(
            $player,
            $this->request(-3, ItemStackRequestAction::take(1, $this->inventorySlot(0), $this->cursorSlot()))
        );

        self::assertSame(ItemStackNetResult::ACTION_REQUEST_NOT_ALLOWED, $response->result);
        self::assertSame(3, $player->inventory()->getSlot(0)?->count());
    }

    public function testForgetDropsCursorState(): void
    {
        $player = $this->player();
        $player->inventory()->setSlot(0, new ItemStack('minecraft:bread', 3));
        $this->manager->handle(
            $player,
            $this->request(-3, ItemStackRequestAction::take(1, $this->inventorySlot(0), $this->cursorSlot()))
        );

        self::assertNotNull($this->manager->cursor($player->sessionId));

        $this->manager->forget($player->sessionId);

        self::assertNull($this->manager->cursor($player->sessionId));
    }

    public function testCursorPersistsBetweenRequests(): void
    {
        $player = $this->player();
        $player->inventory()->setSlot(0, new ItemStack('minecraft:bread', 5));

        $this->manager->handle(
            $player,
            $this->request(-3, ItemStackRequestAction::take(2, $this->inventorySlot(0), $this->cursorSlot()))
        );
        $this->manager->handle(
            $player,
            $this->request(-5, ItemStackRequestAction::place(2, $this->cursorSlot(), $this->inventorySlot(7)))
        );

        self::assertNull($this->manager->cursor($player->sessionId));
        self::assertSame(2, $player->inventory()->getSlot(7)?->count());
        self::assertSame(3, $player->inventory()->getSlot(0)?->count());
    }

    /**
     * Phase 10.3 incelemesi (gerçek bulgu): imleç kalıcı değildir; oyuncu
     * imleçte eşya varken ayrılırsa eşya **kaybolmamalı**. `release()` imleci
     * envantere geri yazar.
     */
    public function testReleaseMergesCursorStackBackIntoInventory(): void
    {
        $player = $this->player();
        $player->inventory()->setSlot(4, new ItemStack('minecraft:bread', 5));

        $this->manager->handle(
            $player,
            $this->request(-3, ItemStackRequestAction::take(3, $this->inventorySlot(4), $this->cursorSlot()))
        );

        self::assertSame(2, $player->inventory()->getSlot(4)?->count());
        self::assertSame(3, $this->manager->cursor($player->sessionId)?->count());

        $this->manager->release($player->sessionId, $player->inventory());

        self::assertNull($this->manager->cursor($player->sessionId));
        self::assertSame(5, $player->inventory()->getSlot(4)?->count());
        self::assertSame('minecraft:bread', $player->inventory()->getSlot(4)?->type());
    }

    public function testReleaseUsesEmptySlotWhenNoStackCanMerge(): void
    {
        $player = $this->player();
        $player->inventory()->setSlot(4, new ItemStack('minecraft:stone', 3));

        $this->manager->handle(
            $player,
            $this->request(-3, ItemStackRequestAction::take(3, $this->inventorySlot(4), $this->cursorSlot()))
        );

        self::assertNull($player->inventory()->getSlot(4));

        $this->manager->release($player->sessionId, $player->inventory());

        self::assertNull($this->manager->cursor($player->sessionId));
        self::assertSame(3, $player->inventory()->getSlot(0)?->count());
        self::assertSame('minecraft:stone', $player->inventory()->getSlot(0)?->type());
    }

    public function testReleaseWithoutCursorLeavesInventoryUntouched(): void
    {
        $player = $this->player();
        $player->inventory()->setSlot(0, new ItemStack('minecraft:bread', 2));

        $this->manager->release($player->sessionId, $player->inventory());

        self::assertSame(2, $player->inventory()->getSlot(0)?->count());
        self::assertSame(1, $player->inventory()->usedSlots());
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

