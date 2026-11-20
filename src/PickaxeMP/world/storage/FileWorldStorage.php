<?php

declare(strict_types=1);

namespace PickaxeMP\world\storage;

use PickaxeMP\world\chunk\Chunk;
use PickaxeMP\world\chunk\ChunkPosition;
use PickaxeMP\world\chunk\SubChunk;
use PickaxeMP\world\chunk\SubChunkSerializer;
use PickaxeMP\world\WorldException;
use PickaxeMP\world\WorldProperties;

/**
 * Dosya tabanlı dünya depolaması.
 *
 * ## Dizin yapısı
 *
 * ```
 * worlds/<name>/
 *   world.json          meta veri
 *   chunks/<x>_<z>.chunk  ikili chunk verisi
 * ```
 *
 * ## Dosya biçimi (chunk)
 *
 * ```
 * magic      4 bayt  "PKCH"     (PickaxeMP CHunk)
 * version    uint16 LE
 * chunkX     int32  LE
 * chunkZ     int32  LE
 * count      uint16 LE          (yazılan subchunk sayısı)
 * per subchunk:
 *   index    int8               (dikey indeks)
 *   length   uint32 LE
 *   data     <length> bayt      (SubChunkSerializer v9)
 * crc32      uint32 LE          (önceki tüm baytların)
 * ```
 *
 * ## Güvenli yazma
 *
 * 1. Geçici dosyaya yaz (`.tmp`).
 * 2. `fflush` + `fsync` (mümkünse).
 * 3. CRC32 doğrula (geri okuyarak).
 * 4. Atomik `rename` (Windows'ta `rename` üzerine yazmaz → önce hedefi
 *    yedekle).
 * 5. Başarısızsa eski dosya korunur.
 */
final class FileWorldStorage implements WorldStorageInterface
{
    private const MAGIC = 'PKCH';
    private const FORMAT_VERSION = 1;

    private readonly SubChunkSerializer $subChunkSerializer;

    public function __construct(
        private readonly string $rootDirectory,
        private readonly int $airNetworkId = 0,
        ?SubChunkSerializer $subChunkSerializer = null
    ) {
        $this->subChunkSerializer = $subChunkSerializer ?? new SubChunkSerializer();
    }

    public function worldDirectory(string $worldName): string
    {
        $safe = $this->sanitizeName($worldName);

        return $this->rootDirectory . DIRECTORY_SEPARATOR . $safe;
    }

    private function chunksDirectory(string $worldName): string
    {
        return $this->worldDirectory($worldName) . DIRECTORY_SEPARATOR . 'chunks';
    }

    public function propertiesExist(string $worldName): bool
    {
        return is_file($this->worldDirectory($worldName) . DIRECTORY_SEPARATOR . 'world.json');
    }

    public function saveProperties(WorldProperties $properties): void
    {
        $directory = $this->worldDirectory($properties->name);
        $this->ensureDirectory($directory);

        $json = json_encode($properties->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw WorldException::saveFailed($directory, 'JSON kodlanamadi');
        }

        $this->writeAtomically($directory . DIRECTORY_SEPARATOR . 'world.json', $json);
    }

    public function loadProperties(string $worldName): WorldProperties
    {
        $path = $this->worldDirectory($worldName) . DIRECTORY_SEPARATOR . 'world.json';

        if (!is_file($path)) {
            throw WorldException::unknownWorld($worldName);
        }

        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw WorldException::corruptWorldFile($path, 'okunamadi');
        }

