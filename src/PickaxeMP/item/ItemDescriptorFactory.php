<?php

declare(strict_types=1);

namespace PickaxeMP\item;

use PickaxeMP\protocol\packet\types\NetworkItemStackDescriptor;

/**
 * `ItemStack` → ağ tanımlayıcısı (`NetworkItemStackDescriptor`).
 *
 * ## Neden tek yer
 *
 * Aynı dönüşüm üç yerde gerekiyordu (`PlayerAuthInput` envanter
 * bildirimleri, `ItemActorManager` yere düşen eşya, `MobEquipment`
 * yayını). Kopyalanmış üç sürüm, "kayıtlı olmayan eşya" davranışının
 * birinde değişip diğerlerinde eski kalmasına yol açar: istemci bilinmeyen
 * bir sayısal kimlik alır ve eşyayı "boş" gösterir.
 *
 * ## Sözleşme
 *
 * - Boş yığın → `NetworkItemStackDescriptor::empty()` (kimlik 0, sayı 0).
 * - Kayıtlı olmayan eşya → `null`: çağıran **bildirim göndermemeli**dir.
 *   Sunucu-istemci ayrışması yerine sessiz atlama yeğlenir.
 */
final class ItemDescriptorFactory
{
    public static function forStack(
        ?ItemStack $stack,
        ?ItemRegistry $registry
    ): ?NetworkItemStackDescriptor {
        if ($stack === null || $stack->isEmpty()) {
            return NetworkItemStackDescriptor::empty();
        }

        if ($registry === null || !$registry->has($stack->type())) {
            return null;
        }

        return new NetworkItemStackDescriptor(
            id: $registry->get($stack->type())->networkId(),
            count: $stack->count()
        );
    }
}
