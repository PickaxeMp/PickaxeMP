<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit;

use PHPUnit\Framework\TestCase;
use PickaxeMP\inventory\Inventory;
use PickaxeMP\inventory\InventoryTransaction;
use PickaxeMP\item\ItemRegistry;
use PickaxeMP\item\ItemStack;
use PickaxeMP\player\PlayerException;

/**
 * Atomiklik sözleşmesi.
 *
 * Kural: bir işlem ya **tamamen** uygulanır ya da **hiç** uygulanmaz. Testler
 * bunu "hata sonrası durum değişmedi" biçiminde doğrular; `expectException`
 * kullanılan yerlerde iddialar `try/catch` içine alınmıştır çünkü
 * `expectException` sonrası satırlar çalışmaz.
 */
final class AtomicTransactionsTest extends TestCase
{
    private ItemRegistry $registry;
    private Inventory $inventory;

    protected function setUp(): void
    {
        $this->registry = new ItemRegistry();
        $this->inventory = new Inventory('', 27);
    }

    public function testItemRegistrationIsAtomic(): void
    {
        $this->registry->register('minecraft:stone', 1);

        try {
            $this->registry->register('minecraft:stone', 2);
            self::fail('Yinelenen kayit kabul edilmemeliydi');
        } catch (PlayerException) {
            // yalnızca ilk kayıt kaldı
            self::assertSame(1, $this->registry->count());
            self::assertSame(1, $this->registry->get('minecraft:stone')->networkId());
        }
    }

    public function testInventorySlotOperationIsAtomic(): void
    {
        $this->inventory->setSlot(0, new ItemStack('minecraft:diamond', 10));

        try {
            $this->inventory->setSlot(27, new ItemStack('minecraft:air', 1));
            self::fail('Sinir disi yuva kabul edilmemeliydi');
        } catch (PlayerException) {
            self::assertSame(10, $this->inventory->getSlot(0)->count());
            self::assertSame(10, $this->inventory->count());
        }
    }

    public function testInventoryTransferIsAtomic(): void
    {
        $this->inventory->setSlot(0, new ItemStack('minecraft:diamond', 10));
        $this->inventory->setSlot(1, new ItemStack('minecraft:iron_ingot', 16));

        try {
            $this->inventory->transfer(0, 36); // geçersiz hedef yuva
            self::fail('Gecersiz hedef yuva kabul edilmemeliydi');
        } catch (PlayerException) {
            self::assertSame(10, $this->inventory->getSlot(0)->count());
            self::assertSame(16, $this->inventory->getSlot(1)->count());
        }
    }

    public function testRollbackOnException(): void
    {
        $this->inventory->setSlot(0, new ItemStack('minecraft:diamond', 10));
        $this->inventory->setSlot(1, new ItemStack('minecraft:diamond', 5));

        try {
            $this->inventory->setSlot(27, new ItemStack('minecraft:air', 1));
            self::fail('Sinir disi yuva kabul edilmemeliydi');
        } catch (PlayerException) {
            self::assertSame(10, $this->inventory->getSlot(0)->count());
            self::assertSame(5, $this->inventory->getSlot(1)->count());
        }
    }

    public function testRollbackPreservesCount(): void
    {
        $this->inventory->setSlot(0, new ItemStack('minecraft:diamond', 10));

        try {
            $this->inventory->setSlot(-1, new ItemStack('minecraft:air', 1));
            self::fail('Negatif yuva kabul edilmemeliydi');
        } catch (PlayerException) {
            self::assertSame(10, $this->inventory->count());
            self::assertSame(10, $this->inventory->getSlot(0)->count());
        }
    }

    public function testItemStackValidationIsAtomic(): void
    {
        $this->expectException(PlayerException::class);
        new ItemStack('minecraft:diamond', 0);
    }

    public function testTransactionCommitsAllOrNothing(): void
    {
        $this->inventory->setSlot(0, new ItemStack('minecraft:diamond', 10));

        $transaction = new InventoryTransaction($this->inventory);
        $transaction->remove(0, 5);
        $transaction->setSlot(1, new ItemStack('minecraft:diamond', 5));

        self::assertTrue($transaction->commit());
        self::assertSame(5, $this->inventory->getSlot(0)->count());
        self::assertSame(5, $this->inventory->getSlot(1)->count());
    }

    public function testTransactionRollsBackOnValidationError(): void
    {
        $this->inventory->setSlot(0, new ItemStack('minecraft:diamond', 10));

        $transaction = new InventoryTransaction($this->inventory);
        $transaction->remove(0, 5);
        $transaction->remove(0, 999); // imkânsız: geri alma gerekir

        try {
            $transaction->commit();
            self::fail('Transaction gecersiz islemi kabul etmemeliydi');
        } catch (PlayerException) {
            self::assertSame(10, $this->inventory->getSlot(0)->count());
            self::assertSame(10, $this->inventory->count());
        }
    }

    public function testDuplicateItemIdIsRejected(): void
    {
        $this->registry->register('minecraft:diamond_sword', 264);

        try {
            $this->registry->register('minecraft:diamond_sword', 265);
            self::fail('Yinelenen esya kimligi kabul edilmemeliydi');
        } catch (PlayerException) {
            self::assertSame(1, $this->registry->count());
        }
    }
}
