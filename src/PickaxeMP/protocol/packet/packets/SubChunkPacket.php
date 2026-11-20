<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\types\SubChunkRequestResult;

/**
 * `SubChunkPacket` (id 174, S→C).
 *
 * Şema (Mojang v1.26.65, 2225) — ordinal sırası:
 *   0 Cache Enabled (bool)
 *   1 Dimension Type (int32 varint)
 *   2 Center Pos (SubChunkPos: 3 × int32 **sıkıştırmasız**)
 *   3 SubChunk Data (array<SubChunkPacketData>)
 *
 * `SubChunkPacketData` (ordinal sırası):
 *   0 SubChunk Pos Offset (3 × int8 — merkeze **göre**)
 *   1 SubChunk Request Result (uint8, 1..6)
 *   2 Serialized Sub Chunk (string)
 *   3 Height Map Data (bkz. aşağıda)
 *   4 Blob Id (uint64 — opsiyonel, cache açıksa)
 *
 * ## Dürüst notlar
 *
 * - `Height Map Data` şeması bu depoda ayrıntılı değil; PickaxeMP **boş**
 *   heightmap gönderir (0 tür, 0 giriş). Bu, düz dünya için yeterli
 *   olabilir; gerçek istemci testi gerekir.
 * - `SubChunkPos` şemada `"Compression"` **yok** → düz int32.
 * - `Pos Offset` int8'dir (−128..127), yani merkeze göre en fazla ±127.
 *
 * Phase 4'te LevelChunk tam veri gönderdiği için bu paket **kullanılmaz**;
 * istek modu (client sub-chunk request) Phase 5+ kapsamıdır. Yine de
 * kodlanmış ve test edilmiştir.
 */
final class SubChunkPacket implements PacketInterface
{
    /**
     * @param list<SubChunkPacketData> $entries
     */
    public function __construct(
        public readonly bool $cacheEnabled = false,
        public readonly int $dimension = 0,
        public readonly int $centerX = 0,
        public readonly int $centerY = 0,
        public readonly int $centerZ = 0,
        public readonly array $entries = []
    ) {
    }

    public function getId(): int
    {
        return PacketId::SUB_CHUNK;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeBool($this->cacheEnabled);
        $writer->writeVarInt($this->dimension);

        // Center Pos — SubChunkPos: 3 × int32 sıkıştırmasız
        $writer->writeInt32($this->centerX);
        $writer->writeInt32($this->centerY);
        $writer->writeInt32($this->centerZ);

        $writer->writeVarUInt(count($this->entries));

        foreach ($this->entries as $entry) {
            $entry->encode($writer);
        }
    }

    public static function decode(BinaryReader $reader): static
    {
        $cacheEnabled = $reader->readBool();
        $dimension = $reader->readVarInt();
        $centerX = $reader->readInt32();
        $centerY = $reader->readInt32();
        $centerZ = $reader->readInt32();

        $count = $reader->readVarUInt();
        $entries = [];

        for ($i = 0; $i < $count; $i++) {
            $entries[] = SubChunkPacketData::decode($reader);
        }

        return new static($cacheEnabled, $dimension, $centerX, $centerY, $centerZ, $entries);
    }
}
