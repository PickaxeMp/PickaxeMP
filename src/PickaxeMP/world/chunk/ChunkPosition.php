<?php

declare(strict_types=1);

namespace PickaxeMP\world\chunk;

/**
 * Chunk sütununun yatay konumu (16x16 blok).
 *
 * Negatif koordinatlar `>> 4` ile doğru hesaplanır (aritmetik kaydırma).
 */
final class ChunkPosition
{
    public function __construct(
        public readonly int $x,
        public readonly int $z
    ) {
    }

    public static function fromBlock(int $blockX, int $blockZ): self
    {
        return new self($blockX >> 4, $blockZ >> 4);
    }

    public function equals(self $other): bool
    {
        return $this->x === $other->x && $this->z === $other->z;
    }

    public function key(): string
    {
        return "{$this->x},{$this->z}";
    }

    /**
     * Bu chunk'ın dünya koordinatlarındaki köşesi.
     */
    public function originX(): int
    {
        return $this->x << 4;
    }

    public function originZ(): int
    {
        return $this->z << 4;
    }

    public function __toString(): string
    {
        return "chunk({$this->x}, {$this->z})";
    }
}
