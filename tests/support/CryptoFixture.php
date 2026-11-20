<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\support;

/**
 * Gerçek EC P-384 anahtar çiftleri ve ES384 imzalama.
 *
 * Testlerde **sahte** imza üretilmez: OpenSSL ile gerçek imza atılır, gerçek
 * doğrulama yapılır. Bu, `EcdsaSignature::rawToDer` dönüşümünün doğruluğunu
 * kanıtlar.
 */
final class CryptoFixture
{
    /**
     * Yeni bir P-384 anahtar çifti üretir.
     *
     * @return array{resource: \OpenSSLAsymmetricKey, pem: string, der: string, trusted: \PickaxeMP\auth\jwt\TrustedPublicKey}
     */
    public static function generateKeyPair(): array
    {
        // OpenSSL 3.x provider config'i için açık yol veriyoruz: bu PHP
        // derlemesinde varsayılan `openssl.cnf` yok.
        $configPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'openssl.cnf';

        $options = [
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'secp384r1',
            'private_key_bits' => 384,
        ];

        if (is_file($configPath)) {
            $options['config'] = $configPath;
        }

        $resource = openssl_pkey_new($options);

        // Config yolu işe yaramadıysa env değişkeniyle son bir deneme.
        if ($resource === false) {
            $previous = getenv('OPENSSL_CONF');

            if ($previous === false && is_file($configPath)) {
                putenv('OPENSSL_CONF=' . $configPath);
                $resource = openssl_pkey_new($options);
            }
        }

        if ($resource === false) {
            throw new \RuntimeException('Test anahtari uretilemedi (OpenSSL config?)');
        }

        $details = openssl_pkey_get_details($resource);

        if ($details === false) {
            throw new \RuntimeException('Anahtar detaylari alinamadi');
        }

        $pem = $details['key'];
        $der = \PickaxeMP\auth\jwt\TrustedPublicKey::pemToDer($pem);

        return [
            'resource' => $resource,
            'pem' => $pem,
            'der' => $der,
            'trusted' => \PickaxeMP\auth\jwt\TrustedPublicKey::fromDer($der),
        ];
    }

    /**
     * Base64 SPKI DER (Bedrock'ın kullandığı biçim).
     *
     * @param array{resource: \OpenSSLAsymmetricKey, pem: string, der: string, trusted: \PickaxeMP\auth\jwt\TrustedPublicKey} $keyPair
     */
    public static function base64Der(array $keyPair): string
    {
        return base64_encode($keyPair['der']);
    }

    /**
     * JWT oluşturur (gerçek ES384 imzasıyla).
     *
     * @param array<string, mixed> $claims
     * @param array<string, mixed> $headerExtras
     * @param array{resource: \OpenSSLAsymmetricKey, pem: string, der: string, trusted: \PickaxeMP\auth\jwt\TrustedPublicKey} $signerKey
     */
    public static function createJwt(
        array $signerKey,
        array $claims,
        array $headerExtras = [],
        string $alg = 'ES384'
    ): string {
        $header = array_merge(['alg' => $alg], $headerExtras);

        $encodedHeader = \PickaxeMP\auth\jwt\Base64Url::encode(
            json_encode($header, JSON_UNESCAPED_SLASHES) ?: '{}'
        );
        $encodedPayload = \PickaxeMP\auth\jwt\Base64Url::encode(
            json_encode($claims, JSON_UNESCAPED_SLASHES) ?: '{}'
        );

        $signingInput = $encodedHeader . '.' . $encodedPayload;

        $derSignature = '';
        $ok = openssl_sign($signingInput, $derSignature, $signerKey['resource'], OPENSSL_ALGO_SHA384);

        if (!$ok) {
            throw new \RuntimeException('Test imzasi atilamadi');
        }

        $rawSignature = \PickaxeMP\auth\jwt\EcdsaSignature::derToRaw(
            $derSignature,
            \PickaxeMP\auth\jwt\JwtAlgorithm::ES384
        );

        return $signingInput . '.' . \PickaxeMP\auth\jwt\Base64Url::encode($rawSignature);
    }

    /**
     * Mojang kök anahtarı ile imzalanmış "gerçekçi" bir zincir üretir.
     *
     * NOT: Mojang'ın **özel** anahtarı elimizde yok. Bu fixture, zincir
     * yürüme mantığını sınamak için **kendi** kök anahtarımızı kullanır; test
     * deposuna bu anahtar "güvenilen kök" olarak eklenir. Gerçek Mojang
     * zinciri yalnızca gerçek bir istemciyle test edilebilir.
     *
     * @return array{chain: list<string>, playerKey: array<string, mixed>, rootKey: array<string, mixed>, claims: array<string, mixed>}
     */
    public static function createRealisticChain(
        string $xuid = '2535400000000001',
        string $uuid = '11111111-2222-3333-4444-555555555555',
        string $name = 'TestPlayer',
        ?int $exp = null,
        bool $withClientPublicKey = false
    ): array {
        $rootKey = self::generateKeyPair();

        $chain = self::createChainWithRoot($rootKey, $xuid, $uuid, $name, $withClientPublicKey, $exp);

        return $chain + ['rootKey' => $rootKey];
    }

    /**
     * Verilen kök anahtarla imzalanmış zincir üretir.
     *
     * Aynı köke bağlı **iki ayrı oyuncu** üretmek için kullanılır (çok
     * bağlantılı testler).
     *
     * @param array<string, mixed> $rootKey
     *
     * @return array{chain: list<string>, playerKey: array<string, mixed>, clientKey: array<string, mixed>|null, claims: array<string, mixed>}
     */
    public static function createChainWithRoot(
        array $rootKey,
        string $xuid = '2535400000000001',
        string $uuid = '11111111-2222-3333-4444-555555555555',
        string $name = 'TestPlayer',
        bool $withClientPublicKey = false,
        ?int $exp = null
    ): array {
        $playerKey = self::generateKeyPair();

        $exp ??= time() + 3600;

        // Halka 1: kök kendi kendini imzalar, oyuncu anahtarını taşır.
        $rootToken = self::createJwt(
            $rootKey,
            [
                'identityPublicKey' => self::base64Der($playerKey),
                'exp' => $exp,
            ],
            ['x5u' => self::base64Der($rootKey)]
        );

        // İstemcinin ECDH anahtarı (`cpk`). Yalnızca şifreli akışı sınayan
        // testlerde eklenir; aksi hâlde mevcut zincirler değişmez.
        $clientKey = $withClientPublicKey ? self::generateKeyPair() : null;

        $playerClaims = [
            'xuid' => $xuid,
            'identity' => $uuid,
            'displayName' => $name,
            'exp' => $exp,
        ];

        if ($clientKey !== null) {
            $playerClaims['cpk'] = self::base64Der($clientKey);
        }

        // Halka 2: oyuncu anahtarı imzalar, gerçek kimlik buradadır.
        $playerToken = self::createJwt(
            $playerKey,
            $playerClaims,
            ['x5u' => self::base64Der($playerKey)]
        );

        return [
            'chain' => [$rootToken, $playerToken],
            'playerKey' => $playerKey,
            'clientKey' => $clientKey,
            'claims' => ['xuid' => $xuid, 'identity' => $uuid, 'displayName' => $name],
        ];
    }

    /**
     * Login paketi için `connectionRequest` JSON'u.
     *
     * @param list<string> $chain
     */
    public static function connectionRequest(array $chain, ?string $extraData = null): string
    {
        $payload = ['chain' => $chain];

        if ($extraData !== null) {
            $payload['extraData'] = $extraData;
        }

        return json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
