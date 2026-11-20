<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;

/**
 * `ServerToClientHandshakePacket` (id **3**, S→C).
 *
 * Şema (Mojang v1.26.65, 2225):
 *   Handshake WebToken — string (JWT)
 *
 * Bu paket **şifreleme el sıkışmasının başlangıcıdır**: gönderildikten
 * sonra sunucu, istemciden gelen `ClientToServerHandshake` paketini bekler
 * ve o andan itibaren tüm trafik şifrelenir.
 */
final class ServerToClientHandshakePacket implements PacketInterface
{
    public function __construct(public readonly string $handshakeWebToken)
    {
    }

    public function getId(): int
    {
        return PacketId::SERVER_TO_CLIENT_HANDSHAKE;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeString($this->handshakeWebToken);
    }

    public static function decode(BinaryReader $reader): static
    {
        return new static($reader->readString());
    }
}
