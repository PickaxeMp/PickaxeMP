<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\protocol;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\binary\Uuid;
use PickaxeMP\protocol\ProtocolException;

final class BinaryRoundTripTest extends TestCase
{
    public function testDocumentedUint16Example(): void
    {
        // Mojang: 300 uint16 → 0x2C 0x01 (little-endian)
        $writer = new BinaryWriter();
        $writer->writeUint16(300);

        self::assertSame("\x2C\x01", $writer->bytes());
        self::assertSame(300, (new BinaryReader($writer->bytes()))->readUint16());
    }

    public function testDocumentedFloatExample(): void
    {
        // Mojang: 3.5f → 00 00 60 40 (little-endian)
        $writer = new BinaryWriter();
        $writer->writeFloat(3.5);

        self::assertSame("\x00\x00\x60\x40", $writer->bytes());
    }

    #[DataProvider('signedInt32')]
    public function testInt32LittleEndianRoundTrip(int $value): void
    {
        $writer = new BinaryWriter();
        $writer->writeInt32($value);

        self::assertSame($value, (new BinaryReader($writer->bytes()))->readInt32());
    }

    /**
     * @return array<string, array{int}>
     */
    public static function signedInt32(): array
    {
        return [
            'zero' => [0],
            'one' => [1],
            'minus one' => [-1],
            'max' => [2147483647],
            'min' => [-2147483648],
            '300' => [300],
        ];
    }

    #[DataProvider('signedInt32')]
    public function testInt32BigEndianRoundTrip(int $value): void
    {
        $writer = new BinaryWriter();
        $writer->writeInt32BigEndian($value);

        self::assertSame($value, (new BinaryReader($writer->bytes()))->readInt32BigEndian());
    }

    public function testBigEndianDiffersFromLittleEndian(): void
    {
        $le = new BinaryWriter();
        $le->writeInt32(1);

        $be = new BinaryWriter();
        $be->writeInt32BigEndian(1);

        self::assertSame("\x01\x00\x00\x00", $le->bytes());
        self::assertSame("\x00\x00\x00\x01", $be->bytes());
    }

    #[DataProvider('unsignedValues')]
    public function testUintRoundTrips(int $value): void
    {
        $writer = new BinaryWriter();
        $writer->writeUint16($value);
        self::assertSame($value, (new BinaryReader($writer->bytes()))->readUint16());

        $writer = new BinaryWriter();
        $writer->writeUint32($value <= 0xFFFFFFFF ? $value : 0xFFFFFFFF);
        self::assertSame(
            $value <= 0xFFFFFFFF ? $value : 0xFFFFFFFF,
            (new BinaryReader($writer->bytes()))->readUint32()
        );
    }

    /**
     * @return array<string, array{int}>
     */
    public static function unsignedValues(): array
    {
        return [
            'zero' => [0],
            'one' => [1],
            '255' => [255],
            '65535' => [65535],
        ];
    }

    public function testUint64RoundTrip(): void
    {
        $writer = new BinaryWriter();
        $writer->writeUint64(1234567890123456789);

        self::assertSame(
            1234567890123456789,
            (new BinaryReader($writer->bytes()))->readUint64()
        );
    }

    public function testFloatRoundTrip(): void
    {
        foreach ([0.0, 1.0, -1.5, 3.5, 1.0e6, -0.0001] as $value) {
            $writer = new BinaryWriter();
            $writer->writeFloat($value);

            $read = (new BinaryReader($writer->bytes()))->readFloat();

            self::assertEqualsWithDelta($value, $read, 1.0e-3, "float {$value}");
        }
    }

    public function testDoubleRoundTrip(): void
    {
        foreach ([0.0, 1.0, -1.5, 3.141592653589793, 1.0e12] as $value) {
            $writer = new BinaryWriter();
            $writer->writeDouble($value);

            self::assertEqualsWithDelta(
                $value,
                (new BinaryReader($writer->bytes()))->readDouble(),
                1.0e-9
            );
        }
    }

