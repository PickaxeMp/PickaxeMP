<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\auth;

use PHPUnit\Framework\TestCase;
use PickaxeMP\auth\AuthException;
use PickaxeMP\auth\crypto\AesCtrCipher;
use PickaxeMP\auth\crypto\EcdhKeyExchange;
use PickaxeMP\auth\crypto\EcPublicKey;
use PickaxeMP\auth\crypto\HandshakeToken;
use PickaxeMP\Tests\support\CryptoFixture;

final class EncryptionTest extends TestCase
{
    private function clientKeyPair(): array
    {
        return CryptoFixture::generateKeyPair();
    }

    // --- ECDH -----------------------------------------------------------

    public function testServerKeyIsEcP384(): void
    {
        $server = EcdhKeyExchange::generate();

        self::assertSame(EcPublicKey::SPKI_LENGTH, strlen($server->publicKeyDer));
        EcPublicKey::assertEcP384(openssl_pkey_get_public($server->publicKeyPem) ?: throw new \RuntimeException('pem'));
    }

    public function testBothSidesDeriveSameSecret(): void
    {
        $server = EcdhKeyExchange::generate();
        $client = $this->clientKeyPair();
        $clientPublic = openssl_pkey_get_public($client['pem']);

        self::assertNotFalse($clientPublic);

        $serverSecret = $server->computeSharedSecret($clientPublic);

        $serverPublic = openssl_pkey_get_public($server->publicKeyPem);
        self::assertNotFalse($serverPublic);

        $clientSecret = EcdhKeyExchange::deriveForTest($client['resource'], $serverPublic);

        self::assertSame(EcdhKeyExchange::SHARED_SECRET_LENGTH, strlen($serverSecret));
        self::assertTrue(hash_equals($serverSecret, $clientSecret), 'Iki taraf ayni sirri uretmeli');
    }

    public function testDifferentKeysProduceDifferentSecrets(): void
    {
        $server = EcdhKeyExchange::generate();
        $a = $this->clientKeyPair();
        $b = $this->clientKeyPair();

        $secretA = $server->computeSharedSecret(openssl_pkey_get_public($a['pem']) ?: throw new \RuntimeException('a'));
        $secretB = $server->computeSharedSecret(openssl_pkey_get_public($b['pem']) ?: throw new \RuntimeException('b'));

        self::assertFalse(hash_equals($secretA, $secretB));
    }

    public function testEncryptionKeyDerivation(): void
    {
        $secret = random_bytes(EcdhKeyExchange::SHARED_SECRET_LENGTH);
        $salt = EcdhKeyExchange::generateSalt();

        $key = EcdhKeyExchange::deriveEncryptionKey($salt, $secret);

        self::assertSame(32, strlen($key));
        // SHA256(salt||secret) deterministik.
        self::assertSame($key, EcdhKeyExchange::deriveEncryptionKey($salt, $secret));
    }

    public function testDerivationDependsOnSalt(): void
    {
        $secret = random_bytes(48);

        $a = EcdhKeyExchange::deriveEncryptionKey(random_bytes(16), $secret);
        $b = EcdhKeyExchange::deriveEncryptionKey(random_bytes(16), $secret);

        self::assertNotSame($a, $b);
    }

    public function testRejectsWrongSecretLength(): void
    {
        $this->expectException(AuthException::class);
        EcdhKeyExchange::deriveEncryptionKey(random_bytes(16), 'kisa');
    }

    // --- JWK ------------------------------------------------------------

    public function testJwkConvertsToSameKeyAsDer(): void
    {
        $server = EcdhKeyExchange::generate();
        $client = $this->clientKeyPair();

        $details = openssl_pkey_get_details($client['resource']);
        self::assertNotFalse($details);

        // OpenSSL baskin sifir baytini kirpar: koordinat 47 bayt donebilir.
        // JWK sozlesmesi her zaman 48 bayt ister; sola sifirla doldur.
        $x = str_pad($details['ec']['x'], 48, "\x00", STR_PAD_LEFT);
        $y = str_pad($details['ec']['y'], 48, "\x00", STR_PAD_LEFT);

        $jwk = [
            'kty' => 'EC',
            'crv' => 'P-384',
            'x' => rtrim(strtr(base64_encode($x), '+/', '-_'), '='),
            'y' => rtrim(strtr(base64_encode($y), '+/', '-_'), '='),
        ];

        $fromJwk = EcPublicKey::fromJwk($jwk);
        $fromDer = EcPublicKey::fromBase64Der(base64_encode($client['der']));

        $secretJwk = $server->computeSharedSecret($fromJwk);
        $secretDer = $server->computeSharedSecret($fromDer);

        self::assertTrue(hash_equals($secretJwk, $secretDer));
    }

    public function testRejectsWrongJwkCurve(): void
    {
        $this->expectException(AuthException::class);
        EcPublicKey::fromJwk(['kty' => 'EC', 'crv' => 'P-256', 'x' => 'a', 'y' => 'b']);
    }

    public function testRejectsWrongJwkType(): void
    {
        $this->expectException(AuthException::class);
        EcPublicKey::fromJwk(['kty' => 'RSA', 'x' => 'a', 'y' => 'b']);
    }

    // --- AES-CTR --------------------------------------------------------

