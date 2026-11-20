<?php

declare(strict_types=1);

namespace PickaxeMP\auth\jwt;

use PickaxeMP\auth\AuthException;

/**
 * JWT başlığı (JOSE header).
 *
 * Bedrock login zincirinde her JWT'nin başlığında `x5u` alanı bulunur ve bu
 * alan **Base64 kodlanmış X.509 (SPKI DER) açık anahtarı** taşır.
 *
 * DÜRÜST NOT: `x5u` adı standart JOSE'da "sertifika URL'i" anlamına gelir,
 * ancak Bedrock bunu **URL değil, anahtarın kendisi** olarak kullanır. Bu
 * nedenle burada ayrı bir `x5uKey` erişimcisi sunulur ve URL olduğu
 * varsayılmaz.
 */
final class JwtHeader
{
    /**
     * @param array<string, mixed> $raw
     */
    private function __construct(
        public readonly JwtAlgorithm $algorithm,
        public readonly ?string $x5u,
        public readonly ?string $keyId,
        public readonly ?string $type,
        private readonly array $raw
    ) {
    }

    /**
     * @param array<string, mixed> $decoded
     *
     * @throws AuthException `alg` yoksa/geçersizse
     */
    public static function fromArray(array $decoded): self
    {
        $alg = $decoded['alg'] ?? null;

        if (!is_string($alg)) {
            throw AuthException::malformedJwt('header.alg bir metin olmali');
        }

        $x5u = $decoded['x5u'] ?? null;

        if ($x5u !== null && !is_string($x5u)) {
            throw AuthException::invalidClaimType('header.x5u', 'string', $x5u);
        }

        $kid = $decoded['kid'] ?? null;
        $typ = $decoded['typ'] ?? null;

        return new self(
            JwtAlgorithm::fromHeader($alg),
            $x5u,
            is_string($kid) ? $kid : null,
            is_string($typ) ? $typ : null,
            $decoded
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->raw;
    }

    public function hasX5u(): bool
    {
        return $this->x5u !== null && $this->x5u !== '';
    }
}
