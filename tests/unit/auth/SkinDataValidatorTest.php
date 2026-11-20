<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\auth;

use PHPUnit\Framework\TestCase;
use PickaxeMP\auth\AuthenticationEngine;
use PickaxeMP\auth\AuthException;
use PickaxeMP\auth\certificate\CertificateValidator;
use PickaxeMP\auth\certificate\TrustedKeyStore;
use PickaxeMP\auth\jwt\JwtParser;
use PickaxeMP\auth\jwt\JwtVerifier;
use PickaxeMP\auth\SkinData;
use PickaxeMP\auth\SkinDataValidator;
use PickaxeMP\protocol\packet\packets\LoginPacket;
use PickaxeMP\Tests\support\CryptoFixture;
use PickaxeMP\utils\Logger;

/**
 * `extraData` skin JWT doğrulaması.
 *
 * DÜRÜST SINIR: Bu testler gerçek bir Mojang zinciri kullanmaz (Mojang'ın özel
 * anahtarı elimizde yok). İmza doğrulama mantığını gerçek ES384 anahtarlarıyla
 * kanıtlarlar.
 */
final class SkinDataValidatorTest extends TestCase
{
    /**
     * @return array{skinId: string, skinData: string, capeData: string, geometryName: string, geometryData: string}
     */
    private function skinClaims(): array
    {
        return [
            'SkinId' => 'c18e65aa-7b21-4637-9b63-8ad63622ef01',
            'SkinData' => base64_encode('PNG-BYTES'),
            'CapeData' => base64_encode('CAPE-BYTES'),
            'SkinGeometryName' => 'geometry.humanoid.custom',
            'SkinGeometryData' => base64_encode('{"format_version":"1.12.0"}'),
        ];
    }

    private function logger(): Logger
    {
        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);

        return new Logger('skin-test', $stream, Logger::DEBUG);
    }

    private function engineFor(array $fixture): AuthenticationEngine
    {
        $store = TrustedKeyStore::empty();
        $store->add($fixture['rootKey']['trusted'], 'test-root');

        return new AuthenticationEngine(
            new CertificateValidator(new JwtParser(), new JwtVerifier(), $store),
            new \PickaxeMP\auth\PlayerIdentityFactory(),
            $this->logger()
        );
    }

    public function testValidExtraDataVerifiesAgainstPlayerKey(): void
    {
        $fixture = CryptoFixture::createRealisticChain();
        $last = (new JwtParser())->parse($fixture['chain'][1]);

        $extraData = CryptoFixture::createJwt(
            $fixture['playerKey'],
            $this->skinClaims(),
            ['x5u' => CryptoFixture::base64Der($fixture['playerKey'])]
        );

        $skin = (new SkinDataValidator())->validate($extraData, $last);

        self::assertInstanceOf(SkinData::class, $skin);
        self::assertSame('c18e65aa-7b21-4637-9b63-8ad63622ef01', $skin->skinId());
        self::assertSame(base64_encode('PNG-BYTES'), $skin->skinData());
        self::assertSame(base64_encode('CAPE-BYTES'), $skin->capeData());
        self::assertSame('geometry.humanoid.custom', $skin->geometryName());
        self::assertTrue($skin->hasCape());
    }

    public function testExtraDataSignedByAnotherKeyIsRejected(): void
    {
        $fixture = CryptoFixture::createRealisticChain();
        $last = (new JwtParser())->parse($fixture['chain'][1]);

        $attacker = CryptoFixture::generateKeyPair();

        $extraData = CryptoFixture::createJwt(
            $attacker,
            $this->skinClaims(),
            ['x5u' => CryptoFixture::base64Der($attacker)]
        );

        $this->expectException(AuthException::class);
        (new SkinDataValidator())->validate($extraData, $last);
    }

    public function testExtraDataPresentButUnsignedIsRejected(): void
    {
        $fixture = CryptoFixture::createRealisticChain();
        $last = (new JwtParser())->parse($fixture['chain'][1]);

        // İmzasız "token": yalnızca iki parça.
        $unsigned = base64_encode('{"alg":"ES384"}') . '.' . base64_encode('{"SkinId":"x"}');

        $this->expectException(AuthException::class);
        (new SkinDataValidator())->validate($unsigned, $last);
    }

    public function testMissingExtraDataYieldsNullSkin(): void
    {
        $fixture = CryptoFixture::createRealisticChain();
        $last = (new JwtParser())->parse($fixture['chain'][1]);

        self::assertNull((new SkinDataValidator())->validate(null, $last));
        self::assertNull((new SkinDataValidator())->validate('', $last));
    }

    public function testExtraDataWithMissingSkinIdIsRejected(): void
    {
        $fixture = CryptoFixture::createRealisticChain();
        $last = (new JwtParser())->parse($fixture['chain'][1]);

        $extraData = CryptoFixture::createJwt(
            $fixture['playerKey'],
            ['SkinData' => base64_encode('PNG-BYTES')]
        );

        $this->expectException(AuthException::class);
        (new SkinDataValidator())->validate($extraData, $last);
    }

    /**
     * `extraData` kimlik alanları taşısa bile bunlar **kullanılmaz**:
     * kimlik yalnızca zincirden gelir.
     */
    public function testExtraDataCannotOverrideIdentityFields(): void
    {
        $fixture = CryptoFixture::createRealisticChain();

        $extraData = CryptoFixture::createJwt(
            $fixture['playerKey'],
            $this->skinClaims() + [
                'xuid' => '9999999999999999',
                'identity' => 'deadbeef-dead-beef-dead-beefdeadbeef',
                'displayName' => 'SahteIsim',
            ]
        );

        $result = $this->engineFor($fixture)->authenticate(new LoginPacket(
            2225,
            CryptoFixture::connectionRequest($fixture['chain'], $extraData)
        ));

        self::assertFalse($result->failed(), 'Gecerli extraData kimlik dogrulamayi engellememeli');

        $identity = $result->requireIdentity();
        self::assertSame('2535400000000001', $identity->xuid, 'xuid zincirden gelmeli');
        self::assertSame('TestPlayer', $identity->displayName, 'displayName zincirden gelmeli');
        self::assertSame(
            '11111111-2222-3333-4444-555555555555',
            $identity->uuid,
            'uuid zincirden gelmeli'
        );

        self::assertInstanceOf(SkinData::class, $result->skin);
        self::assertSame('c18e65aa-7b21-4637-9b63-8ad63622ef01', $result->skin->skinId());
    }

    public function testEngineFailsClosedWhenExtraDataSignatureIsInvalid(): void
    {
        $fixture = CryptoFixture::createRealisticChain();
        $attacker = CryptoFixture::generateKeyPair();

        $extraData = CryptoFixture::createJwt($attacker, $this->skinClaims());

        $result = $this->engineFor($fixture)->authenticate(new LoginPacket(
            2225,
            CryptoFixture::connectionRequest($fixture['chain'], $extraData)
        ));

        self::assertTrue($result->failed(), 'Gecersiz extraData imzasi oturumu reddetmeli');
        self::assertNull($result->identity);
        self::assertNull($result->skin);
    }
}
