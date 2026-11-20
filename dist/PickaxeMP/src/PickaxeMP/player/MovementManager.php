<?php

declare(strict_types=1);

namespace PickaxeMP\player;

use PickaxeMP\protocol\packet\packets\PlayerAuthInputPacket;
use PickaxeMP\utils\Logger;

/**
 * Phase 5 — oyuncu hareketi ve pozisyon validasyonu.
 *
 * PlayerAuthInput paketlerini alır, hareket vektörünü doğrular, hız sınırlarını kontrol eder
 * ve oyuncu pozisyonunu günceller.
 */
final class MovementManager
{
    public function __construct(
        private readonly Logger $logger,
        private readonly PlayerPositionSync $positionSync
    ) {
    }

    /**
     * PlayerAuthInput'u işler.
     *
     * @param callable(\PickaxeMP\protocol\packet\PacketInterface): void $send
     *        Pozisyon düzeltmesini taşıyacak gerçek gönderim yolu
     *
     * @throws PlayerException geçersiz hareket veya durum
     */
    public function handle(Player $player, PlayerAuthInputPacket $input, callable $send): void
    {
        if ($player->state() !== PlayerState::Spawned) {
            throw PlayerException::invalidState(
                $player->state()->value,
                PlayerState::Spawned->value
            );
        }

        // Delta ve hız sınırlaması
        $dx = $input->posDeltaX;
        $dy = $input->posDeltaY;
        $dz = $input->posDeltaZ;

        $maxDelta = 10.0;
        $maxSpeed = 8.0;

        $distance = sqrt($dx*$dx + $dy*$dy + $dz*$dz);
        if (abs($dx) > $maxDelta || abs($dy) > $maxDelta || abs($dz) > $maxDelta || $distance > $maxSpeed) {
            $this->logger->warning('Hareket delta/speed sınırı aşıldı.', [
                'player' => $player->identity->displayName,
                'dx' => $dx,
                'dy' => $dy,
                'dz' => $dz,
                'distance' => $distance,
            ]);
            return;
        }

        // Pitch/yaw sınırlama
        $pitch = max(-90.0, min(90.0, $input->pitch));
        $yaw = fmod($input->yaw, 360.0);
        if ($yaw < 0) $yaw += 360.0;

        $newX = $player->x() + $dx;
        $newY = $player->y() + $dy;
        $newZ = $player->z() + $dz;

        $player->setPosition($newX, $newY, $newZ);
        $player->setRotation($pitch, $yaw);

        // Pozisyon değişikliğini istemciye bildir. Gönderim yolu dışarıdan
        // verilir: bu sınıf taşıyıcıyı bilmez.
        $this->positionSync->sendUpdate($player, $send);
    }
}
