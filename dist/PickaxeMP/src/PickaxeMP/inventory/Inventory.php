<?php

declare(strict_types=1);

namespace PickaxeMP\inventory;

use PickaxeMP\item\ItemStack;
use PickaxeMP\player\PlayerException;

/**
 * Sabit boyutlu yuva dizisi.
 *
 * ## Sözleşme
 *
 * - `size()` yuva **sayısıdır**, `count()` ise **toplam eşya** sayısıdır.
 *   İkisi karıştırılırsa dolu/boş mantığı bozulur; bu yüzden ayrı adlar.
 * - `isFull()` "hiç boş yuva yok" demektir. Tek bir 64'lük yığın, 27 yuvalı
 *   bir envanteri doldurmaz.
 * - Her mutasyon `PlayerException` ile **önce** doğrulanır; hata hâlinde
 *   hiçbir yuva değişmez (kısmi mutasyon yok).
 * - `version()` her başarılı mutasyonda artar. Paylaşılan kaplar (sandık,
 *   fırın) bu sayacı kilit/yarış denetimi için kullanır.
 *
 * Boş yuva `null`'dır; `ItemStack::empty()` yalnızca "boşaltılmış" yığın
 * nesnesini temsil eder ve yuvaya yazılmaz.
 */
class Inventory
{
    /** @var array<int, ItemStack|null> */
    protected array $slots = [];

    private int $version = 0;

    public function __construct(
        protected readonly string $storageRoot,
        protected readonly int $size = 27
    ) {
        if ($size < 1) {
            throw PlayerException::invalidSlot($size, $size);
        }

        $this->slots = array_fill(0, $size, null);
    }

    public function size(): int
    {
        return $this->size;
    }

    public function storageRoot(): string
    {
        return $this->storageRoot;
    }

    public function version(): int
    {
        return $this->version;
    }

    /**
     * Toplam eşya sayısı (yığın miktarlarının toplamı).
     */
    public function count(): int
    {
        $total = 0;

        foreach ($this->slots as $stack) {
            $total += $stack?->count() ?? 0;
        }

        return $total;
    }

    /**
     * Dolu yuva sayısı.
     */
    public function usedSlots(): int
    {
        $used = 0;

        foreach ($this->slots as $stack) {
            if ($stack !== null && !$stack->isEmpty()) {
                $used++;
            }
        }

        return $used;
    }

    public function isEmpty(): bool
    {
        return $this->usedSlots() === 0;
    }

    public function isFull(): bool
    {
        foreach ($this->slots as $stack) {
            if ($stack === null || $stack->isEmpty()) {
                return false;
            }
        }

        return true;
    }

    public function getSlot(int $slot): ?ItemStack
    {
        $this->assertSlot($slot);

        return $this->slots[$slot];
    }

    public function setSlot(int $slot, ?ItemStack $stack): void
    {
        $this->assertSlot($slot);

        if ($stack !== null && $stack->isEmpty()) {
            $stack = null;
        }

        $this->slots[$slot] = $stack;
        $this->version++;
    }

    public function clearSlot(int $slot): void
    {
        $this->setSlot($slot, null);
    }

    public function swapSlots(int $first, int $second): void
    {
        $this->assertSlot($first);
        $this->assertSlot($second);

        if ($first === $second) {
            return;
        }

        $temporary = $this->slots[$first];
        $this->slots[$first] = $this->slots[$second];
        $this->slots[$second] = $temporary;
        $this->version++;
    }

    /**
     * Kaynaktaki yığını hedefe taşır (gerekiyorsa birleştirir).
     *
     * Hiçbir şey taşınamıyorsa `false` döner ve **hiçbir yuva değişmez**.
     */
    public function transfer(int $from, int $to): bool
    {
        $this->assertSlot($from);
        $this->assertSlot($to);

        if ($from === $to) {
            return false;
        }

        $source = $this->slots[$from];

        if ($source === null || $source->isEmpty()) {
            return false;
        }

        $target = $this->slots[$to];

        if ($target === null) {
            $this->slots[$to] = $source;
            $this->slots[$from] = null;
            $this->version++;

            return true;
        }

        if (!$target->canMergeWith($source)) {
            return false;
        }

        $space = $target->remainingSpace();

        if ($space <= 0) {
            return false;
        }

        $moved = min($space, $source->count());

        $target->add($moved);
        $source->remove($moved);

        if ($source->isEmpty()) {
            $this->slots[$from] = null;
        }

        $this->version++;

        return true;
    }

    /**
     * Yuvadan belirtilen miktarı düşer ve düşen kısmın kopyasını döner.
     */
    public function removeFromSlot(int $slot, int $amount): ItemStack
    {
        $this->assertSlot($slot);

        $stack = $this->slots[$slot];

        if ($stack === null || $stack->isEmpty()) {
            throw PlayerException::invalidAmount('removeFromSlot', $amount);
        }

        if ($amount < 1 || $amount > $stack->count()) {
            throw PlayerException::invalidAmount('removeFromSlot', $amount);
        }

        $removed = $stack->withCount($amount);
        $stack->remove($amount);

        if ($stack->isEmpty()) {
            $this->slots[$slot] = null;
        }

        $this->version++;

        return $removed;
    }

    /**
     * Boş yuva arar; yoksa `null`.
     */
    public function firstEmptySlot(): ?int
    {
        foreach ($this->slots as $index => $stack) {
            if ($stack === null || $stack->isEmpty()) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @return list<ItemStack|null>
     */
    public function contents(): array
    {
        return array_values($this->slots);
    }

    /**
     * @return array{size: int, slots: list<array<string, mixed>|null>}
     */
    public function toArray(): array
    {
        return [
            'size' => $this->size,
            'slots' => array_map(
                static fn (?ItemStack $stack): ?array => $stack?->toArray(),
                array_values($this->slots)
            ),
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws PlayerException veri boyutla uyuşmazsa
     */
    public function loadFromArray(array $data): void
    {
        $slots = $data['slots'] ?? null;

        if (!is_array($slots) || count($slots) !== $this->size) {
            throw PlayerException::invalidSlot(count(is_array($slots) ? $slots : []), $this->size);
        }

        $restored = [];

        foreach (array_values($slots) as $index => $entry) {
            if ($entry === null) {
                $restored[$index] = null;

                continue;
            }

            if (!is_array($entry)) {
                throw PlayerException::invalidSlot($index, $this->size);
            }

            $restored[$index] = ItemStack::fromArray($entry);
        }

        $this->slots = $restored;
        $this->version++;
    }

    protected function assertSlot(int $slot): void
    {
        if ($slot < 0 || $slot >= $this->size) {
            throw PlayerException::invalidSlot($slot, $this->size);
        }
    }
}
