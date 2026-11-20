<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\types\Attribute;
use PickaxeMP\protocol\ProtocolException;

/**
 * `UpdateAttributesPacket` (id **29**, sunucu → istemci).
 *
 * Şema (Mojang v1.26.65, 2225; `UpdateAttributesPacketPayload`):
 * `Target Runtime ID` → varuint64, `Attribute List` → varuint32 sayı +
 * {@see Attribute}, `Tick` → varuint64 (`PlayerInputTick`).
 *
 * `Tick`, **en son işlenen** `PlayerAuthInput` tick'idir. Bizde sunucu yetkili
 * hareket olmadığı için `0` kabul edilir (şema bunu açıkça izin verir).
 */
final class UpdateAttributesPacket implements PacketInterface
{
    public const MAX_ATTRIBUTES = 64;

    /**
     * @param list<Attribute> $attributes
     */
    public function __construct(
        public readonly int $targetRuntimeId,
        public readonly array $attributes,
        public readonly int $tick = 0
    ) {
        if ($targetRuntimeId < 0) {
            throw ProtocolException::varIntOverflow('target runtime id (varuint64)');
        }

        if ($tick < 0) {
            throw ProtocolException::varIntOverflow('tick (varuint64)');
        }

        if (count($attributes) > self::MAX_ATTRIBUTES) {
            throw ProtocolException::lengthTooLarge('attributes', count($attributes), self::MAX_ATTRIBUTES);
        }

        foreach ($attributes as $attribute) {
            if (!$attribute instanceof Attribute) {
                throw new ProtocolException('UpdateAttributes: attributes Attribute olmali');
            }
        }
    }

    public function getId(): int
    {
        return PacketId::UPDATE_ATTRIBUTES;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeVarULong($this->targetRuntimeId);
        $writer->writeVarUInt(count($this->attributes));

        foreach ($this->attributes as $attribute) {
            $attribute->encode($writer);
        }

        $writer->writeVarULong($this->tick);
    }

    public static function decode(BinaryReader $reader): static
    {
        $targetRuntimeId = $reader->readVarULong();
        $count = $reader->readVarUInt();

        if ($count > self::MAX_ATTRIBUTES) {
            throw ProtocolException::lengthTooLarge('attributes', $count, self::MAX_ATTRIBUTES);
        }

        $attributes = [];

        for ($i = 0; $i < $count; $i++) {
            $attributes[] = Attribute::decode($reader);
        }

        return new static($targetRuntimeId, $attributes, $reader->readVarULong());
    }
}
