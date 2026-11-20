<?php

declare(strict_types=1);

namespace PickaxeMP\item;

use PickaxeMP\player\PlayerException;

/**
 * Belirli bir eşyadan belirli miktarda taşıyan yığın.
 *
 * ## Kurucu sınırı ile yığın sınırı neden farklı?
 *
 * Protokol `Stack size` alanını `uint16` olarak taşır, ancak bir yuvada
 * tutulabilecek gerçek sınır eşyanın kendi `maxStackSize` değeridir (64).
 * Kurucu **biçim** sınırını (1..`MAX_COUNT`) doğrular; `add()` ise **oyun**
 * sınırını uygular. Böylece ağdan gelen bir miktar sessizce kırpılmaz,
 * açıkça reddedilir.
 *
 * `ItemStack::empty()` miktarı 0 olan `minecraft:air` yığınıdır; boş yuvalar
 * `null` ile temsil edilir, `empty()` yalnızca "boşaltılmış" yığın içindir.
 */
final class ItemStack
{
    /** Biçimsel üst sınır — `add()`/`remove()` için değil, ayrıştırma içindir. */
    public const MAX_COUNT = 128;

    /** Boş yığının kimliği. */
    public const EMPTY_IDENTIFIER = 'minecraft:air';

    private int $count;

    public function __construct(
        private readonly string $type,
        int $count,
        private readonly int $maxStackSize = Item::DEFAULT_MAX_STACK_SIZE
    ) {
        if (preg_match('~^[a-z0-9_.-]+:[a-z0-9_./-]+$~', $type) !== 1) {
            throw PlayerException::invalidItemIdentifier($type);
        }

        if ($maxStackSize < 1 || $maxStackSize > self::MAX_COUNT) {
            throw PlayerException::invalidAmount('maxStackSize', $maxStackSize);
        }

        if ($count < 0 || $count > self::MAX_COUNT) {
            throw PlayerException::invalidAmount('count', $count);
        }

        // Miktar 0 yalnızca "boş" yığın için geçerlidir; yeni yığın daima dolu.
        if ($count === 0 && $type !== self::EMPTY_IDENTIFIER) {
            throw PlayerException::invalidAmount('count', $count);
        }

        $this->count = $count;
    }

    public static function empty(): self
    {
        return new self(self::EMPTY_IDENTIFIER, 0);
    }

    public function type(): string
    {
        return $this->type;
    }

    public function count(): int
    {
        return $this->count;
    }

    public function maxStackSize(): int
    {
        return $this->maxStackSize;
    }

    public function isEmpty(): bool
    {
        return $this->count === 0;
    }

    public function remainingSpace(): int
    {
        return max(0, $this->maxStackSize - $this->count);
    }

    public function canMergeWith(self $other): bool
    {
        return !$this->isEmpty()
            && !$other->isEmpty()
            && $this->type === $other->type
            && $this->maxStackSize === $other->maxStackSize;
    }

    /**
     * @throws PlayerException miktar geçersizse veya yığın sınırı aşılırsa
     */
    public function add(int $amount): void
    {
        if ($amount < 1) {
            throw PlayerException::invalidAmount('add', $amount);
        }

        $next = $this->count + $amount;

        if ($next > $this->maxStackSize) {
            throw PlayerException::invalidAmount('add', $amount);
        }

        $this->count = $next;
    }

    /**
     * @throws PlayerException miktar geçersizse veya elde yeterli yoksa
     */
    public function remove(int $amount): void
    {
        if ($amount < 1) {
            throw PlayerException::invalidAmount('remove', $amount);
        }

        if ($amount > $this->count) {
            throw PlayerException::invalidAmount('remove', $amount);
        }

        $this->count -= $amount;
    }

    public function withCount(int $count): self
    {
        return new self($this->type, $count, $this->maxStackSize);
    }

    public function copy(): self
    {
        return new self($this->type, $this->count, $this->maxStackSize);
    }

    public function describe(): string
    {
        return sprintf('%s x%d', $this->type, $this->count);
    }

    /**
     * Kalıcılık biçimi.
     *
     * @return array{type: string, count: int, max: int}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'count' => $this->count,
            'max' => $this->maxStackSize,
        ];
    }

    /**
     * Kalıcılık biçiminden geri yükler; bozuk veri `PlayerException` üretir.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $type = $data['type'] ?? null;
        $count = $data['count'] ?? null;
        $max = $data['max'] ?? Item::DEFAULT_MAX_STACK_SIZE;

        if (!is_string($type) || !is_int($count) || !is_int($max)) {
            throw PlayerException::invalidAmount('serialized', is_int($count) ? $count : -1);
        }

        return new self($type, $count, $max);
    }
}
