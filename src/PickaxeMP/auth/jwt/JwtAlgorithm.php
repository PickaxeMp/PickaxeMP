<?php

declare(strict_types=1);

namespace PickaxeMP\auth\jwt;

use PickaxeMP\auth\AuthException;

/**
 * İmza algoritması.
 *
 * Yalnızca **açıkça izin verilen** algoritmalar kabul edilir. Bilinmeyen
 * algoritma ve özellikle `none` reddedilir — `alg` başlığı istemci
 * tarafından kontrol edildiği için "algoritma karışıklığı" saldırılarının
 * birincil hedefidir.
 */
enum JwtAlgorithm: string
{
    case ES384 = 'ES384';
    case ES256 = 'ES256';
    case RS256 = 'RS256';
    case RS384 = 'RS384';

    /**
     * @throws AuthException bilinmeyen veya yasak algoritma
     */
    public static function fromHeader(string $alg): self
    {
        $normalized = strtolower(trim($alg));

        if ($normalized === 'none' || $normalized === '') {
            throw AuthException::algNoneRejected();
        }

        $match = match (strtoupper($normalized)) {
            'ES384' => self::ES384,
            'ES256' => self::ES256,
            'RS256' => self::RS256,
            'RS384' => self::RS384,
            default => null,
        };

        if ($match === null) {
            throw AuthException::unsupportedAlgorithm($alg);
        }

        return $match;
    }

    /**
     * OpenSSL sabiti ve hash algoritması.
     *
     * @return array{algorithm: int, hash: int}
     */
    public function openssl(): array
    {
        return match ($this) {
            self::ES384 => ['algorithm' => OPENSSL_ALGO_SHA384, 'hash' => OPENSSL_ALGO_SHA384],
            self::ES256 => ['algorithm' => OPENSSL_ALGO_SHA256, 'hash' => OPENSSL_ALGO_SHA256],
            self::RS256 => ['algorithm' => OPENSSL_ALGO_SHA256, 'hash' => OPENSSL_ALGO_SHA256],
            self::RS384 => ['algorithm' => OPENSSL_ALGO_SHA384, 'hash' => OPENSSL_ALGO_SHA384],
        };
    }

    public function isEcdsa(): bool
    {
        return $this === self::ES384 || $this === self::ES256;
    }

    public function isRsa(): bool
    {
        return $this === self::RS256 || $this === self::RS384;
    }

    /**
     * ECDSA için ham imza uzunluğu (R || S, her biri eğri boyu).
     */
    public function ecdsaCoordinateBytes(): int
    {
        return match ($this) {
            self::ES256 => 32,
            self::ES384 => 48,
            default => throw AuthException::unsupportedAlgorithm($this->value),
        };
    }

    /**
     * Beklenen ham imza uzunluğu (JOSE biçimi).
     */
    public function rawSignatureLength(): int
    {
        return $this->isEcdsa() ? $this->ecdsaCoordinateBytes() * 2 : 0;
    }
}
