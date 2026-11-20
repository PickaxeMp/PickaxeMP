<?php

declare(strict_types=1);

namespace PickaxeMP\player;

use PickaxeMP\item\ItemStack;

final class FoodConsumptionResult
{
    public function __construct(
        public readonly int $slot,
        public readonly ?ItemStack $remaining,
        public readonly FoodNutrition $nutrition,
        public readonly string $consumedType,
    ) {
    }

    public static function emptySlot(int $slot, FoodNutrition $nutrition, string $consumedType): self
    {
        return new self($slot, null, $nutrition, $consumedType);
    }
}
