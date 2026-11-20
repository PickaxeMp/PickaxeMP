<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit;

use PHPUnit\Framework\TestCase;
use PickaxeMP\transport\sidecar\IpcChallenge;
use PickaxeMP\transport\sidecar\IpcProtocolException;

final class IpcChallengeTest extends TestCase
{
    private const TOKEN = 'shared-secret';

    private const NONCE = '1111111111111111111111111111111111111111111111111111111111111111';

    public function testProofMatchesVersionedHmacVector(): void
    {
        self::assertSame(
            'b8a957da878e7493fa92a0edcfce62b91e784d83f1601580adad649f26c0dd4b',
            IpcChallenge::createProof(self::TOKEN, self::NONCE)
        );
    }

    public function testCorrectProofIsAccepted(): void
    {
        $proof = IpcChallenge::createProof(self::TOKEN, self::NONCE);

        self::assertTrue(IpcChallenge::verifyProof(self::TOKEN, self::NONCE, $proof));
    }

    public function testWrongProofIsRejected(): void
    {
        $proof = IpcChallenge::createProof(self::TOKEN, self::NONCE);
        $wrong = str_repeat('0', 64);

        self::assertFalse(IpcChallenge::verifyProof(self::TOKEN, self::NONCE, $wrong));
        self::assertFalse(IpcChallenge::verifyProof('wrong-token', self::NONCE, $proof));
    }

    public function testProofCannotBeReplayedForAnotherNonce(): void
    {
        $proof = IpcChallenge::createProof(self::TOKEN, self::NONCE);
        $otherNonce = str_repeat('22', 32);

        self::assertFalse(IpcChallenge::verifyProof(self::TOKEN, $otherNonce, $proof));
    }

    public function testMalformedNonceIsRejected(): void
    {
        $this->expectException(IpcProtocolException::class);
        IpcChallenge::createProof(self::TOKEN, 'not-hex');
    }

    public function testUppercaseNonceIsRejected(): void
    {
        $this->expectException(IpcProtocolException::class);
        IpcChallenge::createProof(self::TOKEN, str_repeat('AB', 32));
    }

    public function testMalformedProofIsRejected(): void
    {
        $this->expectException(IpcProtocolException::class);
        IpcChallenge::verifyProof(self::TOKEN, self::NONCE, 'not-hex');
    }

    public function testEmptyTokenIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        IpcChallenge::createProof('', self::NONCE);
    }
}
