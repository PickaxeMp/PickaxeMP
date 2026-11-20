<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;

/**
 * `SetLocalPlayerAsInitializedPacket` (id **113**, istemci → sunucu).
 *
 * Şema (Mojang v1.26.65, 2225): `Player ID` → `ActorRuntimeID` = **varuint64**.
 *
 * İstemci, dünya yüklendikten ve oyuncu ekranda göründükten sonra gönderir.
 * Sunucu için anlamı: "artık chunk akışına ve zaman senkronuna hazırım".
 * Paket gelmeden ağır veri göndermek, istemci tarafında boşa iş olur.
 *
 * Runtime kimlik istemci tarafından **iddia edilir**; çağıran katman bunu
 * oturumun gerçek kimliğiyle karşılaştırmalıdır.
 */
final class SetLocalPlayerAsInitializedPacket implements PacketInterface
{
    public function __construct(
        public readonly int $playerRuntimeId
    ) {
    }

    public function getId(): int
    {
        return PacketId::SET_LOCAL_PLAYER_AS_INITIALIZED;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeVarULong($this->playerRuntimeId);
    }

    public static function decode(BinaryReader $reader): static
    {
        return new static($reader->readVarULong());
    }
}
