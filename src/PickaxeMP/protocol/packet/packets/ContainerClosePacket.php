<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;

/**
 * `ContainerClosePacket` (id **47**, sunucu → istemci).
 *
 * Sema (Mojang v1.26.65, 2225; `ContainerClosePacketPayload.json`):
 * Container Id uint8, Container Type uint8, Server Initiated Close bool.
 */
final class ContainerClosePacket implements PacketInterface
{
    public function __construct(
        public readonly int $containerId,
        public readonly int $containerType,
        public readonly bool $serverInitiatedClose
    ) {
    }

    public function getId(): int
    {
        return PacketId::CONTAINER_CLOSE;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeByte($this->containerId);
        $writer->writeByte($this->containerType);
        $writer->writeBool($this->serverInitiatedClose);
    }

    public static function decode(BinaryReader $reader): static
    {
        return new static(
            $reader->readByte(),
            $reader->readByte(),
            $reader->readBool()
        );
    }
}