    public function testBoolRoundTrip(): void
    {
        $writer = new BinaryWriter();
        $writer->writeBool(true)->writeBool(false);

        $reader = new BinaryReader($writer->bytes());

        self::assertTrue($reader->readBool());
        self::assertFalse($reader->readBool());
    }

    public function testBoolAcceptsAnyNonZeroAsTrue(): void
    {
        // Mojang: "Readers accept any nonzero byte as true."
        self::assertTrue((new BinaryReader("\x02"))->readBool());
        self::assertTrue((new BinaryReader("\xFF"))->readBool());
        self::assertFalse((new BinaryReader("\x00"))->readBool());
    }

    /**
     * Mojang örneği: "Hi" → 0x02 'H' 'i' (varuint32 uzunluk).
     */
    public function testDocumentedStringExample(): void
    {
        $writer = new BinaryWriter();
        $writer->writeString('Hi');

        self::assertSame("\x02Hi", $writer->bytes());
        self::assertSame('Hi', (new BinaryReader($writer->bytes()))->readString());
    }

    public function testStringRoundTripWithUtf8(): void
    {
        $samples = ['', 'ascii', 'Türkçe ğüşiöç', '日本語', 'emoji 🎮', str_repeat('a', 200)];

        foreach ($samples as $sample) {
            $writer = new BinaryWriter();
            $writer->writeString($sample);

            self::assertSame($sample, (new BinaryReader($writer->bytes()))->readUtf8String());
        }
    }

    public function testStringLengthIsByteCountNotCharCount(): void
    {
        // 4 karakter, 8 bayt (her biri 2 bayt UTF-8).
        $writer = new BinaryWriter();
        $writer->writeString('ğğğğ');

        self::assertSame(9, $writer->length(), 'varuint(8) + 8 bayt');
        self::assertSame('ğğğğ', (new BinaryReader($writer->bytes()))->readString());
    }

    public function testStringRejectsOversizedLength(): void
    {
        $writer = new BinaryWriter();
        $writer->writeVarUInt(1000);
        $writer->write(str_repeat('a', 1000));

        $reader = new BinaryReader($writer->bytes(), 100);

        $this->expectException(ProtocolException::class);
        $reader->readString();
    }

    public function testStringRejectsTruncatedPayload(): void
    {
        // Uzunluk 10 diyor ama 3 bayt var.
        $data = "\x0A" . 'abc';

        $this->expectException(ProtocolException::class);
        (new BinaryReader($data))->readString();
    }

    public function testUtf8ValidationRejectsInvalidBytes(): void
    {
        $writer = new BinaryWriter();
        $writer->writeString("\xFF\xFE");

        $this->expectException(ProtocolException::class);
        (new BinaryReader($writer->bytes()))->readUtf8String();
    }

    public function testUuidRoundTrip(): void
    {
        $uuid = Uuid::fromString('123e4567-e89b-12d3-a456-426614174000');

        $writer = new BinaryWriter();
        $writer->writeUuid($uuid);

        self::assertSame(16, $writer->length());

        $read = (new BinaryReader($writer->bytes()))->readUuid();

        self::assertTrue($uuid->equals($read));
        self::assertSame('123e4567-e89b-12d3-a456-426614174000', $read->toString());
    }

    public function testUuidEmptyIsZero(): void
    {
        $writer = new BinaryWriter();
        $writer->writeUuid(Uuid::empty());

        $read = (new BinaryReader($writer->bytes()))->readUuid();

        self::assertTrue($read->isZero());
        self::assertSame('00000000-0000-0000-0000-000000000000', $read->toString());
    }

    public function testUuidRejectsMalformedText(): void
    {
        $this->expectException(ProtocolException::class);
        Uuid::fromString('not-a-uuid');
    }

