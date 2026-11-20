<?php

declare(strict_types=1);

namespace PickaxeMP\auth\crypto;

use PickaxeMP\auth\AuthException;
use PickaxeMP\auth\jwt\Base64Url;
use PickaxeMP\auth\jwt\EcdsaSignature;
use PickaxeMP\auth\jwt\JwtAlgorithm;

/**
 * `ServerToClientHandshake` JWT'sini üretir ve doğrular.
 *
 * ## Yapı (Mojang şeması, 2225)
 *
 * ```
 * header  { "alg": "ES384", "x5u": "<base64 SPKI DER of server key>" }
 * payload { "salt": "<base64 of 16 random bytes>" }
 * ```
 *
 * `x5u` **URL değil**, ECDH için kullanılacak sunucu açık anahtarının
 * kendisidir (Bedrock'a özgü sapma). İstemci bu anahtarla ortak sırrı
 * hesaplar; JWT imzası da aynı anahtarla doğrulanabilir.
 *
 * ## Dürüst sınır
 *
 * Token üretimi ve imza, gerçek OpenSSL ile test edilmiştir. İstemcinin bu
 * token'ı kabul etmesi **gerçek istemci testi** gerektirir.
 */
final class HandshakeToken
{
    public const ALGORITHM = 'ES384';

    /**
     * Handshake JWT'si üretir.
     *
     * @param \OpenSSLAsymmetricKey $serverPrivate Sunucunun geçici özel anahtarı
     * @param string $serverPublicDer Sunucunun SPKI DER açık anahtarı
     * @param string $salt 16 baytlık rastgele salt
     */
    public static function create(
        \OpenSSLAsymmetricKey $serverPrivate,
        string $serverPublicDer,
        string $salt
    ): string {
        if (strlen($salt) !== EcdhKeyExchange::SALT_LENGTH) {
            throw AuthException::invalidKey('salt 16 bayt olmali');
        }

        $header = Base64Url::encode(json_encode([
            'alg' => self::ALGORITHM,
            'x5u' => base64_encode($serverPublicDer),
        ], JSON_UNESCAPED_SLASHES) ?: '{}');

        $payload = Base64Url::encode(json_encode([
            'salt' => base64_encode($salt),
        ], JSON_UNESCAPED_SLASHES) ?: '{}');

        $signingInput = $header . '.' . $payload;

        $derSignature = '';
        $ok = openssl_sign($signingInput, $derSignature, $serverPrivate, OPENSSL_ALGO_SHA384);

        if (!$ok) {
            throw AuthException::opensslFailure('handshake JWT imzasi');
        }

        $raw = EcdsaSignature::derToRaw($derSignature, JwtAlgorithm::ES384);

        return $signingInput . '.' . Base64Url::encode($raw);
    }

    /**
     * Token'ı ayrıştırır (imza doğrulaması YAPMAZ — test/teşhis için).
     *
     * @return array{header: array<string, mixed>, payload: array<string, mixed>}
     */
    public static function parse(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw AuthException::malformedJwt('handshake token 3 parca olmali');
        }

        $header = json_decode(Base64Url::decode($parts[0], 'header'), true);
        $payload = json_decode(Base64Url::decode($parts[1], 'payload'), true);

        if (!is_array($header) || !is_array($payload)) {
            throw AuthException::malformedJwt('handshake token JSON gecersiz');
        }

        return ['header' => $header, 'payload' => $payload];
    }

    /**
     * Token'daki salt'ı base64'ten çözer.
     */
    public static function extractSalt(string $token): string
    {
        $parsed = self::parse($token);
        $salt = $parsed['payload']['salt'] ?? null;

        if (!is_string($salt)) {
            throw AuthException::malformedJwt('salt eksik');
        }

        $decoded = base64_decode($salt, true);

        if ($decoded === false || strlen($decoded) !== EcdhKeyExchange::SALT_LENGTH) {
            throw AuthException::malformedJwt('salt gecersiz');
        }

        return $decoded;
    }

    /**
     * Token'daki sunucu açık anahtarını (SPKI DER) döndürür.
     */
    public static function extractServerPublicKey(string $token): string
    {
        $parsed = self::parse($token);
        $x5u = $parsed['header']['x5u'] ?? null;

        if (!is_string($x5u) || $x5u === '') {
            throw AuthException::malformedJwt('x5u eksik');
        }

        $decoded = base64_decode($x5u, true);

        if ($decoded === false || $decoded === '') {
            throw AuthException::malformedJwt('x5u gecersiz base64');
        }

        return $decoded;
    }
}
