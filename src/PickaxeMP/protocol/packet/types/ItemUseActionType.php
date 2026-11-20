<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

/**
 * `ItemUseActionType` - tel üzerinde `int32` (Compression → ZigZag varint32).
 *
 * Mojang `v1.26.65` `x-enum-binary-value` = [0, 1, 2, 3].
 */
enum ItemUseActionType: int
{
    case Place = 0;
    case Use = 1;
    case Destroy = 2;
    case UseAsAttack = 3;

    public static function fromWire(int $value): self
    {
        return self::tryFrom($value) ?? self::Use;
    }
}
