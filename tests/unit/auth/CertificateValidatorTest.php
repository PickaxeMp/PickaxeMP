<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\auth;

use PHPUnit\Framework\TestCase;
use PickaxeMP\auth\AuthException;
use PickaxeMP\auth\certificate\CertificateChain;
use PickaxeMP\auth\certificate\CertificateValidator;
use PickaxeMP\auth\certificate\TrustedKeyStore;
use PickaxeMP\auth\jwt\JwtParser;
use PickaxeMP\auth\jwt\JwtVerifier;
use PickaxeMP\Tests\support\CryptoFixture;

final class CertificateValidatorTest extends TestCase
{
    /**
     * Test deposu: fixture'ın ürettiği kök anahtarı "güvenilen" sayar.
     *
     * GERÇEK MOJANG ZİNCİRİ DEĞİL — Mojang'ın özel anahtarı elimizde yok.
     * Bu testler zincir **yürüme mantığını** kanıtlar; gerçek zincir
     * doğrulaması yalnızca gerçek istemciyle test edilebilir.
     */
    private function validatorFor(array $rootKey): CertificateValidator
    {
        $store = TrustedKeyStore::empty();
        $store->add($rootKey['trusted'], 'test-root');

        return new CertificateValidator(new JwtParser(), new JwtVerifier(), $store);
    }

    public function testValidTwoLinkChainPasses(): void
    {
        $fixture = CryptoFixture::createRealisticChain();
        $validator = $this->validatorFor($fixture['rootKey']);

        $chain = CertificateChain::fromConnectionRequest(
            CryptoFixture::connectionRequest($fixture['chain'])
        );

        $tokens = $validator->validate($chain);

        self::assertCount(2, $tokens);
        self::assertSame('TestPlayer', $tokens[1]->claims->displayName());
    }

    public function testChainWithoutTrustedRootIsRejected(): void
    {
        $fixture = CryptoFixture::createRealisticChain();

        // Boş depo: hiçbir kök güvenilir değil.
        $validator = new CertificateValidator(
            new JwtParser(),
            new JwtVerifier(),
            TrustedKeyStore::empty()
        );

        $chain = CertificateChain::fromConnectionRequest(
            CryptoFixture::connectionRequest($fixture['chain'])
        );

        $this->expectException(AuthException::class);
        $validator->validate($chain);
    }

    public function testTamperedChainIsRejected(): void
    {
        $fixture = CryptoFixture::createRealisticChain();
        $validator = $this->validatorFor($fixture['rootKey']);

        // İkinci halkanın imzasını boz.
        $broken = $fixture['chain'];
        $parts = explode('.', $broken[1]);
        $sig = \PickaxeMP\auth\jwt\Base64Url::decode($parts[2]);
        $sig[0] = chr((ord($sig[0]) + 1) % 256);
        $broken[1] = $parts[0] . '.' . $parts[1] . '.' . \PickaxeMP\auth\jwt\Base64Url::encode($sig);

        $chain = CertificateChain::fromConnectionRequest(CryptoFixture::connectionRequest($broken));

        $this->expectException(AuthException::class);
        $validator->validate($chain);
    }

    public function testMismatchedX5uIsRejected(): void
    {
        $fixture = CryptoFixture::createRealisticChain();
        $other = CryptoFixture::generateKeyPair();
        $validator = $this->validatorFor($fixture['rootKey']);

        // İkinci halkanın x5u'sunu başka anahtarla değiştir.
        $playerToken = \PickaxeMP\auth\jwt\Base64Url::decode(
            explode('.', $fixture['chain'][1])[1]
        );
        $claims = json_decode($playerToken, true);

        $forged = CryptoFixture::createJwt(
            $fixture['playerKey'],
            $claims,
            ['x5u' => CryptoFixture::base64Der($other)]
        );

        $chain = CertificateChain::fromConnectionRequest(
            CryptoFixture::connectionRequest([$fixture['chain'][0], $forged])
        );

        $this->expectException(AuthException::class);
        $validator->validate($chain);
    }

    public function testExpiredLinkIsRejected(): void
    {
        $fixture = CryptoFixture::createRealisticChain(exp: time() - 7200);
        $validator = $this->validatorFor($fixture['rootKey']);

        $chain = CertificateChain::fromConnectionRequest(
            CryptoFixture::connectionRequest($fixture['chain'])
        );

        $this->expectException(AuthException::class);
        $validator->validate($chain);
    }

    public function testMissingX5uIsRejected(): void
    {
        $fixture = CryptoFixture::createRealisticChain();
        $validator = $this->validatorFor($fixture['rootKey']);

        $playerClaims = json_decode(
            \PickaxeMP\auth\jwt\Base64Url::decode(explode('.', $fixture['chain'][1])[1]),
            true
        );

        // x5u başlığı olmadan token.
        $noX5u = CryptoFixture::createJwt($fixture['playerKey'], $playerClaims);

        $chain = CertificateChain::fromConnectionRequest(
            CryptoFixture::connectionRequest([$fixture['chain'][0], $noX5u])
        );

        $this->expectException(AuthException::class);
        $validator->validate($chain);
    }

    public function testChainRejectsTooManyTokens(): void
    {
        $this->expectException(AuthException::class);
        CertificateChain::fromConnectionRequest(json_encode([
            'chain' => array_fill(0, 10, 'a.b.c'),
        ]) ?: '{}');
    }

    public function testChainRejectsMissingChain(): void
    {
        $this->expectException(AuthException::class);
        CertificateChain::fromConnectionRequest('{"baska": 1}');
    }

    public function testChainRejectsInvalidJson(): void
    {
        $this->expectException(AuthException::class);
        CertificateChain::fromConnectionRequest('{bozuk');
    }

    public function testChainRejectsOversizedRequest(): void
    {
        $this->expectException(AuthException::class);
        CertificateChain::fromConnectionRequest(str_repeat('a', CertificateChain::MAX_REQUEST_SIZE + 1));
    }

    public function testMojangRootKeyStoreIsValid(): void
    {
        $store = TrustedKeyStore::withMojangRoot();

        self::assertSame(1, $store->count());
        self::assertTrue($store->verifyMojangFingerprint());
        self::assertTrue($store->isTrustedFingerprint(TrustedKeyStore::MOJANG_ROOT_FINGERPRINT));
    }

    public function testMojangRootKeyIsEcP384(): void
    {
        $store = TrustedKeyStore::withMojangRoot();
        $keys = $store->all();

        self::assertTrue($keys[0]->isEcP384());
    }
}
