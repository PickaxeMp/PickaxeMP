<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit;

use PHPUnit\Framework\TestCase;
use PickaxeMP\transport\sidecar\IpcProtocolException;
use PickaxeMP\transport\sidecar\SidecarMessage;

final class SidecarMessageTest extends TestCase
{
    public function testPayloadRoundTrip(): void
    {
        $encoded = SidecarMessage::encodePayload(
            SidecarMessage::CHANNEL_RELIABLE,
            'c0a1b2c3',
            "\x01\x02binary\x00data"
        );

        $decoded = SidecarMessage::decodePayload($encoded);

        self::assertSame(SidecarMessage::CHANNEL_RELIABLE, $decoded['channel']);
        self::assertSame('c0a1b2c3', $decoded['connectionId']);
        self::assertSame("\x01\x02binary\x00data", $decoded['data']);
    }

    public function testUnreliableChannelRoundTrip(): void
    {
        $decoded = SidecarMessage::decodePayload(
            SidecarMessage::encodePayload(SidecarMessage::CHANNEL_UNRELIABLE, 'c1', 'x')
        );

        self::assertSame(SidecarMessage::CHANNEL_UNRELIABLE, $decoded['channel']);
    }

    public function testEmptyDataIsAllowed(): void
    {
        $decoded = SidecarMessage::decodePayload(
            SidecarMessage::encodePayload(SidecarMessage::CHANNEL_RELIABLE, 'c1', '')
        );

        self::assertSame('', $decoded['data']);
    }

    public function testInvalidChannelIsRejected(): void
    {
        $this->expectException(IpcProtocolException::class);
        SidecarMessage::encodePayload(7, 'c1', 'x');
    }

    public function testInvalidChannelByteIsRejected(): void
    {
        $this->expectException(IpcProtocolException::class);
        SidecarMessage::decodePayload("\x09\x00\x00");
    }

    public function testShortPayloadBodyIsRejected(): void
    {
        $this->expectException(IpcProtocolException::class);
        SidecarMessage::decodePayload("\x00\x00");
    }

    public function testTruncatedConnectionIdIsRejected(): void
    {
        $this->expectException(IpcProtocolException::class);
        SidecarMessage::decodePayload("\x00\x00\x0a");
    }

    public function testOversizedConnectionIdIsRejected(): void
    {
        $this->expectException(IpcProtocolException::class);
        SidecarMessage::encodePayload(
            SidecarMessage::CHANNEL_RELIABLE,
            str_repeat('a', SidecarMessage::MAX_ID_LEN + 1),
            ''
        );
    }

    public function testCloseRoundTrip(): void
    {
        $decoded = SidecarMessage::decodeClose(
            SidecarMessage::encodeClose('c42', 'zaman asimi')
        );

        self::assertSame('c42', $decoded['connectionId']);
        self::assertSame('zaman asimi', $decoded['reason']);
    }

    public function testCloseAllowsEmptyReason(): void
    {
        $decoded = SidecarMessage::decodeClose(SidecarMessage::encodeClose('c1', ''));

        self::assertSame('', $decoded['reason']);
    }

    public function testCloseRejectsShortBody(): void
    {
        $this->expectException(IpcProtocolException::class);
        SidecarMessage::decodeClose("\x00");
    }

    public function testJsonRoundTrip(): void
    {
        $encoded = SidecarMessage::encodeJson(['token' => 'abc', 'pid' => 42]);
        $decoded = SidecarMessage::decodeJson($encoded);

        self::assertSame('abc', $decoded['token']);
        self::assertSame(42, $decoded['pid']);
    }

    public function testHelloRoundTrip(): void
    {
        $nonce = str_repeat('ab', 32);
        $body = SidecarMessage::encodeJson([
            'nonce' => $nonce,
            'version' => '0.1.0',
            'pid' => 42,
        ]);

        $hello = SidecarMessage::decodeHello($body);

        self::assertSame($nonce, $hello['nonce']);
        self::assertSame('0.1.0', $hello['version']);
        self::assertSame(42, $hello['pid']);
    }

    public function testHelloRejectsTokenDisclosure(): void
    {
        $body = SidecarMessage::encodeJson([
            'nonce' => str_repeat('ab', 32),
            'version' => '0.1.0',
            'pid' => 42,
            'token' => 'must-not-cross-the-ipc-socket',
        ]);

        $this->expectException(IpcProtocolException::class);
        SidecarMessage::decodeHello($body);
    }

    public function testHelloRejectsMalformedNonce(): void
    {
        $body = SidecarMessage::encodeJson([
            'nonce' => 'not-hex',
            'version' => '0.1.0',
            'pid' => 42,
        ]);

        $this->expectException(IpcProtocolException::class);
        SidecarMessage::decodeHello($body);
    }

    public function testHelloAckContainsOnlyProof(): void
    {
        $proof = str_repeat('cd', 32);
        $body = SidecarMessage::encodeHelloAck($proof);
        $decoded = SidecarMessage::decodeJson($body);

        self::assertSame(['proof' => $proof], $decoded);
        self::assertStringNotContainsString('token', $body);
    }

    public function testJsonRejectsGarbage(): void
    {
        $this->expectException(IpcProtocolException::class);
        SidecarMessage::decodeJson('{not json');
    }

    public function testJsonRejectsOversizedBody(): void
    {
        $this->expectException(IpcProtocolException::class);
        SidecarMessage::decodeJson(str_repeat(' ', 70000));
    }

    /**
     * Rust tarafındaki `matches_php_layout` testiyle aynı baytları üretmeli.
     */
    public function testFrameLayoutMatchesRustExpectations(): void
    {
        $frame = new \PickaxeMP\transport\sidecar\IpcFrame(0x10, 0x0102030405060708, 'AB');
        $encoded = $frame->encode();

        self::assertSame("\x50\x4B", substr($encoded, 0, 2));
        self::assertSame(1, ord($encoded[2]));
        self::assertSame(0x10, ord($encoded[3]));
        self::assertSame("\x01\x02\x03\x04\x05\x06\x07\x08", substr($encoded, 4, 8));
        self::assertSame("\x00\x00\x00\x02", substr($encoded, 12, 4));
        self::assertSame('AB', substr($encoded, 16));
    }
}
