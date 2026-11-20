<?php

declare(strict_types=1);

namespace PickaxeMP\auth\crypto;

use PickaxeMP\auth\AuthException;

/**
 * EC P-384 açık anahtar dönüşümleri.
 *
 * Bedrock istemcileri anahtarlarını **iki farklı biçimde** gönderebilir:
 *  - 1.26.40 **öncesi**: base64 kodlanmış **SPKI DER**
 *  - 1.26.40 **sonrası**: **JWK** nesnesi (`kty=EC`, `crv=P-384`, `x`, `y`)
 *
 * Her iki biçim de OpenSSL'in anlayacağı PEM'e çevrilebilmelidir. Bu sınıf
 * dönüşümü tek yerde toplar; ECDH kodu biçim farkını bilmez.
 *
 * ## JWK → SPKI DER
 *
 * P-384 için SPKI sabit bir iskelete sahiptir (120 bayt):
 *
 * ```
 * 30 76                                  SEQUENCE (118)
 *   30 10                                SEQUENCE (16)
 *     06 07 2A 86 48 CE 3D 02 01         OID id-ecPublicKey
 *     06 05 2B 81 04 00 22               OID secp384r1
 *   03 62 00                             BIT STRING (98), 0 unused bits
 *     04                                 uncompressed point
 *     x (48 bayt) || y (48 bayt)
 * ```
 *
 * Bu iskelet Mojang kök anahtarının DER uzunluğuyla (120 bayt) birebir
 * eşleşir — testlerle doğrulanmıştır.
 */
final class EcPublicKey
{
    /** secp384r1 DER toplam uzunluğu. */
    public const SPKI_LENGTH = 120;

    /** Koordinat boyu (bayt). */
    public const COORDINATE_LENGTH = 48;

    /**
     * Base64 SPKI DER'den OpenSSL anahtar tanıtıcısı üretir.
     *
     * @return \OpenSSLAsymmetricKey
     */
    public static function fromBase64Der(string $base64): \OpenSSLAsymmetricKey
    {
        $der = self::decodeBase64($base64);

        return self::fromDer($der);
    }

    /**
     * Ham SPKI DER'den anahtar üretir.
     *
     * @return \OpenSSLAsymmetricKey
     */
    public static function fromDer(string $der): \OpenSSLAsymmetricKey
    {
        if ($der === '') {
            throw AuthException::invalidKey('bos DER');
        }

        $pem = "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END PUBLIC KEY-----\n";

        $key = openssl_pkey_get_public($pem);

        if ($key === false) {
            throw AuthException::invalidKey('OpenSSL DER anahtarini yukleyemedi');
        }

        return self::assertEcP384($key);
    }

    /**
     * JWK nesnesinden (EC P-384) anahtar üretir.
     *
     * @param array<string, mixed> $jwk
     *
     * @return \OpenSSLAsymmetricKey
     */
    public static function fromJwk(array $jwk): \OpenSSLAsymmetricKey
    {
        $kty = $jwk['kty'] ?? null;
        $crv = $jwk['crv'] ?? null;
        $x = $jwk['x'] ?? null;
        $y = $jwk['y'] ?? null;

        if ($kty !== 'EC') {
            throw AuthException::invalidKey('JWK: kty EC olmali');
        }

        // "P-384" ve "secp384r1" aynı eğridir; ikisi de kabul edilir.
        if ($crv !== 'P-384' && $crv !== 'secp384r1') {
            throw AuthException::invalidKey("JWK: desteklenmeyen egri: " . (is_string($crv) ? $crv : '?'));
        }

        if (!is_string($x) || !is_string($y)) {
            throw AuthException::invalidKey('JWK: x/y eksik');
        }

        $xBytes = self::decodeBase64Url($x, 'jwk.x');
        $yBytes = self::decodeBase64Url($y, 'jwk.y');

        if (strlen($xBytes) !== self::COORDINATE_LENGTH || strlen($yBytes) !== self::COORDINATE_LENGTH) {
            throw AuthException::invalidKey(
                'JWK: koordinat boyu 48 bayt olmali (' . strlen($xBytes) . '/' . strlen($yBytes) . ')'
            );
        }

        return self::fromDer(self::buildSpkiDer($xBytes, $yBytes));
    }

    /**
     * İstemciden gelen `cpk` değerini anahtara çevirir (iki biçimi de kabul).
     *
     * @param string|array<string, mixed> $cpk
     *
     * @return \OpenSSLAsymmetricKey
     */
    public static function fromClientPublicKey(string|array $cpk): \OpenSSLAsymmetricKey
    {
        if (is_string($cpk)) {
            return self::fromBase64Der($cpk);
        }

        // Bazı istemciler JWK'yı string olarak sarar; çift kodlamayı çöz.
        if (isset($cpk['kty'])) {
            return self::fromJwk($cpk);
        }

        throw AuthException::invalidKey('cpk: taninmayan bicim');
    }

    /**
     * JWK koordinatlarından SPKI DER kurar.
     */
    public static function buildSpkiDer(string $x, string $y): string
    {
        $point = "\x04" . $x . $y; // uncompressed

        $algorithmIdentifier = "\x30\x10"
            . "\x06\x07\x2A\x86\x48\xCE\x3D\x02\x01"   // id-ecPublicKey
            . "\x06\x05\x2B\x81\x04\x00\x22";           // secp384r1

        $bitString = "\x03" . chr(strlen($point) + 1) . "\x00" . $point;

        $body = $algorithmIdentifier . $bitString;

        return "\x30" . chr(strlen($body)) . $body;
    }

    /**
     * Anahtarın gerçekten EC P-384 olduğunu doğrular.
     *
     * @param \OpenSSLAsymmetricKey $key
     *
     * @return \OpenSSLAsymmetricKey
     */
    public static function assertEcP384(\OpenSSLAsymmetricKey $key): \OpenSSLAsymmetricKey
    {
        $details = openssl_pkey_get_details($key);

        if ($details === false) {
            throw AuthException::invalidKey('anahtar detaylari alinamadi');
        }

        if ($details['type'] !== OPENSSL_KEYTYPE_EC) {
            throw AuthException::invalidKey('EC anahtari bekleniyordu');
        }

        if (($details['ec']['curve_name'] ?? '') !== 'secp384r1') {
            throw AuthException::invalidKey(
                'secp384r1 bekleniyordu, alinan: ' . ($details['ec']['curve_name'] ?? '?')
            );
        }

        return $key;
    }

    /**
     * Standart base64 (dolgu olabilir) veya base64url kabul eder.
     */
    private static function decodeBase64(string $value): string
    {
        $normalized = strtr(trim($value), '-_', '+/');
        $remainder = strlen($normalized) % 4;

        if ($remainder !== 0) {
            $normalized .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode($normalized, true);

        if ($decoded === false || $decoded === '') {
            throw AuthException::invalidKey('gecersiz base64');
        }

        return $decoded;
    }

    /**
     * JWK değerleri **base64url**'dir (dolgu yok).
     */
    private static function decodeBase64Url(string $value, string $context): string
    {
        $normalized = strtr($value, '-_', '+/');
        $remainder = strlen($normalized) % 4;

        if ($remainder !== 0) {
            $normalized .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode($normalized, true);

        if ($decoded === false) {
            throw AuthException::invalidKey("{$context}: gecersiz base64url");
        }

        return $decoded;
    }
}
