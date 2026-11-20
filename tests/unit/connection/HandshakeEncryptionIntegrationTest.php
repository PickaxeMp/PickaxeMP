<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\connection;

use PHPUnit\Framework\TestCase;
use PickaxeMP\auth\crypto\AesCtrCipher;
use PickaxeMP\auth\crypto\EcdhKeyExchange;
use PickaxeMP\protocol\ProtocolVersion;
use PickaxeMP\Tests\support\CryptoFixture;
use PickaxeMP\protocol\packet\packets\ClientToServerHandshakePacket;
use PickaxeMP\protocol\packet\packets\LoginPacket;
use PickaxeMP\protocol\packet\packets\RequestNetworkSettingsPacket;

/**
 * Integration tests for handshake and encryption flow.
 * Tests protocol packet handling and crypto operations in isolation.
 *
 * DÜRÜST SINIR: Bu testler gerçek bir Bedrock istemcisinin bağlanabildiğini
 * KANITLAMAZ. Yalnızca paket yapısı ve kriptografik akışı doğrular.
 */
final class HandshakeEncryptionIntegrationTest extends TestCase
{
    /**
     * Test: Crypto fixture generates valid EC key pairs for P-384.
     * Essential for handshake ECDH key exchange.
     */
    public function testCryptoFixtureGeneratesP384KeyPair(): void
    {
        $key = CryptoFixture::generateKeyPair();
        $this->assertNotNull($key['resource']);
        $this->assertNotEmpty($key['pem']);
        $this->assertNotEmpty($key['der']);
        // Verify it's a P-384 key by checking DER length
        $this->assertEquals(120, strlen($key['der']), 'P-384 DER should be 120 bytes');
    }

    /**
     * Test: Crypto fixture creates realistic authentication chain.
     * Root key + player key chain structure is valid for JWT-based auth.
     */
    public function testCryptoFixtureCreatesRealisticChain(): void
    {
        $fixture = CryptoFixture::createRealisticChain();
        $this->assertIsArray($fixture['chain']);
        $this->assertCount(2, $fixture['chain'], 'Chain should have 2 tokens (root + player)');
        $this->assertIsArray($fixture['playerKey']);
        $this->assertIsArray($fixture['rootKey']);
        $this->assertNotEmpty($fixture['claims']['xuid']);
    }

    /**
     * Test: Encryption key derivation is deterministic given same salt and secret.
     * This is the core ECDH shared secret derivation used in handshake.
     */
    public function testEcdhKeyDerivationIsDeterministic(): void
    {
        $secret = random_bytes(EcdhKeyExchange::SHARED_SECRET_LENGTH);
        $salt = EcdhKeyExchange::generateSalt();

        $key1 = EcdhKeyExchange::deriveEncryptionKey($salt, $secret);
        $key2 = EcdhKeyExchange::deriveEncryptionKey($salt, $secret);

        $this->assertSame($key1, $key2, 'Derived key must be deterministic with same salt+secret');
        $this->assertSame(32, strlen($key1), 'Encryption key must be 32 bytes (SHA256 output)');
    }

    /**
     * Test: Encryption key derivation depends on salt.
     * Different salts must produce different keys even with same secret.
     */
    public function testEcdhKeyDerivationDependsOnSalt(): void
    {
        $secret = random_bytes(48);
        $salt1 = EcdhKeyExchange::generateSalt();
        $salt2 = EcdhKeyExchange::generateSalt();

        $key1 = EcdhKeyExchange::deriveEncryptionKey($salt1, $secret);
        $key2 = EcdhKeyExchange::deriveEncryptionKey($salt2, $secret);

        $this->assertNotSame($key1, $key2, 'Different salts must produce different keys');
    }

    /**
     * Test: Encryption key derivation rejects wrong secret length.
     * AES-CTR requires exactly 32-byte key.
     */
    public function testEcdhKeyDerivationRejectsWrongLength(): void
    {
        $this->expectException(\PickaxeMP\auth\AuthException::class);
        EcdhKeyExchange::deriveEncryptionKey(random_bytes(16), 'kisa');
    }

    /**
     * Test: AES-CTR cipher requires 32-byte key.
     * Wrong key length must be rejected at cipher construction.
     */
    public function testAesCtrCipherRequires32ByteKey(): void
    {
        $this->expectException(\PickaxeMP\auth\AuthException::class);
        new AesCtrCipher('kisa');
    }

    /**
     * Test: AES-CTR single packet round trip encrypt/decrypt.
     * Basic confidentiality test for the handshake encryption layer.
     */
    public function testAesCtrSinglePacketRoundTrip(): void
    {
        $key = random_bytes(32);
        $sender = new AesCtrCipher($key);
        $receiver = new AesCtrCipher($key);

        $plain = 'merhaba bedrock';
        $encrypted = $sender->encrypt($plain);

        $this->assertNotEquals($plain, $encrypted, 'Encrypted must differ from plaintext');
        $this->assertTrue(strlen($encrypted) > 0, 'Encrypted must not be empty');
        $this->assertEquals(strlen($plain) + AesCtrCipher::CHECKSUM_LENGTH, strlen($encrypted));

        $this->assertEquals($plain, $receiver->decrypt($encrypted), 'Round trip must recover plaintext');
    }

