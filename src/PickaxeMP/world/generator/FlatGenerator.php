<?php

declare(strict_types=1);

namespace PickaxeMP\world\generator;

use PickaxeMP\world\block\BlockRegistry;
use PickaxeMP\world\block\BlockState;
use PickaxeMP\world\chunk\Chunk;
use PickaxeMP\world\chunk\ChunkPosition;

/**
 * Düz dünya üreticisi.
 *
 * ## Katmanlar
 *
 * | Y | Blok |
 * |---|---|
 * | −64 | bedrock |
 * | −63 … −61 | stone |
 * | −60 … −58 | dirt |
 * | −59 | grass_block  (dirt'in en üstü) |
 *
 * Pratikte: bedrock −64, stone −63..−61, dirt −60..−59, grass −58.
 * Spawn güvenli olsun diye üst yüzey **−58**'dir; oyuncu −57'de doğar.
 *
 * ## Deterministik
 *
 * Düz dünya seed'den **bağımsızdır** (tüm seed'ler aynı sonucu verir).
 * Seed yalnızca meta veride saklanır. Bu bilinçli: ilk hedef "görünür dünya",
 * arazi üretimi Phase 6+.
 */
final class FlatGenerator
{
    public const BEDROCK_Y = -64;
    public const STONE_MIN_Y = -63;
    public const STONE_MAX_Y = -61;
    public const DIRT_MIN_Y = -60;
    public const DIRT_MAX_Y = -59;
    public const GRASS_Y = -58;

    /** Oyuncunun doğacağı Y (grass'ın bir üstü). */
    public const SPAWN_Y = -57;

    private readonly BlockRegistry $registry;

    public function __construct(?BlockRegistry $registry = null)
    {
        $this->registry = $registry ?? BlockRegistry::default();
    }

    public function airNetworkId(): int
    {
        return $this->state('minecraft:air')->getNetworkId();
    }

    /**
     * Verilen chunk konumu için chunk üretir.
     */
    public function generate(ChunkPosition $position): Chunk
    {
        $airId = $this->airNetworkId();
        $chunk = new Chunk($position, $airId);

        $bedrock = $this->state('minecraft:bedrock')->getNetworkId();
        $stone = $this->state('minecraft:stone')->getNetworkId();
        $dirt = $this->state('minecraft:dirt')->getNetworkId();
        $grass = $this->state('minecraft:grass_block', ['snowy' => false])->getNetworkId();

        for ($x = 0; $x < 16; $x++) {
            for ($z = 0; $z < 16; $z++) {
                $chunk->setBlock(self::BEDROCK_Y, $x, $z, $bedrock);

                for ($y = self::STONE_MIN_Y; $y <= self::STONE_MAX_Y; $y++) {
                    $chunk->setBlock($y, $x, $z, $stone);
                }

                for ($y = self::DIRT_MIN_Y; $y <= self::DIRT_MAX_Y; $y++) {
                    $chunk->setBlock($y, $x, $z, $dirt);
                }

                $chunk->setBlock(self::GRASS_Y, $x, $z, $grass);
            }
        }

        $chunk->markClean();

        return $chunk;
    }

    /**
     * Dünya için güvenli spawn konumu (chunk 0,0 merkezi).
     *
     * @return array{x: float, y: float, z: float}
     */
    public function spawnPoint(): array
    {
        return [
            'x' => 8.5,
            'y' => (float) self::SPAWN_Y,
            'z' => 8.5,
        ];
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function state(string $name, array $properties = []): BlockState
    {
        return $this->registry->state($name, $properties);
    }
}
