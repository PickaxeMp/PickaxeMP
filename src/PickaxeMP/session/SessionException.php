<?php

declare(strict_types=1);

namespace PickaxeMP\session;

use PickaxeMP\exception\PickaxeException;

/**
 * Oturum katmanı hatası.
 */
final class SessionException extends PickaxeException
{
    public static function illegalTransition(string $from, string $to, string $sessionId): self
    {
        return new self("Gecersiz oturum gecisi [{$sessionId}]: {$from} → {$to}");
    }

    public static function alreadyRegistered(string $connectionId): self
    {
        return new self("Bu baglanti icin oturum zaten var: {$connectionId}");
    }

    public static function unknownSession(string $sessionId): self
    {
        return new self("Oturum bulunamadi: {$sessionId}");
    }

    public static function duplicatePlayer(string $xuid, string $existingSessionId): self
    {
        return new self(
            "Bu oyuncu zaten bagli (xuid={$xuid}, oturum={$existingSessionId})"
        );
    }

    public static function timedOut(string $sessionId, int $lastActivity, int $now): self
    {
        return new self(
            "Oturum zaman asimi [{$sessionId}]: son aktivite {$lastActivity}, simdi {$now}"
        );
    }
}
