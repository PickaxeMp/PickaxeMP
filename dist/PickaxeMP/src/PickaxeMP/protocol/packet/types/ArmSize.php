<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

/**
 * `persona::ArmSize::Type` - tel üzerinde `uint8`.
 *
 * Değerler Mojang `v1.26.65` şemasındaki `x-enum-binary-value` dizisinden
 * birebir alınmıştır (`persona__ArmSize__Type.json`).
 */
enum ArmSize: int
{
    case Slim = 0;
    case Wide = 1;

    public static function fromWire(int $value): self
    {
        return self::tryFrom($value) ?? self::Wide;
    }
}
