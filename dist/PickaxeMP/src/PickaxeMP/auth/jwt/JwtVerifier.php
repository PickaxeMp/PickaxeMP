<?php

declare(strict_types=1);

namespace PickaxeMP\auth\jwt;

use PickaxeMP\auth\AuthException;

/**
 * JWT doğrulama orkestratörü: zaman + imza.
 *
 * `verify()` **yalnızca** imza ve zaman kontrolü yapar. Zincir/trust-anchor
 * kontrolü `CertificateValidator`'ın işidir. İkisi birbirini tamamlar:
 *
 *   JwtVerifier        → "bu token bu anahtarla imzalanmış mı?"
 *   CertificateValidator → "bu anahtara güvenebilir miyim?"
 */
final class JwtVerifier
{
    public function __construct(
        private readonly JwtParser $parser = new JwtParser(),
        private readonly SignatureVerifierInterface $verifier = new OpenSslJwtVerifier(),
        private readonly int $clockSkewSeconds = 60
    ) {
    }

    public function getParser(): JwtParser
    {
        return $this->parser;
    }

    /**
     * Token'ı verilen anahtarla doğrular.
     *
     * @param int|null $now Test için zaman dondurma
     *
     * @throws AuthException yapı/zaman hatasında
     */
    public function verify(JwtToken $token, TrustedPublicKey $key, ?int $now = null): VerificationResult
    {
        $token->claims->validateTimes($now ?? time(), $this->clockSkewSeconds);

        return $this->verifier->verify(
            $token->signingInput,
            $token->signatureRaw,
            $token->header->algorithm,
            $key
        );
    }

    /**
     * Ham metni ayrıştır + doğrula.
     */
    public function parseAndVerify(string $jwt, TrustedPublicKey $key, ?int $now = null): JwtToken
    {
        $token = $this->parser->parse($jwt);
        $result = $this->verify($token, $key, $now);

        if ($result->failed()) {
            throw AuthException::signatureInvalid($result->reason);
        }

        return $token;
    }
}
