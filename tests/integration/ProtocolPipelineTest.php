<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\integration;

use PHPUnit\Framework\TestCase;
use PickaxeMP\protocol\compression\CompressionAlgorithm;
use PickaxeMP\protocol\compression\CompressionManager;
use PickaxeMP\protocol\compression\CompressionSettings;
use PickaxeMP\protocol\ConnectionState;
use PickaxeMP\protocol\packet\PacketContext;
use PickaxeMP\protocol\packet\PacketDirection;
use PickaxeMP\protocol\packet\packets\LoginPacket;
use PickaxeMP\protocol\packet\packets\NetworkSettingsPacket;
use PickaxeMP\protocol\packet\packets\RequestNetworkSettingsPacket;
use PickaxeMP\protocol\packet\packets\ResourcePackClientResponsePacket;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\PacketRegistry;
use PickaxeMP\protocol\packet\types\ResourcePackResponse;
use PickaxeMP\protocol\ProtocolEngine;
use PickaxeMP\protocol\ProtocolHandshake;
use PickaxeMP\protocol\ProtocolVersion;
use PickaxeMP\transport\ConnectionInterface;
use PickaxeMP\transport\TransportListenerInterface;
use PickaxeMP\Tests\support\FakeTransport;

/**
 * Taşıyıcıdan bağımsız protokol testi.
 *
 * `ProtocolEngine` gerçek bir sokete veya sidecar'a bağımlı değildir; bu test
 * bunu kanıtlar. `FakeTransport` üzerinden tam el sıkışma akışı yürütülür.
 *
 * DÜRÜST SINIR: Bu test gerçek bir Bedrock istemcisinin bağlanabildiğini
 * KANITLAMAZ. Yalnızca katmanlar arası sözleşmeyi doğrular.
 */
final class ProtocolPipelineTest extends TestCase
{
    /** @var list<string> */
    private array $received = [];

    private FakeTransport $transport;

    private ProtocolEngine $engine;

    private ProtocolHandshake $handshake;

    private string $connectionId = 'c-test';

    protected function setUp(): void
    {
        $settings = new CompressionSettings(CompressionAlgorithm::Zlib, 256);

        $this->engine = new ProtocolEngine(
            PacketRegistry::withSupportedPackets(),
            new CompressionManager($settings)
        );

        $this->handshake = new ProtocolHandshake($settings);

        $this->transport = new FakeTransport();
        $this->transport->setListener($this->listener());
        $this->transport->start();
        $this->transport->connect($this->connectionId);
    }

    private function listener(): TransportListenerInterface
    {
        return new class ($this) implements TransportListenerInterface {
            public function __construct(private ProtocolPipelineTest $test)
            {
            }

            public function onConnectionOpen(ConnectionInterface $connection): void
            {
            }

            public function onPayload(ConnectionInterface $connection, string $payload): void
            {
                $this->test->acceptPayload($payload);
            }

            public function onConnectionClosed(ConnectionInterface $connection, string $reason): void
            {
            }
        };
    }

    /**
     * Taşıyıcıdan gelen ham baytı çözer (test dinleyicisinden çağrılır).
     */
    public function acceptPayload(string $payload): void
    {
        $packet = $this->engine->decode(
            $payload,
            new PacketContext($this->connectionId, PacketDirection::ClientToServer)
        );

        $result = $this->handshake->handle($packet);

        foreach ($result->responses as $response) {
            $this->transport->sendReliable(
                $this->connectionId,
                $this->engine->encode($response)
            );
        }

        $this->received[] = $packet::class;
    }

    private function clientSends(PacketInterface $packet): void
    {
        $this->transport->injectPayload($this->connectionId, $this->engine->encode($packet));
    }

    private function serverPackets(): array
    {
        $connection = $this->transport->getConnections()[$this->connectionId];

        return array_map(
            fn (string $bytes): PacketInterface => $this->engine->decode(
                $bytes,
                new PacketContext($this->connectionId, PacketDirection::ServerToClient)
            ),
            $connection->sentReliable
        );
    }

    public function testFullHandshakeStopsBeforeAuthentication(): void
    {
        // 1) İstemci ilk paketi gönderir.
        $this->clientSends(new RequestNetworkSettingsPacket(ProtocolVersion::PROTOCOL));

        self::assertSame(ConnectionState::AwaitingLogin, $this->handshake->getState());

        // 2) Sunucu NetworkSettings ile yanıtlar.
        $responses = $this->serverPackets();

        self::assertCount(1, $responses);
        self::assertInstanceOf(NetworkSettingsPacket::class, $responses[0]);
        self::assertSame(256, $responses[0]->compressionThreshold);

        // 3) İstemci Login gönderir.
        $this->clientSends(new LoginPacket(
            ProtocolVersion::PROTOCOL,
            '{"chain":["a.b.c","d.e.f"]}'
        ));

        // 4) Kimlik doğrulaması yapılmadan Ready'ye GEÇİLMEMELİ.
        self::assertSame(ConnectionState::AwaitingAuthentication, $this->handshake->getState());
        self::assertFalse($this->handshake->isReady());
        self::assertSame(
            [RequestNetworkSettingsPacket::class, LoginPacket::class],
            $this->received
        );
    }

    public function testAuthenticationCompletionThenReady(): void
    {
        $this->clientSends(new RequestNetworkSettingsPacket(ProtocolVersion::PROTOCOL));
        $this->clientSends(new LoginPacket(ProtocolVersion::PROTOCOL, '{"chain":["a.b.c","d.e.f"]}'));

        // Phase 3 devraldı varsayımıyla (testte) tamamlıyoruz.
        $packets = $this->handshake->completeAuthentication();

        self::assertSame(ConnectionState::AwaitingResourcePacks, $this->handshake->getState());
        self::assertCount(3, $packets);

        $this->clientSends(ResourcePackClientResponsePacket::of(
            ResourcePackResponse::ResourcePackStackFinished
        ));

        self::assertTrue($this->handshake->isReady());
    }

    public function testCorruptPayloadThroughTransportIsRejected(): void
    {
        $this->clientSends(new RequestNetworkSettingsPacket(ProtocolVersion::PROTOCOL));

        $this->expectException(\PickaxeMP\protocol\ProtocolException::class);
        $this->transport->injectPayload($this->connectionId, "\xF7\x00\x00");
    }

    public function testEngineDoesNotDependOnTransport(): void
    {
        // Aynı motor, soket olmadan doğrudan da kullanılabilir.
        $bytes = $this->engine->encode(new RequestNetworkSettingsPacket(2225));

        $packet = $this->engine->decode(
            $bytes,
            new PacketContext('', PacketDirection::ClientToServer)
        );

        self::assertInstanceOf(RequestNetworkSettingsPacket::class, $packet);
        self::assertSame(2225, $packet->clientNetworkVersion);
    }

    public function testCompressedRoundTripThroughEngine(): void
    {
        $large = new LoginPacket(2225, str_repeat('a', 2000));

        $compressed = $this->engine->encodeCompressed($large);

        self::assertNotSame(
            $this->engine->encode($large),
            $compressed,
            'Esik ustu yuk sikistirilmaliydi'
        );

        $decoded = $this->engine->decodeCompressed(
            $compressed,
            new PacketContext('', PacketDirection::ClientToServer)
        );

        self::assertInstanceOf(LoginPacket::class, $decoded);
        self::assertSame($large->connectionRequest, $decoded->connectionRequest);
    }
}
