<?php

declare(strict_types=1);

namespace PickaxeMP\auth\jwt;

use PickaxeMP\auth\AuthException;

/**
 * OpenSSL tabanlı JWT imza doğrulayıcı.
 *
 * Kritik ayrıntılar:
 *  1. **Orijinal `header.payload` baytları** imzalanır. JSON yeniden
 *     kodlanmaz — yeniden kodlama farklı bayt üretir ve imza bozulur.
 *  2. **JOSE ham imzası (R||S)** OpenSSL'in beklediği **DER**'e çevrilir
 *     (`EcdsaSignature::rawToDer`). Bu dönüşüm olmadan ES384 asla geçmez.
 *  3. `openssl_verify` sonucu **1** ise geçerli, **0** geçersiz,
 *     **-1** hata. `-1` sessizce "geçersiz" sayılır ama loglanır.
 *  4. Algoritma ile anahtar tipi uyuşmalı (ES* → EC, RS* → RSA). Aksi halde
 *     "algoritma karışıklığı" saldırısı mümkün olur.
 */
final class OpenSslJwtVerifier implements SignatureVerifierInterface
{
    public function verify(
        string $signingInput,
        string $signatureRaw,
        JwtAlgorithm $algorithm,
        TrustedPublicKey $key
    ): VerificationResult {
        try {
            return $this->doVerify($signingInput, $signatureRaw, $algorithm, $key);
        } catch (AuthException $exception) {
            return VerificationResult::failure($exception->getMessage());
        }
    }

    private function doVerify(
        string $signingInput,
        string $signatureRaw,
        JwtAlgorithm $algorithm,
        TrustedPublicKey $key
    ): VerificationResult {
        if ($signingInput === '') {
            return VerificationResult::failure('imzalanan veri bos');
        }

        $publicKey = openssl_pkey_get_public($key->pem);

        if ($publicKey === false) {
            return VerificationResult::failure('acik anahtar yuklenemedi');
        }

        $details = openssl_pkey_get_details($publicKey);

        if ($details === false) {
            return VerificationResult::failure('anahtar detaylari alinamadi');
        }

        // Algoritma ↔ anahtar tipi uyumu.
        if ($algorithm->isEcdsa() && $details['type'] !== OPENSSL_KEYTYPE_EC) {
            return VerificationResult::failure('ECDSA algoritmasi icin EC anahtari gerekli');
        }

        if ($algorithm->isRsa() && $details['type'] !== OPENSSL_KEYTYPE_RSA) {
            return VerificationResult::failure('RSA algoritmasi icin RSA anahtari gerekli');
        }

        // Ham imzayı OpenSSL biçimine çevir.
        if ($algorithm->isEcdsa()) {
            if (!EcdsaSignature::isValidRaw($signatureRaw, $algorithm)) {
                return VerificationResult::failure('gecersiz ECDSA imza bicimi');
            }

            $signature = EcdsaSignature::rawToDer($signatureRaw, $algorithm);
        } else {
            $signature = $signatureRaw;
        }

        $opensslAlgorithm = $algorithm->openssl()['algorithm'];
        $result = openssl_verify($signingInput, $signature, $publicKey, $opensslAlgorithm);

        if ($result === 1) {
            return VerificationResult::success();
        }

        if ($result === 0) {
            return VerificationResult::failure('imza eslesmiyor');
        }

        return VerificationResult::failure('openssl dogrulama hatasi');
    }
}
