<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\ProtocolException;

/**
 * `ItemStackResponseSlotInfo` — yanıtta tek yuvanın **gerçek** durumu.
 *
 * Tel biçimi (Mojang `ItemStackResponseSlotInfo.json`, 2225):
 *
 * ```
 *  0 Requested Slot        uint8   (istemcinin istedigi yuva)
 *  1 Slot                  uint8   (sunucunun uyguladigi yuva)
 *  2 Amount                uint8   (yuvadaki toplam miktar)
 *  3 Item Stack Net Id     istege bagli (varlik bayti + varint32)
 *  4 Custom Name           string
 *  5 Filtered Name         istege bagli string
 *  6 Durability Correction varint32 (zigzag, int16 araligina sigar)
 * ```
 *
 * Sunucu ağ kimliği üretmediği için `netId` `null` gönderilir; varlık baytı
 * yine de yazılır (şema "her zaman bayt" der). İstemci bu durumda "legacy"
 * davranır.
 */
final class ItemStackResponseSlotInfo
{
    public function __construct(
        public readonly int $requestedSlot,
        public readonly int $slot,
        public readonly int $amount,
        public readonly ?int $netId = null,
        public readonly string $customName = '',
        public readonly ?string $filteredCustomName = null,
        public readonly int $durabilityCorrection = 0
    ) {
        foreach (['requestedSlot' => $requestedSlot, 'slot' => $slot, 'amount' => $amount] as $name => $value) {
            if ($value < 0 || $value > 0xFF) {
                throw ProtocolException::varIntOverflow("item stack response {$name} (uint8)");
            }
        }

        if ($durabilityCorrection < -32768 || $durabilityCorrection > 32767) {
            throw ProtocolException::varIntOverflow('item stack response durability correction (int16)');
        }
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeByte($this->requestedSlot);
        $writer->writeByte($this->slot);
        $writer->writeByte($this->amount);

        $writer->writeOptional($this->netId !== null, function (BinaryWriter $w): void {
            $w->writeVarInt($this->netId ?? 0);
        });

        $writer->writeString($this->customName);

        $writer->writeOptional($this->filteredCustomName !== null, function (BinaryWriter $w): void {
            $w->writeString($this->filteredCustomName ?? '');
        });

        $writer->writeVarInt($this->durabilityCorrection);
    }

    public static function decode(BinaryReader $reader): self
    {
        $requestedSlot = $reader->readByte();
        $slot = $reader->readByte();
        $amount = $reader->readByte();
        $netId = $reader->readBool() ? $reader->readVarInt() : null;
        $customName = $reader->readString(ItemStackRequest::MAX_FILTER_STRING_BYTES);
        $filteredCustomName = $reader->readBool()
            ? $reader->readString(ItemStackRequest::MAX_FILTER_STRING_BYTES)
            : null;
        $durabilityCorrection = $reader->readVarInt();

        return new self(
            requestedSlot: $requestedSlot,
            slot: $slot,
            amount: $amount,
            netId: $netId,
            customName: $customName,
            filteredCustomName: $filteredCustomName,
            durabilityCorrection: $durabilityCorrection
        );
    }
}
