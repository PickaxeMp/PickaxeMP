<?php

declare(strict_types=1);

namespace PickaxeMP\auth;

use PickaxeMP\auth\jwt\JwtToken;

/**
 * Doğrulanmış zincirden `PlayerIdentity` üretir.
 *
 * ## Neden ayrı sınıf?
 *
 * `PlayerIdentity` oluşturmak, claim'lerin **tutarlı** olduğunu doğrulamayı
 * gerektirir (UUID biçimi, XUID varlığı, isim uzunluğu). Bu kontrolleri tek
 * yerde toplamak, "yanlışlıkla doğrulanmamış veriden kimlik üretme" riskini
 * ortadan kaldırır.
 *
 * ## Dürüst sınır
 *
 * UUID biçimi kanonik 8-4-4-4-12 **olmalı**; Xbox Live kimlikleri bu biçimde
 * gelir. Biçim bozuksa reddedilir — "düzeltmeye" çalışmayız.
 */
final class PlayerIdentityFactory
{
    /** Oyuncu adı sınırları. */
    public const MIN_NAME_LENGTH = 1;
    public const MAX_NAME_LENGTH = 64;

    /**
     * Doğrulanmış zincirin **son** halkasından kimlik üretir.
     *
     * @param list<JwtToken> $allTokens Tüm doğrulanmış halkalar
     *
     * @throws AuthException claim'ler eksik/tutarsızsa
     */
    public function fromVerifiedChain(JwtToken $last, array $allTokens, int $verifiedAt): PlayerIdentity
    {
        $xuid = $last->claims->xuid();
        $uuid = $last->claims->identity();
        $name = $last->claims->displayName();

        if ($xuid === null || $xuid === '') {
            throw AuthException::missingClaim('xuid');
        }

        if ($uuid === null || $uuid === '') {
            throw AuthException::missingClaim('identity');
        }

        if ($name === null || $name === '') {
            throw AuthException::missingClaim('displayName');
        }

        $this->assertValidUuid($uuid);
        $this->assertValidName($name);

        // Zincirdeki tüm halkalarda XUID tutarlı olmalı (varsa).
        foreach ($allTokens as $token) {
            $candidate = $token->claims->xuid();

            if ($candidate !== null && $candidate !== '' && $candidate !== $xuid) {
                throw AuthException::identityMismatch('zincir icinde farkli xuid');
            }
        }

        return new PlayerIdentity(
            xuid: $xuid,
            uuid: strtolower($uuid),
            displayName: $name,
            type: IdentityType::XboxLive,
            verifiedAt: $verifiedAt,
            clientPublicKey: $last->claims->clientPublicKey(),
            chainFingerprint: $this->fingerprintOf($allTokens)
        );
    }

    /**
     * Offline/test kimliği — **üretimde kullanılmaz**.
     *
     * Deterministik UUID: aynı isim her zaman aynı UUID üretir, böylece test
     * tekrarlanabilir kalır.
     */
    public function offline(string $name): PlayerIdentity
    {
        if ($name === '') {
            $name = 'OfflinePlayer';
        }

        $this->assertValidName($name);

        // MD5 tabanlı deterministik UUID (yalnızca offline mod).
        $hash = md5('PickaxeMP-Offline-' . $name);
        $uuid = sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12)
        );

        // XUID: 16 haneli deterministik sayı.
        $xuid = sprintf('%016d', hexdec(substr($hash, 0, 12)) % 9000000000000000 + 1000000000000000);

        return new PlayerIdentity(
            xuid: $xuid,
            uuid: $uuid,
            displayName: $name,
            type: IdentityType::Offline,
            verifiedAt: time(),
            clientPublicKey: null,
            chainFingerprint: 'offline'
        );
    }

    private function assertValidUuid(string $uuid): void
    {
        if (preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $uuid) !== 1) {
            throw AuthException::identityMismatch('gecersiz UUID bicimi: ' . substr($uuid, 0, 40));
        }
    }

    private function assertValidName(string $name): void
    {
        $length = \mb_strlen($name);

        if ($length < self::MIN_NAME_LENGTH || $length > self::MAX_NAME_LENGTH) {
            throw AuthException::identityMismatch(
                "gecersiz oyuncu adi uzunlugu: {$length}"
            );
        }
    }

    /**
     * @param list<JwtToken> $tokens
     */
    private function fingerprintOf(array $tokens): string
    {
        $parts = array_map(
            static fn (JwtToken $token): string => hash('sha256', $token->signingInput),
            $tokens
        );

        return substr(hash('sha256', implode('|', $parts)), 0, 32);
    }
}
