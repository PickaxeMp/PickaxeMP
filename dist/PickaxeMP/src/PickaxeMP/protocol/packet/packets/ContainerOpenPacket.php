<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\types\BlockPos;

/**
 * `ContainerOpenPacket` (id **46**, sunucu → istemci).
 *
 * Sema (Mojang v1.26.65, 2225; `ContainerOpenPacketPayload.json`):
 * Container Id uint8, Container Type uint8, Position BlockPos,
 * Target Actor ID int64 (Compression → varlong).
 */
final class ContainerOpenPacket implements PacketInterface
{
    public function __construct(
        public readonly int $containerId,
        public readonly int $containerType,
        public readonly BlockPos $position,
        public readonly int $targetActorId
    ) {
    }

    public function getId(): int
    {
        return PacketId::CONTAINER_OPEN;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeByte($this->containerId);
        $writer->writeByte($this->containerType);
        $this->position->encode($writer);
        $writer->writeVarLong($this->targetActorId);
    }

    public static function decode(BinaryReader $reader): static
    {
        return new static(
            $reader->readByte(),
            $reader->readByte(),
            BlockPos::decode($reader),
            $reader->readVarLong()
        );
    }
}
