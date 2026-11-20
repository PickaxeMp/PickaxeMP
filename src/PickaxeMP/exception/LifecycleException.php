<?php

declare(strict_types=1);

namespace PickaxeMP\exception;

/**
 * Sunucu yaşam döngüsü sırası ihlali (boot edilmeden tick, iki kez boot vb.).
 */
final class LifecycleException extends PickaxeException
{
    public static function alreadyBooted(): self
    {
        return new self('Sunucu zaten baslatilmis.');
    }

    public static function notBooted(): self
    {
        return new self('Sunucu baslatilmamis.');
    }
}
