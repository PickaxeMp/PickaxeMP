<?php

declare(strict_types=1);

namespace PickaxeMP\player;

use PickaxeMP\protocol\packet\packets\MovePlayerPacket;
use PickaxeMP\utils\Logger;

/**
 * Phase 5 — oyuncu pozisyon senkronizasyonu.
 *
 * Sunucu otoritesiyle belirlenen pozisyonu istemciye MovePlayer paketiyle bildirir.
 */
final class PlayerPositionSync
{
    public function __construct(
        private readonly Logger $logger
    ) {
    }

    public function sendUpdate(Player $player, callable $send): void
    {
        $packet = new MovePlayerPacket(
            runtimeId: $player->runtimeId,
            x: $player->x(),
            y: $player->y(),
            z: $player->z(),
            pitch: $player->pitch(),
            yaw: $player->yaw(),
            headYaw: $player->yaw(),
            mode: MovePlayerPacket::MODE_TELEPORT
        );

        $send($packet);

        $this->logger->debug('Oyuncu pozisyonu senkronize edildi.', [
            'player' => $player->identity->displayName,
            'x' => $player->x(),
            'y' => $player->y(),
            'z' => $player->z(),
        ]);
    }
}
