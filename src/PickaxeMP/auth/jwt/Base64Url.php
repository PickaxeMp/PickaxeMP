<?php

declare(strict_types=1);

namespace PickaxeMP\auth\jwt;

use PickaxeMP\auth\AuthException;

/**
 * Base64URL (RFC 4648 §5) kodlama/çözme.
 *
 * JOSE standardı: `+` yerine `-`, `/` yerine `_`, dolgu (`=`) yok.
 *
 * Katı davranış:
 *  - Dolgu karakteri (`=`) **kabul edilmez** (JOSE'da yoktur).
 *  - Base64URL alfabesi dışındaki karakterler reddedilir.
 *  - Uzunluk `% 4 == 1` olamaz (geçersiz base64).
 *  - `base64_decode(strict: true)` kullanılır; sessizce bozuk veri üretilmez.
 */
final class Base64Url
{
    /** Token parçası için üst sınır: 1 MiB. Login zinciri ~100 KB. */
    public const MAX_ENCODED_LENGTH = 1024 * 1024;

    public static function encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * @throws AuthException geçersiz base64url ise
     */
    public static function decode(string $encoded, string $context = 'base64url'): string
    {
        if ($encoded === '') {
            throw AuthException::invalidBase64Url($context, 'bos girdi');
        }

        if (strlen($encoded) > self::MAX_ENCODED_LENGTH) {
            throw AuthException::invalidBase64Url(
                $context,
                'cok uzun: ' . strlen($encoded) . ' bayt'
            );
        }

        if (str_contains($encoded, '=')) {
            throw AuthException::invalidBase64Url($context, 'dolgu karakteri iceriyor');
        }

        if (preg_match('/^[A-Za-z0-9\-_]+$/', $encoded) !== 1) {
            throw AuthException::invalidBase64Url($context, 'gecersiz karakter');
        }

        if (strlen($encoded) % 4 === 1) {
            throw AuthException::invalidBase64Url($context, 'gecersiz uzunluk');
        }

        $standard = strtr($encoded, '-_', '+/');
        $padded = str_pad($standard, (int) (ceil(strlen($standard) / 4) * 4), '=', STR_PAD_RIGHT);

        $decoded = base64_decode($padded, true);

        if ($decoded === false) {
            throw AuthException::invalidBase64Url($context, 'cozulemedi');
        }

        return $decoded;
    }

    /**
     * Kodlanmış uzunluğu doğrulamadan çözer; yalnızca test/teşhis için.
     */
    public static function tryDecode(string $encoded): ?string
    {
        try {
            return self::decode($encoded);
        } catch (AuthException) {
            return null;
        }
    }
}
