<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\ProtocolException;

/**
 * `LegacySetSlot` — istemcinin degistirdigi konteyner slotlari.
 *
 * Tel bicimi: `Container Enum` uint8 + `Slots` (varuint32 sayi + uint8'ler).
 */
final class LegacySetSlot
{
    public const MAX_SLOTS = 128;

    /**
     * @param list<int> $slots
     */
    public function __construct(
        public readonly int $containerEnum,
        public readonly array $slots
    ) {
        if ($containerEnum < 0 || $containerEnum > 0xFF) {
            throw ProtocolException::varIntOverflow('legacy set slot container enum (uint8)');
        }

        if (count($slots) > self::MAX_SLOTS) {
            throw ProtocolException::lengthTooLarge('legacy set slot list', count($slots), self::MAX_SLOTS);
        }

        foreach ($slots as $slot) {
            if (!is_int($slot) || $slot < 0 || $slot > 0xFF) {
                throw ProtocolException::varIntOverflow('legacy set slot index (uint8)');
            }
        }
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeByte($this->containerEnum);
        $writer->writeVarUInt(count($this->slots));

        foreach ($this->slots as $slot) {
            $writer->writeByte($slot);
        }
    }

    public static function decode(BinaryReader $reader): self
    {
        $containerEnum = $reader->readByte();
        $count = $reader->readVarUInt();

        if ($count > self::MAX_SLOTS) {
            throw ProtocolException::lengthTooLarge('legacy set slot list', $count, self::MAX_SLOTS);
        }

        $slots = [];

        for ($i = 0; $i < $count; $i++) {
            $slots[] = $reader->readByte();
        }

        return new self($containerEnum, $slots);
    }
}
