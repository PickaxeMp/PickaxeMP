<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\types\ItemEntry;

/**
 * `ItemRegistryPacket` (id **162**, sunucu -> istemci).
 *
 * Şema (Mojang v1.26.65, 2225): gövde tek bir dizi.
 *
 * ```
 * 0 Items  list<ItemEntry>  (varuint32 sayi + girisler)
 * ```
 *
 * ## Neden zorunlu?
 *
 * Şema notu: *"This packet needs to be sent immediately after the
 * StartGamePacket for primary clients."* İstemci sayısal eşya kimliklerini
 * (ör. `285` = `minecraft:apple`) yalnızca bu tablodan çözer. Tablo
 * gönderilmezse `InventorySlot`, `AddItemActor` ve `ItemStackResponse`
 * içindeki kimlikler istemcide anlamsızdır; mobil istemciler eksik listede
 * çökebilir (gophertunnel `ItemRegistry` yorumu).
 */
final class ItemRegistryPacket implements PacketInterface
{
    /**
     * Üst sınır: vanilya tablosu ~1900 giriş. Sınır, bozuk/istismar edilen
     * bir uzunluk alanının belleği şişirmesini engeller.
     */
    public const MAX_ENTRIES = 8192;

    /**
     * @param list<ItemEntry> $entries
     */
    public function __construct(public readonly array $entries)
    {
        if (count($entries) > self::MAX_ENTRIES) {
            throw new \InvalidArgumentException(
                'ItemRegistry: giris sayisi siniri asti (' . count($entries) . ')'
            );
        }

        foreach ($entries as $entry) {
            if (!$entry instanceof ItemEntry) {
                throw new \InvalidArgumentException('ItemRegistry: girisler ItemEntry olmali');
            }
        }
    }

    public function getId(): int
    {
        return PacketId::ITEM_REGISTRY;
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    public function count(): int
    {
        return count($this->entries);
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeVarUInt(count($this->entries));

        foreach ($this->entries as $entry) {
            $entry->encode($writer);
        }
    }

    public static function decode(BinaryReader $reader): static
    {
        $count = $reader->readVarUInt();

        if ($count > self::MAX_ENTRIES) {
            throw new \InvalidArgumentException('ItemRegistry: giris sayisi siniri asti (' . $count . ')');
        }

        $entries = [];

        for ($i = 0; $i < $count; $i++) {
            $entries[] = ItemEntry::decode($reader);
        }

        $reader->assertFullyConsumed('ItemRegistry');

        return new static($entries);
    }
}
