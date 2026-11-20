<?php

declare(strict_types=1);

namespace PickaxeMP\auth\certificate;

use PickaxeMP\auth\AuthException;

/**
 * Login paketindeki `chain` dizisinin ayrıştırıcısı.
 *
 * Bedrock `Login.connectionRequest` bir **JSON nesnesi** içerir:
 *
 * ```json
 * {
 *   "chain": ["<jwt>", "<jwt>", "<jwt>"],
 *   "extraData": { ... }        // bazı sürümlerde iç içe JWT
 * }
 * ```
 *
 * Zincir tipik olarak 3 JWT'dir ama sayı garanti değildir; 1..5 aralığı
 * makul kabul edilir. Sayı dışındaysa reddedilir.
 */
final class CertificateChain
{
    public const MIN_TOKENS = 1;
    public const MAX_TOKENS = 5;

    /** Ham bağlantı isteği için üst sınır (şema: ~100 KB). */
    public const MAX_REQUEST_SIZE = 512 * 1024;

    /**
     * @param list<string> $tokens
     */
    private function __construct(
        public readonly array $tokens,
        public readonly ?string $extraData
    ) {
    }

    /**
     * @throws AuthException yapı bozuksa
     */
    public static function fromConnectionRequest(string $request): self
    {
        if ($request === '') {
            throw AuthException::malformedJwt('baglanti istegi bos');
        }

        if (strlen($request) > self::MAX_REQUEST_SIZE) {
            throw AuthException::jwtTooLong(strlen($request), self::MAX_REQUEST_SIZE);
        }

        $decoded = json_decode($request, true, 64);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw AuthException::malformedJwt(
                'baglanti istegi JSON hatasi: ' . json_last_error_msg()
            );
        }

        if (!is_array($decoded)) {
            throw AuthException::malformedJwt('baglanti istegi JSON nesnesi olmali');
        }

        $chain = $decoded['chain'] ?? null;

        if (!is_array($chain) || $chain === []) {
            throw AuthException::malformedJwt('chain dizisi eksik veya bos');
        }

        $tokens = [];

        foreach ($chain as $token) {
            if (!is_string($token) || $token === '') {
                throw AuthException::malformedJwt('chain ogesi metin olmali');
            }

            $tokens[] = $token;
        }

        if (count($tokens) < self::MIN_TOKENS || count($tokens) > self::MAX_TOKENS) {
            throw AuthException::chainTooShortOrLong(count($tokens));
        }

        $extraData = null;

        if (isset($decoded['extraData'])) {
            if (!is_string($decoded['extraData'])) {
                throw AuthException::malformedJwt('extraData metin olmali');
            }

            $extraData = $decoded['extraData'];
        }

        return new self($tokens, $extraData);
    }

    public function count(): int
    {
        return count($this->tokens);
    }

    public function hasExtraData(): bool
    {
        return $this->extraData !== null && $this->extraData !== '';
    }
}
