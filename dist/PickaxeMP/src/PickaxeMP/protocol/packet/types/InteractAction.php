<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

/**
 * `InteractPacketPayload.Action` - `uint8`, Enum-as-Value.
 *
 * Mojang `v1.26.65`: `x-enum-binary-value` = [0, 3, 4, 5, 6].
 */
enum InteractAction: int
{
    case Invalid = 0;
    case StopRiding = 3;
    case InteractUpdate = 4;
    case NpcOpen = 5;
    case OpenInventory = 6;

    /**
     * Konum alani bu eylemde yazilir mi?
     *
     * Sema `Position` alanini `required` saymaz; gophertunnel ve pmmp
     * davranisi yalnizca `InteractUpdate` icin konum yazmaktir. Bu bir
     * **varsayimdir** (gercek istemciyle sinanmadi).
     */
    public function carriesPosition(): bool
    {
        return $this === self::InteractUpdate;
    }

    public static function fromWire(int $value): self
    {
        return self::tryFrom($value) ?? self::Invalid;
    }
}
