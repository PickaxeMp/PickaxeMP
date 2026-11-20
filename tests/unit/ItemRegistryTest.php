<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit;

use PHPUnit\Framework\TestCase;
use PickaxeMP\item\ItemRegistry;
use PickaxeMP\player\PlayerException;

/**
 * ItemRegistry sözleşmesi.
 *
 * `register()` **`Item` döner** ve yinelenen kayıtta istisna fırlatır; bu
 * yüzden "ikinci kayıt false döner" biçiminde bir beklenti yoktur.
 */
final class ItemRegistryTest extends TestCase
{
    private string $root;
    private ItemRegistry $registry;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'pickaxe-item-registry-' . bin2hex(random_bytes(4));
        mkdir($this->root, 0755, true);
        $this->registry = new ItemRegistry($this->root);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testRegisterAndLookup(): void
    {
        $item = $this->registry->register('minecraft:diamond_sword', 264);
        $found = $this->registry->get('minecraft:diamond_sword');
        self::assertSame($item, $found);
        self::assertSame(264, $found->networkId());
        self::assertSame('minecraft:diamond_sword', $found->identifier());
        self::assertSame(64, $found->maxStackSize());
    }

    public function testDuplicateRegistrationThrows(): void
    {
        $this->registry->register('minecraft:diamond_sword', 264);
        $this->expectException(PlayerException::class);
        $this->registry->register('minecraft:diamond_sword', 265);
    }

    public function testDuplicateNetworkIdThrows(): void
    {
        $this->registry->register('minecraft:diamond_sword', 264);
        $this->expectException(PlayerException::class);
        $this->registry->register('minecraft:iron_sword', 264);
    }

    public function testFailedRegistrationLeavesRegistryUnchanged(): void
    {
        $this->registry->register('minecraft:stone', 1);

        try {
            $this->registry->register('minecraft:iron_sword', 1);
            self::fail('Cakisan ag kimligi kabul edilmemeliydi');
        } catch (PlayerException) {
            self::assertSame(1, $this->registry->count());
            self::assertFalse($this->registry->has('minecraft:iron_sword'));
        }
    }

    public function testUnknownItemThrows(): void
    {
        $this->expectException(PlayerException::class);
        $this->registry->get('minecraft:non_existent');
    }

    public function testInvalidIdentifierThrows(): void
    {
        $this->expectException(PlayerException::class);
        $this->registry->register('diamond_sword', 1);
    }

    public function testNegativeNetworkIdThrows(): void
    {
        $this->expectException(PlayerException::class);
        $this->registry->register('minecraft:diamond_sword', -1);
    }

    public function testCount(): void
    {
        self::assertSame(0, $this->registry->count());
        $this->registry->register('minecraft:air', 0);
        self::assertSame(1, $this->registry->count());
    }

    public function testSaveAndReloadRoundTrip(): void
    {
        $this->registry->register('minecraft:diamond', 264, 64);
        $this->registry->register('minecraft:stone', 1, 64);
        $this->registry->save();

        $reloaded = new ItemRegistry($this->root);

        self::assertSame(2, $reloaded->count());
        self::assertSame(264, $reloaded->get('minecraft:diamond')->networkId());
        self::assertSame(64, $reloaded->get('minecraft:diamond')->maxStackSize());
    }

    public function testCorruptRegistryFileIsIgnored(): void
    {
        file_put_contents($this->root . DIRECTORY_SEPARATOR . 'item-registry.json', 'PXITEM1' . pack('N', 4) . 'nope' . pack('N', 0));

        $registry = new ItemRegistry($this->root);

        self::assertSame(0, $registry->count());
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
