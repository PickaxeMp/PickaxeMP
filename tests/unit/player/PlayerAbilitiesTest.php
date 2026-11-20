<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\player;

use PHPUnit\Framework\TestCase;
use PickaxeMP\player\GameMode;
use PickaxeMP\player\PlayerAbilities;

/**
 * Oyun modu -> yetenek katmani eslemesi.
 *
 * Bit degerleri yanlis olursa istemci yanlis izni acar (or. hayatta kalmada
 * ucus). Testler tek tek bitleri kontrol eder; "toplam sayi" karsilastirmasi
 * yanlis bitin farkli bir biti maskelemesine izin verirdi.
 */
final class PlayerAbilitiesTest extends TestCase
{
    public function testSurvivalHasNoFlight(): void
    {
        $data = PlayerAbilities::survival(7);

        self::assertSame(7, $data->targetPlayerRawId);
        self::assertCount(1, $data->layers);

        $set = $data->layers[0]->abilitiesSet;

        self::assertSame(PlayerAbilities::SURVIVAL_ABILITIES, $set);
        self::assertSame(0, $set & PlayerAbilities::ABILITY_FLYING);
        self::assertSame(0, $set & PlayerAbilities::ABILITY_MAY_FLY);
        self::assertSame(0, $set & PlayerAbilities::ABILITY_INSTANT_BUILD);
    }

    public function testCreativeAllowsFlightButDoesNotStartFlying(): void
    {
        $set = PlayerAbilities::forGameMode(GameMode::Creative, 1)->layers[0]->abilitiesSet;

        self::assertNotSame(0, $set & PlayerAbilities::ABILITY_MAY_FLY);
        self::assertNotSame(0, $set & PlayerAbilities::ABILITY_INSTANT_BUILD);
        self::assertSame(0, $set & PlayerAbilities::ABILITY_FLYING);
        self::assertNotSame(0, $set & PlayerAbilities::ABILITY_BUILD);
        self::assertNotSame(0, $set & PlayerAbilities::ABILITY_MINE);
    }

    public function testCreativeFlyingAddsFlyingBit(): void
    {
        $set = PlayerAbilities::forGameMode(GameMode::Creative, 1, flying: true)
            ->layers[0]->abilitiesSet;

        self::assertNotSame(0, $set & PlayerAbilities::ABILITY_FLYING);
        self::assertNotSame(0, $set & PlayerAbilities::ABILITY_MAY_FLY);
    }

    public function testFlightFlagIsIgnoredWhenModeDoesNotAllowIt(): void
    {
        $set = PlayerAbilities::forGameMode(GameMode::Survival, 1, flying: true)
            ->layers[0]->abilitiesSet;

        self::assertSame(0, $set & PlayerAbilities::ABILITY_FLYING);
    }

    public function testAdventureDisablesBuildingAndMining(): void
    {
        $set = PlayerAbilities::forGameMode(GameMode::Adventure, 1)->layers[0]->abilitiesSet;

        self::assertSame(PlayerAbilities::ADVENTURE_ABILITIES, $set);
        self::assertSame(0, $set & PlayerAbilities::ABILITY_BUILD);
        self::assertSame(0, $set & PlayerAbilities::ABILITY_MINE);
        self::assertNotSame(0, $set & PlayerAbilities::ABILITY_OPEN_CONTAINERS);
        self::assertNotSame(0, $set & PlayerAbilities::ABILITY_ATTACK_MOBS);
    }

    public function testSpectatorCanFlyButCannotBuild(): void
    {
        $set = PlayerAbilities::forGameMode(GameMode::Spectator, 1)->layers[0]->abilitiesSet;

        self::assertSame(PlayerAbilities::SPECTATOR_ABILITIES, $set);
        self::assertNotSame(0, $set & PlayerAbilities::ABILITY_FLYING);
        self::assertNotSame(0, $set & PlayerAbilities::ABILITY_NO_CLIP);
        self::assertSame(0, $set & PlayerAbilities::ABILITY_BUILD);
    }

    public function testGameModeWireValuesMatchBedrock(): void
    {
        self::assertSame(0, GameMode::Survival->value);
        self::assertSame(1, GameMode::Creative->value);
        self::assertSame(2, GameMode::Adventure->value);
        self::assertSame(6, GameMode::Spectator->value);
    }

    public function testGameModeCapabilities(): void
    {
        self::assertFalse(GameMode::Survival->allowsFlight());
        self::assertTrue(GameMode::Creative->allowsFlight());
        self::assertFalse(GameMode::Adventure->allowsBuilding());
        self::assertTrue(GameMode::Spectator->isSpectator());
        self::assertTrue(GameMode::Creative->isCreative());
    }
}
