<?php

declare(strict_types=1);

namespace PickaxeMP\player;

final class FoodNutrition
{
    public function __construct(
        public readonly int $hunger,
        public readonly float $saturation
    ) {
        if ($hunger <= 0) {
            throw new PlayerException("Besin degeri (hunger) 0'dan buyuk olmali.");
        }

        if (!is_finite($saturation) || $saturation < 0.0) {
            throw new PlayerException("Doygunluk (saturation) negatif ya da sonsuz olamaz.");
        }
    }

    public static function bread(): self { return new self(5, 6.0); }
    public static function apple(): self { return new self(4, 2.4); }
    public static function steak(): self { return new self(8, 12.8); }
    public static function breadSlice(): self { return new self(2, 1.0); }
}
