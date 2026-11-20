<?php

declare(strict_types=1);

namespace PickaxeMP\auth;

use PickaxeMP\exception\PickaxeException;

/**
 * Kimlik doğrulama katmanı hatası.
 *
 * Her hata **fail-closed** davranışına yol açar: şüphe varsa oyuncu reddedilir.
 */
final class AuthException extends PickaxeException
{
    public static function invalidBase64Url(string $context, string $reason): self
    {
        return new self("Gecersiz base64url ({$context}): {$reason}");
    }

    public static function malformedJwt(string $reason): self
    {
        return new self("Gecersiz JWT: {$reason}");
    }

    public static function unsupportedAlgorithm(string $algorithm): self
    {
        return new self("Desteklenmeyen imza algoritmasi: {$algorithm}");
    }

    public static function algNoneRejected(): self
    {
        return new self("alg=none kabul edilmez.");
    }

    public static function missingClaim(string $claim): self
    {
        return new self("Zorunlu claim eksik: {$claim}");
    }

    public static function invalidClaimType(string $claim, string $expected, mixed $actual): self
    {
        return new self(
            "Gecersiz claim tipi '{$claim}': {$expected} bekleniyordu, " . get_debug_type($actual) . ' alindi'
        );
    }

    public static function expired(int $exp, int $now): self
    {
        return new self("Token suresi dolmus: exp={$exp}, simdi={$now}");
    }

    public static function notYetValid(int $nbf, int $now): self
    {
        return new self("Token henuz gecerli degil: nbf={$nbf}, simdi={$now}");
    }

    public static function signatureInvalid(string $reason = ''): self
    {
        return new self('Imza dogrulanamadi.' . ($reason === '' ? '' : " ({$reason})"));
    }

    public static function invalidKey(string $reason): self
    {
        return new self("Gecersiz anahtar: {$reason}");
    }

    public static function unknownTrustAnchor(string $x5u): self
    {
        return new self("Bilinmeyen guven kokü (x5u): {$x5u}");
    }

    public static function invalidChain(string $reason): self
    {
        return new self("Gecersiz sertifika zinciri: {$reason}");
    }

    public static function chainTooShortOrLong(int $count): self
    {
        return new self("Sertifika zinciri 1..5 JWT icermeli, {$count} alindi");
    }

    public static function jwtTooLong(int $length, int $limit): self
    {
        return new self("JWT cok uzun: {$length} > {$limit} bayt");
    }

    public static function onlineModeUnavailable(string $reason): self
    {
        return new self("Online kimlik dogrulama kullanilamiyor: {$reason}");
    }

    public static function offlineModeRejected(): self
    {
        return new self(
            'Bu sunucu online-mode ile yapilandirilmis; offline kimlik kabul edilmez.'
        );
    }

    public static function identityMismatch(string $field): self
    {
        return new self("Kimlik alanlari tutarsiz: {$field}");
    }

    public static function opensslFailure(string $operation): self
    {
        $errors = [];

        while (($error = openssl_error_string()) !== false) {
            $errors[] = $error;
        }

        return new self(
            "OpenSSL hatasi ({$operation})" . ($errors === [] ? '' : ': ' . implode('; ', $errors))
        );
    }
}
