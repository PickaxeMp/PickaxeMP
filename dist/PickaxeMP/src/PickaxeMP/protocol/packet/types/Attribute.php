<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\ProtocolException;

/**
 * `AttributeData` — `UpdateAttributes` içindeki tek bir öznitelik.
 *
 * Tel biçimi (Mojang `AttributeData.json` ordinal 0..7):
 * `Min` float, `Max` float, `Current` float, `Default Min` float,
 * `Default Max` float, `Default` float, `Name` hashed_string (düz string),
 * `Modifiers` varuint32 sayı + {@see AttributeModifier}.
 *
 * Sıra tahmin edilmedi: `gophertunnel` `protocol.Attribute.Marshal` aynı
 * alanları aynı sırada yazar. **Alan sırası değişirse istemci paketi yanlış
 * çözer** — bu yüzden altın bayt testi vardır.
 */
final class Attribute
{
    public const MAX_MODIFIERS = 64;

    /** Sağlık (yarım kalp = 1 birim). */
    public const HEALTH = 'minecraft:health';

    /** Açlık (0..20). */
    public const HUNGER = 'minecraft:player.hunger';

    /** Doygunluk (0..20). */
    public const SATURATION = 'minecraft:player.saturation';

    /** Tükenme (0..20). */
    public const EXHAUSTION = 'minecraft:player.exhaustion';

    /** Yürüme hızı (blok/tick). */
    public const MOVEMENT = 'minecraft:movement';

    /**
     * @param list<AttributeModifier> $modifiers
     */
    public function __construct(
        public readonly string $name,
        public readonly float $min,
        public readonly float $max,
        public readonly float $current,
        public readonly float $defaultMin,
        public readonly float $defaultMax,
        public readonly float $default,
        public readonly array $modifiers = []
    ) {
        if ($name === '') {
            throw new ProtocolException('Attribute: ad bos olamaz');
        }

        foreach ([
            'min' => $min,
            'max' => $max,
            'current' => $current,
            'defaultMin' => $defaultMin,
            'defaultMax' => $defaultMax,
            'default' => $default,
        ] as $field => $value) {
            if (!is_finite($value)) {
                throw new ProtocolException("Attribute: {$field} sonlu olmali");
            }
        }

        if (count($modifiers) > self::MAX_MODIFIERS) {
            throw ProtocolException::lengthTooLarge('attribute modifiers', count($modifiers), self::MAX_MODIFIERS);
        }

        foreach ($modifiers as $modifier) {
            if (!$modifier instanceof AttributeModifier) {
                throw new ProtocolException('Attribute: modifiers AttributeModifier olmali');
            }
        }
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeFloat($this->min);
        $writer->writeFloat($this->max);
        $writer->writeFloat($this->current);
        $writer->writeFloat($this->defaultMin);
        $writer->writeFloat($this->defaultMax);
        $writer->writeFloat($this->default);
        $writer->writeString($this->name, 256);
        $writer->writeVarUInt(count($this->modifiers));

        foreach ($this->modifiers as $modifier) {
            $modifier->encode($writer);
        }
    }

    public static function decode(BinaryReader $reader): self
    {
        $min = $reader->readFloat();
        $max = $reader->readFloat();
        $current = $reader->readFloat();
        $defaultMin = $reader->readFloat();
        $defaultMax = $reader->readFloat();
        $default = $reader->readFloat();
        $name = $reader->readString(256);
        $modifierCount = $reader->readVarUInt();

        if ($modifierCount > self::MAX_MODIFIERS) {
            throw ProtocolException::lengthTooLarge('attribute modifiers', $modifierCount, self::MAX_MODIFIERS);
        }

        $modifiers = [];

        for ($i = 0; $i < $modifierCount; $i++) {
            $modifiers[] = AttributeModifier::decode($reader);
        }

        return new self($name, $min, $max, $current, $defaultMin, $defaultMax, $default, $modifiers);
    }
}
