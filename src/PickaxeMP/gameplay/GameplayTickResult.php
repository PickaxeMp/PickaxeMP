<?php

declare(strict_types=1);

namespace PickaxeMP\gameplay;

use PickaxeMP\protocol\packet\packets\MoveActorDeltaPacket;
use PickaxeMP\protocol\packet\packets\RemoveActorPacket;
use PickaxeMP\protocol\packet\types\Attribute;

/**
 * Tek bir oyun tick'inin **ağdan bağımsız** sonucu.
 *
 * `ConnectionPipeline` bu sonucu oturumlara dağıtır; `GameplayTicker` ağ
 * katmanını bilmez. Böylece tick mantığı gerçek soket olmadan test edilir.
 */
final class GameplayTickResult
{
    /**
     * @param int $time Tick sonunda dünya saati
     * @param int|null $timeToBroadcast `SetTime` yayınlanacaksa saat, yoksa `null`
     * @param list<RemoveActorPacket> $removedItems Süresi dolan eşya varlıkları
     * @param array<string, list<Attribute>> $attributeUpdates Oturum kimliği → yeni öznitelikler
     * @param list<MoveActorDeltaPacket> $movedItems Hareket eden eşya varlıkları
     * @param array<string, int> $healthUpdates Oturum kimliği → yeni sağlık
     * @param array<string, int> $deaths Bu tick'te ölen oturumlar: oturum kimliği → varlık kimliği
     */
    public function __construct(
        public readonly int $time,
        public readonly ?int $timeToBroadcast,
        public readonly array $removedItems,
        public readonly array $attributeUpdates,
        public readonly array $movedItems = [],
        public readonly array $healthUpdates = [],
        public readonly array $deaths = []
    ) {
    }

    public function hasWork(): bool
    {
        return $this->timeToBroadcast !== null
            || $this->removedItems !== []
            || $this->attributeUpdates !== []
            || $this->movedItems !== []
            || $this->healthUpdates !== []
            || $this->deaths !== [];
    }
}
