<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;

/**
 * `TakeItemActorPacket` (id **17**, istemci → sunucu).
 *
 * Sema (Mojang v1.26.65, 2225; `TakeItemActorPacketPayload.json`, ordinal 0..1):
 *
 * ```
 *  0 Item Runtime ID   varuint64   (yerdeki eşya varlığının runtime id'si)
 *  1 Actor Runtime ID  varuint64   (alan oyuncunun runtime id'si)
 * ```
 *
 * İstemci yerdeki bir eşyayı toplamak istediğinde gönderir. Sunucu mesafeyi
 * doğrular, envanteri günceller ve varlığı kaldırır. **Kimliklere güvenilmez**:
 * paket yalnızca "hangi varlığı istiyorum" der; gerçek eşya/miktar sunucudaki
 * varlık kaydından okunur.
 */
final class TakeItemActorPacket implements PacketInterface
{
    public function __construct(
        public readonly int $itemRuntimeId,
        public readonly int $actorRuntimeId
    ) {
    }

    public function getId(): int
    {
        return PacketId::TAKE_ITEM_ACTOR;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeVarULong($this->itemRuntimeId);
        $writer->writeVarULong($this->actorRuntimeId);
    }

    public static function decode(BinaryReader $reader): static
    {
        return new static(
            itemRuntimeId: $reader->readVarULong(),
            actorRuntimeId: $reader->readVarULong()
        );
    }
}
