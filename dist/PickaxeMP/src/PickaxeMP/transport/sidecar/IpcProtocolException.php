<?php

declare(strict_types=1);

namespace PickaxeMP\transport\sidecar;

use PickaxeMP\exception\PickaxeException;

/**
 * IPC çerçeve protokolü ihlali. Bozuk veri sessizce yorumlanmaz.
 */
final class IpcProtocolException extends PickaxeException
{
    public static function badMagic(int $magic): self
    {
        return new self(sprintf('Gecersiz IPC sihirli sayisi: 0x%04X', $magic));
    }

    public static function unsupportedVersion(int $version): self
    {
        return new self("Desteklenmeyen IPC protokol surumu: {$version} (beklenen: " . IpcFrame::VERSION . ')');
    }

    public static function payloadTooLarge(int $length): self
    {
        return new self("IPC yuku siniri asildi: {$length} bayt (sinir: " . IpcFrame::MAX_PAYLOAD . ')');
    }

    public static function truncated(int $expected, int $actual): self
    {
        return new self("Eksik IPC cercevesi: beklenen {$expected} bayt, mevcut {$actual}");
    }
}
