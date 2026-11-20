<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\types\PlayStatus;
use PickaxeMP\protocol\packet\PacketInterface;

/**
 * `PlayStatusPacket` (id 2, S→C).
 *
 * Şema (Mojang v1.26.65, 2225):
 *   Status — int32, **Big Endian**, enum 0..9
 *
 * Sunucu login sonucunu bu paketle bildirir. Bilinmeyen bir durum kodu
 * geldiğinde hata fırlatılmaz; ham değer korunur (ileri uyumluluk).
 */
final class PlayStatusPacket implements PacketInterface
{
    public function __construct(public readonly int $status)
    {
    }

    public static function of(PlayStatus $status): self
    {
        return new self($status->value);
    }

    public function getId(): int
    {
        return PacketId::PLAY_STATUS;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeInt32BigEndian($this->status);
    }

    public static function decode(BinaryReader $reader): static
    {
        return new static($reader->readInt32BigEndian());
    }

    public function statusEnum(): ?PlayStatus
    {
        return PlayStatus::fromWire($this->status);
    }

    public function isSuccess(): bool
    {
        return $this->status === PlayStatus::LoginSuccess->value;
    }
}
