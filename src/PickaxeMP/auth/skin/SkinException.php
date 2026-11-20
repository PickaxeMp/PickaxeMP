<?php

declare(strict_types=1);

namespace PickaxeMP\auth\skin;

/**
 * Deri (skin) verisiyle ilgili hatalar.
 *
 * `AuthException`'dan **ayrıdır**: kimlik doğrulama başarılı olduktan sonra
 * deri çözülemezse oyuncu atılmaz (vanilla davranışı: varsayılan deri).
 * Bu yüzden ayrı bir istisna tipi ve ayrı bir ele alma noktası vardır.
 */
final class SkinException extends \RuntimeException
{
    public static function invalidPng(string $reason): self
    {
        return new self('Gecersiz PNG: ' . $reason);
    }

    public static function unsupportedSkin(string $reason): self
    {
        return new self('Desteklenmeyen deri: ' . $reason);
    }
}
