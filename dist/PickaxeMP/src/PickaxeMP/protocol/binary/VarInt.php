<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\binary;

use PickaxeMP\protocol\ProtocolException;

/**
 * VarInt / VarLong kodlaması — Mojang "Primitives" belgesine birebir uygun.
 *
 * Doğrulanan kurallar:
 *   - `varint32`  : **ZigZag** kodlanır, sonra base-128 grupları (yüksek bit =
 *                   devam), en fazla 5 bayt.
 *   - `varint64`  : ZigZag + base-128, en fazla 10 bayt.
 *   - `varuint32` : ZigZag YOK, sadece base-128, en fazla 5 bayt.
 *   - `varuint64` : ZigZag yok, en fazla 10 bayt.
 *
 * Örnek doğrulama (Mojang belgesi): 64 değeri varint32 olarak `0x80 0x01`.
 * ZigZag(64) = 128 → base-128 = 0x80 0x01. ✓
 *
 * String uzunluk öneki **varuint32**'dir (ZigZag'sız). ZigZag kullanmak
 * uzunlukları bozar; bu yüzden ayrı metotlar vardır.
 */
final class VarInt
{
    public const MAX_BYTES_32 = 5;
    public const MAX_BYTES_64 = 10;

    /** 32-bit işaretli aralık. */
    public const MIN_INT32 = -2147483648;
    public const MAX_INT32 = 2147483647;

    /**
     * 32-bit değeri ZigZag ile işaretsiz bit desenine çevirir.
     *
     * Formül: (v << 1) ^ (v >> 31), 32 bit ile maskelenir.
     */
    public static function zigzagEncode32(int $value): int
    {
        if ($value < self::MIN_INT32 || $value > self::MAX_INT32) {
            throw ProtocolException::varIntOverflow('int32');
        }

        return (($value << 1) ^ ($value >> 31)) & 0xFFFFFFFF;
    }

    /**
     * 32-bit ZigZag çözer. Girdi işaretsiz bit deseni (0..0xFFFFFFFF).
     */
    public static function zigzagDecode32(int $unsigned): int
    {
        $value = (($unsigned >> 1) ^ -($unsigned & 1)) & 0xFFFFFFFF;

        if ($value >= 0x80000000) {
            $value -= 0x100000000;
        }

        return $value;
    }

    /**
     * 64-bit değeri ZigZag ile işaretsiz bit desenine çevirir.
     *
     * PHP tam sayıları 64-bit işaretlidir; sonuç negatif görünebilir ama bit
     * deseni doğrudur.
     */
    public static function zigzagEncode64(int $value): int
    {
        return ($value << 1) ^ ($value >> 63);
    }

    /**
     * 64-bit ZigZag çözer. Mantıksal sağa kaydırma maskeyle yapılır, çünkü
     * PHP'nin `>>` operatörü negatiflerde aritmetik kaydırır.
     */
    public static function zigzagDecode64(int $unsigned): int
    {
        return (($unsigned >> 1) & PHP_INT_MAX) ^ -($unsigned & 1);
    }

    /**
     * İşaretsiz bit desenini base-128 gruplarına yazar.
     */
    public static function encodeGroups(int $unsigned): string
    {
        $out = '';

        do {
            $byte = $unsigned & 0x7F;
            $unsigned = ($unsigned >> 7) & 0x01FFFFFFFFFFFFFF;

            if ($unsigned !== 0) {
                $byte |= 0x80;
            }

            $out .= chr($byte);
        } while ($unsigned !== 0);

        return $out;
    }

    /**
     * Base-128 gruplarını çözer.
     *
     * @param string $buffer Kaynak tampon
     * @param int $offset Başlangıç konumu
     * @param int $maxBytes Üst sınır (5 veya 10)
     *
     * @return array{value: int, length: int} `value` işaretsiz bit deseni
     *
     * @throws ProtocolException eksik veri veya aşırı uzun kodlama
     */
    public static function decodeGroups(string $buffer, int $offset, int $maxBytes): array
    {
        $length = strlen($buffer);
        $value = 0;
        $read = 0;

        while (true) {
            if ($offset + $read >= $length) {
                throw ProtocolException::unexpectedEnd('varint', 1, 0);
            }

            $byte = ord($buffer[$offset + $read]);
            $read++;

            if ($read > $maxBytes) {
                throw ProtocolException::varIntTooLong($maxBytes, $read);
            }

            $shift = ($read - 1) * 7;
            $value |= ($byte & 0x7F) << $shift;

            if (($byte & 0x80) === 0) {
                break;
            }
        }

        return ['value' => $value, 'length' => $read];
    }

    /**
     * Son grubun gereksiz yüksek bit taşıyıp taşımadığını kontrol eder.
     *
     * Saldırgan bir istemci sınırsız büyüyen kodlama göndermesin diye
     * `decodeGroups` zaten bayt sınırı uygular; bu metot ayrıca değerin
     * temsil edilebilir aralıkta olduğunu doğrular.
     */
    public static function assertFits32(int $unsigned): void
    {
        if ($unsigned < 0 || $unsigned > 0xFFFFFFFF) {
            throw ProtocolException::varIntOverflow('varuint32');
        }
    }
}
