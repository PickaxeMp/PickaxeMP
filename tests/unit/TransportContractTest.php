<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit;

use PHPUnit\Framework\TestCase;
use PickaxeMP\exception\TransportException;
use PickaxeMP\transport\ConnectionInterface;
use PickaxeMP\transport\TransportListenerInterface;
use PickaxeMP\Tests\support\FakeTransport;

/**
 * Taşıyıcı sözleşmesinin davranış testi.
 *
 * Not: Bu testler FakeTransport üzerinde çalışır. Gerçek NetherNet/RakNet
 * davranışını KANITLAMAZ.
 */
final class TransportContractTest extends TestCase
{
    private FakeTransport $transport;

    private SpyListener $listener;

    protected function setUp(): void
    {
        $this->transport = new FakeTransport();
        $this->listener = new SpyListener();
        $this->transport->setListener($this->listener);
        $this->transport->start();
    }

    public function testStartsAndReportsRunning(): void
    {
        self::assertTrue($this->transport->isRunning());
        self::assertSame('fake', $this->transport->getName());
    }

    public function testStartingTwiceThrows(): void
    {
        $this->expectException(TransportException::class);
        $this->transport->start();
    }

    public function testTickWhenStoppedThrows(): void
    {
        $this->transport->stop();

        $this->expectException(TransportException::class);
        $this->transport->tick(0.05);
    }

    public function testConnectionOpenIsReported(): void
    {
        $connection = $this->transport->connect('c1');

        self::assertSame([$connection], $this->listener->opened);
        self::assertSame(1, $this->transport->getStats()->activeConnections);
    }

    public function testIdentityIsPassedThrough(): void
    {
        $identity = ['xuid' => '123', 'uuid' => 'abc'];
        $connection = $this->transport->connect('c1', $identity);

        self::assertSame($identity, $connection->getIdentity());
    }

    public function testUnverifiedConnectionHasNoIdentity(): void
    {
        $connection = $this->transport->connect('c1');

        self::assertNull($connection->getIdentity());
    }

    public function testPayloadIsDeliveredToListener(): void
    {
        $connection = $this->transport->connect('c1');
        $this->transport->injectPayload('c1', "\x01\x02\x03");

        self::assertCount(1, $this->listener->payloads);
        self::assertSame($connection, $this->listener->payloads[0][0]);
        self::assertSame("\x01\x02\x03", $this->listener->payloads[0][1]);
    }

    public function testSendReliableRecordsPayload(): void
    {
        $connection = $this->transport->connect('c1');
        $this->transport->sendReliable('c1', 'merhaba');

        self::assertSame(['merhaba'], $connection->sentReliable);
        self::assertSame(1, $this->transport->getStats()->payloadsSent);
        self::assertSame(7, $this->transport->getStats()->bytesSent);
    }

    public function testSendUnreliableUsesSeparateChannel(): void
    {
        $connection = $this->transport->connect('c1');
        $this->transport->sendUnreliable('c1', 'hareket');

        self::assertSame(['hareket'], $connection->sentUnreliable);
        self::assertSame([], $connection->sentReliable);
    }

    public function testSendToUnknownConnectionThrows(): void
    {
        $this->expectException(TransportException::class);
        $this->transport->sendReliable('yok', 'x');
    }

    public function testCloseNotifiesListenerAndRemovesConnection(): void
    {
        $connection = $this->transport->connect('c1');
        $this->transport->close('c1', 'test');

        self::assertTrue($connection->closed);
        self::assertSame([], $this->transport->getConnections());
        self::assertCount(1, $this->listener->closed);
        self::assertSame('test', $this->listener->closed[0][1]);
    }

    public function testCloseUnknownConnectionIsNoOp(): void
    {
        $this->transport->close('yok', 'test');

        self::assertSame([], $this->listener->closed);
    }

    public function testStatsAccumulate(): void
    {
        $this->transport->connect('c1');
        $this->transport->connect('c2');
        $this->transport->injectPayload('c1', 'abc');

        $stats = $this->transport->getStats();

        self::assertSame(2, $stats->activeConnections);
        self::assertSame(2, $stats->totalConnections);
        self::assertSame(3, $stats->bytesReceived);
        self::assertSame(1, $stats->payloadsReceived);
    }

    public function testStatsToArrayHasAllKeys(): void
    {
        $array = $this->transport->getStats()->toArray();

        foreach ([
            'active_connections',
            'total_connections',
            'bytes_received',
            'bytes_sent',
            'payloads_received',
            'payloads_sent',
            'rejected_connections',
            'dropped_payloads',
        ] as $key) {
            self::assertArrayHasKey($key, $array);
        }
    }
}

/**
 * Test yardımcı dinleyici.
 */
final class SpyListener implements TransportListenerInterface
{
    /** @var list<ConnectionInterface> */
    public array $opened = [];

    /** @var list<array{0: ConnectionInterface, 1: string}> */
    public array $payloads = [];

    /** @var list<array{0: ConnectionInterface, 1: string}> */
    public array $closed = [];

    public function onConnectionOpen(ConnectionInterface $connection): void
    {
        $this->opened[] = $connection;
    }

    public function onPayload(ConnectionInterface $connection, string $payload): void
    {
        $this->payloads[] = [$connection, $payload];
    }

    public function onConnectionClosed(ConnectionInterface $connection, string $reason): void
    {
        $this->closed[] = [$connection, $reason];
    }
}
