<?php

declare(strict_types=1);

namespace PickaxeMP\world\chunk;

use PickaxeMP\protocol\packet\packets\LevelChunkPacket;

/**
 * Bir `Chunk` sütununu `LevelChunk` paketine çeviren tek yer.
 *
 * `PlayerJoinService` (tek chunk, giriş) ve `ChunkStreamer` (görüş
 * mesafesi kadar chunk) aynı serileştirmeyi kullanmak zorundadır; iki ayrı
 * kopya zamanla ayrışır ve istemci birinde bozuk chunk görür.
 *
 * Yalnızca **dolu** subchunk'lar gönderilir (`nonEmptySubChunkIndices()`);
 * boş olanlar `subChunkCount`'a girmez. Sıra artan Y indeksidir — istemci
 * bunu bekler.
 */
final class ChunkSerializer
{
    private readonly SubChunkSerializer $subChunkSerializer;

    public function __construct(?SubChunkSerializer $subChunkSerializer = null)
    {
        $this->subChunkSerializer = $subChunkSerializer ?? new SubChunkSerializer();
    }

    public function subChunkCount(Chunk $chunk): int
    {
        return count($chunk->nonEmptySubChunkIndices());
    }

    public function serialize(Chunk $chunk): string
    {
        $output = '';

        foreach ($chunk->nonEmptySubChunkIndices() as $index) {
            $subChunk = $chunk->getSubChunk($index);

            if (!$subChunk instanceof SubChunk) {
                continue;
            }

            $output .= $this->subChunkSerializer->serialize(
                $subChunk,
                $index,
                $chunk->airNetworkId()
            );
        }

        return $output;
    }

    public function packet(Chunk $chunk, ChunkPosition $position, int $dimensionId = 0): LevelChunkPacket
    {
        return new LevelChunkPacket(
            chunkX: $position->x,
            chunkZ: $position->z,
            dimensionId: $dimensionId,
            subChunkCount: $this->subChunkCount($chunk),
            serializedChunkData: $this->serialize($chunk),
            isClientBiomeUpdate: false
        );
    }
}
