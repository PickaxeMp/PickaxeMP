<?php

declare(strict_types=1);

namespace PickaxeMP\transport\sidecar;

use PickaxeMP\exception\TransportException;

/**
 * PHP stream üzerine kurulmuş IPC soketi.
 *
 * Soket **bloke etmeyen** modda açılır: `tick()` ana döngüyü kilitlememeli.
 * Bu yüzden `write()` kısmi yazma döndürebilir; taşıyıcı kalan baytları
 * giden kuyrukta tutar ve sonraki tick'te devam eder.
 */
final class StreamIpcSocket implements IpcSocketInterface
{
    /** @var resource|null */
    private $stream;

    /**
     * @param resource $stream Açık, bloke etmeyen PHP stream
     */
    public function __construct($stream)
    {
        $this->stream = $stream;
    }

    /**
     * TCP soketine bağlanır.
     *
     * @throws TransportException bağlantı kurulamazsa
     */
    public static function connect(string $address, float $timeoutSeconds): self
    {
        $socket = @stream_socket_client($address, $errorCode, $errorMessage, $timeoutSeconds);

        if ($socket === false) {
            throw TransportException::startFailed(
                "IPC soketine baglanilamadi ({$address}): {$errorMessage} ({$errorCode})"
            );
        }

        stream_set_blocking($socket, false);

        return new self($socket);
    }

    public function read(int $length): string|false
    {
        if ($this->stream === null) {
            return false;
        }

        return @fread($this->stream, $length);
    }

    public function write(string $data): int|false
    {
        if ($this->stream === null) {
            return false;
        }

        return @fwrite($this->stream, $data);
    }

    public function eof(): bool
    {
        if ($this->stream === null) {
            return true;
        }

        return feof($this->stream);
    }

    public function close(): void
    {
        if ($this->stream !== null) {
            @fclose($this->stream);
            $this->stream = null;
        }
    }
}
