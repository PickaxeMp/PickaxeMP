<?php

declare(strict_types=1);

namespace PickaxeMP\world\chunk;

use PickaxeMP\world\WorldException;

/**
 * SubChunk ağ serileştirmesi — **sürüm 9** (Bedrock 1.18+ / 1.21+).
 *
 * ## Tel biçimi (doğrulanmış)
 *
 * ```
 * version        uint8 = 9
 * storageCount   uint8 = 1        (tek katman: bloklar; su ayrı katman değil)
 * per layer:
 *   subChunkIndex int8            (v9'da eklendi — dikey konum)
 *   header        uint8 = (bitsPerBlock << 1) | 1
 *                                 (düşük bit 1 = ağ kimlikleri hash)
 *   paletteCount  varuint32       (yalnızca bitsPerBlock > 0)
 *   palette[]     varuint32 × N   (ağ kimlikleri)
 *   indices       uint32 × M      (bit-packed, little-endian kelimeler)
 * ```
 *
 * `M = ceil(4096 / floor(32 / bitsPerBlock))`
 *
 * ## Kaynak
 *
 * - minecraft.wiki "Bedrock Edition level format": *"9 Version byte,
 *   storage-layer count, and signed one-byte subchunk Y index"*
 * - Team-Lodestone "Subchunk Format": bit-per-index / header düzeni
 *
 * ## Dürüst sınır
 *
 * Sürüm 9 ve header düzeni topluluk belgelerinden doğrulanmıştır; **gerçek
 * istemciyle** paket kabulü sınanmamıştır. Biçim değişirse tek yer
 * burasıdır.
 */
final class SubChunkSerializer
{
    public const VERSION = 9;

    /** 32 bit kelime başına indeks sayısı tablosu. */
    /** Bir subchunk paletinde temsil edilebilir en fazla giriş (16 bit). */
    public const MAX_PALETTE_SIZE = 65536;

    private const INDICES_PER_WORD = [
        1 => 32,
        2 => 16,
        3 => 10,
        4 => 8,
        5 => 6,
        6 => 5,
        7 => 4,
        8 => 4,
        9 => 3,
        10 => 3,
        11 => 2,
        12 => 2,
        13 => 2,
        14 => 2,
        15 => 2,
        16 => 2,
    ];

    public function __construct(private readonly bool $networkIdsAreHashes = true)
    {
    }

    /**
     * SubChunk'ı serileştirir.
     *
     * @param int $subChunkIndex Dikey indeks (−4..19 aralığı dünya
     *        yüksekliğine göre). v9'da tel üzerinde taşınır.
     */
    public function serialize(SubChunk $subChunk, int $subChunkIndex, int $airNetworkId): string
    {
        if ($subChunk->isUniformWith($airNetworkId)) {
            // Tamamen boş: v8+ kuralı — bitsPerBlock=0 ve paletsiz.
            return $this->writeHeader($subChunkIndex, 0) . chr(0);
        }

        $uniqueIds = $subChunk->uniqueIds();
        $palette = [];
        $indexOf = [];

        foreach ($uniqueIds as $position => $id) {
            $palette[$id] = $position;
            $indexOf[$id] = $position;
        }

        $paletteSize = count($palette);

        if ($paletteSize > self::MAX_PALETTE_SIZE) {
            throw WorldException::paletteTooLarge($paletteSize, self::MAX_PALETTE_SIZE);
        }

        $bitsPerBlock = $this->bitsFor($paletteSize);

        $output = $this->writeHeader($subChunkIndex, $bitsPerBlock);

        // Palet.
        $output .= $this->writeUnsignedVarInt($paletteSize);

        foreach (array_keys($palette) as $networkId) {
            $output .= $this->writeUnsignedVarInt($networkId);
        }

        // Bit-packed indeksler.
        $output .= $this->packIndices($subChunk, $indexOf, $bitsPerBlock);

        return $output;
    }

    /**
     * Serileştirilmiş veriyi çözer.
     *
     * @return array{subChunk: SubChunk, index: int}
     */
    public function deserialize(string $data, int $offset = 0): array
    {
        $length = strlen($data);

        if ($offset >= $length) {
            throw new WorldException('SubChunk verisi bos');
        }

        $version = ord($data[$offset++]);

        if ($version !== self::VERSION) {
            throw new WorldException("Desteklenmeyen SubChunk surumu: {$version}");
        }

        if ($offset >= $length) {
            throw new WorldException('SubChunk katman sayisi eksik');
        }

        $storageCount = ord($data[$offset++]);

        if ($storageCount < 1) {
            throw new WorldException("Gecersiz katman sayisi: {$storageCount}");
        }

        // v9: imzalı indeks.
        $subChunkIndex = $this->readSignedByte($data, $offset);

        // İlk katman blok katmanıdır; ek katmanlar (su) yok sayılır.
        $subChunk = new SubChunk();

        if ($offset >= $length) {
            throw new WorldException('SubChunk header eksik');
        }

        $header = ord($data[$offset++]);
        $bitsPerBlock = $header >> 1;

        if ($bitsPerBlock === 0) {
            // Tamamen boş subchunk.
            return ['subChunk' => $subChunk, 'index' => $subChunkIndex];
        }

        $paletteSize = $this->readUnsignedVarInt($data, $offset);
        $palette = [];

        for ($i = 0; $i < $paletteSize; $i++) {
            $palette[] = $this->readUnsignedVarInt($data, $offset);
        }

        if ($palette === []) {
            throw new WorldException('Bos palet');
        }

        $indices = $this->unpackIndices($data, $offset, $bitsPerBlock, count($palette));

        if (count($indices) !== SubChunk::BLOCK_COUNT) {
            throw new WorldException(
                'SubChunk indeks sayisi yanlis: ' . count($indices) . ' (beklenen ' . SubChunk::BLOCK_COUNT . ')'
            );
        }

        foreach ($indices as $position => $paletteIndex) {
            $subChunk->setIndex($position, $palette[$paletteIndex]);
        }

        $subChunk->markClean();

        return ['subChunk' => $subChunk, 'index' => $subChunkIndex];
    }

