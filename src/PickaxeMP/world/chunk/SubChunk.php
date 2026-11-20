<?php

declare(strict_types=1);

namespace PickaxeMP\world\chunk;

use PickaxeMP\world\WorldException;

/**
 * 16×16×16 blokluk dikey katman (subchunk).
 *
 * ## Depolama
 *
 * 4096 blok, **sabit bellekte** (4096 × 4 bayt = 16 KiB) tutulur. Palet
 * sıkıştırması yalnızca **serileştirme sırasında** uygulanır; bellekte tam
 * dizi tutmak okuma/yazmayı basit ve hatasız kılar.
 *
 * ## Neden bellekte palet yok?
 *
 * Palet + bit-packing, tek blok değişiminde yeniden paketleme gerektirir.
 * Düz dünya ve Phase 5 (blok kırma) için doğrudan dizi hem daha hızlı hem
 * daha az hata üretir. Sıkıştırma kazancı ağ katmanında yine elde edilir.
 */
final class SubChunk
{
    public const SIZE = 16;
    public const BLOCK_COUNT = 4096; // 16*16*16

    /** @var array<int, int> Doğrusal indeks => ağ kimliği */
    private array $blocks;

    private bool $dirty = false;

    /**
     * @param array<int, int>|null $blocks
     */
    public function __construct(?array $blocks = null)
    {
        if ($blocks === null) {
            $this->blocks = array_fill(0, self::BLOCK_COUNT, 0);

            return;
        }

        if (count($blocks) !== self::BLOCK_COUNT) {
            throw new WorldException(
                'SubChunk ' . self::BLOCK_COUNT . ' blok icermeli, ' . count($blocks) . ' alindi'
            );
        }

        $this->blocks = $blocks;
    }

    /**
     * Yerel koordinat → doğrusal indeks.
     *
     * Bedrock sırası: X en hızlı, sonra Z, sonra Y (Y-major).
     */
    public static function index(int $x, int $y, int $z): int
    {
        if ($x < 0 || $x > 15 || $y < 0 || $y > 15 || $z < 0 || $z > 15) {
            throw new WorldException("SubChunk yerel koordinat disinda: ({$x}, {$y}, {$z})");
        }

        return ($y << 8) | ($z << 4) | $x;
    }

    public function get(int $x, int $y, int $z): int
    {
        return $this->blocks[self::index($x, $y, $z)];
    }

    public function set(int $x, int $y, int $z, int $networkId): void
    {
        $index = self::index($x, $y, $z);

        if ($this->blocks[$index] === $networkId) {
            return;
        }

        $this->blocks[$index] = $networkId;
        $this->dirty = true;
    }

    public function setIndex(int $index, int $networkId): void
    {
        if ($index < 0 || $index >= self::BLOCK_COUNT) {
            throw new WorldException("SubChunk indeks disinda: {$index}");
        }

        $this->blocks[$index] = $networkId;
    }

    public function getIndex(int $index): int
    {
        return $this->blocks[$index] ?? 0;
    }

    /**
     * @return array<int, int>
     */
    public function blocks(): array
    {
        return $this->blocks;
    }

    /**
     * Tüm bloklar aynı mı? (boş chunk optimizasyonu)
     */
    public function isUniform(): bool
    {
        $first = $this->blocks[0];

        foreach ($this->blocks as $value) {
            if ($value !== $first) {
                return false;
            }
        }

        return true;
    }

    /**
     * Tüm bloklar verilen kimlikle aynı mı?
     */
    public function isUniformWith(int $networkId): bool
    {
        foreach ($this->blocks as $value) {
            if ($value !== $networkId) {
                return false;
            }
        }

        return true;
    }

    /**
     * Kullanılan benzersiz ağ kimlikleri (palet oluşturmak için).
     *
     * @return list<int> İlk görülme sırasına göre
     */
    public function uniqueIds(): array
    {
        $seen = [];
        $unique = [];

        foreach ($this->blocks as $value) {
            if (isset($seen[$value])) {
                continue;
            }

            $seen[$value] = true;
            $unique[] = $value;
        }

        return $unique;
    }

    public function isEmpty(int $airNetworkId): bool
    {
        return $this->isUniformWith($airNetworkId);
    }

    public function isDirty(): bool
    {
        return $this->dirty;
    }

    public function markClean(): void
    {
        $this->dirty = false;
    }

    public function markDirty(): void
    {
        $this->dirty = true;
    }
}
