<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit;

use PHPUnit\Framework\TestCase;
use PickaxeMP\auth\PlayerIdentityFactory;
use PickaxeMP\item\ItemStack;
use PickaxeMP\player\PlayerInventory;
use PickaxeMP\player\PlayerManager;
use PickaxeMP\session\Session;
use PickaxeMP\session\SessionState;
use PickaxeMP\utils\Logger;
use PickaxeMP\world\block\BlockRegistry;
use PickaxeMP\world\World;
use PickaxeMP\world\generator\FlatGenerator;
use PickaxeMP\world\storage\FileWorldStorage;
use PickaxeMP\world\WorldManager;

/**
 * Oyuncu envanteri: 36 yuva + kalıcılık sözleşmesi.
 */
final class PlayerInventoryPersistenceTest extends TestCase
{
    private string $root;
    private Logger $logger;
    private World $world;
    private PlayerInventory $inventory;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'pickaxe-player-inventory-' . bin2hex(random_bytes(4));
        mkdir($this->root, 0755, true);

        $stream = fopen('php://memory', 'w+b');
        $this->logger = new Logger('player-inventory-test', $stream, Logger::DEBUG);

        $blockRegistry = BlockRegistry::withPhase4Blocks();
        $airNetworkId = $blockRegistry->state('minecraft:air')->getNetworkId();
        $worlds = new WorldManager(
            $this->logger,
            $this->root,
            $airNetworkId,
            new FileWorldStorage($this->root, $airNetworkId),
            new FlatGenerator($blockRegistry)
        );
        $this->world = $worlds->loadOrCreate('world', 1, 'Test World');

        $session = new Session('conn-persistence-test', '127.0.0.1:51000', '127.0.0.1:51000');
        $session->transitionTo(SessionState::AwaitingNetworkSettings);
        $session->transitionTo(SessionState::AwaitingLogin);
        $session->transitionTo(SessionState::Authenticating);
        $session->markAuthenticated((new PlayerIdentityFactory())->offline('PersistenceTest'));

        $players = new PlayerManager($this->logger, $this->root);
        $player = $players->create($session, $this->world);
        $this->inventory = $player->inventory();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testInventoryInitialState(): void
    {
        self::assertTrue($this->inventory->isEmpty());
        self::assertSame(36, $this->inventory->size());
        self::assertSame(PlayerInventory::HOTBAR_SIZE, 9);
    }

    public function testInventorySlotPersists(): void
    {
        $stack = new ItemStack('minecraft:diamond', 10);
        $this->inventory->setSlot(0, $stack);
        self::assertSame(10, $this->inventory->getSlot(0)->count());
        self::assertSame(10, $this->inventory->count());
    }

    public function testInventoryClearSlotPersists(): void
    {
        $this->inventory->setSlot(0, new ItemStack('minecraft:diamond', 10));
        $this->inventory->clearSlot(0);
        self::assertNull($this->inventory->getSlot(0));
        self::assertSame(0, $this->inventory->count());
    }

    public function testInventoryTransfer(): void
    {
        $this->inventory->setSlot(0, new ItemStack('minecraft:diamond', 10));
        $this->inventory->setSlot(1, new ItemStack('minecraft:iron_ingot', 16));
        // Boş yuvaya taşıma: yığın yer değiştirir.
        self::assertTrue($this->inventory->transfer(0, 2));
        self::assertNull($this->inventory->getSlot(0));
        self::assertSame(10, $this->inventory->getSlot(2)->count());
        self::assertSame(16, $this->inventory->getSlot(1)->count());
    }

    public function testInventoryCountAfterOperations(): void
    {
        $this->inventory->setSlot(0, new ItemStack('minecraft:diamond', 5));
        $this->inventory->setSlot(1, new ItemStack('minecraft:diamond', 10));
        self::assertSame(15, $this->inventory->count());

        $this->inventory->removeFromSlot(0, 3);
        self::assertSame(12, $this->inventory->count());
    }

    public function testInventoryIsFull(): void
    {
        self::assertFalse($this->inventory->isFull());

        for ($slot = 0; $slot < 36; $slot++) {
            $this->inventory->setSlot($slot, new ItemStack('minecraft:diamond', 1));
        }

        self::assertTrue($this->inventory->isFull());
    }

    public function testSaveAndLoadRoundTrip(): void
    {
        $this->inventory->setSlot(4, new ItemStack('minecraft:diamond', 33));
        self::assertTrue($this->inventory->isDirty());
        $this->inventory->save();
        self::assertFalse($this->inventory->isDirty());
        self::assertFileExists($this->inventory->path());

        $reloaded = new PlayerInventory($this->root, $this->inventory->ownerUuid(), $this->logger);
        self::assertTrue($reloaded->load());
        self::assertSame(33, $reloaded->getSlot(4)->count());
        self::assertSame(0, $reloaded->firstEmptySlot());
        self::assertSame(33, $reloaded->count());
    }

    public function testCorruptFileIsRejected(): void
    {
        $this->inventory->setSlot(0, new ItemStack('minecraft:diamond', 1));
        $this->inventory->save();

        // CRC'yi boz: son baytı değiştir.
        $path = $this->inventory->path();
        $body = (string) file_get_contents($path);
        file_put_contents($path, substr($body, 0, -1) . chr(ord(substr($body, -1)) ^ 0xFF));

        $reloaded = new PlayerInventory($this->root, $this->inventory->ownerUuid(), $this->logger);
        self::assertFalse($reloaded->load());
        self::assertTrue($reloaded->isEmpty());
    }

    public function testInventoryOfDifferentPlayerIsNotShared(): void
    {
        $this->inventory->setSlot(0, new ItemStack('minecraft:diamond', 5));
        $this->inventory->save();

        $other = new PlayerInventory($this->root, '00000000-0000-0000-0000-0000000000ff', $this->logger);

        self::assertFalse($other->load());
        self::assertTrue($other->isEmpty());
    }

    public function testInvalidOwnerUuidThrows(): void
    {
        $this->expectException(\PickaxeMP\player\PlayerException::class);
        new PlayerInventory($this->root, '../../etc/passwd', $this->logger);
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
            is_dir($full) ? $this->removeDirectory($full) : @unlink($full);
        }

        @rmdir($path);
    }
}
