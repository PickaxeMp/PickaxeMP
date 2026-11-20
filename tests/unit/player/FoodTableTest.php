<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\player;

use PHPUnit\Framework\TestCase;
use PickaxeMP\player\FoodNutrition;
use PickaxeMP\player\FoodTable;
use PickaxeMP\player\PlayerAttributes;
use PickaxeMP\player\PlayerException;

/**
 * Yenilebilir eşya tablosu ve besin değeri sözleşmesi.
 *
 * Kritik kurallar:
 *  - Bilinmeyen eşya `null` döner; "sıfır besin" uydurulmaz.
 *  - Tablodaki her değer protokolün açlık/doygunluk aralığındadır.
 *  - Geçersiz besin değeri oluşturulamaz.
 */
final class FoodTableTest extends TestCase
{
    public function testDefaultTableKnowsApple(): void
    {
        $nutrition = FoodTable::withDefaultFoods()->nutritionFor('minecraft:apple');

        self::assertNotNull($nutrition);
        self::assertSame(4, $nutrition->hunger);
        self::assertEqualsWithDelta(2.4, $nutrition->saturation, 1e-9);
    }

    public function testDefaultTableKnowsBread(): void
    {
        $nutrition = FoodTable::withDefaultFoods()->nutritionFor('minecraft:bread');

        self::assertNotNull($nutrition);
        self::assertSame(5, $nutrition->hunger);
        self::assertEqualsWithDelta(6.0, $nutrition->saturation, 1e-9);
    }

    public function testUnknownItemIsNotFood(): void
    {
        $table = FoodTable::withDefaultFoods();

        self::assertNull($table->nutritionFor('minecraft:stone'));
        self::assertNull($table->nutritionFor('minecraft:not_a_real_food'));
    }

    public function testEveryDefaultFoodIsWithinProtocolBounds(): void
    {
        $table = FoodTable::withDefaultFoods();

        self::assertGreaterThanOrEqual(20, $table->count());

        foreach ($table->all() as $identifier => $nutrition) {
            self::assertMatchesRegularExpression(
                '~^[a-z0-9_.-]+:[a-z0-9_./-]+$~',
                $identifier,
                'Gecersiz esya kimligi: ' . $identifier
            );
            self::assertGreaterThanOrEqual(1, $nutrition->hunger);
            self::assertLessThanOrEqual(PlayerAttributes::MAX_HUNGER, $nutrition->hunger);
            self::assertGreaterThanOrEqual(0.0, $nutrition->saturation);
            self::assertLessThanOrEqual(PlayerAttributes::MAX_SATURATION, $nutrition->saturation);
        }
    }

    public function testNutritionRejectsZeroHunger(): void
    {
        $this->expectException(PlayerException::class);

        new FoodNutrition(0, 0.0);
    }

    public function testNutritionRejectsNegativeSaturation(): void
    {
        $this->expectException(PlayerException::class);

        new FoodNutrition(1, -0.1);
    }

    public function testNutritionRejectsNonFiniteSaturation(): void
    {
        $this->expectException(PlayerException::class);

        new FoodNutrition(1, INF);
    }
}