    public function testCipherRequiresThirtyTwoByteKey(): void
    {
        $this->expectException(AuthException::class);
        new AesCtrCipher('kisa');
    }

    public function testFakeGcmIvLayout(): void
    {
        $key = random_bytes(32);
        $iv = AesCtrCipher::fakeGcmIv($key);

        self::assertSame(16, strlen($iv));
        self::assertSame(substr($key, 0, 12), substr($iv, 0, 12));
        self::assertSame("\x00\x00\x00\x02", substr($iv, 12));
    }

    public function testSinglePacketRoundTrip(): void
    {
        $key = random_bytes(32);
        $sender = new AesCtrCipher($key);
        $receiver = new AesCtrCipher($key);

        $plain = 'merhaba bedrock';
        $encrypted = $sender->encrypt($plain);

        self::assertSame(strlen($plain) + AesCtrCipher::CHECKSUM_LENGTH, strlen($encrypted));
        self::assertSame($plain, $receiver->decrypt($encrypted));
    }

    /**
     * EN KRİTİK TEST: CTR akışı paketler arasında süreklidir. İkinci
     * paketten itibaren IV sıfırlanırsa şifre çözme bozulur.
     */
    public function testMultiplePacketsMaintainKeystream(): void
    {
        $key = random_bytes(32);
        $sender = new AesCtrCipher($key);
        $receiver = new AesCtrCipher($key);

        $packets = [
            'ilk',
            str_repeat('X', 200),
            str_repeat('Y', 33),
            random_bytes(1000),
            'son',
        ];

        foreach ($packets as $index => $plain) {
            $encrypted = $sender->encrypt($plain);
            self::assertSame($plain, $receiver->decrypt($encrypted), "paket {$index}");
        }
    }

    public function testEmptyPayloadRoundTrip(): void
    {
        $key = random_bytes(32);
        $sender = new AesCtrCipher($key);
        $receiver = new AesCtrCipher($key);

        $encrypted = $sender->encrypt('');

        self::assertSame(AesCtrCipher::CHECKSUM_LENGTH, strlen($encrypted));
        self::assertSame('', $receiver->decrypt($encrypted));
    }

    public function testBothDirectionsUseSeparateCounters(): void
    {
        $key = random_bytes(32);
        $server = new AesCtrCipher($key);
        $client = new AesCtrCipher($key);

        // S→C
        $a = $server->encrypt('sunucudan');
        self::assertSame('sunucudan', $client->decrypt($a));

        // C→S
        $b = $client->encrypt('istemciden');
        self::assertSame('istemciden', $server->decrypt($b));

        // S→C tekrar
        $c = $server->encrypt('tekrar');
        self::assertSame('tekrar', $client->decrypt($c));
    }

    public function testCorruptedCiphertextIsRejected(): void
    {
        $key = random_bytes(32);
        $encrypted = (new AesCtrCipher($key))->encrypt('gizli');

        $encrypted[2] = chr((ord($encrypted[2]) + 1) % 256);

        $this->expectException(AuthException::class);
        (new AesCtrCipher($key))->decrypt($encrypted);
    }

    public function testWrongKeyIsRejected(): void
    {
        $encrypted = (new AesCtrCipher(random_bytes(32)))->encrypt('gizli');

        $this->expectException(AuthException::class);
        (new AesCtrCipher(random_bytes(32)))->decrypt($encrypted);
    }

    public function testTruncatedCiphertextIsRejected(): void
    {
        $key = random_bytes(32);

        $this->expectException(AuthException::class);
        (new AesCtrCipher($key))->decrypt('kisa');
    }

    public function testCounterIncrements(): void
    {
        $cipher = new AesCtrCipher(random_bytes(32));

        self::assertSame(0, $cipher->encryptCounter());
        $cipher->encrypt('a');
        self::assertSame(1, $cipher->encryptCounter());
        $cipher->encrypt('b');
        self::assertSame(2, $cipher->encryptCounter());
    }

    // --- Handshake token ------------------------------------------------

    public function testHandshakeTokenRoundTrip(): void
    {
        $server = EcdhKeyExchange::generate();
        $salt = EcdhKeyExchange::generateSalt();

        $token = HandshakeToken::create($server->privateKey(), $server->publicKeyDer, $salt);

        self::assertSame($salt, HandshakeToken::extractSalt($token));
        self::assertSame($server->publicKeyDer, HandshakeToken::extractServerPublicKey($token));
    }

    public function testHandshakeTokenHeaderUsesEs384(): void
    {
        $server = EcdhKeyExchange::generate();
        $token = HandshakeToken::create($server->privateKey(), $server->publicKeyDer, EcdhKeyExchange::generateSalt());
        $parsed = HandshakeToken::parse($token);

        self::assertSame('ES384', $parsed['header']['alg']);
        self::assertArrayHasKey('x5u', $parsed['header']);
        self::assertArrayHasKey('salt', $parsed['payload']);
    }

    public function testRejectsBadSaltLength(): void
    {
        $server = EcdhKeyExchange::generate();
        $this->expectException(AuthException::class);
        HandshakeToken::create($server->privateKey(), $server->publicKeyDer, 'kisa');
    }

    public function testParseRejectsMalformedToken(): void
    {
        $this->expectException(AuthException::class);
        HandshakeToken::parse('tek-parca');
    }
}
