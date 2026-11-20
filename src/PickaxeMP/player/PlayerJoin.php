<?php

declare(strict_types=1);

namespace PickaxeMP\player;

use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\session\Session;

/**
 * Hazırlanmış dünya girişinin sonucu.
 *
 * Paketler önce taşıyıcıya gönderilir; gönderim tamamlanınca
 * `PlayerJoinService::complete()` çağrılır ve oturum `Playing` durumuna geçer.
 */
final class PlayerJoin
{
    /**
     * @param list<PacketInterface> $packets
     */
    public function __construct(
        public readonly Session $session,
        public readonly Player $player,
        public readonly array $packets
    ) {
    }
}
