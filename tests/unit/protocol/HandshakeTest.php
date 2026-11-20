<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\protocol;

use PHPUnit\Framework\TestCase;
use PickaxeMP\protocol\compression\CompressionAlgorithm;
use PickaxeMP\protocol\compression\CompressionSettings;
use PickaxeMP\protocol\ConnectionState;
use PickaxeMP\protocol\HandshakeException;
use PickaxeMP\protocol\packet\packets\LoginPacket;
use PickaxeMP\protocol\packet\packets\NetworkSettingsPacket;
use PickaxeMP\protocol\packet\packets\PlayStatusPacket;
use PickaxeMP\protocol\packet\packets\RequestNetworkSettingsPacket;
use PickaxeMP\protocol\packet\packets\ResourcePackClientResponsePacket;
use PickaxeMP\protocol\packet\types\PlayStatus;
use PickaxeMP\protocol\packet\types\ResourcePackResponse;
use PickaxeMP\protocol\ProtocolHandshake;
use PickaxeMP\protocol\ProtocolVersion;

final class HandshakeTest extends TestCase
{
    private const JWT = '{"chain":["a.b.c","d.e.f"]}';

    private function handshake(): ProtocolHandshake
    {
        return new ProtocolHandshake();
    }

    public function testStartsAwaitingRequestNetworkSettings(): void
    {
        self::assertSame(
            ConnectionState::AwaitingRequestNetworkSettings,
            $this->handshake()->getState()
        );
    }

    public function testRequestNetworkSettingsProducesNetworkSettings(): void
    {
        $handshake = $this->handshake();

        $result = $handshake->handle(new RequestNetworkSettingsPacket(ProtocolVersion::PROTOCOL));

        self::assertSame(ConnectionState::AwaitingLogin, $result->state);
        self::assertCount(1, $result->responses);
        self::assertInstanceOf(NetworkSettingsPacket::class, $result->responses[0]);
        self::assertFalse($result->authenticationRequired);
    }

    public function testNetworkSettingsCarriesServerCompression(): void
    {
        $settings = new CompressionSettings(CompressionAlgorithm::Zlib, 512);
        $handshake = new ProtocolHandshake($settings);

        $result = $handshake->handle(new RequestNetworkSettingsPacket(2225));

        /** @var NetworkSettingsPacket $packet */
        $packet = $result->responses[0];

        self::assertSame(512, $packet->compressionThreshold);
        self::assertSame(CompressionAlgorithm::Zlib, $packet->compressionAlgorithm);
    }

    public function testIncompatibleProtocolClosesWithPlayStatus(): void
    {
        $handshake = $this->handshake();

        $result = $handshake->handle(new RequestNetworkSettingsPacket(2193));

        self::assertTrue($result->isClosed());
        self::assertSame(ConnectionState::Closed, $result->state);
        self::assertCount(1, $result->responses);
        self::assertInstanceOf(PlayStatusPacket::class, $result->responses[0]);
        self::assertSame(PlayStatus::LoginFailedServerOld->value, $result->responses[0]->status);
    }

    public function testLoginStopsAtAuthenticationAndDoesNotSucceed(): void
    {
        $handshake = $this->handshake();
        $handshake->handle(new RequestNetworkSettingsPacket(2225));

        $result = $handshake->handle(new LoginPacket(2225, self::JWT));

        // EN ÖNEMLİ TEST: kimlik doğrulaması yapılmadan Ready'ye geçiş YOK.
        self::assertSame(ConnectionState::AwaitingAuthentication, $result->state);
        self::assertTrue($result->authenticationRequired);
        self::assertFalse($handshake->isReady());
        self::assertSame([], $result->responses, 'Dogrulama oncesi istemciye basari paketi gitmemeli');
    }

    public function testLoginWithBadJwtShapeFails(): void
    {
        $handshake = $this->handshake();
        $handshake->handle(new RequestNetworkSettingsPacket(2225));

        $result = $handshake->handle(new LoginPacket(2225, 'tek-parca'));

        self::assertTrue($result->isClosed());
        self::assertTrue($result->authenticationRequired === false);
    }

    public function testLoginWithIncompatibleVersionFails(): void
    {
        $handshake = $this->handshake();
        $handshake->handle(new RequestNetworkSettingsPacket(2225));

        $result = $handshake->handle(new LoginPacket(2193, self::JWT));

        self::assertTrue($result->isClosed());
    }

