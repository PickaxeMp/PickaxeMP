<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\types\SerializedAbilitiesData;

/**
 * `UpdateAbilitiesPacket` (id **187**, sunucu → istemci).
 *
 * Şema: `Data` → {@see SerializedAbilitiesData}. `AdventureSettings`
 * paketinin yerini alır (v1.19.10+); oyuncunun uçma/inşa izinlerini ve
 * yürüme/uçuş hızlarını belirler.
 */
final class UpdateAbilitiesPacket implements PacketInterface
{
    public function __construct(
        public readonly SerializedAbilitiesData $data
    ) {
    }

    public function getId(): int
    {
        return PacketId::UPDATE_ABILITIES;
    }

    public function encode(BinaryWriter $writer): void
    {
        $this->data->encode($writer);
    }

    public static function decode(BinaryReader $reader): static
    {
        return new static(SerializedAbilitiesData::decode($reader));
    }
}
