<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\protocol;

use PHPUnit\Framework\TestCase;
use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\binary\Uuid;
use PickaxeMP\protocol\ProtocolException;

/**
 * Deterministik sınır/fuzz testleri.
 *
 * `mt_srand` sabit tohumla kullanıldığı için her çalıştırmada aynı diziler
 * üretilir; test sonucu tekrarlanabilir.
 */
final class BinaryFuzzTest extends TestCase
{
    private const SEED = 20251117;
    private const ITERATIONS = 400;

    public function testInt32RoundTripOnRandomValues(): void
    {
        mt_srand(self::SEED);

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $value = mt_rand(-2147483648, 2147483647);

            $writer = new BinaryWriter();
            $writer->writeInt32($value);

            self::assertSame($value, (new BinaryReader($writer->bytes()))->readInt32());
        }
    }

    public function testVarIntRoundTripOnRandomValues(): void
    {
        mt_srand(self::SEED + 1);

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $value = mt_rand(-2147483648, 2147483647);

            $writer = new BinaryWriter();
            $writer->writeVarInt($value);

            $reader = new BinaryReader($writer->bytes());
            self::assertSame($value, $reader->readVarInt());
            self::assertTrue($reader->isAtEnd());
        }
    }

    public function testVarLongRoundTripOnRandomValues(): void
    {
        mt_srand(self::SEED + 2);

        for ($i = 0; $i < 200; $i++) {
            // 64-bit genişlikte güvenli aralık.
            $value = mt_rand(-PHP_INT_MAX, PHP_INT_MAX);

            $writer = new BinaryWriter();
            $writer->writeVarLong($value);

            self::assertSame($value, (new BinaryReader($writer->bytes()))->readVarLong());
        }
    }

    public function testVarUIntRoundTripOnRandomValues(): void
    {
        mt_srand(self::SEED + 3);

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $value = mt_rand(0, 0xFFFFFFFF);

            $writer = new BinaryWriter();
            $writer->writeVarUInt($value);

            self::assertSame($value, (new BinaryReader($writer->bytes()))->readVarUInt());
        }
    }

    public function testRandomByteStringsSurviveAsPayload(): void
    {
        mt_srand(self::SEED + 4);

        for ($i = 0; $i < 50; $i++) {
            $length = mt_rand(0, 256);
            $payload = '';

            for ($b = 0; $b < $length; $b++) {
                $payload .= chr(mt_rand(0, 255));
            }

            $writer = new BinaryWriter();
            $writer->write($payload);

            self::assertSame($payload, (new BinaryReader($writer->bytes()))->read($length));
        }
    }

    public function testUuidRoundTripOnRandomValues(): void
    {
        mt_srand(self::SEED + 5);

        for ($i = 0; $i < 50; $i++) {
            $uuid = new Uuid(mt_rand(-PHP_INT_MAX, PHP_INT_MAX), mt_rand(-PHP_INT_MAX, PHP_INT_MAX));

            $writer = new BinaryWriter();
            $writer->writeUuid($uuid);

            $read = (new BinaryReader($writer->bytes()))->readUuid();

            self::assertTrue($uuid->equals($read));
        }
    }

    /**
     * Rastgele bozuk baytlar hiçbir zaman sessizce kabul edilmemeli;
     * ya geçerli bir değer üretmeli ya da `ProtocolException` fırlatmalı.
     */
    public function testTruncatedVarIntsAlwaysThrow(): void
    {
        $valid = (new BinaryWriter())->writeVarInt(\PickaxeMP\protocol\binary\VarInt::MAX_INT32)->bytes();

        // Tüm ön ekler (son bayt hariç) kesik sayılmalı.
        for ($cut = 0; $cut < strlen($valid); $cut++) {
            if ($cut === 0) {
                continue;
            }

            $truncated = substr($valid, 0, $cut);

            try {
                (new BinaryReader($truncated))->readVarInt();
                self::fail("Kesik varint kabul edilmemeliydi (cut={$cut})");
            } catch (ProtocolException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testEveryPrefixOfAPacketFailsOrIsShorter(): void
    {
        $writer = new BinaryWriter();
        $writer->writeVarUInt(143);
        $writer->writeUint16(256);
        $writer->writeUint16(0);
        $writer->writeBool(false);
        $writer->writeByte(0);
        $writer->writeFloat(0.0);

        $full = $writer->bytes();

        for ($cut = 0; $cut < strlen($full); $cut++) {
            $prefix = substr($full, 0, $cut);

            try {
                $reader = new BinaryReader($prefix);
                $reader->readVarUInt();
                $reader->readUint16();
                $reader->readUint16();
                $reader->readBool();
                $reader->readByte();
                $reader->readFloat();

                self::fail("Kesik paket tam cozulmemeliydi (cut={$cut})");
            } catch (ProtocolException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testStringLengthLargerThanBufferAlwaysThrows(): void
    {
        // Bildirilen uzunluk, mevcut bayttan BÜYÜKSE hata; küçükse geçerlidir.
        foreach ([5, 10, 25, 100, 200] as $declared) {
            $data = (new BinaryWriter())->writeVarUInt($declared)->write('kisa')->bytes();

            try {
                (new BinaryReader($data))->readString();
                self::fail("Yanlis uzunluk kabul edilmemeliydi ({$declared})");
            } catch (ProtocolException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testStringLengthSmallerThanBufferIsValid(): void
    {
        // Bildirilen uzunluk 2, tamponda 4 bayt var → ilk 2 bayt okunur.
        $data = (new BinaryWriter())->writeVarUInt(2)->write('kisa')->bytes();

        self::assertSame('ki', (new BinaryReader($data))->readString());
    }
}
