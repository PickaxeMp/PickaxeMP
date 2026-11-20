<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\protocol;

use PHPUnit\Framework\TestCase;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\compression\CompressionAlgorithm;
use PickaxeMP\protocol\compression\CompressionManager;
use PickaxeMP\protocol\compression\CompressionSettings;
use PickaxeMP\protocol\PacketBatch;
use PickaxeMP\protocol\ProtocolException;

final class PacketBatchTest extends TestCase
{
    public function testNethernetBatchPrefixesEveryPacketWithVaruintLength(): void
    {
        $batch = new PacketBatch(new CompressionManager(CompressionSettings::disabled()));

        $encoded = $batch->encode(["\x01\x02", "\x03"]);

        self::assertSame("\x02\x01\x02\x01\x03", $encoded);
        self::assertSame(["\x01\x02", "\x03"], $batch->decode($encoded));
    }

    public function testRaknetAddsGamePacketMarker(): void
    {
        $batch = new PacketBatch(
            new CompressionManager(CompressionSettings::disabled()),
            PacketBatch::TRANSPORT_RAKNET
        );

        $encoded = $batch->encode(['abc']);

        self::assertSame(0xFE, ord($encoded[0]));
        self::assertSame("\x03abc", substr($encoded, 1));
        self::assertSame(['abc'], $batch->decode($encoded));
    }

    public function testRaknetRejectsBadMarker(): void
    {
        $batch = new PacketBatch(
            new CompressionManager(CompressionSettings::disabled()),
            PacketBatch::TRANSPORT_RAKNET
        );

        $this->expectException(ProtocolException::class);
        $batch->decode("\xfd\x01a");
    }

    public function testPreNegotiationBatchHasNoCompressionIdentifier(): void
    {
        $manager = new CompressionManager(new CompressionSettings(CompressionAlgorithm::Zlib, 1));
        $batch = new PacketBatch($manager, PacketBatch::TRANSPORT_NETHERNET, false);

        $encoded = $batch->encode([str_repeat('a', 300)]);

        self::assertNotSame(PacketBatch::COMPRESSION_ZLIB, ord($encoded[0]));
        self::assertCount(1, $batch->decode($encoded));
    }

    public function testCompressionFramingUsesRawDeflateIdentifier(): void
    {
        $manager = new CompressionManager(new CompressionSettings(CompressionAlgorithm::Zlib, 1));
        $batch = new PacketBatch($manager, PacketBatch::TRANSPORT_NETHERNET, true);
        $packet = str_repeat('a', 300);

        $encoded = $batch->encode([$packet]);

        self::assertSame(PacketBatch::COMPRESSION_ZLIB, ord($encoded[0]));

        $writer = new BinaryWriter();
        $writer->writeVarUInt(strlen($packet));
        $writer->write($packet);
        self::assertSame($writer->bytes(), gzinflate(substr($encoded, 1)));
        self::assertSame([$packet], $batch->decode($encoded));
    }

    public function testCompressionFramingUsesUncompressedIdentifierBelowThreshold(): void
    {
        $manager = new CompressionManager(new CompressionSettings(CompressionAlgorithm::Zlib, 1000));
        $batch = new PacketBatch($manager, PacketBatch::TRANSPORT_NETHERNET, true);

        $encoded = $batch->encode(['small']);

        self::assertSame(PacketBatch::COMPRESSION_NONE, ord($encoded[0]));
        self::assertSame("\x05small", substr($encoded, 1));
        self::assertSame(['small'], $batch->decode($encoded));
    }

    public function testNoneAlgorithmUsesUncompressedIdentifier(): void
    {
        $batch = new PacketBatch(
            new CompressionManager(CompressionSettings::disabled()),
            PacketBatch::TRANSPORT_NETHERNET,
            true
        );

        $encoded = $batch->encode(['data']);

        self::assertSame(PacketBatch::COMPRESSION_NONE, ord($encoded[0]));
        self::assertSame(['data'], $batch->decode($encoded));
    }

    public function testDecodeRejectsUnknownCompressionIdentifier(): void
    {
        $batch = new PacketBatch(
            new CompressionManager(CompressionSettings::disabled()),
            PacketBatch::TRANSPORT_NETHERNET,
            true
        );

        $this->expectException(ProtocolException::class);
        $batch->decode("\x02\x01a");
    }

    public function testDecodeRejectsSnappyCompressionIdentifier(): void
    {
        $batch = new PacketBatch(
            new CompressionManager(CompressionSettings::disabled()),
            PacketBatch::TRANSPORT_NETHERNET,
            true
        );

        $this->expectException(ProtocolException::class);
        $batch->decode("\x01\x01a");
    }

    public function testDecodeRejectsZeroLengthPacket(): void
    {
        $batch = new PacketBatch(new CompressionManager(CompressionSettings::disabled()));

        $this->expectException(ProtocolException::class);
        $batch->decode("\x00");
    }

    public function testDecodeRejectsTruncatedPacket(): void
    {
        $batch = new PacketBatch(new CompressionManager(CompressionSettings::disabled()));

        $this->expectException(ProtocolException::class);
        $batch->decode("\x05a");
    }

    public function testDecodeRejectsTooManyPackets(): void
    {
        $batch = new PacketBatch(
            new CompressionManager(CompressionSettings::disabled()),
            PacketBatch::TRANSPORT_NETHERNET,
            false,
            1
        );

        $this->expectException(ProtocolException::class);
        $batch->decode("\x01a\x01b");
    }

    public function testEncodeRejectsEmptyBatch(): void
    {
        $batch = new PacketBatch(new CompressionManager(CompressionSettings::disabled()));

        $this->expectException(ProtocolException::class);
        $batch->encode([]);
    }

    public function testEncodeRejectsEmptyPacket(): void
    {
        $batch = new PacketBatch(new CompressionManager(CompressionSettings::disabled()));

        $this->expectException(ProtocolException::class);
        $batch->encode(['']);
    }

    public function testRejectsUnknownTransportStrategy(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PacketBatch(
            new CompressionManager(CompressionSettings::disabled()),
            'bilinmeyen'
        );
    }
}
