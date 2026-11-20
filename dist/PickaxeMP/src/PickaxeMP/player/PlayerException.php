<?php

declare(strict_types=1);

namespace PickaxeMP\player;

use RuntimeException;

/**
 * Player katmanındaki durum ve doğrulama hataları.
 */
final class PlayerException extends RuntimeException
{
    public static function unauthenticatedSession(string $sessionId): self
    {
        return new self("Oturum kimlik dogrulanmamis: {$sessionId}");
    }

    public static function duplicateIdentity(string $uuid, string $existingSessionId): self
    {
        return new self("Oyuncu zaten aktif: {$uuid} (oturum {$existingSessionId})");
    }

    public static function duplicateConnection(string $connectionId): self
    {
        return new self("Baglanti zaten bir oyuncuya ait: {$connectionId}");
    }

    public static function invalidState(string $actual, string $expected): self
    {
        return new self("Gecersiz oyuncu durumu: {$actual} (beklenen {$expected})");
    }

    public static function invalidPosition(float $x, float $y, float $z): self
    {
        return new self(sprintf(
            'Gecersiz oyuncu konumu: %.3F, %.3F, %.3F',
            $x,
            $y,
            $z
        ));
    }

    // --- Phase 6: esya ve envanter ---------------------------------------

    public static function invalidItemIdentifier(string $identifier): self
    {
        return new self('Gecersiz esya kimligi: ' . self::safe($identifier));
    }

    public static function invalidNetworkId(int $networkId): self
    {
        return new self("Gecersiz esya ag kimligi: {$networkId}");
    }

    public static function duplicateItem(string $identifier, string $conflict): self
    {
        return new self('Esya zaten kayitli: ' . self::safe($identifier) . " ({$conflict})");
    }

    public static function unknownItem(string $identifier): self
    {
        return new self('Bilinmeyen esya: ' . self::safe($identifier));
    }

    public static function invalidAmount(string $field, int $amount): self
    {
        return new self("Gecersiz miktar ({$field}): {$amount}");
    }

    public static function invalidSlot(int $slot, int $size): self
    {
        return new self("Gecersiz envanter yuvasi: {$slot} (boyut {$size})");
    }

    public static function inventoryCorrupt(string $path, string $reason): self
    {
        return new self('Envanter dosyasi bozuk: ' . self::safe($path) . " ({$reason})");
    }

    public static function inventoryPersistenceFailed(string $path, string $reason): self
    {
        return new self('Envanter yazilamadi: ' . self::safe($path) . " ({$reason})");
    }

    /**
     * Log/konsol çıktısını bozabilecek denetim karakterlerini temizler.
     */
    private static function safe(string $value): string
    {
        return substr(preg_replace('/[^\x20-\x7E]/', '?', $value) ?? '', 0, 96);
    }
}
