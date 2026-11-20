<?php

declare(strict_types=1);

namespace PickaxeMP\auth\skin;

/**
 * Çözülmüş PNG görüntüsü: ham RGBA baytları.
 *
 * Bedrock `SerializedSkin` tel biçimi genişlik + yükseklik + **ham RGBA**
 * bekler; PNG sıkıştırması taşımaz. Bu nesne tam olarak o sözleşmeyi taşır.
 *
 * `rgba` uzunluğu her zaman `width * height * 4`'tür; başka bir değer
 * üretilmez çünkü istemci eksik baytı okurken bağlantıyı düşürür.
 */
final class PngImage
{
    public function __construct(
        public readonly int $width,
        public readonly int $height,
        public readonly string $rgba
    ) {
        if ($width < 1 || $height < 1) {
            throw SkinException::invalidPng('goruntu boyutu sifir olamaz');
        }

        if (strlen($rgba) !== $width * $height * 4) {
            throw SkinException::invalidPng('RGBA uzunlugu boyutla uyusmuyor');
        }
    }
}