        $decoded = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw WorldException::corruptWorldFile(
                $path,
                'JSON hatasi: ' . json_last_error_msg()
            );
        }

        return WorldProperties::fromArray($decoded);
    }

    public function saveChunk(string $worldName, Chunk $chunk): void
    {
        $directory = $this->chunksDirectory($worldName);
        $this->ensureDirectory($directory);

        $payload = $this->encodeChunk($chunk);
        $crc = crc32($payload);

        $contents = $payload . pack('V', $crc & 0xFFFFFFFF);

        $path = $directory . DIRECTORY_SEPARATOR
            . $chunk->getPosition()->x . '_' . $chunk->getPosition()->z . '.chunk';

        $this->writeAtomically($path, $contents);

        $chunk->markClean();
    }

    public function loadChunk(string $worldName, int $chunkX, int $chunkZ): ?Chunk
    {
        $path = $this->chunksDirectory($worldName) . DIRECTORY_SEPARATOR . "{$chunkX}_{$chunkZ}.chunk";

        if (!is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw WorldException::corruptWorldFile($path, 'okunamadi');
        }

        if (strlen($contents) < 8) {
            throw WorldException::corruptWorldFile($path, 'dosya cok kisa');
        }

        // CRC doğrula.
        $body = substr($contents, 0, -4);
        /** @var array{1: int} $stored */
        $stored = unpack('V', substr($contents, -4));
        $actual = crc32($body) & 0xFFFFFFFF;

        if (($stored[1] & 0xFFFFFFFF) !== $actual) {
            throw WorldException::checksumMismatch($stored[1], $actual);
        }

        return $this->decodeChunk($body, $path);
    }

    public function hasChunk(string $worldName, int $chunkX, int $chunkZ): bool
    {
        return is_file(
            $this->chunksDirectory($worldName) . DIRECTORY_SEPARATOR . "{$chunkX}_{$chunkZ}.chunk"
        );
    }

    public function listChunks(string $worldName): array
    {
        $directory = $this->chunksDirectory($worldName);

        if (!is_dir($directory)) {
            return [];
        }

        $keys = [];

        foreach (scandir($directory) ?: [] as $entry) {
            if (preg_match('/^(-?\d+)_(-?\d+)\.chunk$/', $entry, $matches) === 1) {
                $keys[] = $matches[1] . ',' . $matches[2];
            }
        }

        sort($keys);

        return $keys;
    }

    private function encodeChunk(Chunk $chunk): string
    {
        $position = $chunk->getPosition();
        $output = self::MAGIC
            . pack('v', self::FORMAT_VERSION)
            . pack('V', $position->x & 0xFFFFFFFF)
            . pack('V', $position->z & 0xFFFFFFFF);

        $indices = $chunk->nonEmptySubChunkIndices();

        $output .= pack('v', count($indices));

        foreach ($indices as $index) {
            $subChunk = $chunk->getSubChunk($index);

            if (!$subChunk instanceof SubChunk) {
                continue;
            }

            $data = $this->subChunkSerializer->serialize($subChunk, $index, $this->airNetworkId);

            $output .= chr($index & 0xFF) . pack('V', strlen($data)) . $data;
        }

        return $output;
    }

    private function decodeChunk(string $body, string $path): Chunk
    {
        if (strlen($body) < 4 + 2 + 8 + 2) {
            throw WorldException::corruptWorldFile($path, 'baslik kesik');
        }

        if (substr($body, 0, 4) !== self::MAGIC) {
            throw WorldException::corruptWorldFile($path, 'gecersiz magic');
        }

        $offset = 4;

        /** @var array{1: int} $version */
        $version = unpack('v', substr($body, $offset, 2));
        $offset += 2;

        if ($version[1] !== self::FORMAT_VERSION) {
            throw WorldException::corruptWorldFile(
                $path,
                "desteklenmeyen chunk surumu: {$version[1]}"
            );
        }

        /** @var array{1: int} $x */
        $x = unpack('V', substr($body, $offset, 4));
        $offset += 4;
        /** @var array{1: int} $z */
        $z = unpack('V', substr($body, $offset, 4));
        $offset += 4;

        $chunkX = $x[1] >= 0x80000000 ? $x[1] - 0x100000000 : $x[1];
        $chunkZ = $z[1] >= 0x80000000 ? $z[1] - 0x100000000 : $z[1];

        /** @var array{1: int} $count */
        $count = unpack('v', substr($body, $offset, 2));
        $offset += 2;

        $chunk = new Chunk(new ChunkPosition($chunkX, $chunkZ), $this->airNetworkId);

        for ($i = 0; $i < $count[1]; $i++) {
            if ($offset + 5 > strlen($body)) {
                throw WorldException::corruptWorldFile($path, 'subchunk basligi kesik');
            }

            $rawIndex = ord($body[$offset]);
            $index = $rawIndex >= 0x80 ? $rawIndex - 0x100 : $rawIndex;
            $offset += 1;

            /** @var array{1: int} $length */
            $length = unpack('V', substr($body, $offset, 4));
            $offset += 4;

            if ($offset + $length[1] > strlen($body)) {
                throw WorldException::corruptWorldFile($path, 'subchunk verisi kesik');
            }

            $data = substr($body, $offset, $length[1]);
            $offset += $length[1];

            $decoded = $this->subChunkSerializer->deserialize($data);

            $chunk->setSubChunk($decoded['index'], $decoded['subChunk']);
        }

        $chunk->markClean();

        return $chunk;
    }

    /**
     * Atomik yazma: geçici dosya → fsync → rename.
     */
    private function writeAtomically(string $path, string $contents): void
    {
        $temporary = $path . '.tmp';

        $handle = @fopen($temporary, 'wb');

        if ($handle === false) {
            throw WorldException::saveFailed($path, 'gecici dosya acilamadi');
        }

        $written = fwrite($handle, $contents);

        if ($written !== strlen($contents)) {
            fclose($handle);
            @unlink($temporary);

            throw WorldException::saveFailed($path, 'eksik yazma');
        }

        fflush($handle);

        // Windows'ta fsync yok; POSIX'te veri güvenliği için gerekli.
        if (function_exists('fsync')) {
            @fsync($handle);
        }

        fclose($handle);

        // Hedef varsa önce yedekle (Windows rename üzerine yazmaz).
        $backup = null;

        if (is_file($path)) {
            $backup = $path . '.bak';
            @unlink($backup);

            if (!@rename($path, $backup)) {
                @unlink($temporary);

                throw WorldException::saveFailed($path, 'mevcut dosya yedeklenemedi');
            }
        }

        if (!@rename($temporary, $path)) {
            // Geri al.
            if ($backup !== null) {
                @rename($backup, $path);
            }

            @unlink($temporary);

            throw WorldException::saveFailed($path, 'atomik degistirme basarisiz');
        }

        if ($backup !== null) {
            @unlink($backup);
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!@mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw WorldException::saveFailed($directory, 'dizin olusturulamadi');
        }
    }

    private function sanitizeName(string $worldName): string
    {
        if ($worldName === '' || preg_match('/^[A-Za-z0-9_\-]+$/', $worldName) !== 1) {
            throw new WorldException(
                "Gecersiz dunya adi: '{$worldName}' (yalnizca harf, rakam, _ ve -)"
            );
        }

        return $worldName;
    }
}
