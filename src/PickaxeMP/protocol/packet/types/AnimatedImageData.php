<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;

/**
 * `AnimatedImageData` - persona animasyon karesi.
 *
 * Mojang şeması: `AnimatedImageData.json`, ordinal sırası:
 *
 * ```
 * 0 SkinImage            SkinImage
 * 1 AnimatedTextureType  uint32 (Compression -> varuint32)
 * 2 Frames               float
 * 3 AnimationExpression  uint32 (Compression -> varuint32)
 * ```
 */
final class AnimatedImageData
{
    public function __construct(
        public readonly SkinImage $image,
        public readonly AnimatedTextureType $textureType,
        public readonly float $frames,
        public readonly AnimationExpression $expression
    ) {
    }

    public function encode(BinaryWriter $writer): void
    {
        $this->image->encode($writer);
        $writer->writeVarUInt($this->textureType->value);
        $writer->writeFloat($this->frames);
        $writer->writeVarUInt($this->expression->value);
    }

    public static function decode(BinaryReader $reader): self
    {
        return new self(
            image: SkinImage::decode($reader),
            textureType: AnimatedTextureType::fromWire($reader->readVarUInt()),
            frames: $reader->readFloat(),
            expression: AnimationExpression::fromWire($reader->readVarUInt())
        );
    }
}
