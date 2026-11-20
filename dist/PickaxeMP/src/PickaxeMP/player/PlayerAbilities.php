<?php

declare(strict_types=1);

namespace PickaxeMP\player;

use PickaxeMP\protocol\packet\types\AbilityLayer;
use PickaxeMP\protocol\packet\types\SerializedAbilitiesData;

/**
 * Oyuncunun yetenek katmanları (`UpdateAbilities` içeriği).
 *
 * Bit değerleri Mojang/gophertunnel `protocol/ability.go` sabitleriyle
 * **aynı** olmalıdır; yanlış bit, istemcide yanlış izin açar (ör. uçma).
 *
 * Varsayılan hayatta kalma katmanı: inşa, kazma, kapı/anahtar, kap
 * açma, oyuncuya ve yaratığa saldırma. Uçma ve operatör izinleri kapalı.
 */
final class PlayerAbilities
{
    public const ABILITY_BUILD = 1 << 0;

    public const ABILITY_MINE = 1 << 1;

    public const ABILITY_DOORS_AND_SWITCHES = 1 << 2;

    public const ABILITY_OPEN_CONTAINERS = 1 << 3;

    public const ABILITY_ATTACK_PLAYERS = 1 << 4;

    public const ABILITY_ATTACK_MOBS = 1 << 5;

    public const ABILITY_OPERATOR_COMMANDS = 1 << 6;

    public const ABILITY_TELEPORT = 1 << 7;

    public const ABILITY_INVULNERABLE = 1 << 8;

    public const ABILITY_FLYING = 1 << 9;

    public const ABILITY_MAY_FLY = 1 << 10;

    public const ABILITY_INSTANT_BUILD = 1 << 11;

    public const ABILITY_LIGHTNING = 1 << 12;

    public const ABILITY_FLY_SPEED = 1 << 13;

    public const ABILITY_WALK_SPEED = 1 << 14;

    public const ABILITY_MUTED = 1 << 15;

    public const ABILITY_WORLD_BUILDER = 1 << 16;

    public const ABILITY_NO_CLIP = 1 << 17;

    public const ABILITY_PRIVILEGED_BUILDER = 1 << 18;

    public const ABILITY_VERTICAL_FLY_SPEED = 1 << 19;

    /** Temel (hayatta kalma) katmanı. */
    public const LAYER_BASE = 1;

    /** `PlayerPermissionLevel`: normal üye. */
    public const PLAYER_PERMISSION_MEMBER = 1;

    /** `CommandPermissionLevel`: normal oyuncu. */
    public const COMMAND_PERMISSION_NORMAL = 0;

    public const DEFAULT_FLY_SPEED = 0.05;

    public const DEFAULT_VERTICAL_FLY_SPEED = 1.0;

    public const DEFAULT_WALK_SPEED = PlayerAttributes::DEFAULT_MOVEMENT_SPEED;

    /** Hayatta kalma katmanında açık yetenekler. */
    public const SURVIVAL_ABILITIES = self::ABILITY_BUILD
        | self::ABILITY_MINE
        | self::ABILITY_DOORS_AND_SWITCHES
        | self::ABILITY_OPEN_CONTAINERS
        | self::ABILITY_ATTACK_PLAYERS
        | self::ABILITY_ATTACK_MOBS;

    /** Değer alanları (hızlar) bu bitlerle işaretlenir. */
    public const SURVIVAL_VALUES = self::ABILITY_FLY_SPEED
        | self::ABILITY_WALK_SPEED
        | self::ABILITY_VERTICAL_FLY_SPEED;

    /**
     * Yaratıcı mod katmanı: inşa/kazma + uçuş izni + anında kırma.
     *
     * `ABILITY_FLYING` **yalnızca** oyuncu gerçekten uçarken eklenir; aksi
     * hâlde yaratıcı moda geçen oyuncu yerden kalkmadan havada görünürdü.
     */
    public const CREATIVE_ABILITIES = self::SURVIVAL_ABILITIES
        | self::ABILITY_MAY_FLY
        | self::ABILITY_INSTANT_BUILD;

    /** Macera modu: inşa/kazma yok; kapı, kap ve saldırı var. */
    public const ADVENTURE_ABILITIES = self::ABILITY_DOORS_AND_SWITCHES
        | self::ABILITY_OPEN_CONTAINERS
        | self::ABILITY_ATTACK_PLAYERS
        | self::ABILITY_ATTACK_MOBS;

    /** İzleyici modu: uçuş + duvarsız geçiş, inşa/saldırı yok. */
    public const SPECTATOR_ABILITIES = self::ABILITY_MAY_FLY
        | self::ABILITY_FLYING
        | self::ABILITY_NO_CLIP;

    /**
     * Hayatta kalma modu için tel verisi.
     */
    public static function survival(int $targetEntityId): SerializedAbilitiesData
    {
        return self::pack($targetEntityId, self::SURVIVAL_ABILITIES);
    }

    /**
     * Oyun moduna ve uçuş durumuna göre tel verisi.
     */
    public static function forGameMode(
        GameMode $mode,
        int $targetEntityId,
        bool $flying = false
    ): SerializedAbilitiesData {
        $abilities = match ($mode) {
            GameMode::Survival => self::SURVIVAL_ABILITIES,
            GameMode::Creative => self::CREATIVE_ABILITIES,
            GameMode::Adventure => self::ADVENTURE_ABILITIES,
            GameMode::Spectator => self::SPECTATOR_ABILITIES,
        };

        if ($flying && $mode->allowsFlight()) {
            $abilities |= self::ABILITY_FLYING;
        }

        return self::pack($targetEntityId, $abilities);
    }

    /**
     * Bir oyuncunun mevcut yetenek verisi.
     */
    public static function forPlayer(Player $player): SerializedAbilitiesData
    {
        return self::forGameMode($player->gameMode(), $player->entityId, $player->isFlying());
    }

    /**
     * Ortak tel paketleme: tek katman, sabit hız değerleri.
     */
    private static function pack(int $targetEntityId, int $abilities): SerializedAbilitiesData
    {
        return new SerializedAbilitiesData(
            targetPlayerRawId: $targetEntityId,
            playerPermissions: self::PLAYER_PERMISSION_MEMBER,
            commandPermissions: self::COMMAND_PERMISSION_NORMAL,
            layers: [
                new AbilityLayer(
                    layer: self::LAYER_BASE,
                    abilitiesSet: $abilities,
                    abilityValues: self::SURVIVAL_VALUES,
                    flySpeed: self::DEFAULT_FLY_SPEED,
                    verticalFlySpeed: self::DEFAULT_VERTICAL_FLY_SPEED,
                    walkSpeed: self::DEFAULT_WALK_SPEED
                ),
            ]
        );
    }
}