    public function testUuidAcceptsBracesAndUppercase(): void
    {
        $uuid = Uuid::fromString('{123E4567-E89B-12D3-A456-426614174000}');

        self::assertSame('123e4567-e89b-12d3-a456-426614174000', $uuid->toString());
    }

    public function testReaderRejectsReadPastEnd(): void
    {
        $reader = new BinaryReader('ab');

        $this->expectException(ProtocolException::class);
        $reader->read(3);
    }

    public function testReaderRejectsNegativeRead(): void
    {
        $this->expectException(ProtocolException::class);
        (new BinaryReader('ab'))->read(-1);
    }

    public function testReaderDoesNotMutateSource(): void
    {
        $source = "\x01\x02\x03\x04";
        $reader = new BinaryReader($source);

        $reader->readByte();
        $reader->readByte();

        self::assertSame("\x01\x02\x03\x04", $source);
        self::assertSame(2, $reader->offset());
        self::assertSame(2, $reader->remaining());
    }

    public function testReaderTracksPosition(): void
    {
        $reader = new BinaryReader('abcd');

        self::assertSame(0, $reader->offset());
        $reader->readByte();
        self::assertSame(1, $reader->offset());
        self::assertSame(3, $reader->remaining());
        self::assertFalse($reader->isAtEnd());

        $reader->readRemaining();
        self::assertTrue($reader->isAtEnd());
    }

    public function testAssertFullyConsumedDetectsTrailingBytes(): void
    {
        $reader = new BinaryReader('abc');
        $reader->readByte();

        $this->expectException(ProtocolException::class);
        $reader->assertFullyConsumed('test');
    }

    public function testWriterRejectsOutOfRangeByte(): void
    {
        $this->expectException(ProtocolException::class);
        (new BinaryWriter())->writeByte(256);
    }

    public function testWriterRejectsOutOfRangeUint16(): void
    {
        $this->expectException(ProtocolException::class);
        (new BinaryWriter())->writeUint16(65536);
    }

    public function testWriterRejectsOutOfRangeInt32(): void
    {
        $this->expectException(ProtocolException::class);
        (new BinaryWriter())->writeInt32(2147483648);
    }

    /**
     * Tüm ilkel türleri tek akışta yazıp geri okur.
     */
    public function testMixedStreamRoundTrip(): void
    {
        $writer = new BinaryWriter();
        $writer->writeByte(200)
            ->writeSignedByte(-100)
            ->writeBool(true)
            ->writeUint16(65535)
            ->writeInt32(-123456)
            ->writeInt32BigEndian(2225)
            ->writeUint64(987654321)
            ->writeFloat(1.5)
            ->writeDouble(2.25)
            ->writeVarInt(-42)
            ->writeVarLong(-9999999999)
            ->writeVarUInt(300)
            ->writeString('merhaba')
            ->writeUuid(Uuid::fromString('00000000-0000-0000-0000-000000000001'));

        $reader = new BinaryReader($writer->bytes());

        self::assertSame(200, $reader->readByte());
        self::assertSame(-100, $reader->readSignedByte());
        self::assertTrue($reader->readBool());
        self::assertSame(65535, $reader->readUint16());
        self::assertSame(-123456, $reader->readInt32());
        self::assertSame(2225, $reader->readInt32BigEndian());
        self::assertSame(987654321, $reader->readUint64());
        self::assertEqualsWithDelta(1.5, $reader->readFloat(), 1.0e-6);
        self::assertEqualsWithDelta(2.25, $reader->readDouble(), 1.0e-12);
        self::assertSame(-42, $reader->readVarInt());
        self::assertSame(-9999999999, $reader->readVarLong());
        self::assertSame(300, $reader->readVarUInt());
        self::assertSame('merhaba', $reader->readString());
        self::assertSame(
            '00000000-0000-0000-0000-000000000001',
            $reader->readUuid()->toString()
        );
        self::assertTrue($reader->isAtEnd());
    }
}
