<?php

declare(strict_types=1);

namespace PickaxeMP\exception;

/**
 * Taşıyıcı katmanı hataları (başlatma, gönderim, bağlantı kaybı).
 */
final class TransportException extends PickaxeException
{
    public static function notRunning(): self
    {
        return new self('Tasiyici calismiyor.');
    }

    public static function alreadyRunning(): self
    {
        return new self('Tasiyici zaten calisiyor.');
    }

    public static function unknownConnection(string $connectionId): self
    {
        return new self("Bilinmeyen baglanti: '{$connectionId}'");
    }

    public static function startFailed(string $reason): self
    {
        return new self("Tasiyici baslatilamadi: {$reason}");
    }

    public static function payloadTooLarge(int $size, int $limit): self
    {
        return new self("Yuk cok buyuk: {$size} bayt (sinir: {$limit})");
    }
}
