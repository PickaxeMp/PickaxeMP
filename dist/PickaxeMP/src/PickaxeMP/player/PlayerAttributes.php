<?php

declare(strict_types=1);

namespace PickaxeMP\player;

use PickaxeMP\protocol\packet\types\Attribute;

/**
 * Oyuncunun sayısal durumu: açlık, doygunluk, tükenme ve yürüme hızı.
 *
 * ## Neden ayrı sınıf
 *
 * `Player` zaten kimlik/konum/sağlık taşıyor. Açlık zinciri (tükenme →
 * doygunluk → açlık) kendi kurallarıyla test edilebilir olmalı; bu yüzden
 * ayrı ve **durumsuz olmayan** bir kayıt.
 *
 * ## Kurallar (Minecraft'a yaklaşım)
 *
 * - Yürüme her tick `WALK_EXHAUSTION_PER_TICK` tükenme ekler.
 * - Her `EXHAUSTION_PER_POINT` (4.0) tükenme bir puan harcar: önce doygunluk,
 *   doygunluk tükendiyse açlık azalır. Açlık 0'da durur; negatif olmaz.
 * - Bu sınıf **hasar vermez**; açlık 0 iken yıldız açlığı Phase 10'a bırakıldı.
 *
 * Sayılar şemadaki `minecraft:player.hunger` vb. öznitelik aralıklarıyla
 * uyumludur (0..20).
 */
final class PlayerAttributes
{
    public const MAX_HUNGER = 20;

    public const DEFAULT_HUNGER = 20;

    public const MAX_SATURATION = 20.0;

    public const DEFAULT_SATURATION = 5.0;

    /** Bir tükenme puanını harcamak için gereken tükenme. */
    public const EXHAUSTION_PER_POINT = 4.0;

    /** Yürüyerek tick başına tükenme (20 TPS'te ~0.2/sn). */
    public const WALK_EXHAUSTION_PER_TICK = 0.01;

    /** Varsayılan yürüme hızı (blok/tick). */
    public const DEFAULT_MOVEMENT_SPEED = 0.1;

    private int $hunger = self::DEFAULT_HUNGER;

    private float $saturation = self::DEFAULT_SATURATION;

    private float $exhaustion = 0.0;

    private float $movementSpeed = self::DEFAULT_MOVEMENT_SPEED;

    public function hunger(): int
    {
        return $this->hunger;
    }

    public function saturation(): float
    {
        return $this->saturation;
    }

    public function exhaustion(): float
    {
        return $this->exhaustion;
    }

    public function movementSpeed(): float
    {
        return $this->movementSpeed;
    }

    /**
     * Bir oyun tick'i işler.
     *
     * @return bool Açlık/doygunluk **değişti** mi? (yalnızca değişince
     *              `UpdateAttributes` yayınlanır)
     */
    public function tick(bool $moving): bool
    {
        if (!$moving) {
            return false;
        }

        return $this->consume(self::WALK_EXHAUSTION_PER_TICK);
    }

    /**
     * Tükenme ekler ve gerekirse doygunluk/açlık harcar.
     *
     * @return bool Doygunluk veya açlık değişti mi?
     */
    public function consume(float $amount): bool
    {
        if ($amount < 0 || !is_finite($amount)) {
            return false;
        }

        if ($amount === 0.0) {
            return false;
        }

        $beforeSaturation = $this->saturation;
        $beforeHunger = $this->hunger;

        $this->exhaustion += $amount;

        while ($this->exhaustion >= self::EXHAUSTION_PER_POINT) {
            $this->exhaustion -= self::EXHAUSTION_PER_POINT;

            if ($this->saturation >= 1.0) {
                $this->saturation = max(0.0, $this->saturation - 1.0);

                continue;
            }

            $this->saturation = 0.0;

            if ($this->hunger > 0) {
                $this->hunger--;
            }
        }

        return $this->saturation !== $beforeSaturation || $this->hunger !== $beforeHunger;
    }

    /**
     * Yemek yeme: açlık ve doygunluk ekler (üst sınırlara kırpılır).
     */
    public function eat(int $hunger, float $saturation = 0.0): void
    {
        if ($hunger < 0 || $saturation < 0) {
            return;
        }

        $this->hunger = min(self::MAX_HUNGER, $this->hunger + $hunger);
        $this->saturation = min(self::MAX_SATURATION, $this->saturation + $saturation);
    }

    /**
     * Yeniden doğma/ölüm sonrası başlangıç değerlerine döner.
     */
    public function reset(): void
    {
        $this->hunger = self::DEFAULT_HUNGER;
        $this->saturation = self::DEFAULT_SATURATION;
        $this->exhaustion = 0.0;
        $this->movementSpeed = self::DEFAULT_MOVEMENT_SPEED;
    }

    /**
     * `UpdateAttributes` için öznitelik listesi.
     *
     * Sağlık `Player`'dan gelir; burada kopyalanmaz ki iki kaynak
     * birbirinden ayrışmasın.
     *
     * @return list<Attribute>
     */
    public function attributeList(int $health, int $maxHealth = Player::MAX_HEALTH): array
    {
        return [
            new Attribute(
                name: Attribute::HEALTH,
                min: 0.0,
                max: (float) $maxHealth,
                current: (float) max(0, min($health, $maxHealth)),
                defaultMin: 0.0,
                defaultMax: (float) $maxHealth,
                default: (float) $maxHealth
            ),
            new Attribute(
                name: Attribute::HUNGER,
                min: 0.0,
                max: (float) self::MAX_HUNGER,
                current: (float) $this->hunger,
                defaultMin: 0.0,
                defaultMax: (float) self::MAX_HUNGER,
                default: (float) self::DEFAULT_HUNGER
            ),
            new Attribute(
                name: Attribute::SATURATION,
                min: 0.0,
                max: self::MAX_SATURATION,
                current: $this->saturation,
                defaultMin: 0.0,
                defaultMax: self::MAX_SATURATION,
                default: self::DEFAULT_SATURATION
            ),
            new Attribute(
                name: Attribute::EXHAUSTION,
                min: 0.0,
                max: self::EXHAUSTION_PER_POINT,
                current: $this->exhaustion,
                defaultMin: 0.0,
                defaultMax: self::EXHAUSTION_PER_POINT,
                default: 0.0
            ),
            new Attribute(
                name: Attribute::MOVEMENT,
                min: 0.0,
                max: 1.0,
                current: $this->movementSpeed,
                defaultMin: 0.0,
                defaultMax: 1.0,
                default: self::DEFAULT_MOVEMENT_SPEED
            ),
        ];
    }

    /**
     * Teşhis için özet.
     */
    public function describe(): string
    {
        return sprintf(
            'attributes(hunger=%d, saturation=%.2f, exhaustion=%.2f, speed=%.2f)',
            $this->hunger,
            $this->saturation,
            $this->exhaustion,
            $this->movementSpeed
        );
    }
}
