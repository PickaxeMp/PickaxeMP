<?php

declare(strict_types=1);

namespace PickaxeMP\exception;

/**
 * Ayar dosyası okunamadı, ayrıştırılamadı veya doğrulanamadı.
 */
final class ConfigException extends PickaxeException
{
    public static function unreadable(string $path, string $reason): self
    {
        return new self("Ayar dosyasi okunamadi: {$path} ({$reason})");
    }

    public static function syntax(string $source, int $line, string $reason): self
    {
        return new self("Ayar sozdizimi hatasi: {$source}:{$line} — {$reason}");
    }

    public static function invalid(string $key, string $reason): self
    {
        return new self("Gecersiz ayar '{$key}': {$reason}");
    }
}
