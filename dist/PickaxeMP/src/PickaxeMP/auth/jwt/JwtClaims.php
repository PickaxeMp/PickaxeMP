<?php

declare(strict_types=1);

namespace PickaxeMP\auth\jwt;

use PickaxeMP\auth\AuthException;

/**
 * JWT claim kümesi — **tipli ve doğrulanmış** erişim.
 *
 * İstemciden gelen JSON güvenilmez kabul edilir: her alan tip kontrolünden
 * geçer, yanlış tipte `AuthException` fırlatır. "Sessizce null" yoktur.
 */
final class JwtClaims
{
    /**
     * @param array<string, mixed> $raw
     */
    private function __construct(private readonly array $raw)
    {
    }

    /**
     * @param array<string, mixed> $decoded
     */
    public static function fromArray(array $decoded): self
    {
        return new self($decoded);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->raw;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->raw);
    }

    // --- Zaman alanları -------------------------------------------------

    /**
     * `exp` — son geçerlilik (Unix saniye).
     */
    public function expiresAt(): ?int
    {
        return $this->optionalInt('exp');
    }

    /**
     * `nbf` — geçerlilik başlangıcı.
     */
    public function notBefore(): ?int
    {
        return $this->optionalInt('nbf');
    }

    /**
     * `iat` — düzenlenme zamanı.
     */
    public function issuedAt(): ?int
    {
        return $this->optionalInt('iat');
    }

    /**
     * Zaman alanlarını verilen ana göre doğrular.
     *
     * @param int $now Unix saniye
     * @param int $clockSkewSeconds Tolerans (varsayılan 60 sn)
     *
     * @throws AuthException süresi dolmuş veya henüz geçerli değilse
     */
    public function validateTimes(int $now, int $clockSkewSeconds = 60): void
    {
        $exp = $this->expiresAt();

        if ($exp !== null && $now - $clockSkewSeconds >= $exp) {
            throw AuthException::expired($exp, $now);
        }

        $nbf = $this->notBefore();

        if ($nbf !== null && $now + $clockSkewSeconds < $nbf) {
            throw AuthException::notYetValid($nbf, $now);
        }

        // `iat` gelecekte olamaz (toleransla).
        $iat = $this->issuedAt();

        if ($iat !== null && $iat > $now + $clockSkewSeconds) {
            throw AuthException::notYetValid($iat, $now);
        }
    }

    // --- Bedrock kimlik alanları ---------------------------------------

    /**
     * `xuid` — Xbox kullanıcı kimliği (metin olarak taşınır).
     */
    public function xuid(): ?string
    {
        $value = $this->raw['xuid'] ?? null;

        if ($value === null) {
            return null;
        }

        // Bazı istemciler sayı gönderir; güvenli biçimde metne çevir.
        if (is_int($value)) {
            return (string) $value;
        }

        if (!is_string($value)) {
            throw AuthException::invalidClaimType('xuid', 'string|int', $value);
        }

        return $value;
    }

    /**
     * `identity` — oyuncunun UUID'si.
     */
    public function identity(): ?string
    {
        $value = $this->raw['identity'] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw AuthException::invalidClaimType('identity', 'string', $value);
        }

        return $value;
    }

    /**
     * `displayName` — oyuncu adı.
     */
    public function displayName(): ?string
    {
        $value = $this->raw['displayName'] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw AuthException::invalidClaimType('displayName', 'string', $value);
        }

        return $value;
    }

    /**
     * `identityPublicKey` — bir sonraki zincir halkasını doğrulayan açık anahtar.
     *
     * Base64 kodlanmış SPKI DER (EC P-384).
     */
    public function identityPublicKey(): ?string
    {
        $value = $this->raw['identityPublicKey'] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw AuthException::invalidClaimType('identityPublicKey', 'string', $value);
        }

        return $value;
    }

    /**
     * `cpk` — istemci açık anahtarı.
     *
     * Sürüm farkı: 1.26.40 öncesi base64 DER string, sonrası **JWK nesnesi**.
     * Bu yüzden dönüş tipi `string|array|null`.
     *
     * @return string|array<string, mixed>|null
     */
    public function clientPublicKey(): string|array|null
    {
        $value = $this->raw['cpk'] ?? null;

        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            return $value;
        }

        throw AuthException::invalidClaimType('cpk', 'string|array', $value);
    }

    /**
     * `extraData` — skin/cihaz bilgisi taşıyan iç JWT (şifreli metin).
     */
    public function extraData(): ?string
    {
        $value = $this->raw['extraData'] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw AuthException::invalidClaimType('extraData', 'string', $value);
        }

        return $value;
    }

    private function optionalInt(string $name): ?int
    {
        $value = $this->raw[$name] ?? null;

        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        // Bazı üreticiler zaman alanlarını metin gönderir; katı davranıp kabul
        // etmiyoruz: yanlış tip = şüpheli token.
        throw AuthException::invalidClaimType($name, 'int', $value);
    }
}
