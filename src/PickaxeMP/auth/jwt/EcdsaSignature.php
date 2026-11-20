<?php

declare(strict_types=1);

namespace PickaxeMP\auth\jwt;

use PickaxeMP\auth\AuthException;

/**
 * JOSE imzası (ham R||S) ile OpenSSL (DER) arasındaki dönüşüm.
 *
 * NEDEN GEREKLİ:
 * JOSE (RFC 7515/7518) ECDSA imzayı **sabit genişlikli ham** biçimde taşır:
 *   `R (eğri boyu bayt) || S (eğri boyu bayt)`
 * ES384 için 48 + 48 = **96 bayt**.
 *
 * OpenSSL ise `ECDSA-Sig-Value` **DER** yapısını bekler:
 *   `SEQUENCE { INTEGER r, INTEGER s }`
 * DER'de tam sayılar **değişken uzunluktadır** ve baştaki sıfır baytları
 * atılır; yüksek bit set ise başına 0x00 eklenir.
 *
 * Bu iki biçim karıştırılırsa imza doğrulaması **her zaman başarısız** olur
 * (veya daha kötüsü, bazı uygulamalarda yanlışlıkla başarılı).
 *
 * Bu sınıf dönüşümü açıkça yapar ve her iki yönde de test edilir.
 */
final class EcdsaSignature
{
    /**
     * Ham JOSE imzasını (R||S) DER'e çevirir.
     *
     * @param string $raw 96 bayt (ES384) veya 64 bayt (ES256)
     *
     * @throws AuthException uzunluk uyuşmazsa
     */
    public static function rawToDer(string $raw, JwtAlgorithm $algorithm): string
    {
        $expected = $algorithm->rawSignatureLength();

        if ($expected === 0) {
            throw AuthException::unsupportedAlgorithm($algorithm->value);
        }

        if (strlen($raw) !== $expected) {
            throw AuthException::invalidKey(sprintf(
                'Ham ECDSA imzasi %d bayt olmali, %d alindi',
                $expected,
                strlen($raw)
            ));
        }

        $coordinateBytes = $algorithm->ecdsaCoordinateBytes();
        $r = substr($raw, 0, $coordinateBytes);
        $s = substr($raw, $coordinateBytes);

        $rInt = self::encodeInteger($r);
        $sInt = self::encodeInteger($s);

        $sequence = $rInt . $sInt;

        return "\x30" . self::encodeLength(strlen($sequence)) . $sequence;
    }

    /**
     * DER imzayı ham JOSE biçimine çevirir (test/teşhis için).
     */
    public static function derToRaw(string $der, JwtAlgorithm $algorithm): string
    {
        $coordinateBytes = $algorithm->ecdsaCoordinateBytes();
        $offset = 0;

        if (($der[$offset++] ?? '') !== "\x30") {
            throw AuthException::invalidKey('DER: SEQUENCE bekleniyordu');
        }

        $sequenceLength = self::decodeLength($der, $offset);

        if ($offset + $sequenceLength !== strlen($der)) {
            throw AuthException::invalidKey('DER: SEQUENCE uzunlugu uyusmuyor');
        }

        $r = self::decodeInteger($der, $offset);
        $s = self::decodeInteger($der, $offset);

        if ($offset !== strlen($der)) {
            throw AuthException::invalidKey('DER: arti bayt var');
        }

        return self::padLeft($r, $coordinateBytes) . self::padLeft($s, $coordinateBytes);
    }

    /**
     * Ham imzayı doğrular: uzunluk ve sıfır olmama.
     */
    public static function isValidRaw(string $raw, JwtAlgorithm $algorithm): bool
    {
        $expected = $algorithm->rawSignatureLength();

        if ($expected === 0 || strlen($raw) !== $expected) {
            return false;
        }

        $coordinateBytes = $algorithm->ecdsaCoordinateBytes();

        // R ve S sıfır olamaz (geçersiz imza).
        return str_repeat("\x00", $coordinateBytes) !== substr($raw, 0, $coordinateBytes)
            && str_repeat("\x00", $coordinateBytes) !== substr($raw, $coordinateBytes);
    }

    private static function encodeInteger(string $bigEndian): string
    {
        // Baştaki sıfırları at.
        $trimmed = ltrim($bigEndian, "\x00");

        if ($trimmed === '') {
            $trimmed = "\x00";
        }

        // İlk baytın yüksek biti set ise DER pozitif tamsayı için 0x00 ekle.
        if ((ord($trimmed[0]) & 0x80) !== 0) {
            $trimmed = "\x00" . $trimmed;
        }

        return "\x02" . self::encodeLength(strlen($trimmed)) . $trimmed;
    }

    private static function encodeLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        if ($length <= 0xFF) {
            return "\x81" . chr($length);
        }

        if ($length <= 0xFFFF) {
            return "\x82" . pack('n', $length);
        }

        throw AuthException::invalidKey('DER uzunlugu cok buyuk');
    }

    private static function decodeLength(string $der, int &$offset): int
    {
        if (!isset($der[$offset])) {
            throw AuthException::invalidKey('DER: uzunluk eksik');
        }

        $first = ord($der[$offset++]);

        if (($first & 0x80) === 0) {
            return $first;
        }

        $byteCount = $first & 0x7F;

        if ($byteCount === 0 || $byteCount > 2) {
            throw AuthException::invalidKey('DER: desteklenmeyen uzunluk bicimi');
        }

        $length = 0;

        for ($i = 0; $i < $byteCount; $i++) {
            if (!isset($der[$offset])) {
                throw AuthException::invalidKey('DER: uzunluk eksik');
            }

            $length = ($length << 8) | ord($der[$offset++]);
        }

        return $length;
    }

    private static function decodeInteger(string $der, int &$offset): string
    {
        if (($der[$offset++] ?? '') !== "\x02") {
            throw AuthException::invalidKey('DER: INTEGER bekleniyordu');
        }

        $length = self::decodeLength($der, $offset);

        if ($offset + $length > strlen($der)) {
            throw AuthException::invalidKey('DER: INTEGER kesik');
        }

        $value = substr($der, $offset, $length);
        $offset += $length;

        return $value;
    }

    private static function padLeft(string $value, int $size): string
    {
        $trimmed = ltrim($value, "\x00");

        if (strlen($trimmed) > $size) {
            throw AuthException::invalidKey('ECDSA bileseni egri boyundan buyuk');
        }

        return str_pad($trimmed, $size, "\x00", STR_PAD_LEFT);
    }
}
