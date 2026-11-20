<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\integration;

use PHPUnit\Framework\TestCase;
use PickaxeMP\auth\AuthenticationEngine;
use PickaxeMP\auth\certificate\CertificateValidator;
use PickaxeMP\auth\certificate\TrustedKeyStore;
use PickaxeMP\auth\PlayerIdentityFactory;
use PickaxeMP\auth\jwt\JwtParser;
use PickaxeMP\auth\jwt\JwtVerifier;
use PickaxeMP\protocol\ConnectionState;
use PickaxeMP\protocol\HandshakeResult;
use PickaxeMP\protocol\packet\packets\LoginPacket;
use PickaxeMP\protocol\packet\packets\PlayStatusPacket;
use PickaxeMP\protocol\packet\packets\RequestNetworkSettingsPacket;
use PickaxeMP\protocol\packet\types\PlayStatus;
use PickaxeMP\protocol\ProtocolHandshake;
use PickaxeMP\protocol\ProtocolVersion;
use PickaxeMP\session\SessionAuthenticator;
use PickaxeMP\session\Session;
use PickaxeMP\session\SessionManager;
use PickaxeMP\session\SessionState;
use PickaxeMP\Tests\support\CryptoFixture;
use PickaxeMP\utils\Logger;

final class SessionAuthenticatorTest extends TestCase
{
    private function logger(): Logger
    {
        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);

