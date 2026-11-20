<?php

declare(strict_types=1);

namespace PickaxeMP\exception;

/**
 * Modül bağımlılık grafiği çözümlenemedi (eksik bağımlılık, döngü, çift kayıt).
 */
final class DependencyException extends PickaxeException
{
    public static function cycle(array $cycle): self
    {
        return new self('Modul bagimlilik dongusu: ' . implode(' -> ', $cycle));
    }

    public static function missing(string $moduleId, string $missingId): self
    {
        return new self("Modul '{$moduleId}' tanimsiz bagimliliga sahip: '{$missingId}'");
    }

    public static function duplicate(string $moduleId): self
    {
        return new self("Modul zaten kayitli: '{$moduleId}'");
    }

    public static function unknown(string $moduleId): self
    {
        return new self("Modul bulunamadi: '{$moduleId}'");
    }
}
