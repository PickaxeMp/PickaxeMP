<?php

declare(strict_types=1);

namespace PickaxeMP\auth\jwt;

use PickaxeMP\auth\AuthException;

/**
 * JWT yapısal ayrıştırıcı — **kriptografi YAPMAZ**.
 *
 * Sorumluluk: üç parçalı yapıyı ayır, base64url çöz, JSON ayrıştır, başlığı
 * doğrula. İmza kontrolü `JwtVerifier`'ın işidir.
 *
 * Katı kurallar:
 *  - Tam olarak 2 nokta (3 parça). Fazlası/eksiği reddedilir.
 *  - Boş parça reddedilir.
 *  - `alg=none` reddedilir.
 *  - İmza uzunluğu algoritmaya uymalı (ES384 → 96 bayt).
 *  - `signingInput` **orijinal** `header.payload` metnidir; JSON yeniden
 *    kodlanmaz (yeniden kodlama imzayı bozar).
 */
final class JwtParser
{
    /** Tek JWT için üst sınır. Login zinciri ~100 KB; parça başına 1 MiB yeter. */
    public const MAX_TOKEN_LENGTH = 1024 * 1024;

    /**
     * @throws AuthException yapı geçersizse
     */
    public function parse(string $jwt): JwtToken
    {
        if (strlen($jwt) > self::MAX_TOKEN_LENGTH) {
            throw AuthException::jwtTooLong(strlen($jwt), self::MAX_TOKEN_LENGTH);
        }

        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            throw AuthException::malformedJwt(
                'tam olarak 3 parca olmali, ' . count($parts) . ' bulundu'
            );
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        if ($encodedHeader === '' || $encodedPayload === '' || $encodedSignature === '') {
            throw AuthException::malformedJwt('bos parca');
        }

        $headerJson = Base64Url::decode($encodedHeader, 'header');
        $payloadJson = Base64Url::decode($encodedPayload, 'payload');
        $signatureRaw = Base64Url::decode($encodedSignature, 'signature');

        $header = JwtHeader::fromArray($this->decodeJsonObject($headerJson, 'header'));
        $claims = JwtClaims::fromArray($this->decodeJsonObject($payloadJson, 'payload'));

        // İmza uzunluğu algoritmaya uygun olmalı.
        if ($header->algorithm->isEcdsa()) {
            $expected = $header->algorithm->rawSignatureLength();

            if (strlen($signatureRaw) !== $expected) {
                throw AuthException::malformedJwt(sprintf(
                    'ECDSA imzasi %d bayt olmali, %d alindi',
                    $expected,
                    strlen($signatureRaw)
                ));
            }
        }

        return new JwtToken(
            encodedHeader: $encodedHeader,
            encodedPayload: $encodedPayload,
            encodedSignature: $encodedSignature,
            header: $header,
            claims: $claims,
            signingInput: $encodedHeader . '.' . $encodedPayload,
            signatureRaw: $signatureRaw
        );
    }

    /**
     * Bir JWT dizisini (Bedrock zinciri) ayrıştırır.
     *
     * @param list<string> $tokens
     * @return list<JwtToken>
     */
    public function parseAll(array $tokens): array
    {
        $parsed = [];

        foreach ($tokens as $token) {
            if (!is_string($token)) {
                throw AuthException::malformedJwt('zincir ogesi metin olmali');
            }

            $parsed[] = $this->parse($token);
        }

        return $parsed;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonObject(string $json, string $context): array
    {
        $decoded = json_decode($json, true, 32);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw AuthException::malformedJwt(
                "{$context} JSON hatasi: " . json_last_error_msg()
            );
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            throw AuthException::malformedJwt("{$context} bir JSON nesnesi olmali");
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
