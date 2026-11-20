<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit;

use PHPUnit\Framework\TestCase;
use PickaxeMP\auth\AuthenticationEngine;
use PickaxeMP\auth\AuthenticationResult;
use PickaxeMP\auth\IdentityType;
use PickaxeMP\auth\PlayerIdentityFactory;
use PickaxeMP\protocol\packet\packets\LoginPacket;
use PickaxeMP\Tests\support\CryptoFixture;
use PickaxeMP\utils\Logger;

final class AuthenticationEngineTest extends TestCase
{
    private function logger(): Logger
    {
        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);

        return new Logger('auth-test', $stream, Logger::DEBUG);
    }

    public function testOnlineEngineRejectsUnsignedChain(): void
    {
        $engine = AuthenticationEngine::online($this->logger());

        // Rastgele bir JWT zinciri; Mojang kökü yok.
        $fixture = CryptoFixture::createRealisticChain();

        $login = new LoginPacket(
            2225,
            CryptoFixture::connectionRequest($fixture['chain'])
        );

        $result = $engine->authenticate($login);

        self::assertInstanceOf(AuthenticationResult::class, $result);
        self::assertTrue($result->failed(), 'Mojang kokü olmayan zincir reddedilmeli');
        self::assertNull($result->identity);
    }

    public function testOnlineEngineRejectsMalformedRequest(): void
    {
        $engine = AuthenticationEngine::online($this->logger());

        $result = $engine->authenticate(new LoginPacket(2225, 'bu bir json degil'));

        self::assertTrue($result->failed());
    }

    public function testOnlineModeIsProductionSafe(): void
    {
        $engine = AuthenticationEngine::online($this->logger());

        self::assertTrue($engine->getMode()->isProductionSafe());
        $engine->assertProductionSafe(); // istisna fırlatmamalı

        self::assertTrue(true);
    }

    public function testOfflineModeIsNotProductionSafe(): void
    {
        $mode = \PickaxeMP\auth\AuthenticationMode::Offline;

        self::assertFalse($mode->isProductionSafe());
    }

    public function testOfflineModeProducesDeterministicIdentity(): void
    {
        $factory = new PlayerIdentityFactory();

        $a = $factory->offline('Steve');
        $b = $factory->offline('Steve');

        self::assertSame($a->uuid, $b->uuid);
        self::assertSame($a->xuid, $b->xuid);
        self::assertSame(IdentityType::Offline, $a->type);
        self::assertFalse($a->isOnline());
    }

    public function testOfflineIdentityIsMarkedOffline(): void
    {
        $factory = new PlayerIdentityFactory();
        $identity = $factory->offline('Alex');

        self::assertSame(IdentityType::Offline, $identity->type);
        self::assertSame('offline', $identity->chainFingerprint);
    }

    public function testIdentityFactoryRejectsInvalidUuid(): void
    {
        // Doğrulanmış zincir olmadan kimlik üretilemez; fabrika bunu zorunlu
        // kılar çünkü JwtToken gerektirir. Bu test offline yolun ad
        // doğrulamasını sınar.
        $factory = new PlayerIdentityFactory();

        $this->expectException(\PickaxeMP\auth\AuthException::class);
        $factory->offline(str_repeat('x', 200));
    }
}
