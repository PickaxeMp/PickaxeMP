<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\auth;

use PHPUnit\Framework\TestCase;
use PickaxeMP\auth\jwt\Base64Url;
use PickaxeMP\auth\jwt\EcdsaSignature;
use PickaxeMP\auth\jwt\JwtAlgorithm;
use PickaxeMP\auth\jwt\JwtParser;
use PickaxeMP\auth\AuthException;
use PickaxeMP\Tests\support\CryptoFixture;

final class JwtParserTest extends TestCase
{
    private JwtParser $parser;

    protected function setUp(): void
    {
        $this->parser = new JwtParser();
    }

    public function testParsesValidToken(): void
    {
        $key = CryptoFixture::generateKeyPair();
        $jwt = CryptoFixture::createJwt($key, ['xuid' => '123', 'exp' => time() + 60], [
            'x5u' => CryptoFixture::base64Der($key),
        ]);

        $token = $this->parser->parse($jwt);

        self::assertSame(JwtAlgorithm::ES384, $token->header->algorithm);
        self::assertSame('123', $token->claims->xuid());
        self::assertTrue($token->header->hasX5u());
        self::assertSame(96, strlen($token->signatureRaw));
    }

    public function testSigningInputIsOriginalText(): void
    {
        $key = CryptoFixture::generateKeyPair();
        $jwt = CryptoFixture::createJwt($key, ['a' => 1, 'exp' => time() + 60]);

        $token = $this->parser->parse($jwt);
        [$header, $payload] = explode('.', $jwt);

        self::assertSame($header . '.' . $payload, $token->signingInput);
    }

    public function testRejectsWrongPartCount(): void
    {
        $this->expectException(AuthException::class);
        $this->parser->parse('tekparca');
    }

    public function testRejectsTwoPartToken(): void
    {
        $this->expectException(AuthException::class);
        $this->parser->parse('a.b');
    }

    public function testRejectsFourPartToken(): void
    {
        $this->expectException(AuthException::class);
        $this->parser->parse('a.b.c.d');
    }

    public function testRejectsEmptyPart(): void
    {
        $this->expectException(AuthException::class);
        $this->parser->parse('..');
    }

    public function testRejectsAlgNone(): void
    {
        $header = Base64Url::encode(json_encode(['alg' => 'none']) ?: '{}');
        $payload = Base64Url::encode(json_encode(['exp' => time() + 60]) ?: '{}');

        $this->expectException(AuthException::class);
        $this->parser->parse($header . '.' . $payload . '.' . Base64Url::encode('sig'));
    }

    public function testRejectsUnknownAlgorithm(): void
    {
        $header = Base64Url::encode(json_encode(['alg' => 'HS999']) ?: '{}');
        $payload = Base64Url::encode(json_encode(['exp' => time() + 60]) ?: '{}');

        $this->expectException(AuthException::class);
        $this->parser->parse($header . '.' . $payload . '.' . Base64Url::encode(str_repeat('x', 96)));
    }

    public function testRejectsWrongEcdsaSignatureLength(): void
    {
        $key = CryptoFixture::generateKeyPair();
        $jwt = CryptoFixture::createJwt($key, ['exp' => time() + 60]);
        [$header, $payload] = explode('.', $jwt);

        $short = Base64Url::encode(str_repeat('x', 50));

        $this->expectException(AuthException::class);
        $this->parser->parse($header . '.' . $payload . '.' . $short);
    }

    public function testRejectsInvalidJsonInPayload(): void
    {
        $key = CryptoFixture::generateKeyPair();
        $header = Base64Url::encode(json_encode(['alg' => 'ES384']) ?: '{}');
        $badPayload = Base64Url::encode('{bozuk json');
        $sig = Base64Url::encode(str_repeat('x', 96));

        $this->expectException(AuthException::class);
        $this->parser->parse($header . '.' . $badPayload . '.' . $sig);
    }

    public function testRejectsOversizedToken(): void
    {
        $this->expectException(AuthException::class);
        $this->parser->parse(str_repeat('a', JwtParser::MAX_TOKEN_LENGTH + 10));
    }

    public function testRejectsNonObjectPayload(): void
    {
        $header = Base64Url::encode(json_encode(['alg' => 'ES384']) ?: '{}');
        $payload = Base64Url::encode('[1,2,3]');
        $sig = Base64Url::encode(str_repeat('x', 96));

        $this->expectException(AuthException::class);
        $this->parser->parse($header . '.' . $payload . '.' . $sig);
    }
}
