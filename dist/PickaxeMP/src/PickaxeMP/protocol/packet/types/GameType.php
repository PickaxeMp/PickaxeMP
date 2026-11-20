<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

/**
 * `GameType` (Mojang şeması, 2225) — int32.
 *
 * Şemadaki binary değerler birebir alınmıştır:
 * Undefined=-1, Survival=0, Creative=1, Adventure=2, Default=5,
 * Spectator=6, WorldDefault=0.
 */
enum GameType: int
{
    case Undefined = -1;
    case Survival = 0;
    case Creative = 1;
    case Adventure = 2;
    case Default = 5;
    case Spectator = 6;

    /**
     * `minecraft:gamemode` metninden çevirir (config.yml için).
     */
    public static function fromConfig(string $name): ?self
    {
        return match (strtolower($name)) {
            'survival' => self::Survival,
            'creative' => self::Creative,
            'adventure' => self::Adventure,
            'spectator' => self::Spectator,
            default => null,
        };
    }
}
