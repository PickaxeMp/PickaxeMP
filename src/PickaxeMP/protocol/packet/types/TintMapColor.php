<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\ProtocolException;

/**
 * `TintMapColor` - tam olarak dört renk.
 *
 * Mojang şeması `TintMapColor.json`: `Colors` dizisi, `minItems = maxItems = 4`.
 *
 * ## Uzunluk öneki YOK
 *
 * Şemada dizi sabit boyludur ve referans uygulama (gophertunnel
 * `PersonaPieceTintColour.Marshal`) dört rengi **sayı yazmadan** arka arkaya
 * yazar. Bu sınıf da önek yazmaz; yazılırsa paket 1 bayt kayar ve istemci
 * `SerializedSkin`'i çözemez.
 */
final class TintMapColor
{
    public const COUNT = 4;

    /** @var list<Color> */
    public readonly array $colors;

    /**
     * @param list<Color> $colors tam olarak 4 renk
     */
    public function __construct(array $colors)
    {
        if (count($colors) !== self::COUNT) {
            throw new ProtocolException(
                'TintMapColor: tam olarak 4 renk gerekli, verilen: ' . count($colors)
            );
        }

        $this->colors = array_values($colors);
    }

    public static function empty(): self
    {
        return new self([Color::black(), Color::black(), Color::black(), Color::black()]);
    }

    public function encode(BinaryWriter $writer): void
    {
        foreach ($this->colors as $color) {
            $color->encode($writer);
        }
    }

    public static function decode(BinaryReader $reader): self
    {
        $colors = [];

        for ($i = 0; $i < self::COUNT; $i++) {
            $colors[] = Color::decode($reader);
        }

        return new self($colors);
    }
}
