<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;

/**
 * `LevelChunkPacket` (id 58, S→C).
 *
 * Şema (Mojang v1.26.65, 2225) — ordinal sırası:
 *   0 Chunk Position (int32 varint X, int32 varint Z)
 *   1 Dimension Id (int32 varint)
 *   2 Sub-chunks Count (uint32 varint)
 *   3 Client Request SubChunk Limit (int32 varint, **opsiyonel**)
 *   4 Cache Enabled (bool)
 *   5 Cache Metadata (array<{uint64 blobId}>, yalnızca Cache Enabled ise)
 *   6 Serialized Chunk Data (string)
 *   7 Is Client Biome Update (bool)
 *
 * ## Opsiyonel alan semantiği
 *
 * Mojang "Primitives": bir alan `required` değilse başına **varlık baytı**
 * gelir. `Client Request SubChunk Limit` required listesinde **yok** →
 * `0x00` (yok) veya `0x01 + varint` yazılır.
 *
 * PickaxeMP tüm chunk'ları tam gönderdiği için bu alanı **göndermez**
 * (`0x00`). Bu, "istek modu kapalı" anlamına gelir.
 *
 * ## Sub-chunks Count özel anlamı
 *
 * `0`  = chunk tamamen boş (Serialized Chunk Data da boş olmalı)
 * `>0` = sütundaki subchunk sayısı; veri art arda serileştirilmiş
 *        subchunk'lardır (bkz. `SubChunkSerializer` v9).
 *
 * `0xFFFFFFFF` (uint32 -1) "limit yok" anlamında kullanılır ama Phase 3+
 * sunucular bunu göndermez. PickaxeMP gerçek sayıyı gönderir.
 */
final class LevelChunkPacket implements PacketInterface
{
    /**
     * @param int $subChunkCount Gönderilen subchunk sayısı (0 = boş chunk)
     * @param string $serializedChunkData Art arda serileştirilmiş subchunk'lar
     * @param list<int>|null $clientRequestSubChunkLimit null → alan yok
     */
    public function __construct(
        public readonly int $chunkX,
        public readonly int $chunkZ,
        public readonly int $dimensionId = 0,
        public readonly int $subChunkCount = 0,
        public readonly ?int $clientRequestSubChunkLimit = null,
        public readonly bool $cacheEnabled = false,
        public readonly array $cacheMetadata = [],
        public readonly string $serializedChunkData = '',
        public readonly bool $isClientBiomeUpdate = false
    ) {
    }

    public function getId(): int
    {
        return PacketId::LEVEL_CHUNK;
    }

    public function encode(BinaryWriter $writer): void
    {
        // 0 Chunk Position
        $writer->writeVarInt($this->chunkX);
        $writer->writeVarInt($this->chunkZ);

        // 1 Dimension Id
        $writer->writeVarInt($this->dimensionId);

        // 2 Sub-chunks Count
        $writer->writeVarUInt($this->subChunkCount);

        // 3 Client Request SubChunk Limit — opsiyonel
        if ($this->clientRequestSubChunkLimit === null) {
            $writer->writeBool(false);
        } else {
            $writer->writeBool(true);
            $writer->writeVarInt($this->clientRequestSubChunkLimit);
        }

        // 4 Cache Enabled
        $writer->writeBool($this->cacheEnabled);

        // 5 Cache Metadata — yalnızca cache açıksa
        if ($this->cacheEnabled) {
            $writer->writeVarUInt(count($this->cacheMetadata));

            foreach ($this->cacheMetadata as $blobId) {
                $writer->writeUint64($blobId);
            }
        }

        // 6 Serialized Chunk Data
        $writer->writeString($this->serializedChunkData);

        // 7 Is Client Biome Update
        $writer->writeBool($this->isClientBiomeUpdate);
    }

    public static function decode(BinaryReader $reader): static
    {
        $chunkX = $reader->readVarInt();
        $chunkZ = $reader->readVarInt();
        $dimensionId = $reader->readVarInt();
        $subChunkCount = $reader->readVarUInt();

        $limit = null;

        if ($reader->readBool()) {
            $limit = $reader->readVarInt();
        }

        $cacheEnabled = $reader->readBool();
        $metadata = [];

        if ($cacheEnabled) {
            $count = $reader->readVarUInt();

            for ($i = 0; $i < $count; $i++) {
                $metadata[] = $reader->readUint64();
            }
        }

        $serialized = $reader->readString();
        $biomeUpdate = $reader->readBool();

        return new static(
            chunkX: $chunkX,
            chunkZ: $chunkZ,
            dimensionId: $dimensionId,
            subChunkCount: $subChunkCount,
            clientRequestSubChunkLimit: $limit,
            cacheEnabled: $cacheEnabled,
            cacheMetadata: $metadata,
            serializedChunkData: $serialized,
            isClientBiomeUpdate: $biomeUpdate
        );
    }
}
