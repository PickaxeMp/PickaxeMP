<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;

/**
 * `SetTimePacket` (id **10**, sunucu → istemci).
 *
 * Şema (Mojang v1.26.65, 2225): `Time` int32, `Compression` seçeneği
 * nedeniyle **ZigZag varint32** olarak yazılır.
 *
 * Değer dünya saatidir (`0..24000`): `0` = gün doğumu, `6000` = öğle,
 * `12000` = gün batımı, `18000` = gece yarısı. İstemci zamanı yerel olarak
 * ilerletir; sunucu yalnızca referans noktasını bildirir.
 */
final class SetTimePacket implements PacketInterface
{
    /** Öğle vakti (varsayılan referans). */
    public const DEFAULT_TIME = 6000;

    /** Bir günün tick sayısı. */
    public const DAY_LENGTH = 24000;

    public function __construct(
        public readonly int $time = self::DEFAULT_TIME
    ) {
    }

    public function getId(): int
    {
        return PacketId::SET_TIME;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeVarInt($this->time);
    }

    public static function decode(BinaryReader $reader): static
    {
        return new static($reader->readVarInt());
    }
}
