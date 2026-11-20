<?php

declare(strict_types=1);

namespace PickaxeMP\transport\sidecar;

use Closure;
use PickaxeMP\exception\TransportException;
use PickaxeMP\transport\ConnectionInterface;
use PickaxeMP\transport\TransportInterface;
use PickaxeMP\transport\TransportListenerInterface;
use PickaxeMP\transport\TransportStats;

/**
 * Rust NetherNet sidecar'ına bağlanan PHP taşıyıcısı.
 *
 * Sorumluluk: IPC soketini yönetmek, çerçeveleri kodlamak/çözmek ve gelen
 * olayları `TransportListenerInterface`'e iletmek. Bedrock paketlerini
 * ÇÖZMEZ; yalnızca ham bayt taşır.
 *
 * ## Giden kuyruk (outbound queue)
 *
 * IPC soketi **bloke etmeyen** moddadır. `fwrite()` çağrısı baytların
 * yalnızca bir kısmını yazabilir. Bu durumda çerçevenin geri kalanı
 * `outboundQueue` içinde tutulur ve sonraki `tick()`'te yazılmaya devam
 * edilir. Böylece tel üzerindeki bayt dizisi **her zaman** çerçeve
 * sınırlarına uyar; Rust tarafındaki `BytesMut` tamponu eksik çerçeveyi
 * tamamlar.
 *
 * Kuyruk sınırlıdır (`MAX_OUTBOUND_BYTES`). Sınır aşılırsa taşıyıcı
 * **fail-closed** kapanır: soket kapatılır, bağlantılar "koptu" olarak
 * bildirilir ve Rust çocuk süreci durdurulur.
 *
 * DÜRÜST SINIR: Gerçek Bedrock istemcisi bağlanmadan bu sınıfın uçtan uca
 * doğruluğu kanıtlanmış sayılmaz. IPC ve el sıkışma testleri bunu kapsamaz.
 */
final class SidecarTransport implements TransportInterface
{
    /** Giden kuyruğun toplam bayt sınırı (8 MiB). */
    public const MAX_OUTBOUND_BYTES = 8 * 1024 * 1024;

    /** Tek tick içinde yazılacak azami bayt (1 MiB). */
    private const MAX_FLUSH_BYTES_PER_TICK = 1024 * 1024;

    /**
     * Tek tick içinde yapılacak azami `write()` çağrısı.
     *
     * Soket çok kısmi yazıyorsa (ör. 1 bayt/çağrı) sonsuz döngüye
     * girmemek için çağrı sayısı sınırlanır; kalan baytlar sonraki
     * tick'e kalır.
     */
    private const MAX_FLUSH_WRITES_PER_TICK = 64;

    /** Gelen tek okuma isteğinin boyutu. */
    private const READ_CHUNK_SIZE = 65536;

    private ?IpcSocketInterface $socket = null;

    private bool $running = false;

    private string $readBuffer = '';

    private int $requestId = 0;

    /**
     * Yazılmayı bekleyen çerçeveler.
     *
     * Her giriş: kodlanmış baytlar, yazılan offset, yük bayt sayısı ve
     * bunun bir "payload" sayılıp sayılmayacağı.
     *
     * @var list<array{bytes: string, offset: int, payloadBytes: int, isPayload: bool}>
     */
    private array $outboundQueue = [];

    /** Kuyruktaki toplam bayt. */
    private int $outboundBytes = 0;

    /** @var array<string, SidecarConnection> */
    private array $connections = [];

    private ?TransportListenerInterface $listener = null;

    private int $totalConnections = 0;

    private int $rejectedConnections = 0;

    private int $bytesSent = 0;

    private int $bytesReceived = 0;

    private int $payloadsSent = 0;

    private int $payloadsReceived = 0;

    private int $droppedPayloads = 0;

    /**
     * @param SidecarProcessInterface $process Rust sidecar süreci
     * @param Closure(int): IpcSocketInterface|null $socketFactory Testler
     *        için soket üretimini değiştiren sehim; üretilmezse varsayılan
     *        `StreamIpcSocket::connect()` kullanılır.
     */
    public function __construct(
        private readonly SidecarProcessInterface $process,
        private readonly ?Closure $socketFactory = null
    ) {
    }

    public function start(): void
    {
        if ($this->running) {
            throw TransportException::alreadyRunning();
        }

        try {
            $this->process->start();

            $factory = $this->socketFactory
                ?? static fn (int $port): IpcSocketInterface => StreamIpcSocket::connect(
                    sprintf('tcp://127.0.0.1:%d', $port),
                    5.0
                );

            $this->socket = $factory($this->process->getIpcPort());
            $this->readBuffer = '';
            $this->outboundQueue = [];
            $this->outboundBytes = 0;
            $this->running = true;

            // Rust once challenge nonce gonderir; PHP kaniti yalnizca bu
            // nonce'a bagli olarak uretir. Paylasilan token sokete yazilmaz.
            $nonce = $this->awaitHello();
            $this->sendHelloAck($nonce);
        } catch (\Throwable $exception) {
            // Soket, kuyruk ve **cocuk surec** temizlenir; yalniz soket
            // kapatmak yetmez (orphan sidecar olusur).
            $this->failClosed('start_failed');

            throw $exception;
        }
    }

