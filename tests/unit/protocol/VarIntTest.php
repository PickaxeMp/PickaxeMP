<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\protocol;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\binary\VarInt;
use PickaxeMP\protocol\ProtocolException;

final class VarIntTest extends TestCase
{
    /**
     * Mojang "Primitives" belgesindeki doğrulanmış örnek:
     * 64 değeri varint32 olarak `0x80 0x01`.
     */
    public function testDocumentedExampleFor64(): void
    {
        $writer = new BinaryWriter();
        $writer->writeVarInt(64);

        self::assertSame("\x80\x01", $writer->bytes());

        $reader = new BinaryReader($writer->bytes());
        self::assertSame(64, $reader->readVarInt());
    }

    /**
     * Mojang örneği: 255 varuint32 olarak `0xFF 0x01`.
     */
    public function testDocumentedExampleForUnsigned255(): void
    {
        $writer = new BinaryWriter();
        $writer->writeVarUInt(255);

        self::assertSame("\xFF\x01", $writer->bytes());

        $reader = new BinaryReader($writer->bytes());
        self::assertSame(255, $reader->readVarUInt());
    }

    public function testZigZagZeroIsZero(): void
    {
        self::assertSame(0, VarInt::zigzagEncode32(0));
        self::assertSame(0, VarInt::zigzagDecode32(0));
        self::assertSame(0, VarInt::zigzagEncode64(0));
        self::assertSame(0, VarInt::zigzagDecode64(0));
    }

    public function testZigZagSmallValues(): void
    {
        self::assertSame(0, VarInt::zigzagEncode32(0));
        self::assertSame(1, VarInt::zigzagEncode32(-1));
        self::assertSame(2, VarInt::zigzagEncode32(1));
        self::assertSame(3, VarInt::zigzagEncode32(-2));
        self::assertSame(4, VarInt::zigzagEncode32(2));
    }

    #[DataProvider('int32Values')]
    public function testVarIntRoundTrip(int $value): void
    {
        $writer = new BinaryWriter();
        $writer->writeVarInt($value);

        $reader = new BinaryReader($writer->bytes());
        self::assertSame($value, $reader->readVarInt());
        self::assertTrue($reader->isAtEnd());
    }

    /**
     * @return array<string, array{int}>
     */
    public static function int32Values(): array
    {
        return [
            'zero' => [0],
            'one' => [1],
            'minus one' => [-1],
            'max' => [VarInt::MAX_INT32],
            'min' => [VarInt::MIN_INT32],
            'max minus one' => [VarInt::MAX_INT32 - 1],
            'min plus one' => [VarInt::MIN_INT32 + 1],
            '64' => [64],
            '127' => [127],
            '-64' => [-64],
            '8192' => [8192],
            '-8192' => [-8192],
        ];
    }

    #[DataProvider('int64Values')]
    public function testVarLongRoundTrip(int $value): void
    {
        $writer = new BinaryWriter();
        $writer->writeVarLong($value);

        $reader = new BinaryReader($writer->bytes());
        self::assertSame($value, $reader->readVarLong());
        self::assertTrue($reader->isAtEnd());
    }

    /**
     * @return array<string, array{int}>
     */
    public static function int64Values(): array
    {
        return [
            'zero' => [0],
            'one' => [1],
            'minus one' => [-1],
            'max' => [PHP_INT_MAX],
            'min' => [PHP_INT_MIN],
            '2^32' => [4294967296],
            '-2^32' => [-4294967296],
        ];
    }

    public function testVarIntMaxEncodesInFiveBytes(): void
    {
        $writer = new BinaryWriter();
        $writer->writeVarInt(VarInt::MAX_INT32);

        self::assertSame(5, $writer->length());
    }

    public function testVarIntMinEncodesInFiveBytes(): void
    {
        $writer = new BinaryWriter();
        $writer->writeVarInt(VarInt::MIN_INT32);

        self::assertSame(5, $writer->length());
    }

    public function testVarLongMaxEncodesInTenBytes(): void
    {
        $writer = new BinaryWriter();
        $writer->writeVarLong(PHP_INT_MAX);

        self::assertSame(10, $writer->length());
    }

    public function testRejectsOutOfRangeInt32(): void
    {
        $this->expectException(ProtocolException::class);
        VarInt::zigzagEncode32(VarInt::MAX_INT32 + 1);
    }

    public function testRejectsOutOfRangeInt32Negative(): void
    {
        $this->expectException(ProtocolException::class);
        VarInt::zigzagEncode32(VarInt::MIN_INT32 - 1);
    }

    public function testRejectsOverlongVarIntEncoding(): void
    {
        // Altı devam baytı: 5 bayt sınırını aşar.
        $overlong = "\xFF\xFF\xFF\xFF\xFF\x01";

        $this->expectException(ProtocolException::class);
        (new BinaryReader($overlong))->readVarInt();
    }

    public function testRejectsTruncatedVarInt(): void
    {
        // Devam biti set ama sonraki bayt yok.
        $this->expectException(ProtocolException::class);
        (new BinaryReader("\x80"))->readVarInt();
    }

    public function testUnsignedRejectsValueAbove32Bits(): void
    {
        // ZigZag'sız 2^32 → 5 gruplu 0x80 0x80 0x80 0x80 0x10
        $encoded = "\x80\x80\x80\x80\x10";

        $this->expectException(ProtocolException::class);
        (new BinaryReader($encoded))->readVarUInt();
    }

    public function testEncodeGroupsZeroProducesSingleByte(): void
    {
        self::assertSame("\x00", VarInt::encodeGroups(0));
    }

    public function testDecodeGroupsReportsLength(): void
    {
        $decoded = VarInt::decodeGroups("\xFF\x01\xAA", 0, 5);

        self::assertSame(255, $decoded['value']);
        self::assertSame(2, $decoded['length']);
    }

    public function testDecodeGroupsHonoursOffset(): void
    {
        $decoded = VarInt::decodeGroups("\xAA\x05", 1, 5);

        self::assertSame(5, $decoded['value']);
    }
}
