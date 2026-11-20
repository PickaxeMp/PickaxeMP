<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit;

use PHPUnit\Framework\TestCase;
use PickaxeMP\exception\TransportException;
use PickaxeMP\transport\ConnectionInterface;
use PickaxeMP\transport\sidecar\IpcChallenge;
use PickaxeMP\transport\sidecar\IpcFrame;
use PickaxeMP\transport\sidecar\SidecarMessage;
use PickaxeMP\transport\sidecar\SidecarTransport;
use PickaxeMP\transport\TransportListenerInterface;
use PickaxeMP\Tests\support\FakeIpcSocket;
use PickaxeMP\Tests\support\FakeSidecarProcess;

/**
 * `SidecarTransport` hata yolları birim testleri.
 *
 * Bu testler Rust ikilisini ÇALIŞTIRMAZ; sahte soket/süreç üzerinden
 * I/O hata yollarını deterministik olarak tetikler. Gerçek PHP↔Rust
 * bütünleşmesi `tests/integration/SidecarIntegrationTest.php` içindedir.
 */
final class SidecarTransportTest extends TestCase
{
    private const NONCE = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private FakeIpcSocket $socket;

    private FakeSidecarProcess $process;

    private SidecarTransport $transport;

    /** @var list<string> */
    public array $closeReasons = [];

    public int $opened = 0;

    protected function setUp(): void
    {
        $this->socket = new FakeIpcSocket();
        $this->process = new FakeSidecarProcess();
        $this->closeReasons = [];
        $this->opened = 0;

        $this->transport = new SidecarTransport(
            $this->process,
            fn (int $port): FakeIpcSocket => $this->socket
        );

        $this->transport->setListener(new class ($this) implements TransportListenerInterface {
            public function __construct(private readonly SidecarTransportTest $test)
            {
            }

            public function onConnectionOpen(ConnectionInterface $connection): void
            {
                $this->test->opened++;
            }

            public function onPayload(ConnectionInterface $connection, string $payload): void
            {
            }

            public function onConnectionClosed(ConnectionInterface $connection, string $reason): void
            {
                $this->test->closeReasons[] = $reason;
            }
        });
    }

    public function testStartCompletesHelloHandshakeWithProofOnly(): void
    {
        $this->boot();

        $frames = $this->decodeFrames($this->socket->writtenBytes());

        self::assertCount(1, $frames);
        self::assertSame(SidecarMessage::HELLO_ACK, $frames[0]->type);

        $body = SidecarMessage::decodeJson($frames[0]->payload);

        self::assertSame(['proof'], array_keys($body));
        self::assertSame(
            IpcChallenge::createProof($this->process->getToken(), self::NONCE),
            $body['proof']
        );
        self::assertStringNotContainsString($this->process->getToken(), $frames[0]->payload);
    }

    public function testStartFailureStopsChildProcess(): void
    {
        // HELLO yerine yanlış çerçeve tipi gönder: el sıkışma reddedilmeli.
        $this->socket->push(
            (new IpcFrame(
                SidecarMessage::CONNECTION_OPEN,
                1,
                SidecarMessage::encodeJson(['connectionId' => 'x'])
            ))->encode()
        );

        try {
            $this->transport->start();
            self::fail('Beklenmeyen basari');
        } catch (TransportException) {
            // beklenen
        }

        self::assertTrue($this->socket->closed, 'Soket kapatilmalı');
        self::assertGreaterThan(0, $this->process->stopCalls, 'Cocuk surec durdurulmali');
        self::assertFalse($this->transport->isRunning());
    }

