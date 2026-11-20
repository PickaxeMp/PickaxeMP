<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit;

use PHPUnit\Framework\TestCase;
use PickaxeMP\inventory\Inventory;
use PickaxeMP\item\ItemStack;
use PickaxeMP\player\PlayerException;

/**
 * Inventory sözleşmesi.
 *
 * - `size()` yuva sayısı, `count()` **toplam eşya** sayısıdır.
 * - `isFull()` yalnızca **hiç boş yuva yoksa** `true`'dur (27/27).
 */
final class InventoryTest extends TestCase
{
    private string $root;
    private Inventory $inventory;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'pickaxe-inventory-' . bin2hex(random_bytes(4));
        mkdir($this->root, 0755, true);
        $this->inventory = new Inventory($this->root, 27);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testEmptyInventory(): void
    {
        self::assertSame(0, $this->inventory->count());
        self::assertSame(27, $this->inventory->size());
        self::assertTrue($this->inventory->isEmpty());
    }

    public function testSetAndGetSlot(): void
    {
        $stack = new ItemStack('minecraft:diamond', 10);
        $this->inventory->setSlot(0, $stack);
        self::assertSame($stack, $this->inventory->getSlot(0));
        self::assertNull($this->inventory->getSlot(1));
    }

    public function testSetSlotOutsideBoundsThrows(): void
    {
        $this->expectException(PlayerException::class);
        $this->inventory->setSlot(27, new ItemStack('minecraft:air', 1));
    }

    public function testSetNegativeSlotThrows(): void
    {
        $this->expectException(PlayerException::class);
        $this->inventory->setSlot(-1, new ItemStack('minecraft:air', 1));
    }

    public function testGetSlotOutsideBoundsThrows(): void
    {
        $this->expectException(PlayerException::class);
        $this->inventory->getSlot(27);
    }

    public function testSwapSlots(): void
    {
        $this->inventory->setSlot(0, new ItemStack('minecraft:diamond', 10));
        $this->inventory->setSlot(1, new ItemStack('minecraft:iron_ingot', 16));
        $this->inventory->swapSlots(0, 1);
        self::assertSame(16, $this->inventory->getSlot(0)->count());
        self::assertSame(10, $this->inventory->getSlot(1)->count());
    }

    public function testSwapSameSlotNoChange(): void
    {
        $this->inventory->setSlot(0, new ItemStack('minecraft:diamond', 10));
        $this->inventory->swapSlots(0, 0);
        self::assertSame(10, $this->inventory->getSlot(0)->count());
    }

    public function testTransferToOtherSlot(): void
    {
        $this->inventory->setSlot(0, new ItemStack('minecraft:diamond', 10));
        self::assertTrue($this->inventory->transfer(0, 1));
        self::assertSame(10, $this->inventory->getSlot(1)->count());
        self::assertNull($this->inventory->getSlot(0));
    }

    public function testTransferNoSpaceFullStack(): void
    {
        $this->inventory->setSlot(0, new ItemStack('minecraft:diamond', 64));
        $this->inventory->setSlot(1, new ItemStack('minecraft:diamond', 64));
        self::assertFalse($this->inventory->transfer(0, 1));
        self::assertSame(64, $this->inventory->getSlot(0)->count());
        self::assertSame(64, $this->inventory->getSlot(1)->count());
    }

    public function testTransferMergesPartially(): void
    {
        $this->inventory->setSlot(0, new ItemStack('minecraft:diamond', 10));
        $this->inventory->setSlot(1, new ItemStack('minecraft:diamond', 60));
        self::assertTrue($this->inventory->transfer(0, 1));
        self::assertSame(64, $this->inventory->getSlot(1)->count());
        self::assertSame(6, $this->inventory->getSlot(0)->count());
    }

    public function testTransferDifferentTypeFails(): void
    {
        $this->inventory->setSlot(0, new ItemStack('minecraft:diamond', 10));
        $this->inventory->setSlot(1, new ItemStack('minecraft:iron_ingot', 5));
        self::assertFalse($this->inventory->transfer(0, 1));
    }

    public function testCount(): void
    {
        self::assertSame(0, $this->inventory->count());
        $this->inventory->setSlot(0, new ItemStack('minecraft:diamond', 5));
        self::assertSame(5, $this->inventory->count());
    }

    public function testIsFullRequiresEverySlotOccupied(): void
    {
        self::assertFalse($this->inventory->isFull());

        for ($slot = 0; $slot < 27; $slot++) {
            self::assertFalse($this->inventory->isFull());
            $this->inventory->setSlot($slot, new ItemStack('minecraft:diamond', 1));
        }

        self::assertTrue($this->inventory->isFull());
    }

    public function testRemoveFromSlot(): void
    {
        $this->inventory->setSlot(0, new ItemStack('minecraft:diamond', 15));
        $removed = $this->inventory->removeFromSlot(0, 5);

        self::assertSame(5, $removed->count());
        self::assertSame(10, $this->inventory->getSlot(0)->count());
        self::assertSame(10, $this->inventory->count());
    }

    public function testRemoveAllFromSlot(): void
    {
        $this->inventory->setSlot(0, new ItemStack('minecraft:diamond', 10));
        $this->inventory->removeFromSlot(0, 10);
        self::assertNull($this->inventory->getSlot(0));
        self::assertSame(0, $this->inventory->count());
    }

    public function testRemoveTooMuchThrows(): void
    {
        $this->inventory->setSlot(0, new ItemStack('minecraft:diamond', 10));
        $this->expectException(PlayerException::class);
        $this->inventory->removeFromSlot(0, 11);
    }

    public function testVersionIncrementsOnMutation(): void
    {
        $version = $this->inventory->version();
        $this->inventory->setSlot(0, new ItemStack('minecraft:diamond', 1));
        self::assertGreaterThan($version, $this->inventory->version());
    }

    public function testFirstEmptySlot(): void
    {
        self::assertSame(0, $this->inventory->firstEmptySlot());
        $this->inventory->setSlot(0, new ItemStack('minecraft:diamond', 1));
        self::assertSame(1, $this->inventory->firstEmptySlot());
    }

    public function testSerializationRoundTrip(): void
    {
        $this->inventory->setSlot(3, new ItemStack('minecraft:diamond', 7));

        $restored = new Inventory($this->root, 27);
        $restored->loadFromArray($this->inventory->toArray());

        self::assertSame(7, $restored->getSlot(3)->count());
        self::assertSame(7, $restored->count());
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
