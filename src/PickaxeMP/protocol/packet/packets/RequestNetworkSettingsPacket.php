<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\ProtocolVersion;

/**
 * `RequestNetworkSettingsPacket` (id 193, C→S).
 *
 * Şema (Mojang v1.26.65, 2225):
 *   ClientNetworkVersion — int32, **Big Endian**, 2225..2225
 *
 * İstemcinin gönderdiği İLK Bedrock paketidir. Şemadaki not: "bu paket
 * istemci sürümünden başka bir şey içermemeli".
 */
final class RequestNetworkSettingsPacket implements PacketInterface
{
    public function __construct(
        public readonly int $clientNetworkVersion = ProtocolVersion::PROTOCOL
    ) {
    }

    public function getId(): int
    {
        return PacketId::REQUEST_NETWORK_SETTINGS;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeInt32BigEndian($this->clientNetworkVersion);
    }

    public static function decode(BinaryReader $reader): static
    {
        return new static($reader->readInt32BigEndian());
    }

    public function isCompatible(): bool
    {
        return ProtocolVersion::isCompatible($this->clientNetworkVersion);
    }
}