    public function testPartialWriteIsResumedWithoutCorruptingFrames(): void
    {
        $this->boot();
        $this->openConnection('c1');

        // Soket her yazmada en fazla 3 bayt kabul ediyor: kısmi yazma.
        $this->socket->maxWriteChunk = 3;

        $this->transport->sendReliable('c1', 'merhaba-dunya');

        // Kalan baytlar tick'lerle yazılmalı.
        for ($i = 0; $i < 200 && $this->transport->getPendingOutboundBytes() > 0; $i++) {
            $this->transport->tick(0.01);
        }

        self::assertSame(0, $this->transport->getPendingOutboundBytes());

        $frames = $this->decodeFrames($this->socket->writtenBytes());

        // HELLO_ACK + PAYLOAD_OUT
        self::assertCount(2, $frames);
        self::assertSame(SidecarMessage::PAYLOAD_OUT, $frames[1]->type);

        $payload = SidecarMessage::decodePayload($frames[1]->payload);

        self::assertSame(SidecarMessage::CHANNEL_RELIABLE, $payload['channel']);
        self::assertSame('c1', $payload['connectionId']);
        self::assertSame('merhaba-dunya', $payload['data']);
    }

    public function testBlockedSocketKeepsBytesQueuedUntilNextTick(): void
    {
        $this->boot();
        $this->openConnection('c1');

        // Soket hiçbir bayt kabul etmiyor (tampon dolu).
        $this->socket->maxWriteChunk = 0;

        $this->transport->sendReliable('c1', 'kuyrukta-kalsin');

        self::assertGreaterThan(0, $this->transport->getPendingOutboundBytes());
        self::assertTrue($this->transport->isRunning(), 'Dolu soket transportu dusurmeli');

        // Soket boşalır: kalan baytlar bir sonraki tick'te yazılır.
        $this->socket->maxWriteChunk = PHP_INT_MAX;
        $this->transport->tick(0.01);

        self::assertSame(0, $this->transport->getPendingOutboundBytes());

        $frames = $this->decodeFrames($this->socket->writtenBytes());
        self::assertCount(2, $frames);
        self::assertSame(SidecarMessage::PAYLOAD_OUT, $frames[1]->type);

        $payload = SidecarMessage::decodePayload($frames[1]->payload);
        self::assertSame('kuyrukta-kalsin', $payload['data']);
    }

    public function testWriteErrorFailsClosedAndStopsChildProcess(): void
    {
        $this->boot();
        $this->openConnection('c1');

        $this->socket->failWrites = true;

        try {
            $this->transport->sendReliable('c1', 'veri');
            self::fail('Beklenmeyen basari');
        } catch (TransportException) {
            // beklenen
        }

        self::assertFalse($this->transport->isRunning(), 'Yazma hatasinda transport durmali');
        self::assertTrue($this->socket->closed, 'Soket kapatilmalı');
        self::assertGreaterThan(0, $this->process->stopCalls, 'Cocuk surec durdurulmali');
        self::assertContains('ipc_write_failed', $this->closeReasons, 'Baglanti koptu bildirilmeli');
        self::assertCount(0, $this->transport->getConnections());
    }

    public function testOutboundQueueOverflowFailsClosed(): void
    {
        $this->boot();
        $this->openConnection('c1');

        // Soket hiçbir bayt kabul etmiyor: kuyruk dolar.
        $this->socket->maxWriteChunk = 0;

        $chunk = str_repeat('x', 900 * 1024);
        $failure = null;

        try {
            for ($i = 0; $i < 20; $i++) {
                $this->transport->sendReliable('c1', $chunk);
            }
        } catch (TransportException $exception) {
            $failure = $exception;
        }

        self::assertNotNull($failure, 'Kuyruk tasmasi hata vermeli');
        self::assertFalse($this->transport->isRunning());
        self::assertGreaterThan(0, $this->process->stopCalls, 'Cocuk surec durdurulmali');
        self::assertSame(0, $this->transport->getPendingOutboundBytes(), 'Kuyruk temizlenmeli');
    }

