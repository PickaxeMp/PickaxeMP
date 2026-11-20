<?php

declare(strict_types=1);

namespace PickaxeMP\auth\certificate;

use PickaxeMP\auth\AuthException;
use PickaxeMP\auth\jwt\JwtParser;
use PickaxeMP\auth\jwt\JwtToken;
use PickaxeMP\auth\jwt\JwtVerifier;
use PickaxeMP\auth\jwt\TrustedPublicKey;

/**
 * Sertifika zinciri doğrulayıcı.
 *
 * ## Doğrulama kuralı (wiki.vg "Validating the Chain of Authority")
 *
 * Her JWT'nin başlığında `x5u` (base64 SPKI DER) bulunur. Zincir şöyle
 * yürünür:
 *
 * ```
 * öncekiAnahtar = null
 * for her jwt in chain:
 *     buAnahtar = TrustedPublicKey(jwt.header.x5u)
 *     if öncekiAnahtar == null:
 *         // İlk halka kendi kendini imzalar
 *         öncekiAnahtar = buAnahtar
 *     else if öncekiAnahtar != buAnahtar:
 *         REDDET
 *     if !verify(jwt, öncekiAnahtar):
 *         REDDET
 *     if öncekiAnahtar is MOJANG_ROOT:
 *         mojangGoruldu = true
 *     öncekiAnahtar = TrustedPublicKey(jwt.claims.identityPublicKey)
 * ```
 *
 * Zincir ancak **Mojang kök anahtarı görüldüyse** geçerlidir. Bu, "herhangi
 * birinin kendi anahtarıyla imzaladığı zinciri" kabul etmeyi engeller.
 *
 * ## Dürüst uyarılar
 *
 * 1. `x5u` burada URL değil anahtardır (Bedrock'a özgü sapma).
 * 2. İlk halkanın kendini imzalaması kriptografik olarak "hiçbir şey kanıtlamaz";
 *    güven yalnızca zincirde Mojang anahtarının bulunmasından gelir.
 * 3. Zaman kontrolü her token için yapılır (`exp`/`nbf`).
 */
final class CertificateValidator
{
    /** Saat kayması toleransı (saniye). */
    public const CLOCK_SKEW_SECONDS = 60;

    public function __construct(
        private readonly JwtParser $parser = new JwtParser(),
        private readonly JwtVerifier $verifier = new JwtVerifier(),
        private readonly TrustedKeyStore $trustStore = new TrustedKeyStore()
    ) {
    }

    /**
     * Zinciri doğrular ve halkaları döndürür.
     *
     * @param int|null $now Test için zaman dondurma
     *
     * @return list<JwtToken> Doğrulanmış halkalar (sırayla)
     *
     * @throws AuthException zincir geçersizse
     */
    public function validate(CertificateChain $chain, ?int $now = null): array
    {
        $validated = [];
        $previousKey = null;
        $sawMojangRoot = false;
        $now ??= time();

        foreach ($chain->tokens as $index => $rawToken) {
            $token = $this->parser->parse($rawToken);

            if (!$token->header->hasX5u()) {
                throw AuthException::invalidChain("halka {$index}: x5u basligi eksik");
            }

            /** @var string $x5u */
            $x5u = $token->header->x5u;
            $attestedKey = TrustedPublicKey::fromBase64($x5u, "chain[{$index}].x5u");

            if ($previousKey === null) {
                // İlk halka kendini imzalar; kimliği bu anahtardır.
                $previousKey = $attestedKey;
            } elseif (!$previousKey->matches($attestedKey)) {
                throw AuthException::invalidChain(
                    "halka {$index}: x5u onceki anahtarla uyusmuyor"
                );
            }

            // Zaman + imza.
            $token->claims->validateTimes($now, self::CLOCK_SKEW_SECONDS);

            $result = $this->verifier->verify($token, $previousKey, $now);

            if ($result->failed()) {
                throw AuthException::invalidChain(
                    "halka {$index}: imza gecersiz ({$result->reason})"
                );
            }

            // Mojang kökü görüldü mü?
            if ($this->trustStore->isTrusted($previousKey)) {
                $sawMojangRoot = true;
            }

            $validated[] = $token;

            // Bir sonraki halkanın anahtarı.
            $nextKey = $token->claims->identityPublicKey();

            if ($nextKey === null || $nextKey === '') {
                // Son halka olabilir; devam eden halka varsa hata.
                if ($index < $chain->count() - 1) {
                    throw AuthException::invalidChain(
                        "halka {$index}: identityPublicKey eksik ama zincir devam ediyor"
                    );
                }

                $previousKey = null;

                continue;
            }

            $previousKey = TrustedPublicKey::fromBase64($nextKey, "chain[{$index}].identityPublicKey");
        }

        if (!$sawMojangRoot) {
            throw AuthException::invalidChain(
                'zincirde guvenilen kok anahtar (Mojang) bulunamadi'
            );
        }

        return $validated;
    }

    public function getParser(): JwtParser
    {
        return $this->parser;
    }

    public function getVerifier(): JwtVerifier
    {
        return $this->verifier;
    }

    public function getTrustStore(): TrustedKeyStore
    {
        return $this->trustStore;
    }
}
