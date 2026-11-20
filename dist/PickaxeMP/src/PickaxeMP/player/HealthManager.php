<?php

declare(strict_types=1);

namespace PickaxeMP\player;

use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\packets\ActorEventPacket;
use PickaxeMP\protocol\packet\packets\LevelSoundEventPacket;
use PickaxeMP\protocol\packet\packets\SetHealthPacket;
use PickaxeMP\protocol\packet\types\ActorEventType;
use PickaxeMP\protocol\packet\types\Vec3;
use PickaxeMP\utils\Logger;

/**
 * Oyuncu sağlığını **oyuncu başına** yönetir.
 *
 * Phase 5'te bu sınıf tek bir `$health` alanını paylaşıyordu; çok oyunculu
 * sunucuda A oyuncusunun hasarı B'yi öldürürdü. Sağlık artık `Player`
 * nesnesindedir; bu sınıf yalnızca kural uygular ve istemciye `SetHealth`
 * gönderir.
 */
final class HealthManager
{
    public function __construct(
        private readonly Logger $logger
    ) {
    }

    /**
     * Hasar uygular ve sağlığı istemciye bildirir.
     *
     * @param callable(PacketInterface): void $send
     *
     * @return int Gerçekte düşen sağlık (0 = uygulanmadı)
     */
    public function damage(Player $player, int $amount, callable $send): int
    {
        $applied = $player->damage($amount);

        if ($applied === 0) {
            return 0;
        }

        $send(new SetHealthPacket($player->health()));

        if ($player->health() === 0) {
            // Olumde yalnizca `Death` gonderilir: `Hurt` + `Death` birlikte
            // gonderilirse istemci once vurulma sonra olum animasyonu oynatir.
            $send(new ActorEventPacket($player->runtimeId, ActorEventType::Death));
            $send($this->sound('death', $player));

            $this->logger->info('Oyuncu oldu.', [
                'player' => $player->identity->displayName,
                'cause' => 'damage',
            ]);
        } else {
            $send(new ActorEventPacket($player->runtimeId, ActorEventType::Hurt));
            $send($this->sound('hurt', $player));
        }

        return $applied;
    }

    /**
     * Sağlık verir ve istemciye bildirir.
     *
     * @param callable(PacketInterface): void $send
     *
     * @return int Gerçekte eklenen sağlık
     */
    public function heal(Player $player, int $amount, callable $send): int
    {
        $applied = $player->heal($amount);

        if ($applied === 0) {
            return 0;
        }

        $send(new SetHealthPacket($player->health()));

        return $applied;
    }

    /**
     * Mevcut sağlığı istemciye gönderir (girişte / eşitlemede).
     *
     * @param callable(PacketInterface): void $send
     */
    public function sendCurrent(Player $player, callable $send): void
    {
        $send(new SetHealthPacket($player->health()));
    }

    public function getHealth(Player $player): int
    {
        return $player->health();
    }

    /**
     * Oyuncunun konumunda çalan geri bildirim sesi.
     *
     * `isGlobal: true`: ses, görüş mesafesindeki tüm istemcilere gider;
     * aksi hâlde yalnızca oyuncunun kendisi duyardı.
     */
    private function sound(string $event, Player $player): LevelSoundEventPacket    {
        return new LevelSoundEventPacket(
            $event,
            new Vec3($player->x(), $player->y(), $player->z()),
            actorIdentifier: 'minecraft:player',
            isGlobal: true,
            actorUniqueId: $player->entityId
        );
    }
}
