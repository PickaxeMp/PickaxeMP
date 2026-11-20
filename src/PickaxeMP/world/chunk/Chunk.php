<?php

declare(strict_types=1);

namespace PickaxeMP\world\chunk;

use PickaxeMP\world\WorldException;

/**
 * 16×16 blokluk yatay sütun; dikey olarak subchunk'lardan oluşur.
 *
 * ## Dikey aralık
 *
 * Bedrock 1.18+ dünya yüksekliği **−64..319**'dur (384 blok = 24 subchunk).
 * Mojang "SubChunk request system v1.18.10" rehberi: *"World height changed
 * from 0-255 to -64 to +319"*.
 *
 * Subchunk indeksi `y >> 4` ile hesaplanır, yani:
 *   y = −64 → indeks −4
 *   y = 319 → indeks 19
 * Toplam 24 subchunk (indeks −4..19).
 */
final class Chunk
{
    public const MIN_SUBCHUNK_INDEX = -4;
    public const MAX_SUBCHUNK_INDEX = 19;
    public const SUBCHUNK_COUNT = 24;

    /** Dünyanın en alt/en üst blok Y'si. */
    public const MIN_Y = -64;
    public const MAX_Y = 319;

    /** @var array<int, SubChunk> indeks => subchunk */
    private array $subChunks = [];

    private bool $dirty = false;

    public function __construct(
        public readonly ChunkPosition $position,
        private readonly int $airNetworkId = 0
    ) {
    }

    public function getPosition(): ChunkPosition
    {
        return $this->position;
    }

    /**
     * Verilen Y'nin subchunk indeksi.
     */
    public static function subChunkIndexFor(int $y): int
    {
        return $y >> 4;
    }

    /**
     * Dünya Y'si bu chunk'ın aralığında mı?
     */
    public static function isYInRange(int $y): bool
    {
        return $y >= self::MIN_Y && $y <= self::MAX_Y;
    }

    public function getSubChunk(int $index): ?SubChunk
    {
        return $this->subChunks[$index] ?? null;
    }

    /**
     * Subchunk'ı döndürür; yoksa oluşturur.
     */
    public function getOrCreateSubChunk(int $index): SubChunk
    {
        if ($index < self::MIN_SUBCHUNK_INDEX || $index > self::MAX_SUBCHUNK_INDEX) {
            throw WorldException::invalidSubChunkIndex(
                $index,
                self::MIN_SUBCHUNK_INDEX,
                self::MAX_SUBCHUNK_INDEX
            );
        }

        return $this->subChunks[$index] ??= new SubChunk(
            array_fill(0, SubChunk::BLOCK_COUNT, $this->airNetworkId)
        );
    }

    public function setSubChunk(int $index, SubChunk $subChunk): void
    {
        if ($index < self::MIN_SUBCHUNK_INDEX || $index > self::MAX_SUBCHUNK_INDEX) {
            throw WorldException::invalidSubChunkIndex(
                $index,
                self::MIN_SUBCHUNK_INDEX,
                self::MAX_SUBCHUNK_INDEX
            );
        }

        $this->subChunks[$index] = $subChunk;
        $this->dirty = true;
    }

    /**
     * @return array<int, SubChunk>
     */
    public function subChunks(): array
    {
        return $this->subChunks;
    }

    /**
     * Yüklü (var olan) subchunk indeksleri, artan sırada.
     *
     * @return list<int>
     */
    public function subChunkIndices(): array
    {
        $indices = array_keys($this->subChunks);
        sort($indices);

        return $indices;
    }

    /**
     * Boş olmayan (tamamen hava olmayan) subchunk indeksleri.
     *
     * @return list<int>
     */
    public function nonEmptySubChunkIndices(): array
    {
        $indices = [];

        foreach ($this->subChunks as $index => $subChunk) {
            if (!$subChunk->isUniformWith($this->airNetworkId)) {
                $indices[] = $index;
            }
        }

        sort($indices);

        return $indices;
    }

    /**
     * Dünya Y'si ile blok okuma.
     */
    public function getBlock(int $worldY, int $localX, int $localZ): int
    {
        $index = self::subChunkIndexFor($worldY);
        $subChunk = $this->subChunks[$index] ?? null;

        if ($subChunk === null) {
            return $this->airNetworkId;
        }

        return $subChunk->get($localX, $worldY & 0xF, $localZ);
    }

    /**
     * Dünya Y'si ile blok yazma. Subchunk gerekiyorsa oluşturulur.
     */
    public function setBlock(int $worldY, int $localX, int $localZ, int $networkId): void
    {
        if (!self::isYInRange($worldY)) {
            throw WorldException::outOfBounds(0, $worldY, 0);
        }

        $index = self::subChunkIndexFor($worldY);

        // Hava yazarken var olmayan subchunk'ı boşuna oluşturma.
        if (!isset($this->subChunks[$index]) && $networkId === $this->airNetworkId) {
            return;
        }

        $this->getOrCreateSubChunk($index)->set($localX, $worldY & 0xF, $localZ, $networkId);
        $this->dirty = true;
    }

    public function isDirty(): bool
    {
        return $this->dirty;
    }

    public function markDirty(): void
    {
        $this->dirty = true;
    }

    public function markClean(): void
    {
        $this->dirty = false;

        foreach ($this->subChunks as $subChunk) {
            $subChunk->markClean();
        }
    }

    public function airNetworkId(): int
    {
        return $this->airNetworkId;
    }

    /**
     * Boş chunk mı? (hiç blok yok)
     */
    public function isEmpty(): bool
    {
        return $this->nonEmptySubChunkIndices() === [];
    }
}
