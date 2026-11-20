<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;

/**
 * `RequestChunkRadiusPacket` (id **69**, istemci → sunucu).
 *
 * Şema (Mojang v1.26.65, 2225):
 *  - `Chunk Radius` int32 + `Compression` → **ZigZag varint32**
 *  - `Max ChunkRadius` uint8
 *
 * İstemci istediği görüş mesafesini bildirir; sunucu **kırpar** ve gerçek
 * değeri `ChunkRadiusUpdated` ile döner. İstemcinin istediği değer otorite
 * değildir: sınırsız kabul etmek, tek bir istemcinin sunucudan çok büyük
 * miktarda chunk verisi çekmesine izin verirdi.
 */
final class RequestChunkRadiusPacket implements PacketInterface
{
    public function __construct(
        public readonly int $chunkRadius,
        public readonly int $maxChunkRadius = 0
    ) {
    }

    public function getId(): int
    {
        return PacketId::REQUEST_CHUNK_RADIUS;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeVarInt($this->chunkRadius);
        $writer->writeByte($this->maxChunkRadius & 0xFF);
    }

    public static function decode(BinaryReader $reader): static
    {
        return new static(
            chunkRadius: $reader->readVarInt(),
            maxChunkRadius: $reader->readByte()
        );
    }
}
