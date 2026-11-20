<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\types\MoveActorDeltaData;

/**
 * `MoveActorDeltaPacket` (id **111**, sunucu -> istemci).
 *
 * Tel biçimi tek bir gömülü yapıdır; ayrıntı ve alan sırası
 * {@see MoveActorDeltaData} sınıfında.
 *
 * Paket yalnızca **değişen** bileşenleri taşır; bu yüzden sunucu tarafında
 * üretirken `MovePlayer` yerine bunu kullanmak bant genişliği kazandırır.
 * Bu sunucu şu an oyuncular için `MovePlayer`, diğer varlıklar için
 * `MoveActorAbsolute` kullanıyor; paket ileride varlık yayını için hazır.
 */
final class MoveActorDeltaPacket implements PacketInterface
{
    public function __construct(
        public readonly MoveActorDeltaData $data
    ) {
    }

    public function getId(): int
    {
        return PacketId::MOVE_ACTOR_DELTA;
    }

    public function encode(BinaryWriter $writer): void
    {
        $this->data->encode($writer);
    }

    public static function decode(BinaryReader $reader): static
    {
        return new static(MoveActorDeltaData::decode($reader));
    }
}
