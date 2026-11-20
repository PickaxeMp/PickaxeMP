<?php

declare(strict_types=1);

namespace PickaxeMP\transport\sidecar;

/**
 * IPC çerçevesi — PHP çekirdeği ile Rust sidecar arasındaki ikili protokol.
 *
 * Tel formatı (ağ bayt sırası, big-endian):
 *
 *   +--------+---------+---------+-----------+--------+---------+
 *   | MAGIC  | VERSION |  TYPE   | REQUESTID |  LEN   | PAYLOAD |
 *   | 2 bayt | 1 bayt  | 1 bayt  |  8 bayt   | 4 bayt |  N bayt |
 *   +--------+---------+---------+-----------+--------+---------+
 *
 * MAGIC = 0x504B ("PK") — yanlış sürücü/çöp veri erken reddedilir.
 * VERSION = 1 — uyumsuz taraf açıkça reddedilir, sessizce yanlış
 *              yorumlanmaz.
 * LEN — yalnızca payload uzunluğu; toplam çerçeve = 16 + LEN.
 *
 * Neden uzunluk öneki? Akış tabanlı bir taşıyıcıda (TCP/pipe) mesaj sınırları
 * yoktur; çerçeveleme olmadan iki mesaj birbirine karışır.
 */
final class IpcFrame
{
    public const MAGIC = 0x504B;
    public const VERSION = 1;
    public const HEADER_SIZE = 16;

    /** Güvenlik sınırı: tek çerçeve en fazla 1 MiB yük taşıyabilir. */
    public const MAX_PAYLOAD = 1024 * 1024;

    public function __construct(
        public readonly int $type,
        public readonly int $requestId,
        public readonly string $payload = ''
    ) {
        if ($type < 0 || $type > 255) {
            throw new \InvalidArgumentException("Gecersiz cerceve tipi: {$type}");
        }

        if ($requestId < 0) {
            throw new \InvalidArgumentException("Gecersiz istek kimligi: {$requestId}");
        }

        if (strlen($payload) > self::MAX_PAYLOAD) {
            throw new \InvalidArgumentException(
                'Cerceve yuku cok buyuk: ' . strlen($payload) . ' bayt (sinir: ' . self::MAX_PAYLOAD . ')'
            );
        }
    }

    public static function of(int $type, int $requestId, string $payload = ''): self
    {
        return new self($type, $requestId, $payload);
    }

    public function encode(): string
    {
        return pack(
            'nCCJN',
            self::MAGIC,
            self::VERSION,
            $this->type,
            $this->requestId,
            strlen($this->payload)
        ) . $this->payload;
    }

    /**
     * Tamponun başından tek bir çerçeve çözer.
     *
     * Tam çerçeve yoksa null döner ve tampona dokunmaz — böylece çağıran
     * daha fazla bayt bekleyebilir (kısmi okuma).
     *
     * @param string $buffer Tampon (referansla; tüketilen baytlar silinir)
     *
     * @throws IpcProtocolException bozuk/uyumsuz çerçeve varsa
     */
    public static function decode(string &$buffer): ?self
    {
        if (strlen($buffer) < self::HEADER_SIZE) {
            return null;
        }

        /** @var array{magic: int, version: int, type: int, requestId: int, length: int} $header */
        $header = unpack('nmagic/Cversion/Ctype/JrequestId/Nlength', $buffer);

        if ($header['magic'] !== self::MAGIC) {
            throw IpcProtocolException::badMagic($header['magic']);
        }

        if ($header['version'] !== self::VERSION) {
            throw IpcProtocolException::unsupportedVersion($header['version']);
        }

        if ($header['length'] > self::MAX_PAYLOAD) {
            throw IpcProtocolException::payloadTooLarge($header['length']);
        }

        $total = self::HEADER_SIZE + $header['length'];

        if (strlen($buffer) < $total) {
            return null;
        }

        $payload = substr($buffer, self::HEADER_SIZE, $header['length']);
        $buffer = substr($buffer, $total);

        return new self($header['type'], $header['requestId'], $payload);
    }
}