    public function stop(): void
    {
        $this->failClosed('transport_stopped');
    }

    public function tick(float $deltaSeconds): void
    {
        if (!$this->running || $this->socket === null) {
            throw TransportException::notRunning();
        }

        // 1) Bekleyen giden baytlari yaz. Yazma hatasi fail-closed'dir.
        $this->flushOutbound();

        // 2) Gelen baytlari oku.
        $chunk = @$this->socket->read(self::READ_CHUNK_SIZE);

        if ($chunk === false) {
            $this->failClosed('ipc_read_failed');

            throw TransportException::startFailed('IPC soketinden okunamadi');
        }

        if ($chunk === '') {
            // Bos okuma: uzak uc kapatti olabilir.
            if ($this->socket->eof()) {
                $this->failClosed('sidecar_disconnected');
            }

            return;
        }

        $this->readBuffer .= $chunk;

        if (strlen($this->readBuffer) > IpcFrame::MAX_PAYLOAD + IpcFrame::HEADER_SIZE * 2) {
            $this->failClosed('ipc_read_buffer_overflow');

            throw new TransportException('IPC tamponu siniri asildi');
        }

        while (true) {
            try {
                $frame = IpcFrame::decode($this->readBuffer);
            } catch (IpcProtocolException $exception) {
                $this->failClosed('ipc_protocol_error');

                throw TransportException::startFailed(
                    'IPC protokol hatasi: ' . $exception->getMessage()
                );
            }

            if ($frame === null) {
                break;
            }

            try {
                $this->handleFrame($frame);
            } catch (IpcProtocolException $exception) {
                $this->failClosed('ipc_protocol_error');

                throw TransportException::startFailed(
                    'IPC protokol hatasi: ' . $exception->getMessage()
                );
            }
        }
    }

    public function setListener(TransportListenerInterface $listener): void
    {
        $this->listener = $listener;
    }

    public function sendReliable(string $connectionId, string $payload): void
    {
        $this->sendPayload(SidecarMessage::CHANNEL_RELIABLE, $connectionId, $payload);
    }

    public function sendUnreliable(string $connectionId, string $payload): void
    {
        $this->sendPayload(SidecarMessage::CHANNEL_UNRELIABLE, $connectionId, $payload);
    }

    public function close(string $connectionId, string $reason = ''): void
    {
        if (!isset($this->connections[$connectionId])) {
            return;
        }

        $this->enqueueFrame(
            SidecarMessage::CLOSE_CONNECTION,
            SidecarMessage::encodeClose($connectionId, $reason)
        );

        $connection = $this->connections[$connectionId];
        unset($this->connections[$connectionId]);
        $connection->markClosed($reason);

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
            rejectedConnections: $this->rejectedConnections,
            droppedPayloads: $this->droppedPayloads
        );
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function getName(): string
    {
        return 'nethernet';
    }

    public function getProcess(): SidecarProcessInterface
    {
        return $this->process;
    }

    /**
     * El sıkışma tamamlandıktan sonra sidecar'ın bildirdiği signaling adresi.
     */
    public function getSignalingAddress(): ?string
    {
        return $this->process->getSignalingAddress();
    }

    /**
     * Sağlık kontrolü: sidecar süreci hâlâ ayakta mı?
     */
    public function healthCheck(): bool
    {
        return $this->running && $this->process->isRunning();
    }

    /**
     * Giden kuyruktaki bekleyen bayt sayısı (teşhis için).
     */
    public function getPendingOutboundBytes(): int
    {
        return $this->outboundBytes;
    }

    private function sendPayload(int $channel, string $connectionId, string $payload): void
    {
        if (!$this->running) {
            throw TransportException::notRunning();
        }

        if (!isset($this->connections[$connectionId])) {
            throw TransportException::unknownConnection($connectionId);
        }

        $this->enqueueFrame(
            SidecarMessage::PAYLOAD_OUT,
            SidecarMessage::encodePayload($channel, $connectionId, $payload),
            true
        );
    }