    /**
     * Bir subchunk'ın serileştirilmiş boyutunu tahmin eder (ağ planlaması).
     */
    public function estimateSize(SubChunk $subChunk, int $airNetworkId): int
    {
        if ($subChunk->isUniformWith($airNetworkId)) {
            return 3; // version + storageCount + index + header(0) + paletteCount(0)
        }

        $paletteSize = count($subChunk->uniqueIds());
        $bits = $this->bitsFor($paletteSize);
        $words = (int) ceil(SubChunk::BLOCK_COUNT / self::INDICES_PER_WORD[$bits]);

        return 4 + $paletteSize * 5 + $words * 4;
    }

    private function writeHeader(int $subChunkIndex, int $bitsPerBlock): string
    {
        return chr(self::VERSION)
            . chr(1)                       // tek katman
            . chr($subChunkIndex & 0xFF)   // imzalı int8
            . chr((($bitsPerBlock << 1) | ($this->networkIdsAreHashes ? 1 : 0)) & 0xFF);
    }

    private function bitsFor(int $paletteSize): int
    {
        if ($paletteSize <= 1) {
            return 1;
        }

        $bits = 1;

        while ((1 << $bits) < $paletteSize) {
            $bits++;
        }

        return min($bits, 16);
    }

    /**
     * @param array<int, int> $indexOf ağ kimliği => palet indeksi
     */
    private function packIndices(SubChunk $subChunk, array $indexOf, int $bitsPerBlock): string
    {
        $blocks = $subChunk->blocks();
        $output = '';

        $perWord = self::INDICES_PER_WORD[$bitsPerBlock];
        $mask = (1 << $bitsPerBlock) - 1;
        $word = 0;
        $shift = 0;
        $count = 0;

        foreach ($blocks as $networkId) {
            $paletteIndex = $indexOf[$networkId] ?? 0;
            $word |= ($paletteIndex & $mask) << $shift;
            $shift += $bitsPerBlock;
            $count++;

            if ($count === $perWord) {
                $output .= pack('V', $word & 0xFFFFFFFF);
                $word = 0;
                $shift = 0;
                $count = 0;
            }
        }

        if ($count > 0) {
            $output .= pack('V', $word & 0xFFFFFFFF);
        }

        return $output;
    }

    /**
     * @return list<int> palet indeksleri (4096 adet)
     */
    private function unpackIndices(string $data, int &$offset, int $bitsPerBlock, int $paletteSize): array
    {
        if (!isset(self::INDICES_PER_WORD[$bitsPerBlock])) {
            throw new WorldException("Desteklenmeyen bitsPerBlock: {$bitsPerBlock}");
        }

        $perWord = self::INDICES_PER_WORD[$bitsPerBlock];
        $mask = (1 << $bitsPerBlock) - 1;
        $indices = [];
        $currentWord = 0;
        $dataLength = strlen($data);

        for ($index = 0; $index < SubChunk::BLOCK_COUNT; $index++) {
            if ($index % $perWord === 0) {
                if ($offset + 4 > $dataLength) {
                    throw new WorldException('SubChunk indeks verisi kesik');
                }

                /** @var array{1: int} $unpacked */
                $unpacked = unpack('V', substr($data, $offset, 4));
                $currentWord = $unpacked[1];
                $offset += 4;
            }

            $shift = ($index % $perWord) * $bitsPerBlock;
            $paletteIndex = ($currentWord >> $shift) & $mask;

            if ($paletteIndex >= $paletteSize) {
                throw new WorldException(
                    "Palet indeksi sinir disinda: {$paletteIndex} >= {$paletteSize}"
                );
            }

            $indices[] = $paletteIndex;
        }

        return $indices;
    }

    private function readSignedByte(string $data, int &$offset): int
    {
        if ($offset >= strlen($data)) {
            throw new WorldException('Imzali bayt eksik');
        }

        $value = ord($data[$offset++]);

        return $value >= 0x80 ? $value - 0x100 : $value;
    }

    private function writeUnsignedVarInt(int $value): string
    {
        $output = '';

        do {
            $byte = $value & 0x7F;
            $value >>= 7;

            if ($value !== 0) {
                $byte |= 0x80;
            }

            $output .= chr($byte);
        } while ($value !== 0);

        return $output;
    }

    private function readUnsignedVarInt(string $data, int &$offset): int
    {
        $value = 0;
        $shift = 0;

        while (true) {
            if ($offset >= strlen($data)) {
                throw new WorldException('VarInt kesik');
            }

            $byte = ord($data[$offset++]);
            $value |= ($byte & 0x7F) << $shift;

            if (($byte & 0x80) === 0) {
                return $value;
            }

            $shift += 7;

            if ($shift > 35) {
                throw new WorldException('VarInt cok uzun');
            }
        }
    }
}
