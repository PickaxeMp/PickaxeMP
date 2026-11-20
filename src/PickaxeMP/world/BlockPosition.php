<?php

declare(strict_types=1);

namespace PickaxeMP\world;

/**
 * Bir bloğun dünya koordinatı.
 *
 * Immutable; eşitlik ve hash anahtarı için `key()` kullanılır.
 */
final class BlockPosition
{
    public function __construct(
        public readonly int $x,
        public readonly int $y,
        public readonly int $z
    ) {
    }

    public function chunkX(): int
    {
        return $this->x >> 4;
    }

    public function chunkZ(): int
    {
        return $this->z >> 4;
    }

    /**
     * Chunk içindeki yerel X (0..15). Negatif koordinatlarda doğru sonuç
     * için bit maskesi kullanılır (`>>` aritmetik kaydırma yapar).
     */
    public function localX(): int
    {
        return $this->x & 0xF;
    }

    public function localZ(): int
    {
        return $this->z & 0xF;
    }

    public function equals(self $other): bool
    {
        return $this->x === $other->x && $this->y === $other->y && $this->z === $other->z;
    }

    public function key(): string
    {
        return "{$this->x},{$this->y},{$this->z}";
    }

    public function withY(int $y): self
    {
        return new self($this->x, $y, $this->z);
    }

    public function __toString(): string
    {
        return $this->key();
    }
}
