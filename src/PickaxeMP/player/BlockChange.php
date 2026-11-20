<?php

declare(strict_types=1);

namespace PickaxeMP\player;

use PickaxeMP\protocol\packet\packets\UpdateBlockPacket;
use PickaxeMP\protocol\packet\types\BlockPos;

/**
 * Tek bir blok değişikliği.
 *
 * `World::setBlock()` çağrısının sonucudur; yayına dönüşmesi çağıranın
 * işidir. Böylece mutasyon mantığı taşıyıcı/paket katmanını bilmez.
 */
final class BlockChange
{
    public function __construct(
        public readonly int $x,
        public readonly int $y,
        public readonly int $z,
        public readonly int $networkId
    ) {
    }

    public function position(): BlockPos
    {
        return new BlockPos($this->x, $this->y, $this->z);
    }

    public function toPacket(): UpdateBlockPacket
    {
        return new UpdateBlockPacket($this->position(), $this->networkId);
    }
}
