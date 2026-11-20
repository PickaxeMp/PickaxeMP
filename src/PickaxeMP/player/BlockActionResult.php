<?php

declare(strict_types=1);

namespace PickaxeMP\player;

use PickaxeMP\item\ItemStack;

/**
 * Bir blok eyleminin sonucu.
 *
 * `changes` yayımlanacak dünya değişiklikleridir. `inventorySlot` doluysa,
 * eylemi yapan oyuncuya o yuvanın yeni hâli (`inventoryStack`, boşsa `null`)
 * `InventorySlot` ile bildirilmelidir. `sounds` içindeki her olay görüş
 * mesafesine yayınlanır; tek pakette birden çok blok kırılabildiği için
 * liste tutulur.
 */
final class BlockActionResult
{
    /**
     * @param list<BlockChange> $changes
     * @param list<BlockSound> $sounds
     */
    public function __construct(
        public readonly array $changes = [],
        public readonly ?int $inventorySlot = null,
        public readonly ?ItemStack $inventoryStack = null,
        public readonly array $sounds = []
    ) {
    }

    /**
     * İlk ses olayı (test ve log kolaylığı).
     */
    public function sound(): ?BlockSound
    {
        return $this->sounds[0] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->changes === [];
    }

    public static function none(): self
    {
        return new self();
    }
}
