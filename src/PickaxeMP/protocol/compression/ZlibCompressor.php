<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\compression;

use PickaxeMP\protocol\ProtocolException;

/**
 * Bedrock'un "ZLib" sıkıştırma algoritması.
 *
 * Tel üzerindeki 0x00 kimliği RFC 1950 zlib sarmalını değil, header/trailer
 * içermeyen **raw DEFLATE** akışını belirtir. Bu nedenle PHP'de
 * `gzdeflate()`/`gzinflate()` kullanılır; `gzcompress()`/`gzuncompress()`
 * yanlış biçim üretir.
 *
 * Güvenlik: sıkıştırılmış veri açılırken **çıktı boyutu sınırlanır**, aksi
 * halde küçük bir yük ile gigabaytlarca bellek tüketilebilir (decompression
 * bomb). Sınır aşılırsa açık hata üretilir, veri kabul edilmez.
 */
final class ZlibCompressor
{
    /** Varsayılan açılmış veri üst sınırı: 8 MiB. */
    public const DEFAULT_MAX_DECOMPRESSED = 8 * 1024 * 1024;

    public function __construct(
        private readonly int $maxDecompressedBytes = self::DEFAULT_MAX_DECOMPRESSED,
        private readonly int $level = 6
    ) {
    }

    public function compress(string $data): string
    {
        if ($data === '') {
            return '';
        }

        $compressed = gzdeflate($data, $this->level);

        if ($compressed === false) {
            throw new ProtocolException('raw DEFLATE sikistirma basarisiz');
        }

        return $compressed;
    }

    /**
     * Sıkıştırılmış veriyi açar.
     *
     * @throws ProtocolException veri bozuksa veya sınır aşılırsa
     */
    public function decompress(string $data, ?int $maxBytes = null): string
    {
        if ($data === '') {
            return '';
        }

        $limit = $maxBytes ?? $this->maxDecompressedBytes;

        // Raw DEFLATE akışı en az bir tam boş final bloğu içerir.
        if (strlen($data) < 2) {
            throw ProtocolException::decompressionFailed('veri cok kisa');
        }

        // `gzinflate` sınır parametresi alır: max_length. Sınır aşıldığında
        // false döner; çözülen veri limitin ötesindeyse açık hata üretilir.
        $result = @gzinflate($data, $limit + 1);

        if ($result === false) {
            throw ProtocolException::decompressionFailed(
                'bozuk raw DEFLATE verisi veya bellek siniri'
            );
        }

        if (strlen($result) > $limit) {
            throw ProtocolException::decompressedTooLarge(strlen($result), $limit);
        }

        return $result;
    }

    public function maxDecompressedBytes(): int
    {
        return $this->maxDecompressedBytes;
    }
}
