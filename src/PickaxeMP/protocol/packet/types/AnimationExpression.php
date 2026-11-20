<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

/**
 * `persona::AnimationExpression` - tel üzerinde `uint32` (Compression).
 *
 * `x-serialization-options: ["Compression", "Enum-as-Value"]` → varuint32.
 */
enum AnimationExpression: int
{
    case Linear = 0;
    case Blinking = 1;

    public static function fromWire(int $value): self
    {
        return self::tryFrom($value) ?? self::Linear;
    }
}
