<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\types\PlayerListEntry;
use PickaxeMP\protocol\ProtocolException;

/**
 * `PlayerListPacket` (id **63**, sunucu -> istemci).
 *
 * Şema (Mojang v1.26.65, 2225) - `PlayerListPacketPayload`, ordinal 0:
 *
 * ```
 * Entries  dizi<PlayerListEntry>  (varuint32 sayi, en fazla 1000)
 * ```
 *
 * Her giriş kendi ayırıcısını taşır; ayrıntı ve iki ayırıcının gerekçesi
 * {@see PlayerListEntry} sınıfında.
 *
 * ## Neden gerekli
 *
 * `AddPlayer` gönderildiğinde istemci, oyuncunun derisini ve birkaç
 * özelliğini **yalnızca** `PlayerList`'ten okur. Liste gönderilmezse
 * oyuncu görünmez.
 */
final class PlayerListPacket implements PacketInterface
{
    public const MAX_ENTRIES = 1000;

    /** @var list<PlayerListEntry> */
    public readonly array $entries;

    /**
     * @param list<PlayerListEntry> $entries
     */
    public function __construct(array $entries = [])
    {
        if (count($entries) > self::MAX_ENTRIES) {
            throw ProtocolException::lengthTooLarge('player list entries', count($entries), self::MAX_ENTRIES);
        }

        $this->entries = array_values($entries);
    }

    public function getId(): int
    {
        return PacketId::PLAYER_LIST;
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
            throw ProtocolException::lengthTooLarge('player list entries', $count, self::MAX_ENTRIES);
        }

        $entries = [];

        for ($i = 0; $i < $count; $i++) {
            $entries[] = PlayerListEntry::decode($reader);
        }

        return new static($entries);
    }
}
