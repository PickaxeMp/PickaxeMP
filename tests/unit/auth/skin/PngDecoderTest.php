<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\auth\skin;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PickaxeMP\auth\skin\PngDecoder;
use PickaxeMP\auth\skin\SkinException;
use PickaxeMP\Tests\support\PngFixture;

/**
 * PNG cozucu testleri.
 *
 * ## Neden kendi cozucumuz?
 *
 * Istemci derisi `extraData` JWT'sinde **base64 PNG** olarak gelir; tel
 * bicimi ise ham RGBA bekler (`genislik * yukseklik * 4`). GD/Imagick
 * eklentisi zorunlu bagimlilik olmadigi icin cozucu saf PHP'dir.
 *
 * Testler PNG'yi **bayt bayt** uretir: her filtre turu ve renk tipi icin
 * beklenen RGBA ciktisi karsilastirilir. Boylece "kendi urettigimiz veriyi
 * kendi kodumuzla dogrulama" tuzagina dusmeyiz; uretici (fixture) ile cozucu
 * ayri algoritmalardir.
 */
final class PngDecoderTest extends TestCase
{
    public function testDecodesRgbaImage(): void
    {
        $rows = [
            [[255, 0, 0, 255], [0, 255, 0, 128]],
            [[0, 0, 255, 64], [17, 34, 51, 68]],
        ];

        $decoded = PngDecoder::decode(PngFixture::encode($rows));

        self::assertSame(2, $decoded->width);
        self::assertSame(2, $decoded->height);
        self::assertSame(PngFixture::toRgba($rows), $decoded->rgba);
    }

    public function testDecodesRgbImageFillingOpaqueAlpha(): void
    {
        $rows = [[[10, 20, 30], [40, 50, 60]]];

        $decoded = PngDecoder::decode(PngFixture::encode($rows, colorType: 2));

        self::assertSame(2, $decoded->width);
        self::assertSame(1, $decoded->height);
        self::assertSame(
            chr(10) . chr(20) . chr(30) . chr(255) . chr(40) . chr(50) . chr(60) . chr(255),
            $decoded->rgba
        );
    }

    public function testDecodesGrayscaleImage(): void
    {
        $rows = [[[0], [128], [255]]];

        $decoded = PngDecoder::decode(PngFixture::encode($rows, colorType: 0));

        self::assertSame(
            chr(0) . chr(0) . chr(0) . chr(255)
            . chr(128) . chr(128) . chr(128) . chr(255)
            . chr(255) . chr(255) . chr(255) . chr(255),
            $decoded->rgba
        );
    }

    public function testDecodesGrayscaleWithAlpha(): void
    {
        $rows = [[[60, 200], [90, 10]]];

        $decoded = PngDecoder::decode(PngFixture::encode($rows, colorType: 4));

        self::assertSame(
            chr(60) . chr(60) . chr(60) . chr(200)
            . chr(90) . chr(90) . chr(90) . chr(10),
            $decoded->rgba
        );
    }

