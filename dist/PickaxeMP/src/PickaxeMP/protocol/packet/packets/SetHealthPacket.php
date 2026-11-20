<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;

/**
 * `SetHealthPacket` (id **42**, sunucu -> istemci).
 *
 * Sema (Mojang v1.26.65, 2225): `Health` int32, `Compression` secenegi
 * nedeniyle **ZigZag varint32** olarak yazilir.
 */
final class SetHealthPacket implements PacketInterface
{
    public function __construct(
        public readonly int $health
    ) {
    }

    public function getId(): int
    {
        return PacketId::SET_HEALTH;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeVarInt($this->health);
    }

    public static function decode(BinaryReader $reader): static
    {
        return new static($reader->readVarInt());
    }
}
