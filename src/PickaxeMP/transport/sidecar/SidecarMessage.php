<?php

declare(strict_types=1);

namespace PickaxeMP\transport\sidecar;

/**
 * IPC çerçevesinin içindeki mesaj gövdeleri — Rust tarafıyla birebir.
 *
 * Kontrol mesajları JSON'dur (küçük, loglanabilir, genişletilebilir).
 * Veri taşıyan mesajlar ikili formattır: base64 her Bedrock paketine %33
 * ek yük getirirdi.
 *
 * İkili düzenler (tüm tam sayılar big-endian):
 *
 *   PAYLOAD_IN / PAYLOAD_OUT : channel(1) | idLen(2) | connectionId | data
 *   CLOSE_CONNECTION         : idLen(2) | connectionId | reason(UTF-8)
 *
 * `channel` 0 = güvenilir, 1 = güvenilmez.
 */
final class SidecarMessage
{
    public const HELLO = 0x01;
    public const HELLO_ACK = 0x02;
    public const PING = 0x03;
    public const PONG = 0x04;

    public const PAYLOAD_IN = 0x10;
    public const PAYLOAD_OUT = 0x11;
    public const CLOSE_CONNECTION = 0x12;

    public const CONNECTION_OPEN = 0x20;
    public const CONNECTION_CLOSED = 0x21;

    public const CHANNEL_RELIABLE = 0;
    public const CHANNEL_UNRELIABLE = 1;

    /** Bağlantı kimliği için üst sınır (Rust tarafıyla aynı). */
    public const MAX_ID_LEN = 128;

    /**
     * @param int $channel 0 = reliable, 1 = unreliable
     */
    public static function encodePayload(int $channel, string $connectionId, string $data): string
    {
        if ($channel !== self::CHANNEL_RELIABLE && $channel !== self::CHANNEL_UNRELIABLE) {
            throw new IpcProtocolException("Gecersiz kanal: {$channel}");
        }

        $idLength = strlen($connectionId);

        if ($idLength > self::MAX_ID_LEN) {
            throw new IpcProtocolException("Baglanti kimligi cok uzun: {$idLength} bayt");
        }

        return chr($channel) . pack('n', $idLength) . $connectionId . $data;
    }

    /**
     * @return array{channel: int, connectionId: string, data: string}
     */
    public static function decodePayload(string $body): array
    {
        if (strlen($body) < 3) {
            throw new IpcProtocolException('Yuk govdesi cok kisa: ' . strlen($body) . ' bayt');
        }

        $channel = ord($body[0]);

        if ($channel !== self::CHANNEL_RELIABLE && $channel !== self::CHANNEL_UNRELIABLE) {
            throw new IpcProtocolException("Gecersiz kanal bayti: {$channel}");
        }

        /** @var array{1: int} $header */
        $header = unpack('n', substr($body, 1, 2));
        $idLength = $header[1];

        if ($idLength > self::MAX_ID_LEN) {
            throw new IpcProtocolException("Baglanti kimligi cok uzun: {$idLength} bayt");
        }

        if (strlen($body) < 3 + $idLength) {
            throw new IpcProtocolException('Yuk govdesi kesik: baglanti kimligi eksik');
        }

        return [
            'channel' => $channel,
            'connectionId' => substr($body, 3, $idLength),
            'data' => substr($body, 3 + $idLength),
        ];
    }

    public static function encodeClose(string $connectionId, string $reason): string
    {
        $idLength = strlen($connectionId);

        if ($idLength > self::MAX_ID_LEN) {
            throw new IpcProtocolException("Baglanti kimligi cok uzun: {$idLength} bayt");
        }

        return pack('n', $idLength) . $connectionId . $reason;
    }

    /**
     * @return array{connectionId: string, reason: string}
     */
    public static function decodeClose(string $body): array
    {
        if (strlen($body) < 2) {
            throw new IpcProtocolException('Kapatma govdesi cok kisa');
        }

        /** @var array{1: int} $header */
        $header = unpack('n', substr($body, 0, 2));
        $idLength = $header[1];

        if ($idLength > self::MAX_ID_LEN) {
            throw new IpcProtocolException("Baglanti kimligi cok uzun: {$idLength} bayt");
        }

        if (strlen($body) < 2 + $idLength) {
            throw new IpcProtocolException('Kapatma govdesi kesik');
        }

        return [
            'connectionId' => substr($body, 2, $idLength),
            'reason' => substr($body, 2 + $idLength),
        ];
    }

    /**
     * @param array<string, mixed> $value
     */
    public static function encodeJson(array $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new IpcProtocolException('JSON kodlanamadi: ' . json_last_error_msg());
        }

        return $json;
    }

    /**
     * Rust sidecar'ın challenge HELLO gövdesini doğrular.
     *
     * Paylaşılan tokenın bu mesajda bulunması bir sözleşme ihlalidir ve
     * sessizce yorumlanmaz: token hiçbir zaman IPC soketine yazılmamalıdır.
     *
     * @return array{nonce: string, version: string, pid: int}
     */
    public static function decodeHello(string $body): array
    {
        $value = self::decodeJson($body);

        if (array_key_exists('token', $value)) {
            throw new IpcProtocolException('IPC HELLO mesaji paylasilan token iceriyor.');
        }

        $nonce = $value['nonce'] ?? null;
        $version = $value['version'] ?? null;
        $pid = $value['pid'] ?? null;

        if (!is_string($nonce) || preg_match('/\A[0-9a-f]{64}\z/', $nonce) !== 1) {
            throw new IpcProtocolException('Gecersiz IPC HELLO nonce bicimi.');
        }

        if (!is_string($version) || $version === '' || strlen($version) > 64) {
            throw new IpcProtocolException('Gecersiz IPC HELLO surumu.');
        }

        if (!is_int($pid) || $pid <= 0) {
            throw new IpcProtocolException('Gecersiz IPC HELLO process kimligi.');
        }

        return [
            'nonce' => $nonce,
            'version' => $version,
            'pid' => $pid,
        ];
    }

    public static function encodeHelloAck(string $proof): string
    {
        if (preg_match('/\A[0-9a-f]{64}\z/', $proof) !== 1) {
            throw new IpcProtocolException('Gecersiz IPC HELLO_ACK kanit bicimi.');
        }

        return self::encodeJson(['proof' => $proof]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function decodeJson(string $body): array
    {
        if (strlen($body) > 64 * 1024) {
            throw new IpcProtocolException('Kontrol mesaji cok buyuk: ' . strlen($body) . ' bayt');
        }

        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            throw new IpcProtocolException('Gecersiz JSON: ' . json_last_error_msg());
        }

        return $decoded;
    }
}
