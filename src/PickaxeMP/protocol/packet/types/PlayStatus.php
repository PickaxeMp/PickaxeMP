<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

/**
 * `PlayStatus` (Mojang şeması, 2225) — int32, Big Endian.
 */
enum PlayStatus: int
{
    case LoginSuccess = 0;
    case LoginFailedClientOld = 1;
    case LoginFailedServerOld = 2;
    case PlayerSpawn = 3;
    case LoginFailedInvalidTenant = 4;
    case LoginFailedEditionMismatchEduToVanilla = 5;
    case LoginFailedEditionMismatchVanillaToEdu = 6;
    case LoginFailedServerFullSubClient = 7;
    case LoginFailedEditorMismatchEditorToVanilla = 8;
    case LoginFailedEditorMismatchVanillaToEditor = 9;

    public static function fromWire(int $value): ?self
    {
        return self::tryFrom($value);
    }
}
