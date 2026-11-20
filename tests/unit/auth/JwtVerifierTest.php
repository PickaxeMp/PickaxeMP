<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\auth;

use PHPUnit\Framework\TestCase;
use PickaxeMP\auth\AuthException;
use PickaxeMP\auth\jwt\JwtAlgorithm;
use PickaxeMP\auth\jwt\JwtParser;
use PickaxeMP\auth\jwt\JwtVerifier;
use PickaxeMP\auth\jwt\OpenSslJwtVerifier;
use PickaxeMP\auth\jwt\TrustedPublicKey;
use PickaxeMP\Tests\support\CryptoFixture;

/**
 * ES384 imza doğrulama testleri — **gerçek** OpenSSL anahtar ve imzalarıyla.
 */
final class JwtVerifierTest extends TestCase
{
    private JwtVerifier $verifier;

    private JwtParser $parser;

    protected function setUp(): void
    {
        $this->verifier = new JwtVerifier();
        $this->parser = new JwtParser();
    }

    public function testValidSignaturePasses(): void
    {
        $key = CryptoFixture::generateKeyPair();
        $jwt = CryptoFixture::createJwt($key, ['xuid' => '1', 'exp' => time() + 600]);

        $token = $this->parser->parse($jwt);
        $result = $this->verifier->verify($token, $key['trusted']);

        self::assertTrue($result->verified, $result->reason);
    }

    public function testCorruptedSignatureFails(): void
    {
        $key = CryptoFixture::generateKeyPair();
        $jwt = CryptoFixture::createJwt($key, ['exp' => time() + 600]);

        $parts = explode('.', $jwt);
        $rawSignature = \PickaxeMP\auth\jwt\Base64Url::decode($parts[2]);
        $rawSignature[5] = chr((ord($rawSignature[5]) + 1) % 256);

        $corrupted = $parts[0] . '.' . $parts[1] . '.' . \PickaxeMP\auth\jwt\Base64Url::encode($rawSignature);

        $token = $this->parser->parse($corrupted);
        $result = $this->verifier->verify($token, $key['trusted']);

        self::assertTrue($result->failed());
    }

    public function testModifiedPayloadFails(): void
    {
        $key = CryptoFixture::generateKeyPair();
        $jwt = CryptoFixture::createJwt($key, ['xuid' => '111', 'exp' => time() + 600]);

        [$header, , $signature] = explode('.', $jwt);
        $forgedPayload = \PickaxeMP\auth\jwt\Base64Url::encode(
            json_encode(['xuid' => '999', 'exp' => time() + 600]) ?: '{}'
        );

        $forged = $header . '.' . $forgedPayload . '.' . $signature;

        $token = $this->parser->parse($forged);

        self::assertTrue($this->verifier->verify($token, $key['trusted'])->failed());
    }

    public function testModifiedHeaderFails(): void
    {
        $key = CryptoFixture::generateKeyPair();
        $jwt = CryptoFixture::createJwt($key, ['exp' => time() + 600]);

        [, $payload, $signature] = explode('.', $jwt);
        $forgedHeader = \PickaxeMP\auth\jwt\Base64Url::encode(
            json_encode(['alg' => 'ES384', 'kid' => 'sahte']) ?: '{}'
        );

        $forged = $forgedHeader . '.' . $payload . '.' . $signature;

        $token = $this->parser->parse($forged);

        self::assertTrue($this->verifier->verify($token, $key['trusted'])->failed());
    }

    public function testWrongPublicKeyFails(): void
    {
        $signer = CryptoFixture::generateKeyPair();
        $other = CryptoFixture::generateKeyPair();

        $jwt = CryptoFixture::createJwt($signer, ['exp' => time() + 600]);
        $token = $this->parser->parse($jwt);

        self::assertTrue($this->verifier->verify($token, $other['trusted'])->failed());
    }

    public function testExpiredTokenFails(): void
    {
        $key = CryptoFixture::generateKeyPair();
        $jwt = CryptoFixture::createJwt($key, ['exp' => time() - 7200]);

        $token = $this->parser->parse($jwt);

        $this->expectException(AuthException::class);
        $this->verifier->verify($token, $key['trusted']);
    }

    public function testTokenWithinClockSkewPasses(): void
    {
        $key = CryptoFixture::generateKeyPair();
        // 30 saniye önce dolmuş — 60 sn tolerans içinde.
        $jwt = CryptoFixture::createJwt($key, ['exp' => time() - 30]);

        $token = $this->parser->parse($jwt);

        self::assertTrue($this->verifier->verify($token, $key['trusted'])->verified);
    }

    public function testFutureNotBeforeFails(): void
    {
        $key = CryptoFixture::generateKeyPair();
        $jwt = CryptoFixture::createJwt($key, ['nbf' => time() + 7200]);

        $token = $this->parser->parse($jwt);

        $this->expectException(AuthException::class);
        $this->verifier->verify($token, $key['trusted']);
    }

    public function testZeroSignatureFails(): void
    {
        $key = CryptoFixture::generateKeyPair();
        $jwt = CryptoFixture::createJwt($key, ['exp' => time() + 600]);
        [$header, $payload] = explode('.', $jwt);

        $zero = $header . '.' . $payload . '.' . \PickaxeMP\auth\jwt\Base64Url::encode(str_repeat("\x00", 96));

        $token = $this->parser->parse($zero);

        self::assertTrue($this->verifier->verify($token, $key['trusted'])->failed());
    }

    public function testParseAndVerifyThrowsOnBadSignature(): void
    {
        $signer = CryptoFixture::generateKeyPair();
        $other = CryptoFixture::generateKeyPair();
        $jwt = CryptoFixture::createJwt($signer, ['exp' => time() + 600]);

        $this->expectException(AuthException::class);
        $this->verifier->parseAndVerify($jwt, $other['trusted']);
    }

    public function testEcKeyRejectedForRsaAlgorithm(): void
    {
        $key = CryptoFixture::generateKeyPair();
        $verifier = new OpenSslJwtVerifier();

        // ES384 imzasını RS256 etiketiyle doğrulamaya çalışmak reddedilmeli.
        $result = $verifier->verify('payload', str_repeat("\x01", 96), JwtAlgorithm::RS256, $key['trusted']);

        self::assertTrue($result->failed());
    }
}