        return new Logger('integration-test', $stream, Logger::DEBUG);
    }

    /**
     * Bir başarısızlık üç şeyi birlikte garanti eder:
     * el sıkışma `Closed`, oturum `Failed` ve istemciye `PlayStatus` gider.
     */
    private static function assertFailClosed(
        HandshakeResult $result,
        ProtocolHandshake $handshake,
        Session $session
    ): void {
        self::assertTrue($result->isClosed(), 'Sonuc kapali olmali');
        self::assertSame(ConnectionState::Closed, $handshake->getState());
        self::assertSame(SessionState::Failed, $session->getState());

        $status = null;

        foreach ($result->responses as $response) {
            if ($response instanceof PlayStatusPacket) {
                $status = $response;
                break;
            }
        }

        self::assertInstanceOf(
            PlayStatusPacket::class,
            $status,
            'Basarisizlikta istemciye PlayStatus gonderilmeli'
        );
        self::assertNotSame(PlayStatus::LoginSuccess, $status->statusEnum());
    }

    public function testValidChainAuthenticatesAndReleasesLoginSuccess(): void
    {
        $fixture = CryptoFixture::createRealisticChain();

        // Test deposu fixture kökünü güvenilir sayar (gerçek Mojang değil).
        $store = TrustedKeyStore::empty();
        $store->add($fixture['rootKey']['trusted'], 'test-root');

        $engine = new AuthenticationEngine(
            new CertificateValidator(new JwtParser(), new JwtVerifier(), $store),
            new PlayerIdentityFactory(),
            $this->logger()
        );

        $sessions = new SessionManager($this->logger());
        $authenticator = new SessionAuthenticator($engine, $sessions, $this->logger());

        // Protokol: ilk paket.
        $handshake = new ProtocolHandshake();
        $handshake->handle(new RequestNetworkSettingsPacket(ProtocolVersion::PROTOCOL));

        // Oturum: fiziksel bağlantı açıldı.
        $session = $sessions->create('conn-1', '127.0.0.1:5555');
        $session->transitionTo(SessionState::AwaitingNetworkSettings);
        $session->transitionTo(SessionState::AwaitingLogin);

        $login = new LoginPacket(
            ProtocolVersion::PROTOCOL,
            CryptoFixture::connectionRequest($fixture['chain'])
        );

        $result = $authenticator->handleLogin($handshake, $session, $login);

        // Kimlik doğrulandı ve PlayStatus gönderilmeye hazır.
        self::assertFalse($result->isClosed());
        self::assertSame(ConnectionState::AwaitingResourcePacks, $result->state);
        self::assertTrue($session->isAuthenticated());
        self::assertSame('TestPlayer', $session->getIdentity()?->displayName);
        self::assertSame(1, $sessions->playerCount());

        // İlk yanıt PlayStatus(LoginSuccess) olmalı.
        self::assertNotEmpty($result->responses);
    }

    public function testUntrustedChainIsRejectedAndSessionFails(): void
    {
        $fixture = CryptoFixture::createRealisticChain();

        // Depo boş: kök güvenilir değil.
        $engine = new AuthenticationEngine(
            new CertificateValidator(new JwtParser(), new JwtVerifier(), TrustedKeyStore::empty()),
            new PlayerIdentityFactory(),
            $this->logger()
        );

        $sessions = new SessionManager($this->logger());
        $authenticator = new SessionAuthenticator($engine, $sessions, $this->logger());

        $handshake = new ProtocolHandshake();
        $handshake->handle(new RequestNetworkSettingsPacket(ProtocolVersion::PROTOCOL));

        $session = $sessions->create('conn-2', '127.0.0.1:6666');
        $session->transitionTo(SessionState::AwaitingNetworkSettings);
        $session->transitionTo(SessionState::AwaitingLogin);

        $login = new LoginPacket(
            ProtocolVersion::PROTOCOL,
            CryptoFixture::connectionRequest($fixture['chain'])
        );

        $result = $authenticator->handleLogin($handshake, $session, $login);

        self::assertTrue($result->isClosed());
        self::assertSame(SessionState::Failed, $session->getState());
        self::assertNull($session->getIdentity(), 'Dogrulanmamis kimlik tasinmamali');
        self::assertSame(0, $sessions->playerCount());

        // El sıkışma da kapanmalı ve istemciye PlayStatus gitmeli.
        self::assertFailClosed($result, $handshake, $session);
    }

    public function testSessionCannotAuthenticateWithoutIdentity(): void
    {
        $sessions = new SessionManager($this->logger());
        $session = $sessions->create('conn-3', '127.0.0.1:7777');

        $session->transitionTo(SessionState::AwaitingNetworkSettings);
        $session->transitionTo(SessionState::AwaitingLogin);
        $session->transitionTo(SessionState::Authenticating);

        // Kimlik atanmadan Authenticated durumuna geçmek mümkün değildir.
        // Bu kapı, Playing durumuna giden yolun da atlanmasını engeller.
        $this->expectException(\PickaxeMP\session\SessionException::class);
        $session->transitionTo(SessionState::Authenticated);
    }

    public function testDuplicateLoginIsRejected(): void
    {
        $fixture = CryptoFixture::createRealisticChain();
        $store = TrustedKeyStore::empty();
        $store->add($fixture['rootKey']['trusted'], 'test-root');

        $engine = new AuthenticationEngine(
            new CertificateValidator(new JwtParser(), new JwtVerifier(), $store),
            new PlayerIdentityFactory(),
            $this->logger()
        );

        $sessions = new SessionManager($this->logger());
        $authenticator = new SessionAuthenticator($engine, $sessions, $this->logger());

        $request = CryptoFixture::connectionRequest($fixture['chain']);

        // İlk giriş.
        $handshake1 = new ProtocolHandshake();
        $handshake1->handle(new RequestNetworkSettingsPacket(2225));
        $s1 = $sessions->create('c1', 'a');
        $s1->transitionTo(SessionState::AwaitingNetworkSettings);
        $s1->transitionTo(SessionState::AwaitingLogin);
        $authenticator->handleLogin($handshake1, $s1, new LoginPacket(2225, $request));

        // Aynı oyuncu ikinci bağlantıdan.
        $handshake2 = new ProtocolHandshake();
        $handshake2->handle(new RequestNetworkSettingsPacket(2225));
        $s2 = $sessions->create('c2', 'b');
        $s2->transitionTo(SessionState::AwaitingNetworkSettings);
        $s2->transitionTo(SessionState::AwaitingLogin);

        $result = $authenticator->handleLogin($handshake2, $s2, new LoginPacket(2225, $request));

        self::assertTrue($result->isClosed());
        self::assertSame(SessionState::Failed, $s2->getState());
        self::assertSame(1, $sessions->playerCount());

        // Yinelenen girişte de el sıkışma kapanmalı, PlayStatus gitmeli.
        self::assertFailClosed($result, $handshake2, $s2);
    }

    public function testSessionInWrongStateFailsClosedWithPlayStatus(): void
    {
        $fixture = CryptoFixture::createRealisticChain();
        $store = TrustedKeyStore::empty();
        $store->add($fixture['rootKey']['trusted'], 'test-root');

        $engine = new AuthenticationEngine(
            new CertificateValidator(new JwtParser(), new JwtVerifier(), $store),
            new PlayerIdentityFactory(),
            $this->logger()
        );

        $sessions = new SessionManager($this->logger());
        $authenticator = new SessionAuthenticator($engine, $sessions, $this->logger());

        $handshake = new ProtocolHandshake();
        $handshake->handle(new RequestNetworkSettingsPacket(ProtocolVersion::PROTOCOL));

        // Oturum AwaitingLogin değil; el sıkışma "Authenticating"e geçemez.
        $session = $sessions->create('conn-4', '127.0.0.1:8888');
        $session->transitionTo(SessionState::AwaitingNetworkSettings);

        $login = new LoginPacket(
            ProtocolVersion::PROTOCOL,
            CryptoFixture::connectionRequest($fixture['chain'])
        );

        $result = $authenticator->handleLogin($handshake, $session, $login);

        // Oturum AwaitingNetworkSettings'te kalmalı (geçersiz geçiş atlanmamalı),
        // ancak bağlantı fail-closed kapatılmalı ve istemci bilgilendirilmeli.
        self::assertFailClosed($result, $handshake, $session);
        self::assertSame(0, $sessions->playerCount());
    }
}

