<?php

declare(strict_types=1);

namespace PickaxeMP\exception;

/**
 * Görev zamanlayıcısı kullanım hatası.
 */
final class SchedulerException extends PickaxeException
{
    public static function invalidDelay(int $delay): self
    {
        return new self("Gecersiz gecikme: {$delay} (>= 1 olmali)");
    }

    public static function invalidPeriod(int $period): self
    {
        return new self("Gecersiz periyot: {$period} (>= 1 olmali)");
    }

    public static function duplicateId(string $id): self
    {
        return new self("Gorev kimligi zaten kullaniliyor: '{$id}'");
    }
}
