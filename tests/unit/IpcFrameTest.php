<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit;

use PHPUnit\Framework\TestCase;
use PickaxeMP\transport\sidecar\IpcFrame;
use PickaxeMP\transport\sidecar\IpcProtocolException;

final class IpcFrameTest extends TestCase
{
    public function testRoundTrip(): void
    {
        $frame = IpcFrame::of(3, 42, 'merhaba');
        $buffer = $frame->encode();

        $decoded = IpcFrame::decode($buffer);

        self::assertNotNull($decoded);
        self::assertSame(3, $decoded->type);
        self::assertSame(42, $decoded->requestId);
        self::assertSame('merhaba', $decoded->payload);
        self::assertSame('', $buffer, 'Tum tampon tuketilmeliydi.');
    }

    public function testEmptyPayload(): void
    {
        $buffer = IpcFrame::of(1, 0)->encode();

        $decoded = IpcFrame::decode($buffer);

        self::assertNotNull($decoded);
        self::assertSame('', $decoded->payload);
    }

    public function testHeaderSizeIsStable(): void
    {
        self::assertSame(16, IpcFrame::HEADER_SIZE);
        self::assertSame(16, strlen(IpcFrame::of(0, 0)->encode()));
    }

    public function testPartialHeaderReturnsNull(): void
    {
        $buffer = substr(IpcFrame::of(1, 1, 'x')->encode(), 0, 10);
        $original = $buffer;

        self::assertNull(IpcFrame::decode($buffer));
        self::assertSame($original, $buffer, 'Kismi okumada tampon degismemeliydi.');
    }

    public function testPartialPayloadReturnsNullThenSucceeds(): void
    {
        $full = IpcFrame::of(2, 7, 'abcdef')->encode();
        $buffer = substr($full, 0, 18);

        self::assertNull(IpcFrame::decode($buffer));

        $buffer .= substr($full, 18);

        $decoded = IpcFrame::decode($buffer);
        self::assertNotNull($decoded);
        self::assertSame('abcdef', $decoded->payload);
    }

    public function testTwoFramesInOneBuffer(): void
    {
        $buffer = IpcFrame::of(1, 1, 'bir')->encode() . IpcFrame::of(2, 2, 'iki')->encode();

        $first = IpcFrame::decode($buffer);
        $second = IpcFrame::decode($buffer);

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertSame('bir', $first->payload);
        self::assertSame('iki', $second->payload);
        self::assertSame('', $buffer);
    }

    public function testBadMagicIsRejected(): void
    {
        $buffer = 'XXXX' . substr(IpcFrame::of(1, 1, 'x')->encode(), 4);

        $this->expectException(IpcProtocolException::class);
        IpcFrame::decode($buffer);
    }

    public function testUnsupportedVersionIsRejected(): void
    {
        $encoded = IpcFrame::of(1, 1, 'x')->encode();
        $encoded[2] = chr(99);

        $this->expectException(IpcProtocolException::class);
        IpcFrame::decode($encoded);
    }

    public function testOversizedLengthIsRejected(): void
    {
        $encoded = IpcFrame::of(1, 1, 'x')->encode();
        $overstated = substr_replace($encoded, pack('N', IpcFrame::MAX_PAYLOAD + 1), 12, 4);

        $this->expectException(IpcProtocolException::class);
        IpcFrame::decode($overstated);
    }

    public function testRejectsInvalidType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        IpcFrame::of(256, 1);
    }

    public function testRejectsNegativeRequestId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        IpcFrame::of(1, -1);
    }

    public function testRejectsOversizedPayloadOnConstruction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        IpcFrame::of(1, 1, str_repeat('x', IpcFrame::MAX_PAYLOAD + 1));
    }

    public function testBinaryPayloadSurvives(): void
    {
        $payload = random_bytes(64) . "\x00\xff";
        $buffer = IpcFrame::of(9, 999, $payload)->encode();

        $decoded = IpcFrame::decode($buffer);

        self::assertNotNull($decoded);
        self::assertSame($payload, $decoded->payload);
    }
}
