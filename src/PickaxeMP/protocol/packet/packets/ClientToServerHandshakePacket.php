<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;

/**
 * `ClientToServerHandshakePacket` (id **4**, C→S).
 *
 * Şema (Mojang v1.26.65, 2225): **gövdesiz** (boş nesne).
 *
 * İstemci, `ServerToClientHandshake`'i işleyip şifrelemeyi kurduktan sonra
 * bunu gönderir. Bu paket **şifreli** olarak gelir; yani sunucu onu
 * okuyabildiyse istemci doğru anahtarı türetmiş demektir.
 */
final class ClientToServerHandshakePacket implements PacketInterface
{
    public function getId(): int
    {
        return PacketId::CLIENT_TO_SERVER_HANDSHAKE;
    }

    public function encode(BinaryWriter $writer): void
    {
        // Gövde yok.
    }

    public static function decode(BinaryReader $reader): static
    {
        return new static();
    }
}
