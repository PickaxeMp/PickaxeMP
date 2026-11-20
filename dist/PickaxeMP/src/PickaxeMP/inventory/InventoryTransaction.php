<?php

declare(strict_types=1);

namespace PickaxeMP\inventory;

use PickaxeMP\item\ItemStack;
use PickaxeMP\player\PlayerException;

/**
 * Doğrula-sonra-uygula işlem yığını.
 *
 * ## Neden?
 *
 * Bir istemci paketi genelde **birden çok** yuva değiştirir (taşı, böl,
 * birleştir). Tek tek uygulanırken ortadaki bir hata, dünyada "eşya kopyalama"
 * veya "eşya kaybı" üretir. Bu sınıf tüm işlemleri gerçek envanterin bir
 * kopyası üzerinde dener; hepsi geçerse uygular, biri bile geçmezse **hiçbiri**
 * uygulanmaz ve istisna çağırana döner.
 *
 * ## Kullanım
 *
 * ```php
 * $tx = new InventoryTransaction($inventory);
 * $tx->remove(0, 5);
 * $tx->setSlot(1, $stack);
 * $tx->commit();           // hata varsa PlayerException + değişiklik yok
 * ```
 */
final class InventoryTransaction
{
    /** @var list<array{op: string, args: array<int, int|ItemStack|null>}> */
    private array $operations = [];

    private bool $committed = false;

    public function __construct(private readonly Inventory $inventory)
    {
    }

    public function setSlot(int $slot, ?ItemStack $stack): self
    {
        $this->operations[] = ['op' => 'set', 'args' => [$slot, $stack]];

        return $this;
    }

    public function clearSlot(int $slot): self
    {
        return $this->setSlot($slot, null);
    }

    public function swap(int $first, int $second): self
    {
        $this->operations[] = ['op' => 'swap', 'args' => [$first, $second]];

        return $this;
    }

    public function transfer(int $from, int $to): self
    {
        $this->operations[] = ['op' => 'transfer', 'args' => [$from, $to]];

        return $this;
    }

    public function remove(int $slot, int $amount): self
    {
        $this->operations[] = ['op' => 'remove', 'args' => [$slot, $amount]];

        return $this;
    }

    public function operationCount(): int
    {
        return count($this->operations);
    }

    /**
     * Tüm işlemleri atomik uygular.
     *
     * @return bool taşıma işlemlerinden biri "yer yok" nedeniyle başarısız
     *              olduysa `false` — bu bir **hata değildir**, ancak o durumda
     *              hiçbir işlem uygulanmaz
     *
     * @throws PlayerException doğrulama hatasında; envanter değişmez
     */
    public function commit(): bool
    {
        if ($this->committed) {
            throw PlayerException::invalidState('committed', 'open');
        }

        $snapshot = $this->inventory->toArray();
        $success = true;

        try {
            foreach ($this->operations as $operation) {
                $ok = $this->apply($operation['op'], $operation['args']);

                if (!$ok) {
                    $success = false;

                    break;
                }
            }
        } catch (PlayerException $exception) {
            // Geri alma: kısmi mutasyon bırakma.
            $this->inventory->loadFromArray($snapshot);

            throw $exception;
        }

        if (!$success) {
            $this->inventory->loadFromArray($snapshot);

            return false;
        }

        $this->committed = true;

        return true;
    }

    /**
     * @param array<int, int|ItemStack|null> $args
     */
    private function apply(string $op, array $args): bool
    {
        switch ($op) {
            case 'set':
                /** @var int $slot */
                $slot = $args[0];
                /** @var ItemStack|null $stack */
                $stack = $args[1];
                $this->inventory->setSlot($slot, $stack);

                return true;

            case 'swap':
                /** @var int $first */
                $first = $args[0];
                /** @var int $second */
                $second = $args[1];
                $this->inventory->swapSlots($first, $second);

                return true;

            case 'transfer':
                /** @var int $from */
                $from = $args[0];
                /** @var int $to */
                $to = $args[1];

                return $this->inventory->transfer($from, $to);

            case 'remove':
                /** @var int $slot */
                $slot = $args[0];
                /** @var int $amount */
                $amount = $args[1];
                $this->inventory->removeFromSlot($slot, $amount);

                return true;

            default:
                throw PlayerException::invalidState($op, 'known_operation');
        }
    }
}
