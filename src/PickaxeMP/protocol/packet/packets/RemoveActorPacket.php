<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;

/**
 * `RemoveActorPacket` (id **14**, sunucu → istemci).
 *
 * Sema (Mojang v1.26.65, 2225): Target Actor ID int64
 * (Compression → varlong).
 */
final class RemoveActorPacket implements PacketInterface
{
    public function __construct(
        public readonly int $targetActorId
    ) {
    }

    public function getId(): int
    {
        return PacketId::REMOVE_ACTOR;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeVarLong($this->targetActorId);
    }

    public static function decode(BinaryReader $reader): static
    {
        return new static($reader->readVarLong());
    }
}
