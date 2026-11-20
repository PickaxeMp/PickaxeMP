<?php

declare(strict_types=1);

namespace PickaxeMP\auth\crypto;

use PickaxeMP\auth\AuthException;

/**
 * ECDH anahtar değişimi (EC P-384).
 *
 * ## Akış
 *
 * 1. Sunucu geçici bir EC P-384 anahtar çifti üretir.
 * 2. Açık anahtarını `ServerToClientHandshake` JWT'sinin `x5u` başlığında
 *    gönderir; payload'da 16 baytlık rastgele `salt` bulunur.
 * 3. İstemci kendi özel anahtarı (`cpk`) ile ECDH yapar.
 * 4. Sunucu da istemcinin `cpk` açık anahtarı ile ECDH yapar.
 *
 * İki taraf **aynı** 48 baytlık ortak sırrı elde eder.
 *
 * ## Şifreleme anahtarı
 *
 * ```
 * encryptionKey = SHA256( salt || sharedSecret )   // 32 bayt
 * ```
 *
 * Bu, Bedrock'un belgelenmiş türetmesidir; tahmin değildir.
 *
 * ## Dürüst sınır
 *
 * Anahtar türetmesi ve ECDH, gerçek OpenSSL ile **test edilmiştir**
 * (sunucu+istemci simülasyonu aynı sırrı üretir). **Gerçek istemciyle**
 * el sıkışma henüz denenmemiştir.
 */
final class EcdhKeyExchange
{
    /** Salt uzunluğu (bayt). */
    public const SALT_LENGTH = 16;

    /** Ortak sır uzunluğu (P-384 → 48 bayt). */
    public const SHARED_SECRET_LENGTH = 48;

    /** Türetilen şifreleme anahtarı uzunluğu (AES-256). */
    public const KEY_LENGTH = 32;

    /**
     * @param \OpenSSLAsymmetricKey $privateKey Sunucunun özel anahtarı
     * @param \OpenSSLAsymmetricKey $publicKey Sunucunun açık anahtarı (PEM için)
     */
    private function __construct(
        private readonly \OpenSSLAsymmetricKey $privateKey,
        private readonly \OpenSSLAsymmetricKey $publicKey,
        public readonly string $publicKeyDer,
        public readonly string $publicKeyPem,
        public readonly string $privateKeyPem
    ) {
    }

    /**
     * Yeni geçici anahtar çifti üretir.
     */
    public static function generate(?string $opensslConfigPath = null): self
    {
        $options = [
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'secp384r1',
            'private_key_bits' => 384,
        ];

        if ($opensslConfigPath !== null && is_file($opensslConfigPath)) {
            $options['config'] = $opensslConfigPath;
        }

        $privateKey = openssl_pkey_new($options);

        if ($privateKey === false) {
            throw AuthException::opensslFailure('anahtar uretimi');
        }

        $details = openssl_pkey_get_details($privateKey);

        if ($details === false) {
            throw AuthException::invalidKey('anahtar detaylari alinamiyor');
        }

        $pem = $details['key'];
        $der = self::pemToDer($pem);

        // `openssl_pkey_get_details()['key']` YALNIZCA açık anahtarı verir.
        // Handshake JWT'sini imzalamak için özel anahtar PEM'i de gerekir.
        $privatePem = '';
        $exported = openssl_pkey_export($privateKey, $privatePem, null, $options);

        if (!$exported || $privatePem === '') {
            throw AuthException::opensslFailure('ozel anahtar disa aktarilamadi');
        }

        EcPublicKey::assertEcP384($privateKey);

        return new self($privateKey, $privateKey, $der, $pem, $privatePem);
    }

    /**
     * JWT imzalamak için özel anahtar tanıtıcısı.
     *
     * @return \OpenSSLAsymmetricKey
     */
    public function privateKey(): \OpenSSLAsymmetricKey
    {
        return $this->privateKey;
    }

    /**
     * Açık anahtarın base64 SPKI DER biçimi (JWT `x5u` için).
     */
    public function publicKeyBase64(): string
    {
        return base64_encode($this->publicKeyDer);
    }

    /**
     * 16 baytlık rastgele salt.
     */
    public static function generateSalt(): string
    {
        return random_bytes(self::SALT_LENGTH);
    }

    /**
     * İstemcinin açık anahtarıyla ortak sırrı hesaplar.
     *
     * @param \OpenSSLAsymmetricKey $peerPublicKey İstemcinin `cpk` anahtarı
     */
    public function computeSharedSecret(\OpenSSLAsymmetricKey $peerPublicKey): string
    {
        $secret = openssl_pkey_derive($peerPublicKey, $this->privateKey);

        if ($secret === false) {
            throw AuthException::opensslFailure('ECDH turetimi');
        }

        if (strlen($secret) !== self::SHARED_SECRET_LENGTH) {
            throw AuthException::invalidKey(
                'ortak sir 48 bayt olmali, ' . strlen($secret) . ' alindi'
            );
        }

        return $secret;
    }

    /**
     * Şifreleme anahtarını türetir: `SHA256(salt || sharedSecret)`.
     */
    public static function deriveEncryptionKey(string $salt, string $sharedSecret): string
    {
        if ($salt === '') {
            throw AuthException::invalidKey('salt bos olamaz');
        }

        if (strlen($sharedSecret) !== self::SHARED_SECRET_LENGTH) {
            throw AuthException::invalidKey('ortak sir 48 bayt olmali');
        }

        return hash('sha256', $salt . $sharedSecret, true);
    }

    /**
     * Testlerde istemci tarafını simüle etmek için: verilen özel anahtar ve
     * sunucu açık anahtarıyla ortak sırrı hesaplar.
     *
     * @param \OpenSSLAsymmetricKey $clientPrivate
     * @param \OpenSSLAsymmetricKey $serverPublic
     */
    public static function deriveForTest(\OpenSSLAsymmetricKey $clientPrivate, \OpenSSLAsymmetricKey $serverPublic): string
    {
        $secret = openssl_pkey_derive($serverPublic, $clientPrivate);

        if ($secret === false) {
            throw AuthException::opensslFailure('ECDH turetimi (test)');
        }

        return $secret;
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
}