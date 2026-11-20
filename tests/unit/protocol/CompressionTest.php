<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\protocol;

use PHPUnit\Framework\TestCase;
use PickaxeMP\protocol\compression\CompressionAlgorithm;
use PickaxeMP\protocol\compression\CompressionManager;
use PickaxeMP\protocol\compression\CompressionSettings;
use PickaxeMP\protocol\compression\ZlibCompressor;
use PickaxeMP\protocol\ProtocolException;

final class CompressionTest extends TestCase
{
    public function testThresholdZeroDisablesCompression(): void
    {
        $settings = new CompressionSettings(CompressionAlgorithm::Zlib, 0);

        self::assertFalse($settings->isEnabled());
        self::assertFalse($settings->shouldCompress(10000));
    }

    public function testThresholdOneCompressesEverything(): void
    {
        $settings = new CompressionSettings(CompressionAlgorithm::Zlib, 1);

        self::assertTrue($settings->isEnabled());
        self::assertTrue($settings->shouldCompress(1));
        self::assertTrue($settings->shouldCompress(10000));
    }

    public function testThresholdIsInclusive(): void
    {
        $settings = new CompressionSettings(CompressionAlgorithm::Zlib, 256);

        self::assertFalse($settings->shouldCompress(255));
        self::assertTrue($settings->shouldCompress(256));
        self::assertTrue($settings->shouldCompress(257));
    }

    public function testNoneAlgorithmDisablesCompression(): void
    {
        $settings = new CompressionSettings(CompressionAlgorithm::None, 1);

        self::assertFalse($settings->isEnabled());
        self::assertFalse($settings->shouldCompress(1000));
    }

    public function testRejectsOutOfRangeThreshold(): void
    {
        $this->expectException(ProtocolException::class);
        new CompressionSettings(CompressionAlgorithm::Zlib, 70000);
    }

    public function testRejectsNegativeThreshold(): void
    {
        $this->expectException(ProtocolException::class);
        new CompressionSettings(CompressionAlgorithm::Zlib, -1);
    }

    public function testZlibRoundTrip(): void
    {
        $compressor = new ZlibCompressor();
        $payload = str_repeat('PickaxeMP ', 500);

        $compressed = $compressor->compress($payload);

        self::assertLessThan(strlen($payload), strlen($compressed), 'Sikistirma kucultmeli');
        self::assertSame($payload, $compressor->decompress($compressed));
    }

    public function testZlibEmptyPayloadRoundTrips(): void
    {
        $compressor = new ZlibCompressor();

        self::assertSame('', $compressor->compress(''));
        self::assertSame('', $compressor->decompress(''));
    }

    public function testZlibProducesRawDeflateWithoutZlibHeaderOrTrailer(): void
    {
        $compressor = new ZlibCompressor();
        $compressed = $compressor->compress('test');

        // Bedrock'un 0x00 "ZLib" kimliği RFC 1950 zlib sarmalını değil,
        // header/trailer içermeyen raw DEFLATE akışını kullanır.
        self::assertNotSame(0x78, ord($compressed[0]));
        self::assertSame('test', gzinflate($compressed));
        self::assertFalse(@gzuncompress($compressed));
    }

    public function testZlibRejectsCorruptData(): void
    {
        $this->expectException(ProtocolException::class);
        (new ZlibCompressor())->decompress("\xFF\xFF\xFF\xFF");
    }

    public function testZlibRejectsTooShortData(): void
    {
        $this->expectException(ProtocolException::class);
        (new ZlibCompressor())->decompress("\x78");
    }

    public function testZlibRejectsDecompressionBomb(): void
    {
        $compressor = new ZlibCompressor(1024);
        $bomb = $compressor->compress(str_repeat('A', 100000));

        $this->expectException(ProtocolException::class);
        $compressor->decompress($bomb);
    }

    public function testManagerSkipsCompressionBelowThreshold(): void
    {
        $manager = new CompressionManager(new CompressionSettings(CompressionAlgorithm::Zlib, 1000));

        self::assertSame('kisa', $manager->compress('kisa'));
    }

    public function testManagerCompressesAboveThreshold(): void
    {
        $manager = new CompressionManager(new CompressionSettings(CompressionAlgorithm::Zlib, 10));
        $payload = str_repeat('x', 500);

        $compressed = $manager->compress($payload);

        self::assertNotSame($payload, $compressed);
        self::assertSame($payload, $manager->decompress($compressed));
    }

    public function testManagerWithCompressionDisabledPassesThrough(): void
    {
        $manager = new CompressionManager(CompressionSettings::disabled());
        $payload = str_repeat('y', 5000);

        self::assertSame($payload, $manager->compress($payload));
        self::assertSame($payload, $manager->decompress($payload));
    }

    public function testManagerRejectsSnappyWhenUnavailable(): void
    {
        $manager = new CompressionManager(new CompressionSettings(CompressionAlgorithm::Snappy, 1));

        $this->expectException(ProtocolException::class);
        $manager->compress('veri');
    }

    public function testNegotiateFallsBackToZlibForUnsupportedAlgorithm(): void
    {
        $manager = new CompressionManager();

        self::assertSame(
            CompressionAlgorithm::Zlib,
            $manager->negotiate(CompressionAlgorithm::Snappy)
        );
    }

    public function testNegotiateKeepsSupportedAlgorithm(): void
    {
        $manager = new CompressionManager();

        self::assertSame(
            CompressionAlgorithm::Zlib,
            $manager->negotiate(CompressionAlgorithm::Zlib)
        );
    }

    public function testSettingsCanBeReplaced(): void
    {
        $manager = new CompressionManager();

        self::assertTrue($manager->getSettings()->isEnabled());

        $manager->setSettings(CompressionSettings::disabled());

        self::assertFalse($manager->getSettings()->isEnabled());
        self::assertFalse($manager->shouldCompress(99999));
    }
}
