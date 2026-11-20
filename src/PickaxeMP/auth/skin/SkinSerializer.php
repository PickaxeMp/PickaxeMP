<?php

declare(strict_types=1);

namespace PickaxeMP\auth\skin;

use PickaxeMP\auth\SkinData;
use PickaxeMP\protocol\packet\types\ArmSize;
use PickaxeMP\protocol\packet\types\Color;
use PickaxeMP\protocol\packet\types\SerializedSkin;
use PickaxeMP\protocol\packet\types\SkinImage;
use PickaxeMP\protocol\packet\types\TrustedSkinFlag;

/**
 * Doğrulanmış `SkinData` → tel formatındaki `SerializedSkin`.
 *
 * ## Neden ayrı sınıf?
 *
 * `SkinData` **JWT alanlarını** taşır (base64 metin); `SerializedSkin`
 * **tel biçimini** (ham RGBA, çözülmüş geometri) taşır. Dönüşümü tek yerde
 * tutmak, "bir yerde base64, başka yerde RGBA" karışıklığını önler.
 *
 * ## Fail-open (bilinçli)
 *
 * Deri çözülemezse oyuncu **atılmaz**; `blank()` döner ve istemci varsayılan
 * deriyi çizer. Bu, vanilla davranışıdır: kimlik doğrulaması geçmiş bir
 * oyuncuyu bozuk bir PNG yüzünden sunucudan atmak orantısızdır.
 *
 * ## Sınırlar
 *
 * - Yalnızca 8 bit, paletsiz, interlaced olmayan PNG (bkz. `PngDecoder`).
 * - Pelerin (`CapeData`) varsa aynı çözücüyle çözülür; yoksa boş görsel.
 * - Geometri `SkinGeometryData` base64 JSON'udur; çözülemezse boş gider.
 */
final class SkinSerializer
{
    public static function serialize(?SkinData $skin): SerializedSkin
    {
        if ($skin === null) {
            return SerializedSkin::blank();
        }

        try {
            $image = self::image($skin->skinData());
        } catch (SkinException) {
            return SerializedSkin::blank($skin->skinId());
        }

        $cape = SkinImage::empty();

        if ($skin->hasCape()) {
            try {
                $cape = self::image($skin->capeData());
            } catch (SkinException) {
                // Pelerin bozuksa yalnızca pelerin düşer; deri korunur.
                $cape = SkinImage::empty();
            }
        }

        return new SerializedSkin(
            id: $skin->skinId(),
            resourcePatch: self::resourcePatch($skin->geometryName()),
            image: $image,
            animatedImages: [],
            capeImage: $cape,
            geometryData: self::base64Text($skin->geometryData()),
            geometryDataMinEngineVersion: self::geometryVersion(self::base64Text($skin->geometryData())),
            animationData: '',
            capeId: $skin->hasCape() ? $skin->skinId() . '-cape' : '',
            fullId: $skin->skinId(),
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

    /**
     * Base64 PNG'yi tel formatındaki ham RGBA görseline çevirir.
     */
    private static function image(string $base64): SkinImage
    {
        $png = base64_decode($base64, true);

        if ($png === false || $png === '') {
            throw SkinException::invalidPng('base64 cozulemedi');
        }

        $image = PngDecoder::decode($png);

        return new SkinImage($image->width, $image->height, $image->rgba);
    }

    /**
     * Geometri adını istemcinin beklediği `ResourcePatch` JSON'una yazar.
     */
    private static function resourcePatch(string $geometryName): string
    {
        if ($geometryName === '') {
            return '';
        }

        try {
            return (string) json_encode(
                ['geometry' => ['default' => $geometryName]],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
            );
        } catch (\JsonException) {
            return '';
        }
    }

    /**
     * Base64 metnini çözer; bozuk/boş veri boş metin olur.
     */
    private static function base64Text(string $base64): string
    {
        if ($base64 === '') {
            return '';
        }

        $decoded = base64_decode($base64, true);

        return $decoded === false ? '' : $decoded;
    }

    /**
     * Geometri JSON'undaki `format_version` alanını okur.
     *
     * İstemci geometriyi yorumlarken bu sürümü kullanır; boş bırakmak
     * özel deri geometrisini devre dışı bırakabilir. JSON çözülemezse boş
     * döner — geometri yine gönderilir, karar istemcinindir.
     */
    private static function geometryVersion(string $geometry): string
    {
        if ($geometry === '') {
            return '';
        }

        try {
            $decoded = json_decode($geometry, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return '';
        }

        if (!is_array($decoded)) {
            return '';
        }

        $version = $decoded['format_version'] ?? '';

        return is_string($version) ? $version : '';
    }
}
