<?php

declare(strict_types=1);

namespace PickaxeMP\auth\jwt;

/**
 * Ayrıştırılmış ama **henüz doğrulanmamış** JWT.
 *
 * `verified` bayrağı olmadan bu nesne kimlik kanıtı sayılmaz. Doğrulama
 * `JwtVerifier` tarafından yapılır ve sonuç ayrı bir `VerificationResult`
 * olarak döner.
 */
final class JwtToken
{
    /**
     * @param string $signingInput İmzalanan tam metin: `header.payload`
     *                             (yeniden kodlanmaz — orijinal baytlar)
     * @param string $signatureRaw Ham imza (base64url çözülmüş)
     */
    public function __construct(
        public readonly string $encodedHeader,
        public readonly string $encodedPayload,
        public readonly string $encodedSignature,
        public readonly JwtHeader $header,
        public readonly JwtClaims $claims,
        public readonly string $signingInput,
        public readonly string $signatureRaw
    ) {
    }

    /**
     * Başlıktaki `x5u` alanından gelen anahtar (base64 SPKI DER), varsa.
     */
    public function attestedKey(): ?string
    {
        return $this->header->x5u;
    }
}
