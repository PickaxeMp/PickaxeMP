<?php

declare(strict_types=1);

namespace PickaxeMP\player;

/**
 * Yenilebilir eşya tablosu: kimlik => {@see FoodNutrition}.
 *
 * Tablo **veri**dir, davranış değil: hangi eşyanın ne kadar açlık/doygunluk
 * verdiğini bilir. Tüketim kuralları {@see FoodConsumptionManager} içindedir.
 *
 * Bilinmeyen eşya için `null` döner — "sıfır besin" uydurulmaz. Böylece
 * kayıtsız eşya yemeğe çalışıldığında sessizce hiçbir şey olmaz.
 */
final class FoodTable
{
    /**
     * Varsayılan tablo. Değerler `hunger` (0..20) ve `saturation` (0..20).
     *
     * Kaynak: Minecraft (Bedrock) eşya besin değerleri. Doygunluk sütunu
     * protokolün `MAX_SATURATION` sınırını aşmaz.
     *
     * @var array<string, array{0: int, 1: float}>
     */
    private const DEFAULT_FOODS = [
        'minecraft:apple' => [4, 2.4],
        'minecraft:baked_potato' => [5, 6.0],
        'minecraft:beef' => [3, 1.8],
        'minecraft:beetroot' => [1, 1.2],
        'minecraft:beetroot_soup' => [6, 7.2],
        'minecraft:bread' => [5, 6.0],
        'minecraft:cake' => [2, 0.4],
        'minecraft:carrot' => [3, 3.6],
        'minecraft:chicken' => [2, 1.2],
        'minecraft:chorus_fruit' => [4, 2.4],
        'minecraft:cod' => [2, 0.4],
        'minecraft:cooked_beef' => [8, 12.8],
        'minecraft:cooked_chicken' => [6, 7.2],
        'minecraft:cooked_cod' => [5, 6.0],
        'minecraft:cooked_mutton' => [6, 9.6],
        'minecraft:cooked_porkchop' => [8, 12.8],
        'minecraft:cooked_rabbit' => [5, 6.0],
        'minecraft:cooked_salmon' => [6, 9.6],
        'minecraft:cookie' => [2, 0.4],
        'minecraft:dried_kelp' => [1, 0.6],
        'minecraft:enchanted_golden_apple' => [4, 9.6],
        'minecraft:golden_apple' => [4, 9.6],
        'minecraft:golden_carrot' => [6, 14.4],
        'minecraft:honey_bottle' => [6, 1.2],
        'minecraft:melon_slice' => [2, 1.2],
        'minecraft:mushroom_stew' => [6, 7.2],
        'minecraft:mutton' => [2, 1.2],
        'minecraft:porkchop' => [3, 1.8],
        'minecraft:potato' => [1, 0.6],
        'minecraft:pufferfish' => [1, 0.2],
        'minecraft:rabbit' => [3, 1.8],
        'minecraft:rabbit_stew' => [10, 12.0],
        'minecraft:rotten_flesh' => [4, 0.8],
        'minecraft:salmon' => [2, 0.4],
        'minecraft:spider_eye' => [2, 3.2],
        'minecraft:sweet_berries' => [2, 0.4],
        'minecraft:tropical_fish' => [1, 0.2],
    ];

    /** @param array<string, FoodNutrition> $foods */
    private function __construct(private readonly array $foods)
    {
    }

    public static function withDefaultFoods(): self
    {
        $foods = [];

        foreach (self::DEFAULT_FOODS as $identifier => [$hunger, $saturation]) {
            $foods[$identifier] = new FoodNutrition($hunger, $saturation);
        }

        return new self($foods);
    }

    /**
     * @param array<string, FoodNutrition|array{0: int, 1: float}> $foods
     */
    public static function fromArray(array $foods): self
    {
        $normalized = [];

        foreach ($foods as $identifier => $nutrition) {
            if (preg_match('~^[a-z0-9_.-]+:[a-z0-9_./-]+$~', $identifier) !== 1) {
                throw new PlayerException("Gecersiz esya kimligi: {$identifier}");
            }

            $normalized[$identifier] = $nutrition instanceof FoodNutrition
                ? $nutrition
                : new FoodNutrition($nutrition[0], $nutrition[1]);
        }

        return new self($normalized);
    }

    public function nutritionFor(string $identifier): ?FoodNutrition
    {
        return $this->foods[$identifier] ?? null;
    }

    /**
     * İçecek (drink) sesi gerektiren eşyalar.
     *
     * Vanilla Bedrock `honey_bottle` içme sesi (`drink`) kullanır, diğer
     * yenilebilir eşyalar `eat` sesi kullanır. Tablodaki `honey_bottle` dışındaki
     * tüm yenilebilir eşyalar yiyecek (`eat`) olarak kabul edilir.
     */
    public function isDrink(string $identifier): bool
    {
        return $identifier === 'minecraft:honey_bottle';
    }

    /** @return array<string, FoodNutrition> */
    public function all(): array
    {
        return $this->foods;
    }

    public function count(): int
    {
        return count($this->foods);
    }
}
