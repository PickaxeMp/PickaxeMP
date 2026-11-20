<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\protocol;

use PHPUnit\Framework\TestCase;
use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\packets\ItemRegistryPacket;
use PickaxeMP\protocol\packet\types\ItemEntry;

/**
 * `ItemRegistryPacket` (162) tel biçimi.
 *
 * Şema (Mojang v1.26.65 / 2225): gövde tek bir dizi —
 * `varuint32` giriş sayısı, ardından her giriş için
 * Name (string), Runtime ID (int16, **sıkıştırmasız LE**),
 * Component Based (bool), Version (varint32), Data (ağ NBT).
 *
 * `Runtime ID` alanı `int16`'dır; negatif değerler (ör. hava `-158`)
 * geçerlidir. Zigzag varint yazılırsa istemci eşya kimliklerini yanlış
 * çözer.
 */
final class ItemRegistryPacketTest extends TestCase
{
    public function testPacketIdIs162(): void
    {
        self::assertSame(162, PacketId::ITEM_REGISTRY);
        self::assertSame('ItemRegistry', PacketId::name(PacketId::ITEM_REGISTRY));
    }

    public function testEmptyRegistryEncodesSingleZeroCount(): void
    {
        $packet = new ItemRegistryPacket([]);

        self::assertSame('00', $this->hex($packet));
        self::assertTrue($packet->isEmpty());
    }

    public function testGoldenBytesForOneEntry(): void
    {
        $packet = new ItemRegistryPacket([
            new ItemEntry('minecraft:apple', 285, false, 2),
        ]);

        // Sayı 1 -> 0x01; "minecraft:apple" = 15 bayt; 285 = 0x011D LE;
        // componentBased=false; version=2 -> zigzag varint32 = 0x04;
        // boş NBT compound = 0A 00 00.
        $expected = '01'
            . '0f' . bin2hex('minecraft:apple')
            . '1d01'
            . '00'
            . '04'
            . '0a0000';

        self::assertSame($expected, $this->hex($packet));
    }

    public function testNegativeRuntimeIdIsWrittenAsSignedInt16(): void
    {
        // Hava `-158`: ikiye tümleyen LE = 0xFF62.
        $packet = new ItemRegistryPacket([
            new ItemEntry('minecraft:air', -158),
        ]);

        $bytes = $this->bytes($packet);

        // Yerlesim: 1 (sayi) + 1 (uzunluk) + 13 ("minecraft:air") = 15 ->
        // runtime id 15..16, sonra bool(1) + version(1) + NBT(3).
        self::assertSame(0x62, ord($bytes[strlen($bytes) - 7]));
        self::assertSame(0xFF, ord($bytes[strlen($bytes) - 6]));
    }

    public function testComponentBasedEntryCarriesCustomData(): void
    {
        $entry = new ItemEntry('minecraft:apple', 285, true, 2, "\x0A\x00\x00");
        $packet = new ItemRegistryPacket([$entry]);

        $decoded = ItemRegistryPacket::decode(new BinaryReader($this->bytes($packet)));

        self::assertCount(1, $decoded->entries);
        self::assertTrue($decoded->entries[0]->componentBased);
        self::assertSame('minecraft:apple', $decoded->entries[0]->name);
        self::assertSame(285, $decoded->entries[0]->runtimeId);
    }

    public function testRoundTripPreservesOrderAndVersions(): void
    {
        $entries = [
            new ItemEntry('minecraft:air', -158, false, 0),
            new ItemEntry('minecraft:diamond_sword', 347, false, 2),
            new ItemEntry('minecraft:honey_bottle', 634, false, 1),
        ];

        $decoded = ItemRegistryPacket::decode(
            new BinaryReader($this->bytes(new ItemRegistryPacket($entries)))
        );

        self::assertSame(
            ['minecraft:air', 'minecraft:diamond_sword', 'minecraft:honey_bottle'],
            array_map(static fn (ItemEntry $entry): string => $entry->name, $decoded->entries)
        );
        self::assertSame([-158, 347, 634], array_map(
            static fn (ItemEntry $entry): int => $entry->runtimeId,
            $decoded->entries
        ));
        self::assertSame([0, 2, 1], array_map(
            static fn (ItemEntry $entry): int => $entry->version,
            $decoded->entries
        ));
    }

    public function testDecodeRejectsTrailingBytes(): void
    {
        $this->expectException(\PickaxeMP\protocol\ProtocolException::class);

        $packet = new ItemRegistryPacket([new ItemEntry('minecraft:apple', 285)]);
        $bytes = $this->bytes($packet) . 'ff';

        ItemRegistryPacket::decode(new BinaryReader($bytes));
    }

    public function testEntryRejectsOutOfRangeRuntimeId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ItemEntry('minecraft:apple', 40000);
    }

    public function testEntryRejectsInvalidIdentifier(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ItemEntry('apple', 285);
    }

    public function testPacketRejectsTooManyEntries(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ItemRegistryPacket(array_fill(
            0,
            ItemRegistryPacket::MAX_ENTRIES + 1,
            new ItemEntry('minecraft:apple', 285)
        ));
    }

    private function bytes(object $packet): string
    {
        $writer = new BinaryWriter();

        /** @var \PickaxeMP\protocol\packet\PacketInterface $packet */
        $packet->encode($writer);

        return $writer->bytes();
    }

    private function hex(object $packet): string
    {
        return bin2hex($this->bytes($packet));
    }
}