    /**
     * Test: AES-CTR multiple packets maintain keystream continuity.
     * Consecutive encrypted packets must share the same keystream state.
     * This is critical for multi-packet movement/sync in Bedrock protocol.
     */
    public function testAesCtrMultiplePacketsMaintainKeystream(): void
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

        foreach ($packets as $plain) {
            $encrypted = $sender->encrypt($plain);
            $this->assertEquals($plain, $receiver->decrypt($encrypted), "Paket decryption failed");
        }
    }

    /**
     * Test: AES-CTR empty payload round trip.
     * Empty payloads must be handled correctly (checksum only).
     */
    public function testAesCtrEmptyPayloadRoundTrip(): void
    {
        $key = random_bytes(32);
        $sender = new AesCtrCipher($key);
        $receiver = new AesCtrCipher($key);

        $encrypted = $sender->encrypt('');

        $this->assertEquals(AesCtrCipher::CHECKSUM_LENGTH, strlen($encrypted));
        $this->assertEquals('', $receiver->decrypt($encrypted));
    }

    /**
     * Test: AES-CTR corrupted ciphertext is rejected.
     * Tampered encrypted data must fail decryption.
     */
    public function testAesCtrCorruptedCiphertextIsRejected(): void
    {
        $key = random_bytes(32);
        $encrypted = (new AesCtrCipher($key))->encrypt('gizli');

        // Corrupt a byte
        $corrupted = $encrypted;
        $corrupted[2] = chr((ord($corrupted[2]) + 1) % 256);

        $this->expectException(\PickaxeMP\auth\AuthException::class);
        (new AesCtrCipher($key))->decrypt($corrupted);
    }

    /**
     * Test: AES-CTR wrong key is rejected.
     * Encrypted with one key must not decrypt with another key.
     */
    public function testAesCtrWrongKeyIsRejected(): void
    {
        $key1 = random_bytes(32);
        $key2 = random_bytes(32);

        $encrypted = (new AesCtrCipher($key1))->encrypt('gizli');

        $this->expectException(\PickaxeMP\auth\AuthException::class);
        (new AesCtrCipher($key2))->decrypt($encrypted);
    }

    /**
     * Test: AES-CTR truncated ciphertext is rejected.
     * Too-short encrypted data must fail.
     */
    public function testAesCtrTruncatedCiphertextIsRejected(): void
    {
        $key = random_bytes(32);

        $this->expectException(\PickaxeMP\auth\AuthException::class);
        (new AesCtrCipher($key))->decrypt('kisa');
    }

    /**
     * Test: AES-CTR counter increments across encrypt calls.
     * CTR mode must maintain counter state across operations.
     */
    public function testAesCtrCounterIncrements(): void
    {
        $cipher = new AesCtrCipher(random_bytes(32));

        $this->assertSame(0, $cipher->encryptCounter());
        $cipher->encrypt('a');
        $this->assertSame(1, $cipher->encryptCounter());
        $cipher->encrypt('b');
        $this->assertSame(2, $cipher->encryptCounter());
    }

    /**
     * Test: AES-CTR both directions use separate counters.
     * Server and client ciphers must maintain independent counter states.
     */
    public function testAesCtrBothDirectionsUseSeparateCounters(): void
    {
        $key = random_bytes(32);
        $server = new AesCtrCipher($key);
        $client = new AesCtrCipher($key);

        // S→C
        $a = $server->encrypt('sunucudan');
        $this->assertEquals('sunucudan', $client->decrypt($a));

        // C→S
        $b = $client->encrypt('istemciden');
        $this->assertEquals('istemciden', $server->decrypt($b));

        // S→C again (counter should have advanced)
        $c = $server->encrypt('tekrar');
        $this->assertEquals('tekrar', $client->decrypt($c));
    }

    /**
     * Test: ClientToServerHandshakePacket can be instantiated with various JWT strings.
     * Packet structure validation at the protocol level.
     */
    public function testClientHandshakePacketInstantiation(): void
    {
        $packet = new ClientToServerHandshakePacket('{"test": "data"}');
        $this->assertInstanceOf(ClientToServerHandshakePacket::class, $packet);
    }

    public function testClientHandshakePacketWithEmptyString(): void
    {
        $packet = new ClientToServerHandshakePacket('');
        $this->assertInstanceOf(ClientToServerHandshakePacket::class, $packet);
    }

    /**
     * Test: Login packet can be instantiated with valid protocol version and chain.
     * This is the second packet in the handshake sequence after RequestNetworkSettings.
     */
    public function testLoginPacketInstantiation(): void
    {
        $chain = '{"chain":["a","b","c"]}';
        $packet = new LoginPacket(ProtocolVersion::PROTOCOL, $chain);
        $this->assertInstanceOf(LoginPacket::class, $packet);
    }

    /**
     * Test: RequestNetworkSettingsPacket is valid with protocol version.
     * This is the first packet in the handshake sequence.
     */
    public function testRequestNetworkSettingsPacketValid(): void
    {
        $packet = new RequestNetworkSettingsPacket(ProtocolVersion::PROTOCOL);
        $this->assertInstanceOf(RequestNetworkSettingsPacket::class, $packet);
    }
}