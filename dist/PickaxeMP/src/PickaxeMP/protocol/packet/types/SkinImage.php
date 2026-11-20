<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\ProtocolException;

/**
 * `SkinImage` - genişlik + yükseklik + ham PNG baytları.
 *
 * Mojang şeması: `SkinImage.json`, ordinal sırası:
 *
 * ```
 * 0 Width       uint32
 * 1 Height      uint32
 * 2 ImageBytes  uint8 dizisi (varuint32 uzunluk + ham baytlar)
 * ```
 *
 * `ImageBytes` bir `uint8` dizisidir; öğe başına 1 bayt olduğu için tel
 * biçimi fiilen `varuint32 uzunluk + ham baytlar` olur. Şema
 * `"No size compression"` işaretlemediğinden uzunluk **varuint32**'dir.
 *
 * Sınırlar: genişlik/yükseklik `0..4096`, bayt sayısı `<= 64 MiB`
 * (`maxItems` şemada 67108864).
 */
final class SkinImage
{
    public const MAX_DIMENSION = 4096;
    public const MAX_BYTES = 64 * 1024 * 1024;

    public readonly int $length;

    public function __construct(
        public readonly int $width,
        public readonly int $height,
        public readonly string $data
    ) {
        if ($width < 0 || $width > self::MAX_DIMENSION) {
            throw new ProtocolException("SkinImage: gecersiz genislik: {$width}");
        }

        if ($height < 0 || $height > self::MAX_DIMENSION) {
            throw new ProtocolException("SkinImage: gecersiz yukseklik: {$height}");
        }

        $length = strlen($data);

        if ($length > self::MAX_BYTES) {
            throw ProtocolException::lengthTooLarge('skin bytes', $length, self::MAX_BYTES);
        }

        $this->length = $length;
    }

    public static function empty(): self
    {
        return new self(0, 0, '');
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeUint32($this->width);
        $writer->writeUint32($this->height);
        $writer->writeVarUInt($this->length);
        $writer->write($this->data);
    }

    public static function decode(BinaryReader $reader): self
    {
        $width = $reader->readUint32();
        $height = $reader->readUint32();
        $length = $reader->readVarUInt();

        if ($length > self::MAX_BYTES) {
            throw ProtocolException::lengthTooLarge('skin bytes', $length, self::MAX_BYTES);
        }

        return new self($width, $height, $reader->read($length));
    }
}
