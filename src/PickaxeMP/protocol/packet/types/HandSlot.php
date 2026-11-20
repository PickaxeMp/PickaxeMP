<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

/**
 * `HandSlot` - tel üzerinde `uint8`.
 *
 * Şema dosyası bu sürümde indirilmedi; değerler Bedrock'un bilinen
 * `MainHand = 0`, `OffHand = 1` sıralamasıdır. Yalnızca `MainHand` işlenir.
 */
enum HandSlot: int
{
    case MainHand = 0;
    case OffHand = 1;

    public static function fromWire(int $value): self
    {
        return self::tryFrom($value) ?? self::MainHand;
    }
}
