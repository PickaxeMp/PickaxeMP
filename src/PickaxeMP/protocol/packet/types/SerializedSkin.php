<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\ProtocolException;

/**
 * `SerializedSkin` - bir oyuncunun deri/kişiselleştirme verisi.
 *
 * Mojang şeması: `SerializedSkin.json` (v1.26.65, protokol 2225) ordinal
 * sırası. Sıra, referans uygulamanın (`gophertunnel` `Skin.Marshal`) yazma
 * sırasıyla **birebir** aynıdır ve bu sınıf onu izler:
 *
 * ```
 *  0 ID                            string
 *  1 ResourcePatch                 byte slice
 *  2 ImageData                     SkinImage (w, h, baytlar)
 *  3 AnimatedImageData             dizi<AnimatedImageData>
 *  4 CapeImageData                 SkinImage
 *  5 GeometryData                  byte slice
 *  6 GeometryDataMinEngineVersion  byte slice
 *  7 AnimationData                 byte slice
 *  8 CapeID                        string
 *  9 FullID                        string
 * 10 ArmSize                       uint8 (enum)
 * 11 SkinColor                     Color (buyuk-endian int32)
 * 12 PersonaPieces                 dizi<SerializedPersonaPieceHandle>
 * 13 PieceTintColors               dizi<PieceTintColor>
 * 14 IsPremium                     bool
 * 15 IsPersona                     bool
 * 16 IsPersonaCapeOnClassicSkin    bool
 * 17 IsPrimaryUser                 bool
 * 18 OverridesPlayerAppearance     bool
 * 19 TrustedSkinFlag               string ("Unset" / "False" / "True")
 * 20 ProfileHash                   string
 * ```
 *
 * ## `TrustedSkinFlag` neden string?
 *
 * Şema `x-underlying-type: "object"` + `x-enum-binary-value: [0,1,2]` der;
 * bu, sayısal bir alan gibi görünür. Gerçek tel biçimi ise **enum adıdır**:
 * referans uygulama `r.String(&trustedStr)` ile "Unset"/"True"/"False"
 * yazar. İki biçim birbirini dışlar; string seçildi çünkü çalışan bir
 * uygulamayla eşleşiyor ve istemci tarafında doğrulanmış tek biçim bu.
 *
 * ## Dürüst sınır
 *
 * Deri verisi gerçek istemciyle **doğrulanmadı**. `blank()` şema-geçerli
 * (0x0 görsel, boş kişiselleştirme) bir gövde üretir; istemcinin bu durumda
 * varsayılan deriyi çizmesi beklenir, bu da doğrulanmadı.
 */
final class SerializedSkin
{
    /**
     * @param list<AnimatedImageData> $animatedImages
     * @param list<SerializedPersonaPieceHandle> $personaPieces
     * @param list<PieceTintColor> $pieceTintColors
     */
    public function __construct(
        public readonly string $id,
        public readonly string $resourcePatch,
        public readonly SkinImage $image,
        public readonly array $animatedImages,
        public readonly SkinImage $capeImage,
        public readonly string $geometryData,
        public readonly string $geometryDataMinEngineVersion,
        public readonly string $animationData,
        public readonly string $capeId,
        public readonly string $fullId,
        public readonly ArmSize $armSize,
        public readonly Color $skinColor,
        public readonly array $personaPieces,
        public readonly array $pieceTintColors,
        public readonly bool $isPremium,
        public readonly bool $isPersona,
        public readonly bool $isPersonaCapeOnClassicSkin,
        public readonly bool $isPrimaryUser,
        public readonly bool $overridesPlayerAppearance,
        public readonly TrustedSkinFlag $trustedSkinFlag,
        public readonly string $profileHash
    ) {
    }