    /**
     * Çerçeveyi giden kuyruğa ekler ve yazmayı dener.
     *
     * @param bool $isPayload Bu çerçeve bir oyuncu yükü mü? (istatistik için)
     */
    private function enqueueFrame(int $type, string $payload, bool $isPayload = false): void
    {
        if (!$this->running || $this->socket === null) {
            throw TransportException::notRunning();
        }

        $frame = new IpcFrame($type, ++$this->requestId, $payload);
        $encoded = $frame->encode();

        if ($this->outboundBytes + strlen($encoded) > self::MAX_OUTBOUND_BYTES) {
            // Kuyruk taşarsa bellek sınırsız büyür ve tel üzerinde kısmi
            // çerçeve birikir. Fail-closed: bağlantıyı bozuk bırakma.
            $this->failClosed('ipc_outbound_overflow');

            throw TransportException::startFailed('IPC giden kuyrugu siniri asildi');
        }

        $this->outboundQueue[] = [
            'bytes' => $encoded,
            'offset' => 0,
            'payloadBytes' => strlen($payload),
            'isPayload' => $isPayload,
        ];
        $this->outboundBytes += strlen($encoded);

        $this->flushOutbound();
    }

    /**
     * Giden kuyruğu sokete yazar.
     *
     * Kısmi yazma desteklenir: yazılan baytlar kuyruğun başından silinir,
     * kalanı sonraki tick'e kalır. Böylece tel üzerindeki bayt dizisi
     * çerçeve sınırlarını asla ihlal etmez.
     *
     * @throws TransportException yazma hatasında (fail-closed uygulanmış olur)
     */
    private function flushOutbound(): void
    {
        if ($this->socket === null) {
            return;
        }

        $writtenTotal = 0;
        $writeCalls = 0;

        while (
            $this->outboundQueue !== []
            && $writtenTotal < self::MAX_FLUSH_BYTES_PER_TICK
            && $writeCalls < self::MAX_FLUSH_WRITES_PER_TICK
        ) {
            $frame = $this->outboundQueue[0];
            $remaining = strlen($frame['bytes']) - $frame['offset'];

            if ($remaining <= 0) {
                array_shift($this->outboundQueue);
                $this->outboundBytes -= strlen($frame['bytes']);

                continue;
            }

            $chunk = substr(
                $frame['bytes'],
                $frame['offset'],
                min($remaining, self::MAX_FLUSH_BYTES_PER_TICK - $writtenTotal)
            );

            $written = @$this->socket->write($chunk);
            $writeCalls++;

            if ($written === false || $written < 0 || $written > strlen($chunk)) {
                $this->failClosed('ipc_write_failed');

                throw TransportException::startFailed('IPC soketine yazilamadi');
            }

            if ($written === 0) {
                // Soket tamponu dolu; kalan baytlar sonraki tick'te yazilacak.
                return;
            }

            $this->outboundQueue[0]['offset'] += $written;
            $writtenTotal += $written;

            if ($this->outboundQueue[0]['offset'] >= strlen($frame['bytes'])) {
                if ($frame['isPayload']) {
                    $this->bytesSent += $frame['payloadBytes'];
                    $this->payloadsSent++;
                }

                array_shift($this->outboundQueue);
                $this->outboundBytes -= strlen($frame['bytes']);
            }
        }
    }

    /**
     * Giden kuyruk tamamen boşalana kadar bekler (sınırlı süre).
     *
     * Yalnızca el sıkışma kanıtı gibi "gönderildikten sonra beklenen"
     * mesajlar için kullanılır.
     */
    private function drainOutbound(float $timeoutSeconds): bool
    {
        $deadline = microtime(true) + $timeoutSeconds;

        while ($this->outboundQueue !== []) {
            $this->flushOutbound();

            if ($this->outboundQueue === []) {
                return true;
            }

            if (microtime(true) >= $deadline) {
                return false;
            }

            usleep(1000);
        }

        return true;
    }

    private function sendHelloAck(string $nonce): void
    {
        $proof = IpcChallenge::createProof($this->process->getToken(), $nonce);

        $this->enqueueFrame(
            SidecarMessage::HELLO_ACK,
            SidecarMessage::encodeHelloAck($proof)
        );

        if (!$this->drainOutbound(1.0)) {
            $this->failClosed('ipc_handshake_write_timeout');

            throw TransportException::startFailed('IPC el sikisma kaniti yazilamadi');
        }
    }

