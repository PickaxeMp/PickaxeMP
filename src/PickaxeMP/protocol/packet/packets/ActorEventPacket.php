<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\types\ActorEventType;
use PickaxeMP\protocol\packet\types\Vec3;

/**
 * `ActorEventPacket` (id **27**, sunucu -> istemci).
 *
 * Şema (Mojang v1.26.65, 2225) - `ActorEventPacketPayload`, ordinal sırası:
 *
 * ```
 * 0 Target Runtime ID  ActorRuntimeID (varuint64)
 * 1 Event ID           uint8 (enum-as-value)
 * 2 Data               int32 (Compression -> zigzag varint32)
 * 3 Fire At Position   Vec3  (zorunlu DEGIL)
 * ```
 *
 * ## `Fire At Position` neden hiç yazılmıyor?
 *
 * Alan `required` listesinde yok; varlığı tel üzerinde bir bayrakla
 * belirtilmiyor (opsiyonel alanlar şemada makine-okunur değil). Bilinen
 * tek taşıyıcı `FIREWORKS_EXPLODE`'dur. Bu sunucu o olayı üretmediği için
 * alan hiç yazılmaz; yazılsaydı istemci paket sonunda beklenmeyen baytlar
 * görürdü. `Vec3` alanı yine de API'de tutulur ki ileride tek bir olay için
 * yazılabilsin.
 */
final class ActorEventPacket implements PacketInterface
{
    public function __construct(
        public readonly int $targetRuntimeId,
        public readonly ActorEventType $event,
        public readonly int $data = 0,
        public readonly ?Vec3 $fireAtPosition = null
    ) {
    }

    public function getId(): int
    {
        return PacketId::ACTOR_EVENT;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeVarULong($this->targetRuntimeId);
        $writer->writeByte($this->event->value);
        $writer->writeVarInt($this->data);

        // Yalnizca bu olay konum tasir; digerlerinde alan yazilmaz. Ayristirici
        // ayni kurali uyguladigi icin kodlama/cozme simetriktir.
        if ($this->fireAtPosition !== null && $this->event->carriesFirePosition()) {
            $this->fireAtPosition->encode($writer);
        }
    }

    public static function decode(BinaryReader $reader): static
    {
        $runtimeId = $reader->readVarULong();
        $event = ActorEventType::fromWire($reader->readByte());
        $data = $reader->readVarInt();
        $fireAt = null;

        // Fire At Position yalnizca FIREWORKS_EXPLODE icin ve yalnizca
        // bayt kaldiysa okunur: uzunluk oneki yok, baska isaret yok.
        if ($event->carriesFirePosition() && $reader->remaining() >= 12) {
            $fireAt = Vec3::decode($reader);
        }

        return new static($runtimeId, $event, $data, $fireAt);
    }
}