    public function testPacketsDuringAuthenticationAreRejected(): void
    {
        $handshake = $this->handshake();
        $handshake->handle(new RequestNetworkSettingsPacket(2225));
        $handshake->handle(new LoginPacket(2225, self::JWT));

        $this->expectException(HandshakeException::class);
        $handshake->handle(new ResourcePackClientResponsePacket(4));
    }

    public function testCompleteAuthenticationReleasesLoginSuccess(): void
    {
        $handshake = $this->handshake();
        $handshake->handle(new RequestNetworkSettingsPacket(2225));
        $handshake->handle(new LoginPacket(2225, self::JWT));

        $packets = $handshake->completeAuthentication();

        self::assertSame(ConnectionState::AwaitingResourcePacks, $handshake->getState());
        self::assertCount(3, $packets);
        self::assertInstanceOf(PlayStatusPacket::class, $packets[0]);
        self::assertSame(PlayStatus::LoginSuccess->value, $packets[0]->status);
    }

    public function testCompleteAuthenticationBeforeLoginThrows(): void
    {
        $handshake = $this->handshake();

        $this->expectException(HandshakeException::class);
        $handshake->completeAuthentication();
    }

    public function testCompleteAuthenticationTwiceThrows(): void
    {
        $handshake = $this->handshake();
        $handshake->handle(new RequestNetworkSettingsPacket(2225));
        $handshake->handle(new LoginPacket(2225, self::JWT));
        $handshake->completeAuthentication();

        $this->expectException(HandshakeException::class);
        $handshake->completeAuthentication();
    }

    public function testFailAuthenticationClosesConnection(): void
    {
        $handshake = $this->handshake();
        $handshake->handle(new RequestNetworkSettingsPacket(2225));
        $handshake->handle(new LoginPacket(2225, self::JWT));

        $packet = $handshake->failAuthentication(PlayStatus::LoginFailedInvalidTenant);

        self::assertSame(PlayStatus::LoginFailedInvalidTenant->value, $packet->status);
        self::assertSame(ConnectionState::Closed, $handshake->getState());
    }

    public function testResourcePackStackFinishedReachesReady(): void
    {
        $handshake = $this->handshake();
        $handshake->handle(new RequestNetworkSettingsPacket(2225));
        $handshake->handle(new LoginPacket(2225, self::JWT));
        $handshake->completeAuthentication();

        $result = $handshake->handle(ResourcePackClientResponsePacket::of(
            ResourcePackResponse::ResourcePackStackFinished
        ));

        self::assertSame(ConnectionState::Ready, $result->state);
        self::assertTrue($handshake->isReady());
    }

    public function testResourcePackCancelClosesConnection(): void
    {
        $handshake = $this->handshake();
        $handshake->handle(new RequestNetworkSettingsPacket(2225));
        $handshake->handle(new LoginPacket(2225, self::JWT));
        $handshake->completeAuthentication();

        $result = $handshake->handle(ResourcePackClientResponsePacket::of(
            ResourcePackResponse::Cancel
        ));

        self::assertTrue($result->isClosed());
    }

    public function testUnexpectedPacketInFirstStateThrows(): void
    {
        $this->expectException(HandshakeException::class);
        $this->handshake()->handle(new PlayStatusPacket(0));
    }

    public function testUnexpectedPacketInLoginStateThrows(): void
    {
        $handshake = $this->handshake();
        $handshake->handle(new RequestNetworkSettingsPacket(2225));

        $this->expectException(HandshakeException::class);
        $handshake->handle(new PlayStatusPacket(0));
    }

    public function testClosedStateRejectsFurtherPackets(): void
    {
        $handshake = $this->handshake();
        $handshake->handle(new RequestNetworkSettingsPacket(2193));

        $this->expectException(HandshakeException::class);
        $handshake->handle(new RequestNetworkSettingsPacket(2225));
    }

    public function testDescribeReportsStateAndProtocol(): void
    {
        $text = $this->handshake()->describe();

        self::assertStringContainsString('awaiting_request_network_settings', $text);
        self::assertStringContainsString((string) ProtocolVersion::PROTOCOL, $text);
    }
}

