<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\support;

use PickaxeMP\exception\TransportException;
use PickaxeMP\transport\ConnectionInterface;
use PickaxeMP\transport\TransportInterface;
use PickaxeMP\transport\TransportListenerInterface;
use PickaxeMP\transport\TransportStats;

/**
 * Bellek içi sahte taşıyıcı.
 *
 * Amaç: TransportInterface sözleşmesinin doğru davrandığını ağ olmadan
 * kanıtlamak. Gerçek NetherNet/RakNet davranışı KANITLAMAZ.
 */
final class FakeTransport implements TransportInterface
{
    private bool $running = false;

    private ?TransportListenerInterface $listener = null;

    /** @var array<string, FakeConnection> */
    private array $connections = [];

    public int $bytesSent = 0;

    public int $bytesReceived = 0;

    public int $payloadsSent = 0;

    public int $payloadsReceived = 0;

    public int $totalConnections = 0;

    public int $rejected = 0;

    public int $dropped = 0;

    /**
     * Güvenilir gönderimden **önce** çağrılacak test kancası.
     *
     * Gönderim hatası taklit etmek için kullanılır: kanca istisna
     * fırlatırsa taşıyıcı hata yoluna düşer.
     *
     * @var null|callable(string $connectionId, string $payload): void
     */
    public $beforeSendReliable = null;

    public function start(): void
    {
        if ($this->running) {
            throw TransportException::alreadyRunning();
        }

        $this->running = true;
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function tick(float $deltaSeconds): void
    {
        if (!$this->running) {
            throw TransportException::notRunning();
        }
    }

    public function setListener(TransportListenerInterface $listener): void
    {
        $this->listener = $listener;
    }

    public function sendReliable(string $connectionId, string $payload): void
    {
        $connection = $this->requireConnection($connectionId);

        if ($this->beforeSendReliable !== null) {
            ($this->beforeSendReliable)($connectionId, $payload);
        }

        $this->bytesSent += strlen($payload);
        $this->payloadsSent++;
        $connection->sentReliable[] = $payload;
    }

    public function sendUnreliable(string $connectionId, string $payload): void
    {
        $connection = $this->requireConnection($connectionId);
        $this->bytesSent += strlen($payload);
        $this->payloadsSent++;
        $connection->sentUnreliable[] = $payload;
    }

    public function close(string $connectionId, string $reason = ''): void
    {
        if (!isset($this->connections[$connectionId])) {
            return;
        }

        $connection = $this->connections[$connectionId];
        $connection->closed = true;
        unset($this->connections[$connectionId]);

        $this->listener?->onConnectionClosed($connection, $reason);
    }

    public function getConnections(): array
    {
        return $this->connections;
    }

    public function getStats(): TransportStats
    {
        return new TransportStats(
            activeConnections: count($this->connections),
            totalConnections: $this->totalConnections,
            bytesReceived: $this->bytesReceived,
            bytesSent: $this->bytesSent,
            payloadsReceived: $this->payloadsReceived,
            payloadsSent: $this->payloadsSent,
            rejectedConnections: $this->rejected,
            droppedPayloads: $this->dropped
        );
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function getName(): string
    {
        return 'fake';
    }

    // --- Test yardımcıları (TransportInterface dışında) ---

    public function connect(string $id, ?array $identity = null): FakeConnection
    {
        $connection = new FakeConnection($id, $identity);
        $this->connections[$id] = $connection;
        $this->totalConnections++;

        $this->listener?->onConnectionOpen($connection);

        return $connection;
    }

    public function injectPayload(string $connectionId, string $payload): void
    {
        $connection = $this->requireConnection($connectionId);
        $this->bytesReceived += strlen($payload);
        $this->payloadsReceived++;

        $this->listener?->onPayload($connection, $payload);
    }

    private function requireConnection(string $id): FakeConnection
    {
        if (!isset($this->connections[$id])) {
            throw TransportException::unknownConnection($id);
        }

        return $this->connections[$id];
    }
}
