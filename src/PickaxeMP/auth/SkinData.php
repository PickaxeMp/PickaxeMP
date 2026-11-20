<?php

declare(strict_types=1);

namespace PickaxeMP\auth;

use PickaxeMP\auth\jwt\JwtClaims;

/**
 * `extraData` JWT'sinden gelen **doğrulanmış** skin verisi.
 *
 * Bu nesne yalnızca `SkinDataValidator` imzayı doğruladıktan sonra üretilir;
 * başka bir yol yoktur. Dolayısıyla `SkinData` taşıyan bir sonuç, "bu skin
 * verisi oyuncunun kendi anahtarıyla imzalanmış" demektir.
 *
 * ## Dürüst sınırlar
 *
 * - `skinData()` **base64 metnini** döndürür; PNG olarak çözülmez ve boyut
 *   doğrulaması yapılmaz (Phase 6 işi).
 * - Bu nesne **kimlik kanıtı değildir**: `xuid`, `identity`, `displayName`
 *   buradan okunmaz. Kimlik yalnızca zincir token'larından gelir.
 *
 * Bedrock `extraData` yükü (özet):
 *
 * ```json
 * {
 *   "SkinId": "…", "SkinData": "base64", "CapeData": "base64",
 *   "SkinGeometryName": "geometry.humanoid.custom",
 *   "SkinGeometryData": "base64"
 * }
 * ```
 */
final class SkinData
{
    public function __construct(
        private readonly string $skinId,
        private readonly string $skinData,
        private readonly string $capeData,
        private readonly string $geometryName,
        private readonly string $geometryData
    ) {
    }

    /**
     * Doğrulanmış JWT claim'lerinden skin verisini okur.
     *
     * Zorunlu alanlar: `SkinId`, `SkinData`. Eksikse **fail-closed**:
     * "yarım" skin verisi servis edilmez.
     *
     * @throws AuthException alan eksikse veya tipi yanlışsa
     */
    public static function fromClaims(JwtClaims $claims): self
    {
        return new self(
            skinId: self::requiredString($claims, 'SkinId'),
            skinData: self::requiredString($claims, 'SkinData'),
            capeData: self::optionalString($claims, 'CapeData'),
            geometryName: self::optionalString($claims, 'SkinGeometryName'),
            geometryData: self::optionalString($claims, 'SkinGeometryData')
        );
    }

    public function skinId(): string
    {
        return $this->skinId;
    }

    /**
     * Base64 kodlanmış skin görseli (çözülmemiş).
     */
    public function skinData(): string
    {
        return $this->skinData;
    }

    /**
     * Base64 kodlanmış pelerin görseli; pelerin yoksa boş metin.
     */
    public function capeData(): string
    {
        return $this->capeData;
    }

    public function geometryName(): string
    {
        return $this->geometryName;
    }

    /**
     * Base64 kodlanmış geometri JSON'u; yoksa boş metin.
     */
    public function geometryData(): string
    {
        return $this->geometryData;
    }

    public function hasCape(): bool
    {
        return $this->capeData !== '';
    }

    private static function requiredString(JwtClaims $claims, string $name): string
    {
        $value = $claims->all()[$name] ?? null;

        if ($value === null) {
            throw AuthException::missingClaim($name);
        }

        if (!is_string($value)) {
            throw AuthException::invalidClaimType($name, 'string', $value);
        }

        return $value;
    }

    private static function optionalString(JwtClaims $claims, string $name): string
    {
        $value = $claims->all()[$name] ?? null;

        if ($value === null) {
            return '';
        }

        if (!is_string($value)) {
            throw AuthException::invalidClaimType($name, 'string', $value);
        }

        return $value;
    }
}