    #[DataProvider('filterProvider')]
    public function testDecodesEveryFilterType(int $filter): void
    {
        $rows = PngFixture::randomRgbaRows(4, 3, seed: 7 + $filter);

        $decoded = PngDecoder::decode(PngFixture::encode($rows, filter: $filter));

        self::assertSame(4, $decoded->width);
        self::assertSame(3, $decoded->height);
        self::assertSame(PngFixture::toRgba($rows), $decoded->rgba, "filtre {$filter}");
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function filterProvider(): array
    {
        return [
            'none' => [0],
            'sub' => [1],
            'up' => [2],
            'average' => [3],
            'paeth' => [4],
        ];
    }

    public function testRejectsWrongSignature(): void
    {
        $png = PngFixture::encode([[[1, 2, 3, 4]]]);
        $png[1] = 'X';

        $this->expectException(SkinException::class);
        PngDecoder::decode($png);
    }

    public function testRejectsEmptyString(): void
    {
        $this->expectException(SkinException::class);
        PngDecoder::decode('');
    }

    public function testRejectsBadCrc(): void
    {
        $png = PngFixture::encode([[[1, 2, 3, 4]]]);
        // Son 4 bayt IEND'in CRC'sidir; bozulursa dosya guvenilmez.
        $png = substr($png, 0, -4) . "\x00\x00\x00\x00";

        $this->expectException(SkinException::class);
        PngDecoder::decode($png);
    }

    public function testRejectsMissingIend(): void
    {
        $png = PngFixture::SIGNATURE
            . PngFixture::chunk('IHDR', pack('NNCCCCC', 1, 1, 8, 6, 0, 0, 0))
            . PngFixture::chunk('IDAT', (string) gzcompress("\x00" . chr(1) . chr(2) . chr(3) . chr(4)));

        $this->expectException(SkinException::class);
        PngDecoder::decode($png);
    }

    public function testRejectsTruncatedChunk(): void
    {
        $png = PngFixture::encode([[[1, 2, 3, 4]]]);

        $this->expectException(SkinException::class);
        PngDecoder::decode(substr($png, 0, 20));
    }

    public function testRejectsInterlacedImage(): void
    {
        $png = PngFixture::SIGNATURE
            . PngFixture::chunk('IHDR', pack('NNCCCCC', 1, 1, 8, 6, 0, 0, 1))
            . PngFixture::chunk('IDAT', (string) gzcompress("\x00" . chr(1) . chr(2) . chr(3) . chr(4)))
            . PngFixture::chunk('IEND', '');

        $this->expectException(SkinException::class);
        PngDecoder::decode($png);
    }

    public function testRejects16BitDepth(): void
    {
        $png = PngFixture::SIGNATURE
            . PngFixture::chunk('IHDR', pack('NNCCCCC', 1, 1, 16, 6, 0, 0, 0))
            . PngFixture::chunk('IDAT', (string) gzcompress("\x00" . str_repeat("\x00", 8)))
            . PngFixture::chunk('IEND', '');

        $this->expectException(SkinException::class);
        PngDecoder::decode($png);
    }

    public function testRejectsPaletteImage(): void
    {
        $png = PngFixture::SIGNATURE
            . PngFixture::chunk('IHDR', pack('NNCCCCC', 1, 1, 8, 3, 0, 0, 0))
            . PngFixture::chunk('PLTE', chr(0) . chr(0) . chr(0))
            . PngFixture::chunk('IDAT', (string) gzcompress("\x00\x00"))
            . PngFixture::chunk('IEND', '');

        $this->expectException(SkinException::class);
        PngDecoder::decode($png);
    }

    public function testRejectsInvalidFilterByte(): void
    {
        $png = PngFixture::SIGNATURE
            . PngFixture::chunk('IHDR', pack('NNCCCCC', 1, 1, 8, 6, 0, 0, 0))
            . PngFixture::chunk('IDAT', (string) gzcompress("\x09" . chr(1) . chr(2) . chr(3) . chr(4)))
            . PngFixture::chunk('IEND', '');

        $this->expectException(SkinException::class);
        PngDecoder::decode($png);
    }

    public function testRejectsCorruptZlibStream(): void
    {
        $png = PngFixture::SIGNATURE
            . PngFixture::chunk('IHDR', pack('NNCCCCC', 1, 1, 8, 6, 0, 0, 0))
            . PngFixture::chunk('IDAT', 'not-a-zlib-stream')
            . PngFixture::chunk('IEND', '');

        $this->expectException(SkinException::class);
        PngDecoder::decode($png);
    }

    public function testRejectsZeroDimensions(): void
    {
        $png = PngFixture::SIGNATURE
            . PngFixture::chunk('IHDR', pack('NNCCCCC', 0, 0, 8, 6, 0, 0, 0))
            . PngFixture::chunk('IDAT', (string) gzcompress(''))
            . PngFixture::chunk('IEND', '');

        $this->expectException(SkinException::class);
        PngDecoder::decode($png);
    }

    public function testRejectsTooManyPixels(): void
    {
        // 5000x5000 sinirin (4096) uzerinde; bellek sisirmesini engeller.
        $png = PngFixture::SIGNATURE
            . PngFixture::chunk('IHDR', pack('NNCCCCC', 5000, 5000, 8, 6, 0, 0, 0))
            . PngFixture::chunk('IDAT', (string) gzcompress(''))
            . PngFixture::chunk('IEND', '');

        $this->expectException(SkinException::class);
        PngDecoder::decode($png);
    }

    public function testAcceptsLargeSkinImage(): void
    {
        $rows = PngFixture::randomRgbaRows(64, 64, seed: 3);

        $decoded = PngDecoder::decode(PngFixture::encode($rows));

        self::assertSame(64, $decoded->width);
        self::assertSame(64, $decoded->height);
        self::assertSame(64 * 64 * 4, strlen($decoded->rgba));
        self::assertSame(PngFixture::toRgba($rows), $decoded->rgba);
    }
}
