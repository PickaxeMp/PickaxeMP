<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\support;

use PickaxeMP\transport\sidecar\IpcSocketInterface;

/**
 * Bellek içi sahte IPC soketi.
 *
 * Amaç: `SidecarTransport`'ın I/O hata yollarını (kısmi yazma, soket
 * dolması, yazma/okuma hatası, EOF) Rust ikilisi olmadan deterministik
 * olarak tetiklemek. Gerçek ağ davranışını KANITLAMAZ.
 */
final class FakeIpcSocket implements IpcSocketInterface
{
    /** @var list<string> Gelen bayt kuyruğu */
    public array $inbound = [];

    /** @var list<string> Yazılan bayt parçaları (yazma sırasıyla) */
    public array $written = [];

    public bool $eof = false;

    public bool $closed = false;

    public bool $failWrites = false;

    public bool $failReads = false;

    /** Tek yazma çağrısında yazılacak azami bayt (kısmi yazma taklidi). */
    public int $maxWriteChunk = PHP_INT_MAX;

    public function push(string $bytes): void
    {
        $this->inbound[] = $bytes;
    }

    public function read(int $length): string|false
    {
        if ($this->failReads) {
            return false;
        }

        if ($this->inbound === []) {
            return '';
        }

        $chunk = array_shift($this->inbound);

        if (strlen($chunk) > $length) {
            $this->inbound[] = substr($chunk, $length);
            $chunk = substr($chunk, 0, $length);
        }

        return $chunk;
    }

    public function write(string $data): int|false
    {
        if ($this->failWrites) {
            return false;
        }

        $count = min(strlen($data), $this->maxWriteChunk);

        if ($count > 0) {
            $this->written[] = substr($data, 0, $count);
        }

        return $count;
    }

    public function eof(): bool
    {
        return $this->eof;
    }

    public function close(): void
    {
        $this->closed = true;
    }

    /**
     * Tel üzerine çıkan tüm baytlar (yazma sırasıyla birleştirilmiş).
     */
    public function writtenBytes(): string
    {
        return implode('', $this->written);
    }
}