    /**
     * Boş deri: görseller 0x0, kişiselleştirme yok.
     *
     * Sunucu şu an JWT'deki deri verisini oyuncu listesine taşımıyor; bu
     * fabrika "bilinmeyen deri" için şema-geçerli bir gövde üretir.
     */
    public static function blank(string $id = 'Standard_Custom', string $fullId = ''): self
    {
        return new self(
            id: $id,
            resourcePatch: '',
            image: SkinImage::empty(),
            animatedImages: [],
            capeImage: SkinImage::empty(),
            geometryData: '',
            geometryDataMinEngineVersion: '',
            animationData: '',
            capeId: '',
            fullId: $fullId,
            armSize: ArmSize::Wide,
            skinColor: Color::black(),
            personaPieces: [],
            pieceTintColors: [],
            isPremium: false,
            isPersona: false,
            isPersonaCapeOnClassicSkin: false,
            isPrimaryUser: false,
            overridesPlayerAppearance: false,
            trustedSkinFlag: TrustedSkinFlag::Unset,
            profileHash: ''
        );
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeString($this->id, 256);
        $writer->writeString($this->resourcePatch, 64 * 1024);
        $this->image->encode($writer);

        $writer->writeVarUInt(count($this->animatedImages));

        foreach ($this->animatedImages as $animated) {
            $animated->encode($writer);
        }

        $this->capeImage->encode($writer);
        $writer->writeString($this->geometryData, 1024 * 1024);
        $writer->writeString($this->geometryDataMinEngineVersion, 64);
        $writer->writeString($this->animationData, 1024 * 1024);
        $writer->writeString($this->capeId, 256);
        $writer->writeString($this->fullId, 256);
        $writer->writeByte($this->armSize->value);
        $this->skinColor->encode($writer);

        $writer->writeVarUInt(count($this->personaPieces));

        foreach ($this->personaPieces as $piece) {
            $piece->encode($writer);
        }

        $writer->writeVarUInt(count($this->pieceTintColors));

        foreach ($this->pieceTintColors as $tint) {
            $tint->encode($writer);
        }

        $writer->writeBool($this->isPremium);
        $writer->writeBool($this->isPersona);
        $writer->writeBool($this->isPersonaCapeOnClassicSkin);
        $writer->writeBool($this->isPrimaryUser);
        $writer->writeBool($this->overridesPlayerAppearance);
        $writer->writeString($this->trustedSkinFlag->value, 8);
        $writer->writeString($this->profileHash, 256);
    }

    public static function decode(BinaryReader $reader): self
    {
        $id = $reader->readString(256);
        $resourcePatch = $reader->readString(64 * 1024);
        $image = SkinImage::decode($reader);

        $animatedCount = $reader->readVarUInt();

        if ($animatedCount > 64) {
            throw ProtocolException::lengthTooLarge('animated skin images', $animatedCount, 64);
        }

        $animatedImages = [];

        for ($i = 0; $i < $animatedCount; $i++) {
            $animatedImages[] = AnimatedImageData::decode($reader);
        }

        $capeImage = SkinImage::decode($reader);
        $geometryData = $reader->readString(1024 * 1024);
        $geometryDataMinEngineVersion = $reader->readString(64);
        $animationData = $reader->readString(1024 * 1024);
        $capeId = $reader->readString(256);
        $fullId = $reader->readString(256);
        $armSize = ArmSize::fromWire($reader->readByte());
        $skinColor = Color::decode($reader);

        $personaCount = $reader->readVarUInt();

        if ($personaCount > 256) {
            throw ProtocolException::lengthTooLarge('persona pieces', $personaCount, 256);
        }

        $personaPieces = [];

        for ($i = 0; $i < $personaCount; $i++) {
            $personaPieces[] = SerializedPersonaPieceHandle::decode($reader);
        }

        $tintCount = $reader->readVarUInt();

        if ($tintCount > 256) {
            throw ProtocolException::lengthTooLarge('piece tint colors', $tintCount, 256);
        }

        $pieceTintColors = [];

        for ($i = 0; $i < $tintCount; $i++) {
            $pieceTintColors[] = PieceTintColor::decode($reader);
        }

        return new self(
            id: $id,
            resourcePatch: $resourcePatch,
            image: $image,
            animatedImages: $animatedImages,
            capeImage: $capeImage,
            geometryData: $geometryData,
            geometryDataMinEngineVersion: $geometryDataMinEngineVersion,
            animationData: $animationData,
            capeId: $capeId,
            fullId: $fullId,
            armSize: $armSize,
            skinColor: $skinColor,
            personaPieces: $personaPieces,
            pieceTintColors: $pieceTintColors,
            isPremium: $reader->readBool(),
            isPersona: $reader->readBool(),
            isPersonaCapeOnClassicSkin: $reader->readBool(),
            isPrimaryUser: $reader->readBool(),
            overridesPlayerAppearance: $reader->readBool(),
            trustedSkinFlag: TrustedSkinFlag::fromName($reader->readString(8)),
            profileHash: $reader->readString(256)
        );
    }
}
