<?php

declare(strict_types=1);

namespace PickaxeMP\auth\jwt;

use PickaxeMP\auth\AuthException;

/**
 * Güvenilen açık anahtar.
 *
 * Bedrock zincirinde anahtarlar **base64 kodlanmış SPKI DER** olarak taşınır
 * (`x5u` başlığı ve `identityPublicKey` claim'i). Bu sınıf onu OpenSSL'e
 * verilebilir hale getirir ve parmak izini hesaplar.
 *
 * Anahtar hiçbir zaman "doğru" varsayılmaz: zincir doğrulaması her adımda
 * imza kontrolü yapar.
 */
final class TrustedPublicKey
{
    /**
     * @param string $spkiDer Ham SPKI DER baytları
     * @param string $pem PEM biçimi (OpenSSL için)
     * @param string $fingerprint SHA-256 (hex)
     */
    private function __construct(
        public readonly string $spkiDer,
        public readonly string $pem,
        public readonly string $fingerprint
    ) {
    }

    /**
     * Base64 SPKI DER'den anahtar oluşturur.
     *
     * @throws AuthException geçersiz base64 veya OpenSSL'in yükleyemediği anahtar
     */
    public static function fromBase64(string $base64, string $context = 'key'): self
    {
        $der = self::strictBase64Decode($base64, $context);

        return self::fromDer($der, $context);
    }

    /**
     * Ham DER baytlarından anahtar oluşturur.
     */
    public static function fromDer(string $der, string $context = 'key'): self
    {
        if ($der === '') {
            throw AuthException::invalidKey("{$context}: bos DER");
        }

        $pem = self::derToPem($der);
        $key = openssl_pkey_get_public($pem);

        if ($key === false) {
            throw AuthException::invalidKey("{$context}: OpenSSL anahtari yukleyemedi");
        }

        $details = openssl_pkey_get_details($key);

        if ($details === false) {
            throw AuthException::invalidKey("{$context}: anahtar detaylari alinamadi");
        }

        if ($details['type'] !== OPENSSL_KEYTYPE_EC && $details['type'] !== OPENSSL_KEYTYPE_RSA) {
            throw AuthException::invalidKey("{$context}: yalnizca EC veya RSA anahtari kabul edilir");
        }

        return new self($der, $pem, hash('sha256', $der));
    }

    /**
     * Bu anahtarın EC P-384 (secp384r1) olup olmadığını kontrol eder.
     */
    public function isEcP384(): bool
    {
        $key = openssl_pkey_get_public($this->pem);

        if ($key === false) {
            return false;
        }

        $details = openssl_pkey_get_details($key);

        return $details !== false
            && $details['type'] === OPENSSL_KEYTYPE_EC
            && ($details['ec']['curve_name'] ?? '') === 'secp384r1';
    }

    /**
     * Sabit zamanlı parmak izi karşılaştırması.
     */
    public function matches(self $other): bool
    {
        return hash_equals($this->fingerprint, $other->fingerprint);
    }

    /**
     * Kısa parmak izi (log için).
     */
    public function shortFingerprint(): string
    {
        return substr($this->fingerprint, 0, 16);
    }

    /**
     * SPKI DER → PEM (SubjectPublicKeyInfo).
     */
    public static function derToPem(string $der): string
    {
        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    /**
     * PEM → SPKI DER.
     */
    public static function pemToDer(string $pem): string
    {
        $body = preg_replace('/-----[^-]+-----/', '', $pem) ?? '';
        $body = preg_replace('/\s+/', '', $body) ?? '';

        $decoded = base64_decode($body, true);

        if ($decoded === false || $decoded === '') {
            throw AuthException::invalidKey('PEM cozulemedi');
        }

        return $decoded;
    }

    /**
     * Base64 decoding with strict validation.
     *
     * Bedrock keys are standard base64 (with padding). Base64URL is accepted
     * too, because some implementations emit it.
     */
    private static function strictBase64Decode(string $base64, string $context): string
    {
        $normalized = strtr(trim($base64), '-_', '+/');

        // Dolgu eksikse ekle.
        $remainder = strlen($normalized) % 4;

        if ($remainder !== 0) {
            $normalized .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode($normalized, true);

        if ($decoded === false || $decoded === '') {
            throw AuthException::invalidKey("{$context}: gecersiz base64");
        }

        return $decoded;
    }
}
