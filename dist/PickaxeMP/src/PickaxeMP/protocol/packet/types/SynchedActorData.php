<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\ProtocolException;

/**
 * `SynchedActorDataList` — varlik meta verisi listesi.
 *
 * Tel bicimi (Mojang `SynchedActorDataList.json`, gophertunnel
 * `Writer.EntityMetadata` ile dogrulandi):
 *
 * ```
 *  Count  varuint32
 *  Data   DataItemEntry[Count]
 * ```
 *
 * ## Siralama
 *
 * Girdiler **ID'ye gore artan** yazilir. Vanilla istemcisi bazi ozellikleri
 * (NPC metni, dugmeler) yalnizca artan sirada dogru isler; gophertunnel de
 * yazmadan once siraliyor. Bu yuzden `encode()` siralamayi kendisi uygular,
 * cagirana guvenmez.
 */
final class SynchedActorData
{
    /** @var list<SynchedActorDataEntry> */
    private array $entries;

    /**
     * @param list<SynchedActorDataEntry> $entries
     */
    public function __construct(array $entries = [])
    {
        $seen = [];

        foreach ($entries as $entry) {
            if (!$entry instanceof SynchedActorDataEntry) {
                throw new ProtocolException('SynchedActorData: yalnizca SynchedActorDataEntry kabul edilir');
            }

            if (isset($seen[$entry->id])) {
                throw new ProtocolException("SynchedActorData: tekrarlanan veri kimligi {$entry->id}");
            }

            $seen[$entry->id] = true;
        }

        $this->entries = array_values($entries);
    }

    public static function empty(): self
    {
        return new self();
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * @return list<SynchedActorDataEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    public function get(int $id): ?SynchedActorDataEntry
    {
        foreach ($this->entries as $entry) {
            if ($entry->id === $id) {
                return $entry;
            }
        }

        return null;
    }

    public function with(SynchedActorDataEntry $entry): self
    {
        $entries = [];

        foreach ($this->entries as $existing) {
            if ($existing->id !== $entry->id) {
                $entries[] = $existing;
            }
        }

        $entries[] = $entry;

        return new self($entries);
    }

    public function encode(BinaryWriter $writer): void
    {
        $entries = $this->entries;
        usort($entries, static fn (SynchedActorDataEntry $a, SynchedActorDataEntry $b): int => $a->id <=> $b->id);

        $writer->writeVarUInt(count($entries));

        foreach ($entries as $entry) {
            $entry->encode($writer);
        }
    }

    public static function decode(BinaryReader $reader): self
    {
        $count = $reader->readVarUInt();

        if ($count > 4096) {
            throw ProtocolException::lengthTooLarge('synched actor data entries', $count, 4096);
        }

        $entries = [];

        for ($i = 0; $i < $count; $i++) {
            $entries[] = SynchedActorDataEntry::decode($reader);
        }

        return new self($entries);
    }
}
