<?php

declare(strict_types=1);

namespace PickaxeMP\console;

/**
 * STDIN'den komut satırı okuyan konsol reader.
 *
 * stream_select() kullanarak ana tick döngüsünü bloklamamalıdır.
 * readLine() bir satır hazırsa döner, yoksa null döner.
 */
final class StdinConsoleReader implements ConsoleInputReaderInterface
{
    private const READ_CHUNK = 4096;

    private $stdin = null;
    private string $buffer = '';
    private bool $closed = false;

    public function __construct()
    {
        if (defined('STDIN') && is_resource(STDIN)) {
            $this->stdin = STDIN;

            // Windows'ta da non-blocking okuma deneyin.
            @stream_set_blocking($this->stdin, false);
        }
    }

    public function readLine(): ?string
    {
        if ($this->closed || $this->stdin === null) {
            return null;
        }

        // Mevcut buffer'da satır sonu varsa döndür.
        $pos = strpos($this->buffer, "\n");

        if ($pos !== false) {
            $line = substr($this->buffer, 0, $pos);
            $this->buffer = substr($this->buffer, $pos + 1);

            // \r\n ise \r'ı temizle.
            if (str_ends_with($line, "\r")) {
                $line = substr($line, 0, -1);
            }

            return $line;
        }

        // Yeni veri okunmaya çalışın.
        $read = @fread($this->stdin, self::READ_CHUNK);

        if ($read === false || $read === '') {
            return null;
        }

        if ($read === '') {
            // EOF
            $this->closed = true;

            return null;
        }

        $this->buffer .= $read;

        $pos = strpos($this->buffer, "\n");

        if ($pos !== false) {
            $line = substr($this->buffer, 0, $pos);
            $this->buffer = substr($this->buffer, $pos + 1);

            if (str_ends_with($line, "\r")) {
                $line = substr($line, 0, -1);
            }

            return $line;
        }

        return null;
    }

    public function close(): void
    {
        $this->closed = true;
        $this->stdin = null;
        $this->buffer = '';
    }
}