    public function testMalformedInboundFrameFailsClosed(): void
    {
        $this->boot();

        // Bozuk sihirli deger: cerceve cozulemez.
        $this->socket->push('ZZZZnot-a-valid-frame-header');

        try {
            $this->transport->tick(0.05);
            self::fail('Beklenmeyen basari');
        } catch (TransportException) {
            // beklenen
        }

        self::assertFalse($this->transport->isRunning());
        self::assertGreaterThan(0, $this->process->stopCalls, 'Cocuk surec durdurulmali');
    }

    public function testOversizedReadBufferFailsClosed(): void
    {
        $this->boot();

        // 1 MiB'lik bir cerceve basligi ilan et, ama govdeyi gonderme.
        $header = pack('nCCJN', IpcFrame::MAGIC, IpcFrame::VERSION, 0x10, 1, IpcFrame::MAX_PAYLOAD);
        $this->socket->push($header . str_repeat('a', IpcFrame::MAX_PAYLOAD + 1024));

        $failure = null;

        try {
            for ($i = 0; $i < 64; $i++) {
                $this->transport->tick(0.05);
            }
        } catch (TransportException $exception) {
            $failure = $exception;
        }

        self::assertNotNull($failure, 'Tampon siniri hata vermeli');
        self::assertFalse($this->transport->isRunning());
        self::assertGreaterThan(0, $this->process->stopCalls, 'Cocuk surec durdurulmali');
    }

    public function testEofStopsChildProcess(): void
    {
        $this->boot();
        $this->openConnection('c1');

        $this->socket->eof = true;

        $this->transport->tick(0.05);

        self::assertFalse($this->transport->isRunning());
        self::assertGreaterThan(0, $this->process->stopCalls, 'Cocuk surec durdurulmali');
        self::assertContains('sidecar_disconnected', $this->closeReasons);
    }

    public function testUnknownFrameTypeIsIgnored(): void
    {
        $this->boot();

        $this->socket->push((new IpcFrame(0x7F, 9, 'bilinmeyen'))->encode());

        $this->transport->tick(0.05);

        self::assertTrue($this->transport->isRunning(), 'Bilinmeyen tip akisi bozmamali');
        self::assertSame(0, $this->process->stopCalls);
    }

    public function testStopStopsChildProcess(): void
    {
        $this->boot();
        $this->openConnection('c1');

        $this->transport->stop();

        self::assertFalse($this->transport->isRunning());
        self::assertGreaterThan(0, $this->process->stopCalls);
        self::assertTrue($this->socket->closed);
    }

    public function testSendToUnknownConnectionIsRejected(): void
    {
        $this->boot();

        $this->expectException(TransportException::class);

        $this->transport->sendReliable('yok', 'x');
    }

    /**
     * Taşıyıcıyı sahte HELLO mesajıyla başlatır.
     */
    private function boot(): void
    {
        $this->socket->push(
            (new IpcFrame(
                SidecarMessage::HELLO,
                1,
                SidecarMessage::encodeJson([
                    'nonce' => self::NONCE,
                    'version' => '1.0.0',
                    'pid' => 4242,
                ])
            ))->encode()
        );

        $this->transport->start();
    }

    /**
     * Sidecar'dan bağlantı açma bildirimi gönderir.
     */
    private function openConnection(string $id): void
    {
        $this->socket->push(
            (new IpcFrame(
                SidecarMessage::CONNECTION_OPEN,
                2,
                SidecarMessage::encodeJson([
                    'connectionId' => $id,
                    'remoteAddress' => '127.0.0.1:5000',
                ])
            ))->encode()
        );

        $this->transport->tick(0.05);

        self::assertSame(1, $this->opened);
    }

    /**
     * Bayt dizisini çerçevelere çözer.
     *
     * @return list<IpcFrame>
     */
    private function decodeFrames(string $bytes): array
    {
        $buffer = $bytes;
        $frames = [];

        while (true) {
            $frame = IpcFrame::decode($buffer);

            if ($frame === null) {
                break;
            }

            $frames[] = $frame;
        }

        return $frames;
    }
}
