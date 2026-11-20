<?php

declare(strict_types=1);

namespace PickaxeMP\auth;

use PickaxeMP\auth\jwt\JwtParser;
use PickaxeMP\auth\jwt\JwtToken;
use PickaxeMP\auth\jwt\JwtVerifier;
use PickaxeMP\auth\jwt\TrustedPublicKey;

/**
 * `extraData` (skin/cihaz) JWT'sini doğrular.
 *
 * ## Neden gerekli?
 *
 * Bedrock istemcisi `connectionRequest.extraData` alanında ayrı bir JWT
 * gönderir. Bu token **zincirin parçası değildir** ama istemcinin kendi özel
 * anahtarıyla imzalanmıştır. İmza doğrulanmadan bu veriyi kullanmak, ağdan
 * gelen herhangi bir JSON'u "oyuncunun skini" saymak demektir.
 *
 * ## Doğrulama anahtarı
 *
 * `extraData`'yı imzalayan anahtar, zincirin **son halkasını** imzalayan
 * anahtardır; yani son token'ın `header.x5u` değeri. Zincir doğrulaması bu
 * anahtarın oyuncuya ait olduğunu zaten kanıtlamıştır
 * (`CertificateValidator`), bu yüzden imza kontrolü yeterlidir.
 *
 * ## Değişmezler
 *
 * 1. `extraData` yoksa `null` döner — skin yok, hata yok.
 * 2. `extraData` varsa **imza doğrulanmadan** hiçbir alan okunmaz.
 * 3. Bu token'dan kimlik alanı (`xuid`, `identity`, `displayName`) okunmaz.
 */
final class SkinDataValidator
{
    public function __construct(
        private readonly JwtParser $parser = new JwtParser(),
        private readonly JwtVerifier $verifier = new JwtVerifier()
    ) {
    }

    /**
     * @param string|null $extraData       `chain` JSON'undaki `extraData` alanı
     * @param JwtToken    $lastChainToken  Zincirin son halkası (doğrulanmış)
     * @param int|null    $now             Test için zaman dondurma
     *
     * @throws AuthException extraData var ama doğrulanamıyorsa
     */
    public function validate(?string $extraData, JwtToken $lastChainToken, ?int $now = null): ?SkinData
    {
        if ($extraData === null || $extraData === '') {
            return null;
        }

        $x5u = $lastChainToken->header->x5u;

        if ($x5u === null || $x5u === '') {
            throw AuthException::invalidKey('extraData: zincir son halkasinda x5u yok');
        }

        $key = TrustedPublicKey::fromBase64($x5u, 'extraData anahtari');
        $token = $this->parser->parse($extraData);
        $result = $this->verifier->verify($token, $key, $now);

        if ($result->failed()) {
            throw AuthException::signatureInvalid('extraData: ' . $result->reason);
        }

        return SkinData::fromClaims($token->claims);
    }
}
