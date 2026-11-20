<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;

/**
 * `Color` - tek bir ARGB renk değeri.
 *
 * Mojang şeması `Color.json`: tek alan `Color`, `int32`.
 *
 * ## Tel biçimi: büyük-endian
 *
 * Şema yalnızca "int32" der; bayt sırasını söylemez. Referans uygulama
 * (`gophertunnel` `Writer.BEARGB`) rengi şöyle kurar:
 *
 * ```go
 * val := int32(A) | int32(R)<<8 | int32(G)<<16 | int32(B)<<24
 * w.BEInt32(&val)
 * ```
 *
 * Yani **alfa en düşük bayttadır** ve değer büyük-endian yazılır. Sonuçta
 * tel üzerindeki dört bayt, en anlamlıdan başlayarak `B, G, R, A` olur.
 *
 * `fromRgba(0x12, 0x34, 0x56, 0x78)` → int32 `0x56341278` → baytlar
 * `56 34 12 78`.
 *
 * ## Dürüst sınır
 *
 * Bu sınıf deri rengi (`SerializedSkin::skinColor`) ve oyuncu listesi rengi
 * (`PlayerListEntry::playerColor`) için kullanılır. Gerçek istemcide renk
 * doğruluğu **görsel olarak doğrulanmadı**; doğrulanan tek şey referans
 * uygulamanın bayt düzeniyle birebir eşleştiğidir.
 */
final class Color
{
    /**
     * @param int $value tel üzerinde yazılan `int32`
     *                    (`A | R<<8 | G<<16 | B<<24`)
     */
    public function __construct(
        public readonly int $value
    ) {
    }

    /**
     * Opak beyaz: `A=R=G=B=0xFF` → int32 `-1`.
     */
    public static function white(): self
    {
        return new self(-1);
    }

    /**
     * Opak siyah: `A=0xFF, R=G=B=0` → int32 `255`.
     *
     * Dikkat: bu `-16777216` **değildir**. Alfa düşük baytta olduğu için
     * siyahın değeri yalnızca 255'tir.
     */
    public static function black(): self
    {
        return self::fromRgba(0, 0, 0);
    }

    /**
     * `R`, `G`, `B`, `A` bileşenlerinden tel değeri üretir.
     */
    public static function fromRgba(int $red, int $green, int $blue, int $alpha = 0xFF): self
    {
        $unsigned = ($alpha & 0xFF)
            | (($red & 0xFF) << 8)
            | (($green & 0xFF) << 16)
            | (($blue & 0xFF) << 24);

        return new self($unsigned > 0x7FFFFFFF ? $unsigned - 0x100000000 : $unsigned);
    }

    /**
     * @return array{red: int, green: int, blue: int, alpha: int}
     */
    public function toRgba(): array
    {
        $unsigned = $this->value & 0xFFFFFFFF;

        return [
            'red' => ($unsigned >> 8) & 0xFF,
            'green' => ($unsigned >> 16) & 0xFF,
            'blue' => ($unsigned >> 24) & 0xFF,
            'alpha' => $unsigned & 0xFF,
        ];
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeInt32BigEndian($this->value);
    }

    public static function decode(BinaryReader $reader): self
    {
        return new self($reader->readInt32BigEndian());
    }
}
