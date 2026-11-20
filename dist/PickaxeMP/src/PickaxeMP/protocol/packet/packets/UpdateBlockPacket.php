<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\types\BlockPos;

/**
 * `UpdateBlockPacket` (id **21**, sunucu -> istemci).
 *
 * Sema (Mojang v1.26.65, 2225) - `UpdateBlockPacketPayload`, ordinal sirasi:
 *
 * ```
 * 0 Block Position     BlockPos (varint32 x 3)
 * 1 Block Runtime ID   uint32 (Compression -> varuint32)
 * 2 Flags              uint32 (Compression -> varuint32)
 * 3 Layer              uint32 (Compression -> varuint32)
 * ```
 *
 * `Flags` bitleri: 0x1 = neighbour'leri de guncelle, 0x2 = ag baglantisi
 * (yalniz istemci ici), 0x4 = "no graphic". Sunucu varsayilani
 * {@see self::FLAG_NEIGHBORS} (1) ve {@see self::FLAG_NETWORK} (2) birlikte
 * gonderilir; gercek istemci kabulu sinanmadi.
 */
final class UpdateBlockPacket implements PacketInterface
{
    /** Komsu bloklari da guncelle. */
    public const FLAG_NEIGHBORS = 0x1;
    /** Ag uzerinden gonderildi (istemci tarafi bilgi). */
    public const FLAG_NETWORK = 0x2;
    /** Grafik guncellemesi yok. */
    public const FLAG_NO_GRAPHIC = 0x4;

    /** Sunucunun varsayilan bayrak birlesimi. */
    public const DEFAULT_FLAGS = self::FLAG_NEIGHBORS | self::FLAG_NETWORK;

    public function __construct(
        public readonly BlockPos $position,
        public readonly int $blockRuntimeId,
        public readonly int $flags = self::DEFAULT_FLAGS,
        public readonly int $layer = 0
    ) {
    }

    public function getId(): int
    {
        return PacketId::UPDATE_BLOCK;
    }

    public function encode(BinaryWriter $writer): void
    {
        $this->position->encode($writer);
        $writer->writeVarUInt($this->blockRuntimeId);
        $writer->writeVarUInt($this->flags);
        $writer->writeVarUInt($this->layer);
    }

    public static function decode(BinaryReader $reader): static
    {
        return new static(
            position: BlockPos::decode($reader),
            blockRuntimeId: $reader->readVarUInt(),
            flags: $reader->readVarUInt(),
            layer: $reader->readVarUInt()
        );
    }
}