    private function awaitHello(): string
    {
        if ($this->socket === null) {
            throw TransportException::notRunning();
        }

        $deadline = microtime(true) + 10.0;

        while (microtime(true) < $deadline) {
            $chunk = @$this->socket->read(self::READ_CHUNK_SIZE);

            if ($chunk !== false && $chunk !== '') {
                $this->readBuffer .= $chunk;

                if (strlen($this->readBuffer) > IpcFrame::MAX_PAYLOAD + IpcFrame::HEADER_SIZE * 2) {
                    throw TransportException::startFailed('IPC el sikismasi tamponu siniri asildi');
                }

                while (true) {
                    try {
                        $frame = IpcFrame::decode($this->readBuffer);
                    } catch (IpcProtocolException $exception) {
                        throw TransportException::startFailed(
                            'IPC el sikisma protokol hatasi: ' . $exception->getMessage()
                        );
                    }

                    if ($frame === null) {
                        break;
                    }

                    if ($frame->type !== SidecarMessage::HELLO) {
                        throw TransportException::startFailed(
                            'IPC el sikismasinda beklenmeyen cerceve tipi: ' . $frame->type
                        );
                    }

                    $hello = SidecarMessage::decodeHello($frame->payload);

                    return $hello['nonce'];
                }
            }

            if ($chunk === false) {
                throw TransportException::startFailed('Sidecar el sikismasi sirasinda okuma hatasi');
            }

            if ($this->socket->eof()) {
                throw TransportException::startFailed('Sidecar el sikismasi sirasinda baglanti kapandi');
            }

            if (!$this->process->isRunning()) {
                throw TransportException::startFailed('Sidecar el sikismasi sirasinda kapandi');
            }

            usleep(10000);
        }

        throw TransportException::startFailed('Sidecar HELLO mesaji gelmedi');
    }

    private function handleFrame(IpcFrame $frame): void
    {
        switch ($frame->type) {
            case SidecarMessage::HELLO:
                // Token zaten gönderildi; sürüm bilgisi yalnızca teşhis içindir.
                break;

            case SidecarMessage::PONG:
                break;

            case SidecarMessage::CONNECTION_OPEN:
                $this->handleConnectionOpen(SidecarMessage::decodeJson($frame->payload));
                break;

            case SidecarMessage::CONNECTION_CLOSED:
                $this->handleConnectionClosed(SidecarMessage::decodeJson($frame->payload));
                break;

            case SidecarMessage::PAYLOAD_IN:
                $this->handlePayloadIn(SidecarMessage::decodePayload($frame->payload));
                break;

            default:
                // Bilinmeyen tip: sessizce yoksay, akışı bozma.
                break;
        }
    }

    /**
     * @param array<string, mixed> $body
     */
    private function handleConnectionOpen(array $body): void
    {
        $id = (string) ($body['connectionId'] ?? '');

        if ($id === '') {
            return;
        }

        $connection = new SidecarConnection(
            id: $id,
            remoteAddress: (string) ($body['remoteAddress'] ?? ''),
            identity: $this->identityFrom($body)
        );

        $this->connections[$id] = $connection;
        $this->totalConnections++;

        $this->listener?->onConnectionOpen($connection);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function handleConnectionClosed(array $body): void
    {
        $id = (string) ($body['connectionId'] ?? '');

        if ($id === '' || !isset($this->connections[$id])) {
            return;
        }

        $connection = $this->connections[$id];
        unset($this->connections[$id]);
        $connection->markClosed((string) ($body['reason'] ?? ''));

        $this->listener?->onConnectionClosed($connection, (string) ($body['reason'] ?? ''));
    }

    /**
     * @param array{channel: int, connectionId: string, data: string} $body
     */
    private function handlePayloadIn(array $body): void
    {
        $connection = $this->connections[$body['connectionId']] ?? null;

        if ($connection === null) {
            $this->droppedPayloads++;

            return;
        }

        $this->bytesReceived += strlen($body['data']);
        $this->payloadsReceived++;

        $this->listener?->onPayload($connection, $body['data']);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>|null
     */
    private function identityFrom(array $body): ?array
    {
        $xuid = $body['xuid'] ?? null;
        $displayName = $body['displayName'] ?? null;

        if ($xuid === null && $displayName === null) {
            return null;
        }

        return array_filter([
            'xuid' => $xuid,
            'displayName' => $displayName,
            'networkId' => $body['networkId'] ?? null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * Fail-closed kapanış.
     *
     * Taşıyıcıyı güvenli biçimde kapatır:
     *   1. bekleyen giden kuyruk atılır (kısmi çerçeve telde kalmasın),
     *   2. soket kapatılır,
     *   3. açık bağlantılar "koptu" olarak dinleyiciye bildirilir,
     *   4. **Rust çocuk süreci durdurulur** (orphan sidecar oluşmaz).
     *
     * Idempotenttir.
     */
    private function failClosed(string $reason): void
    {
        $this->outboundQueue = [];
        $this->outboundBytes = 0;

        if ($this->socket !== null) {
            $this->socket->close();
            $this->socket = null;
        }

        foreach ($this->connections as $connection) {
            $connection->markClosed($reason);
            $this->listener?->onConnectionClosed($connection, $reason);
        }

        $this->connections = [];
        $this->readBuffer = '';
        $this->running = false;

        $this->process->stop();
    }
}
