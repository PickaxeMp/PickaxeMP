<?php

declare(strict_types=1);

namespace PickaxeMP\transport\sidecar;

/**
 * PHP ↔ Rust IPC el sıkışması için challenge-response doğrulaması.
 *
 * Paylaşılan token hiçbir zaman IPC soketine yazılmaz. Rust rastgele bir
 * nonce gönderir; PHP bu nonce ile token'dan HMAC-SHA256 kanıtı üretir.
 * Rust, kanıtı sabit zamanlı olarak doğrular.
 *
 * Kanıt girdisi sürümlüdür; bağlam değişirse eski bir kanıt geçerli olmaz:
 *
 *   proof = HMAC-SHA256(token, "pickaxe-ipc-v1:" || lowercaseHex(nonce))
 */
final class IpcChallenge
{
    public const CONTEXT = 'pickaxe-ipc-v1:';

    public const NONCE_LENGTH = 64;

    public const PROOF_LENGTH = 64;

    public static function createProof(string $token, string $nonce): string
    {
        self::assertToken($token);
        self::assertCanonicalHex($nonce, self::NONCE_LENGTH, 'nonce');

        $proof = hash_hmac('sha256', self::CONTEXT . $nonce, $token);

        if ($proof === false) {
            throw new IpcProtocolException('IPC HMAC kaniti hesaplanamadi.');
        }

        return $proof;
    }

    public static function verifyProof(string $token, string $nonce, string $proof): bool
    {
        self::assertToken($token);
        self::assertCanonicalHex($nonce, self::NONCE_LENGTH, 'nonce');
        self::assertCanonicalHex($proof, self::PROOF_LENGTH, 'proof');

        return hash_equals(self::createProof($token, $nonce), $proof);
    }

    private static function assertToken(string $token): void
    {
        if ($token === '') {
            throw new \InvalidArgumentException('IPC tokeni bos olamaz.');
        }
    }

    private static function assertCanonicalHex(string $value, int $length, string $label): void
    {
        if (preg_match('/\A[0-9a-f]{' . $length . '}\z/', $value) !== 1) {
            throw new IpcProtocolException("Gecersiz IPC {$label} bicimi.");
        }
    }
}
