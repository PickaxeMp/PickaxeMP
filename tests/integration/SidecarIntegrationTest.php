<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\integration;

use PHPUnit\Framework\TestCase;
use PickaxeMP\transport\ConnectionInterface;
use PickaxeMP\transport\sidecar\SidecarProcess;
use PickaxeMP\transport\sidecar\SidecarTransport;
use PickaxeMP\transport\TransportListenerInterface;

/**
 * PHP ↔ Rust entegrasyon testleri.
 *
 * Bu testler GERÇEK sidecar ikilisini çalıştırır. İkili yoksa testler
 * atlanır — "geçti" sayılmaz, `markTestSkipped` ile açıkça bildirilir.
 *
 * Bu testler gerçek bir Bedrock istemcisinin bağlanabildiğini KANITLAMAZ.
 * Yalnızca süreç yönetimi, el sıkışma ve HTTP signaling yüzeyini doğrular.
 */
final class SidecarIntegrationTest extends TestCase
{
    private string $root;

    private string $binary;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
        $this->binary = SidecarProcess::defaultBinaryPath($this->root);

        if (!is_file($this->binary)) {
            self::markTestSkipped(
                'Sidecar ikilisi yok. Once `cargo build --release` calistirin: ' . $this->binary
            );
        }
    }

    public function testProcessStartsAndReportsHandshake(): void
    {
        $process = $this->makeProcess();
        $process->start();

        try {
            self::assertTrue($process->isRunning());
            self::assertGreaterThan(0, $process->getIpcPort());
            self::assertSame(64, strlen($process->getToken()), 'Token 32 bayt hex olmali');
            self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $process->getToken());
        } finally {
            $process->stop();
        }

        self::assertFalse($process->isRunning());
    }

    public function testTransportCompletesIpcHandshake(): void
    {
        $transport = $this->startTransport();

        try {
            self::assertTrue($transport->isRunning());
            self::assertTrue($transport->healthCheck());
            self::assertSame('nethernet', $transport->getName());
            self::assertSame(0, $transport->getStats()->activeConnections);
        } finally {
            $transport->stop();
        }

        self::assertFalse($transport->isRunning());
    }

    public function testSignalingAddressIsReported(): void
    {
        $transport = $this->startTransport();

        try {
            $address = $transport->getSignalingAddress();

            self::assertNotNull($address, 'Sidecar signaling adresini bildirmeliydi');
            self::assertMatchesRegularExpression('/^127\.0\.0\.1:\d+$/', $address);
        } finally {
            $transport->stop();
        }
    }

    public function testJoinEndpointReturnsServerData(): void
    {
        $transport = $this->startTransport();

        try {
            $address = $transport->getSignalingAddress();
            self::assertNotNull($address);

            $response = $this->httpGet($address, '/v1/join');

            self::assertStringContainsString('200', $this->statusLine($response));
            self::assertStringContainsString('"protocol":2225', $response);
            self::assertStringContainsString('"maxPlayers"', $response);
            self::assertStringContainsString('"level"', $response);
        } finally {
            $transport->stop();
        }
    }

    public function testUnknownJoinPathIsNotServerData(): void
    {
        $transport = $this->startTransport();

        try {
            $address = $transport->getSignalingAddress();
            self::assertNotNull($address);

            $response = $this->httpGet($address, '/v1/baska');

            self::assertStringNotContainsString('"maxPlayers"', $response);
        } finally {
            $transport->stop();
        }
    }

    public function testTickIsIdleSafe(): void
    {
        $transport = $this->startTransport();

        try {
            // Hiç istemci yokken tick çökmemeli.
            for ($i = 0; $i < 5; $i++) {
                $transport->tick(0.05);
            }

            self::assertSame(0, $transport->getStats()->activeConnections);
        } finally {
            $transport->stop();
        }
    }

    public function testStopIsIdempotent(): void
    {
        $transport = $this->startTransport();
        $transport->stop();
        $transport->stop();

        self::assertFalse($transport->isRunning());
    }

    public function testTransportRejectsSendToUnknownConnection(): void
    {
        $transport = $this->startTransport();

        try {
            $this->expectException(\PickaxeMP\exception\TransportException::class);
            $transport->sendReliable('yok-boyle-baglanti', 'x');
        } finally {
            $transport->stop();
        }
    }

    public function testListenerReceivesNoPhantomConnections(): void
    {
        $listener = new class implements TransportListenerInterface {
            public int $opened = 0;
            public int $closed = 0;
            public int $payloads = 0;
            /** @var list<string> */
            public array $closeReasons = [];

            public function onConnectionOpen(ConnectionInterface $connection): void
            {
                $this->opened++;
            }

            public function onPayload(ConnectionInterface $connection, string $payload): void
            {
                $this->payloads++;
            }

            public function onConnectionClosed(ConnectionInterface $connection, string $reason): void
            {
                $this->closed++;
                $this->closeReasons[] = $reason;
            }
        };

        $transport = $this->startTransport();
        $transport->setListener($listener);

        try {
            for ($i = 0; $i < 3; $i++) {
                $transport->tick(0.05);
            }

            self::assertSame(0, $listener->opened);
            self::assertSame(0, $listener->payloads);
        } finally {
            $transport->stop();
        }
    }

    private function makeProcess(): SidecarProcess
    {
        $identity = $this->temporaryIdentityPath();

        return new SidecarProcess($this->binary, [
            '--signaling', '127.0.0.1:0',
            '--media', '127.0.0.1:0',
            '--identity-path', $identity,
            '--identity-domain', 'test.local',
            '--protocol-version', '2225',
            '--game-version', '1.26.60',
            '--max-players', '10',
            // Ağ erişimi gerektirmeyen yerel test.
            '--no-token-verification',
        ], 30000);
    }

    /**
     * Taşıyıcı sürecin sahibidir; bu yüzden süreç ÖNCEDEN başlatılmaz.
     */
    private function startTransport(): SidecarTransport
    {
        $transport = new SidecarTransport($this->makeProcess());
        $transport->start();

        return $transport;
    }

    private function temporaryIdentityPath(): string
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pickaxe-sidecar-' . bin2hex(random_bytes(6));

        if (!is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        return $directory . DIRECTORY_SEPARATOR . 'identity.pem';
    }

    private function httpGet(string $address, string $path): string
    {
        $socket = @stream_socket_client("tcp://{$address}", $errno, $errstr, 5.0);

        self::assertIsResource($socket, "HTTP baglantisi kurulamadi: {$errstr} ({$errno})");

        stream_set_timeout($socket, 5);
        fwrite($socket, "GET {$path} HTTP/1.1\r\nHost: {$address}\r\nConnection: close\r\n\r\n");

        $response = '';
        $deadline = microtime(true) + 8.0;

        while (microtime(true) < $deadline) {
            $chunk = fread($socket, 8192);

            if ($chunk === false) {
                break;
            }

            if ($chunk === '') {
                $meta = stream_get_meta_data($socket);

                if ($meta['eof'] || $meta['timed_out']) {
                    break;
                }

                continue;
            }

            $response .= $chunk;
        }

        fclose($socket);

        return $response;
    }

    private function statusLine(string $response): string
    {
        $lines = explode("\r\n", $response);

        return $lines[0] ?? '';
    }
}
