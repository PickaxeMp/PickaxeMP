<?php

declare(strict_types=1);

namespace PickaxeMP\entity;

use PickaxeMP\item\ItemStack;
use PickaxeMP\protocol\packet\packets\RemoveActorPacket;
use PickaxeMP\protocol\packet\types\Vec3;

/**
 * Basarili bir esya toplama isleminin sonucu.
 *
 * `slotUpdates`, envanterin **degisen** yuvalaridir (`null` = yuva bosaldi).
 * Cagiran katman her yuva icin `InventorySlot` gonderir. `removeActor`,
 * istemciye varligin kalktigini bildiren pakettir; toplayan oyuncuya da
 * gonderilmelidir (istemci tahmini ile gercek runtime kimligi ayni olmayabilir).
 *
 * `position` esyanin **toplandigi andaki** konumudur: toplama sesi oradan
 * calar. Varlik kaldirildiktan sonra konum bilinemeyecegi icin sonucun
 * icinde tasinir.
 */
final class ItemPickupResult
{
    /**
     * @param array<int, ItemStack|null> $slotUpdates
     */
    public function __construct(
        public readonly ItemStack $stack,
        public readonly RemoveActorPacket $removeActor,
        public readonly array $slotUpdates,
        public readonly Vec3 $position
    ) {
    }
}
