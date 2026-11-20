<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;

/**
 * `ChunkRadiusUpdatedPacket` (id **70**, sunucu → istemci).
 *
 * Şema (Mojang v1.26.65, 2225): `Chunk Radius` int32 + `Compression`
 * → **ZigZag varint32**.
 *
 * Sunucunun **kabul ettiği** yarıçaptır. İstemci bu değeri
 * `RequestChunkRadius`'a göre daha küçük bulabilir; küçük olan geçerlidir.
 */
final class ChunkRadiusUpdatedPacket implements PacketInterface
{
    public function __construct(
        public readonly int $chunkRadius
    ) {
    }

    public function getId(): int
    {
        return PacketId::CHUNK_RADIUS_UPDATED;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeVarInt($this->chunkRadius);
    }

    public static function decode(BinaryReader $reader): static
    {
        return new static($reader->readVarInt());
    }
}
