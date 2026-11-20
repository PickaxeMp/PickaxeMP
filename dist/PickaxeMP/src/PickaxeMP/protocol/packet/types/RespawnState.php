<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

/**
 * `PlayerRespawnState` - `uint8`, Enum-as-Value.
 *
 * Mojang `v1.26.65`: SearchingForSpawn=0, ReadyToSpawn=1, ClientReadyToSpawn=2.
 */
enum RespawnState: int
{
    case SearchingForSpawn = 0;
    case ReadyToSpawn = 1;
    case ClientReadyToSpawn = 2;

    public static function fromWire(int $value): self
    {
        return self::tryFrom($value) ?? self::SearchingForSpawn;
    }
}
