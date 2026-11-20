<?php

declare(strict_types=1);

namespace PickaxeMP\player;

/**
 * Oyuncu başına hayatta kalma zamanlayıcıları.
 *
 * Phase 9 açlık zincirini kurdu ama açlık 0'da hiçbir sonuç üretmiyordu.
 * Bu sınıf iki kuralı uygular:
 *
 * - **Açlık 0:** her {@see STARVATION_INTERVAL} tick'te 1 hasar.
 * - **Açlık >= 18 ve can dolu değil:** her {@see REGEN_INTERVAL} tick'te 1 can.
 *
 * Zamanlayıcılar koşul bozulunca sıfırlanır; böylece "79 tick biriktir, bir
 * anlık yemek yiyip hemen iyileş" gibi durumlar oluşmaz. Yalnızca **spawn
 * edilmiş** oyuncu işlenir; ölü, giriş aşamasındaki veya ayrılmış oyuncuda
 * sayaçlar sıfırlanır. Sınıf ağ bilmez; sağlık değişimini tam sayı olarak
 * döndürür.
 */
final class PlayerSurvival
{
    /** Doğal yenilenme aralığı (tick): 20 TPS'te 4 saniye. */
    public const REGEN_INTERVAL = 80;

    /** Açlıktan hasar aralığı (tick): 20 TPS'te 4 saniye. */
    public const STARVATION_INTERVAL = 80;

    /** Doğal yenilenme için gereken en düşük açlık. */
    public const REGEN_MIN_HUNGER = 18;

    private int $regenTicks = 0;

    private int $starvationTicks = 0;

    /**
     * Bir oyun tick'i işler.
     *
     * @return int Sağlık değişimi: +1 iyileşme, -1 hasar, 0 değişmedi
     */
    public function tick(Player $player): int
    {
        if ($player->state() !== PlayerState::Spawned || $player->isDead()) {
            $this->regenTicks = 0;
            $this->starvationTicks = 0;

            return 0;
        }

        if ($player->attributes()->hunger() <= 0) {
            $this->regenTicks = 0;

            if (++$this->starvationTicks < self::STARVATION_INTERVAL) {
                return 0;
            }

            $this->starvationTicks = 0;

            return -$player->damage(1);
        }

        $this->starvationTicks = 0;

        if ($player->attributes()->hunger() >= self::REGEN_MIN_HUNGER
            && $player->health() < Player::MAX_HEALTH
        ) {
            if (++$this->regenTicks < self::REGEN_INTERVAL) {
                return 0;
            }

            $this->regenTicks = 0;

            return $player->heal(1);
        }

        $this->regenTicks = 0;

        return 0;
    }
}
