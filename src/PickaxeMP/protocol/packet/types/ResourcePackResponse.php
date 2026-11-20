<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

/**
 * `ResourcePackResponse` (Mojang şeması, 2225) — int8, 1..4.
 */
enum ResourcePackResponse: int
{
    case Cancel = 1;
    case Downloading = 2;
    case DownloadingFinished = 3;
    case ResourcePackStackFinished = 4;

    public static function fromWire(int $value): ?self
    {
        return self::tryFrom($value);
    }
}
