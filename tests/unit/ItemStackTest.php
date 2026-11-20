<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit;

use PHPUnit\Framework\TestCase;
use PickaxeMP\item\ItemStack;
use PickaxeMP\player\PlayerException;

/**
 * ItemStack sözleşmesi.
 *
 * Kurucu **biçim** sınırını (0 < miktar ≤ 128) doğrular; `add()` **oyun**
 * sınırını (`maxStackSize`, varsayılan 64) uygular. 65'lik yeni yığın
 * oluşturulabilir (protokol alanı `uint16`'ya sığar) ancak dolu yığına
 * ekleme yapılamaz.
 */
final class ItemStackTest extends TestCase
{
    public function testValidCreation(): void
    {
        $stack = new ItemStack('minecraft:diamond', 64);
        self::assertSame('minecraft:diamond', $stack->type());
        self::assertSame(64, $stack->count());
    }

    public function testMaxStackSize(): void
    {
        $stack = new ItemStack('minecraft:diamond', 65);
        self::assertSame(64, $stack->maxStackSize());
        self::expectException(PlayerException::class);
        $stack->add(1);
    }

    public function testStackOverMaxThrows(): void
    {
        $this->expectException(PlayerException::class);
        new ItemStack('minecraft:diamond', 129);
    }

    public function testCountZeroThrows(): void
    {
        $this->expectException(PlayerException::class);
        new ItemStack('minecraft:diamond', 0);
    }

    public function testNegativeCountThrows(): void
    {
        $this->expectException(PlayerException::class);
        new ItemStack('minecraft:diamond', -1);
    }

    public function testInvalidTypeThrows(): void
    {
        $this->expectException(PlayerException::class);
        new ItemStack('diamond', 1);
    }

    public function testEmptyStack(): void
    {
        $stack = ItemStack::empty();
        self::assertTrue($stack->isEmpty());
        self::assertSame(0, $stack->count());
    }

    public function testNonEmptyNotEmpty(): void
    {
        $stack = new ItemStack('minecraft:iron_ingot', 1);
        self::assertFalse($stack->isEmpty());
    }

    public function testAddStacksSameType(): void
    {
        $stack = new ItemStack('minecraft:diamond', 10);
        $stack->add(25);
        self::assertSame(35, $stack->count());
    }

    public function testAddExceedsMaxThrows(): void
    {
        $stack = new ItemStack('minecraft:diamond', 60);
        $this->expectException(PlayerException::class);
        $stack->add(10); // 70 > 64
    }

    public function testAddZeroThrows(): void
    {
        $this->expectException(PlayerException::class);
        (new ItemStack('minecraft:diamond', 1))->add(0);
    }

    public function testRemoveReducesCount(): void
    {
        $stack = new ItemStack('minecraft:diamond', 20);
        $stack->remove(5);
        self::assertSame(15, $stack->count());
    }

    public function testRemoveMoreThanAvailableThrows(): void
    {
        $this->expectException(PlayerException::class);
        (new ItemStack('minecraft:diamond', 10))->remove(15);
    }

    public function testRemoveToZeroRemovesStack(): void
    {
        $stack = new ItemStack('minecraft:diamond', 10);
        $stack->remove(10);
        self::assertTrue($stack->isEmpty());
    }

    public function testCount(): void
    {
        $stack = new ItemStack('minecraft:gold_ingot', 42);
        self::assertSame(42, $stack->count());
    }

    public function testRemainingSpace(): void
    {
        $stack = new ItemStack('minecraft:gold_ingot', 42);
        self::assertSame(22, $stack->remainingSpace());
    }

    public function testSerializationRoundTrip(): void
    {
        $stack = new ItemStack('minecraft:diamond', 17);
        $restored = ItemStack::fromArray($stack->toArray());

        self::assertSame($stack->type(), $restored->type());
        self::assertSame($stack->count(), $restored->count());
        self::assertSame($stack->maxStackSize(), $restored->maxStackSize());
    }

    public function testCorruptSerializationThrows(): void
    {
        $this->expectException(PlayerException::class);
        ItemStack::fromArray(['type' => 'minecraft:diamond', 'count' => 'no']);
    }
}
