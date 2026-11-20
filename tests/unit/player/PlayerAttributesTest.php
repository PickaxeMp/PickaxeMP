<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\player;

use PHPUnit\Framework\TestCase;
use PickaxeMP\player\PlayerAttributes;
use PickaxeMP\protocol\packet\types\Attribute;

/**
 * Açlık zinciri sözleşmesi: tükenme → doygunluk → açlık sırası, üst sınırlar,
 * negatife düşmeme ve öznitelik listesi.
 */
final class PlayerAttributesTest extends TestCase
{
    public function testDefaultsMatchVanillaStartingValues(): void
    {
        $attributes = new PlayerAttributes();

        self::assertSame(PlayerAttributes::DEFAULT_HUNGER, $attributes->hunger());
        self::assertSame(PlayerAttributes::DEFAULT_SATURATION, $attributes->saturation());
        self::assertSame(0.0, $attributes->exhaustion());
        self::assertSame(PlayerAttributes::DEFAULT_MOVEMENT_SPEED, $attributes->movementSpeed());
    }

    public function testMovingAccumulatesExhaustionWithoutSpendingPoints(): void
    {
        $attributes = new PlayerAttributes();

        for ($i = 0; $i < 10; $i++) {
            self::assertFalse($attributes->tick(true));
        }

        self::assertEqualsWithDelta(0.10, $attributes->exhaustion(), 1e-9);
        self::assertSame(PlayerAttributes::DEFAULT_HUNGER, $attributes->hunger());
    }

    public function testStandingStillDoesNotConsumeHunger(): void
    {
        $attributes = new PlayerAttributes();

        for ($i = 0; $i < 1000; $i++) {
            self::assertFalse($attributes->tick(false));
        }

        self::assertSame(0.0, $attributes->exhaustion());
        self::assertSame(PlayerAttributes::DEFAULT_HUNGER, $attributes->hunger());
    }

    public function testSaturationIsSpentBeforeHunger(): void
    {
        $attributes = new PlayerAttributes();

        // Tek seferde 14 tükenme: 3 puan harcanır (12), 2 artar.
        self::assertTrue($attributes->consume(14.0));

        self::assertEqualsWithDelta(2.0, $attributes->saturation(), 1e-9);
        self::assertSame(PlayerAttributes::DEFAULT_HUNGER, $attributes->hunger());
        self::assertEqualsWithDelta(2.0, $attributes->exhaustion(), 1e-9);
    }

    public function testHungerDropsOnlyAfterSaturationIsEmpty(): void
    {
        $attributes = new PlayerAttributes();

        // 5 doygunluk + 4 açlık puanı = 9 puan = 36 tükenme.
        $attributes->consume(36.0);

        self::assertSame(0.0, $attributes->saturation());
        self::assertSame(PlayerAttributes::DEFAULT_HUNGER - 4, $attributes->hunger());
    }

    public function testHungerNeverGoesNegative(): void
    {
        $attributes = new PlayerAttributes();

        $attributes->consume(1000.0);

        self::assertSame(0, $attributes->hunger());
        self::assertEqualsWithDelta(0.0, $attributes->saturation(), 1e-9);
    }

    public function testInvalidConsumeAmountIsIgnored(): void
    {
        $attributes = new PlayerAttributes();

        self::assertFalse($attributes->consume(-1.0));
        self::assertFalse($attributes->consume(0.0));
        self::assertSame(0.0, $attributes->exhaustion());
    }

    public function testEatClampsToMaximum(): void
    {
        $attributes = new PlayerAttributes();
        $attributes->consume(1000.0);

        $attributes->eat(50, 100.0);

        self::assertSame(PlayerAttributes::MAX_HUNGER, $attributes->hunger());
        self::assertSame(PlayerAttributes::MAX_SATURATION, $attributes->saturation());
    }

    public function testResetRestoresDefaults(): void
    {
        $attributes = new PlayerAttributes();
        $attributes->consume(1000.0);

        $attributes->reset();

        self::assertSame(PlayerAttributes::DEFAULT_HUNGER, $attributes->hunger());
        self::assertSame(PlayerAttributes::DEFAULT_SATURATION, $attributes->saturation());
        self::assertSame(0.0, $attributes->exhaustion());
    }

    public function testAttributeListCoversHealthHungerSaturationExhaustionAndMovement(): void
    {
        $attributes = new PlayerAttributes();
        $list = $attributes->attributeList(14);

        self::assertCount(5, $list);

        $byName = [];

        foreach ($list as $attribute) {
            self::assertInstanceOf(Attribute::class, $attribute);
            $byName[$attribute->name] = $attribute;
        }

        self::assertSame(
            [
                Attribute::HEALTH,
                Attribute::HUNGER,
                Attribute::SATURATION,
                Attribute::EXHAUSTION,
                Attribute::MOVEMENT,
            ],
            array_keys($byName)
        );

        self::assertSame(14.0, $byName[Attribute::HEALTH]->current);
        self::assertSame(20.0, $byName[Attribute::HEALTH]->max);
        self::assertSame((float) PlayerAttributes::DEFAULT_HUNGER, $byName[Attribute::HUNGER]->current);
    }

    public function testAttributeListClampsHealthIntoRange(): void
    {
        $list = (new PlayerAttributes())->attributeList(99);

        self::assertSame(20.0, $list[0]->current);
        self::assertSame(0.0, $list[0]->min);
    }
}
