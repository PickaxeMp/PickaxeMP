<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\ProtocolException;

/**
 * `PropertySyncData` — senkronize varlik ozellikleri (int ve float listeleri).
 *
 * Tel bicimi (Mojang `PropertySyncData.json`):
 *  - `Int Entries List`   varuint32 sayi; her giris: uint32 indeks + int32 deger
 *  - `Float Entries List` varuint32 sayi; her giris: uint32 indeks + float deger
 */
final class PropertySyncData
{
    public const MAX_ENTRIES = 512;

    /**
     * @param list<array{index: int, value: int}> $intEntries
     * @param list<array{index: int, value: float}> $floatEntries
     */
    public function __construct(
        public readonly array $intEntries = [],
        public readonly array $floatEntries = []
    ) {
        if (count($intEntries) > self::MAX_ENTRIES) {
            throw ProtocolException::lengthTooLarge(
                'property sync int entries',
                count($intEntries),
                self::MAX_ENTRIES
            );
        }

        if (count($floatEntries) > self::MAX_ENTRIES) {
            throw ProtocolException::lengthTooLarge(
                'property sync float entries',
                count($floatEntries),
                self::MAX_ENTRIES
            );
        }
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeVarUInt(count($this->intEntries));

        foreach ($this->intEntries as $entry) {
            $writer->writeUint32($entry['index']);
            $writer->writeInt32($entry['value']);
        }

        $writer->writeVarUInt(count($this->floatEntries));

        foreach ($this->floatEntries as $entry) {
            $writer->writeUint32($entry['index']);
            $writer->writeFloat($entry['value']);
        }
    }

    public static function decode(BinaryReader $reader): self
    {
        $intCount = $reader->readVarUInt();

        if ($intCount > self::MAX_ENTRIES) {
            throw ProtocolException::lengthTooLarge('property sync int entries', $intCount, self::MAX_ENTRIES);
        }

        $intEntries = [];

        for ($i = 0; $i < $intCount; $i++) {
            $intEntries[] = [
                'index' => $reader->readUint32(),
                'value' => $reader->readInt32(),
            ];
        }

        $floatCount = $reader->readVarUInt();

        if ($floatCount > self::MAX_ENTRIES) {
            throw ProtocolException::lengthTooLarge(
                'property sync float entries',
                $floatCount,
                self::MAX_ENTRIES
            );
        }

        $floatEntries = [];

        for ($i = 0; $i < $floatCount; $i++) {
            $floatEntries[] = [
                'index' => $reader->readUint32(),
                'value' => $reader->readFloat(),
            ];
        }

        return new self($intEntries, $floatEntries);
    }
}
