<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

/**
 * `persona::AnimatedTextureType` - tel üzerinde `uint32` (Compression).
 *
 * `x-serialization-options: ["Compression", "Enum-as-Value"]` olduğu için
 * **varuint32** yazılır (şemadaki 0/1/2/3 değerleri).
 */
enum AnimatedTextureType: int
{
    case None = 0;
    case Face = 1;
    case Body32x32 = 2;
    case Body128x128 = 3;

    public static function fromWire(int $value): self
    {
        return self::tryFrom($value) ?? self::None;
    }
}
