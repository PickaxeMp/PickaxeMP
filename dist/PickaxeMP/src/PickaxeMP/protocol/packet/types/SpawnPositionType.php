<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

/**
 * `SpawnPositionType` - tel üzerinde `int32` (Compression => ZigZag varint32).
 *
 * Mojang `v1.26.65` `SpawnPositionType.json`: enum
 * `["PlayerRespawn", "WorldSpawn"]`, `x-enum-binary-value` = `[0, 1]`.
 *
 * `WorldSpawn` pusulayı da bu noktaya çevirir; `PlayerRespawn` yalnızca
 * oyuncunun yeniden doğma noktasını değiştirir.
 */
enum SpawnPositionType: int
{
    case PlayerRespawn = 0;
    case WorldSpawn = 1;
}
