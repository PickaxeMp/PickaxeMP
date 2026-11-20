<?php

declare(strict_types=1);

namespace PickaxeMP\auth\jwt;

/**
 * İmza doğrulayıcı sözleşmesi.
 *
 * Ayrı arayüz olmasının nedeni test edilebilirliktir: testlerde deterministik
 * bir sahte doğrulayıcı kullanılabilir. Üretimde `OpenSslJwtVerifier`.
 */
interface SignatureVerifierInterface
{
    /**
     * @param string $signingInput İmzalanan baytlar (`header.payload`)
     * @param string $signatureRaw Ham JOSE imzası (base64url çözülmüş)
     */
    public function verify(
        string $signingInput,
        string $signatureRaw,
        JwtAlgorithm $algorithm,
        TrustedPublicKey $key
    ): VerificationResult;
}
