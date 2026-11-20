<?php

declare(strict_types=1);

namespace PickaxeMP\protocol;

use PickaxeMP\exception\PickaxeException;

/**
 * Protokol katmanı hatası.
 *
 * İstemciden gelen her bayt güvenilmez kabul edilir; bu istisna "veri bozuk"
 * anlamına gelir ve bağlantı kapatılmalıdır. Sunucu çökmemelidir.
 */
class ProtocolException extends PickaxeException
{
    public static function unexpectedEnd(string $context, int $needed, int $available): self
    {
        return new self(
            "Beklenmeyen veri sonu ({$context}): {$needed} bayt gerekli, {$available} var"
        );
    }

    public static function negativeLength(string $context, int $length): self
    {
        return new self("Negatif uzunluk ({$context}): {$length}");
    }

    public static function lengthTooLarge(string $context, int $length, int $limit): self
    {
        return new self("Uzunluk siniri asildi ({$context}): {$length} > {$limit}");
    }

    public static function varIntTooLong(int $maxBytes, int $read): self
    {
        return new self("VarInt/VarLong cok uzun: {$read} bayt (sinir: {$maxBytes})");
    }

    public static function varIntOverflow(string $type): self
    {
        return new self("{$type} tasmasi: deger temsil edilebilir araligin disinda");
    }

    public static function trailingBytes(string $context, int $remaining): self
    {
        return new self("Cozumden sonra {$remaining} bayt arti kaldi ({$context})");
    }

    public static function unknownPacket(int $id): self
    {
        return new self("Bilinmeyen paket kimligi: {$id} (0x" . dechex($id) . ')');
    }

    public static function invalidEnum(string $context, int $value): self
    {
        return new self("Gecersiz enum degeri ({$context}): {$value}");
    }

    public static function invalidUtf8(string $context): self
    {
        return new self("Gecersiz UTF-8 ({$context})");
    }

    public static function incompatibleProtocol(int $client, int $server): self
    {
        return new self("Uyumsuz protokol: istemci {$client}, sunucu {$server}");
    }

    public static function badMagic(string $context, string $expected, string $actual): self
    {
        return new self(
            "Gecersiz imza ({$context}): beklenen 0x{$expected}, alinan 0x{$actual}"
        );
    }

    public static function invalidJwtShape(string $reason): self
    {
        return new self("Gecersiz JWT yapisi: {$reason}");
    }

    public static function unsupportedCompression(int $algorithm): self
    {
        return new self("Desteklenmeyen sikistirma algoritmasi: {$algorithm}");
    }

    public static function decompressionFailed(string $reason): self
    {
        return new self("Sikistirma cozulemedi: {$reason}");
    }

    public static function decompressedTooLarge(int $size, int $limit): self
    {
        return new self("Sikistirilmis veri cok buyuk: {$size} > {$limit}");
    }
}
