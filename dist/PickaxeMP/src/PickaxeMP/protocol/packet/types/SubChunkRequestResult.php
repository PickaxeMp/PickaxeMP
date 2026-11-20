<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

/**
 * `SubChunk Request Result` (Mojang şeması, 2225) — uint8.
 *
 * Değerler şemadaki `x-enum-binary-value` dizisinden birebir alınmıştır:
 * 1..6. (0 kullanılmaz.)
 */
enum SubChunkRequestResult: int
{
    case Success = 1;
    case LevelChunkDoesntExist = 2;
    case WrongDimension = 3;
    case PlayerDoesntExist = 4;
    case IndexOutOfBounds = 5;
    case SuccessAllAir = 6;
}
