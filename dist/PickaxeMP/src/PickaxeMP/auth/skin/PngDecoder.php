<?php

declare(strict_types=1);

namespace PickaxeMP\auth\skin;

/**
 * Saf PHP PNG çözücü (GD/Imagick zorunlu değil).
 *
 * ## Neden kendi çözücümüz?
 *
 * Bedrock istemcisi deriyi `extraData` JWT'sinde **base64 PNG** olarak
 * gönderir; `SerializedSkin` ise ham RGBA bekler. Sunucunun çalışması için
 * GD/Imagick eklentisi zorunlu tutulamaz (paylaşımlı hosting, minimal imaj).
 * Bu yüzden yalnızca **ihtiyaç duyulan alt küme** çözülür:
 *
 * - 8 bit kanal derinliği (16 bit reddedilir),
 * - renk tipleri: gri (0), RGB (2), gri+alfa (4), RGBA (6),
 * - paletli (3) görüntüler reddedilir — palet tablosu ayrı bir bağımlılık,
 * - interlaced (Adam7) reddedilir; Bedrock derileri interlaced gelmez,
 * - PNG filtreleri 0..4 (None/Sub/Up/Average/Paeth) tam desteklenir.
 *
 * ## Güvenlik sınırları
 *
 * Ağdan gelen veridir: her chunk uzunluğu ve CRC'si doğrulanır, IDAT
 * şişmesi beklenen ham uzunlukla karşılaştırılır ve görüntü boyutu
 * `MAX_DIMENSION` ile sınırlanır. Hiçbir aşamada `unpack`/`substr` sınır
 * dışına taşmaz; hata daima `SkinException`'dır.
 *
 * ## Neden CRC önemli?
 *
 * Bozuk bir deri, istemcide "görünmez oyuncu" ya da bağlantı kopması
 * üretebilir. CRC tutmuyorsa veri güvenilmez; sessizce çözmek yerine
 * reddedilir ve oyuncuya varsayılan deri uygulanır (vanilla davranışı).
 */
final class PngDecoder
{
    public const SIGNATURE = "\x89PNG\r\n\x1a\n";

    public const MAX_DIMENSION = 4096;

    public const MAX_BYTES = 64 * 1024 * 1024;

    private const COLOR_CHANNELS = [
        0 => 1, // gri
        2 => 3, // RGB
        4 => 2, // gri + alfa
        6 => 4, // RGBA
    ];

    /**
     * PNG baytlarını ham RGBA'ya çözer.
     *
     * @throws SkinException imza, chunk, CRC, boyut veya zlib hatasında
     */
    public static function decode(string $png): PngImage
    {
        $length = strlen($png);

        if ($length < strlen(self::SIGNATURE) + 12) {
            throw SkinException::invalidPng('dosya cok kisa');
        }

        if ($length > self::MAX_BYTES) {
            throw SkinException::invalidPng('dosya sinirdan buyuk');
        }

        if (substr($png, 0, 8) !== self::SIGNATURE) {
            throw SkinException::invalidPng('imza uyusmuyor');
        }

        $offset = 8;
        $width = 0;
        $height = 0;
        $colorType = -1;
        $idat = '';
        $sawHeader = false;
        $sawEnd = false;

        while ($offset < $length) {
            if ($offset + 12 > $length) {
                throw SkinException::invalidPng('chunk basligi kesik');
            }

            $chunkLength = unpack('N', substr($png, $offset, 4))[1];

            if ($chunkLength < 0 || $chunkLength > $length - $offset - 12) {
                throw SkinException::invalidPng('chunk uzunlugu gecersiz');
            }

            $type = substr($png, $offset + 4, 4);
            $data = substr($png, $offset + 8, $chunkLength);
            $crcBytes = substr($png, $offset + 8 + $chunkLength, 4);
            $expected = unpack('N', $crcBytes)[1];
            $actual = crc32($type . $data);

            if (($actual & 0xFFFFFFFF) !== ($expected & 0xFFFFFFFF)) {
                throw SkinException::invalidPng("CRC uyusmuyor: {$type}");
            }

            $offset += 12 + $chunkLength;

            if ($type === 'IHDR') {
                if ($sawHeader) {
                    throw SkinException::invalidPng('ikinci IHDR');
                }

                if ($chunkLength !== 13) {
                    throw SkinException::invalidPng('IHDR uzunlugu 13 olmali');
                }

                $header = unpack('Nwidth/Nheight/Cdepth/Ctype/Ccompression/Cfilter/Cinterlace', $data);
                $width = (int) $header['width'];
                $height = (int) $header['height'];
                $colorType = (int) $header['type'];
                $sawHeader = true;

                if ($width < 1 || $height < 1) {
                    throw SkinException::invalidPng('boyut sifir olamaz');
                }

                if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
                    throw SkinException::invalidPng('boyut sinirdan buyuk');
                }

                if ((int) $header['depth'] !== 8) {
                    throw SkinException::invalidPng('yalnizca 8 bit desteklenir');
                }

                if (!isset(self::COLOR_CHANNELS[$colorType])) {
                    throw SkinException::invalidPng('desteklenmeyen renk tipi');
                }

                if ((int) $header['compression'] !== 0) {
                    throw SkinException::invalidPng('desteklenmeyen sikistirma');
                }

                if ((int) $header['filter'] !== 0) {
                    throw SkinException::invalidPng('desteklenmeyen filtre yontemi');
                }

                if ((int) $header['interlace'] !== 0) {
                    throw SkinException::invalidPng('interlaced goruntu desteklenmez');
                }

                continue;
            }

            if ($type === 'IDAT') {
                if (!$sawHeader) {
                    throw SkinException::invalidPng('IDAT IHDR oncesi');
                }

                $idat .= $data;

                continue;
            }

            if ($type === 'IEND') {
                if (!$sawHeader) {
                    throw SkinException::invalidPng('IEND IHDR oncesi');
                }

                $sawEnd = true;

                break;
            }

            // Bilinmeyen kritik chunk'lar (ilk harf buyuk) atlanamaz. Yardimci
            // (ilk harf kucuk) chunk'lar guvenle yok sayilir.
            if ($type !== '' && ctype_upper($type[0])) {
                throw SkinException::invalidPng("desteklenmeyen kritik chunk: {$type}");
            }
        }

        if (!$sawHeader) {
            throw SkinException::invalidPng('IHDR yok');
        }

        if (!$sawEnd) {
            throw SkinException::invalidPng('IEND yok');
        }

        if ($idat === '') {
            throw SkinException::invalidPng('IDAT yok');
        }

        $channels = self::COLOR_CHANNELS[$colorType];
        $stride = $width * $channels;

        if ($stride * $height > self::MAX_BYTES) {
            throw SkinException::invalidPng('piksel verisi sinirdan buyuk');
        }

        $raw = @gzuncompress($idat);

        if ($raw === false) {
            throw SkinException::invalidPng('zlib akisi bozuk');
        }

        $expectedLength = $height * ($stride + 1);

        if (strlen($raw) !== $expectedLength) {
            throw SkinException::invalidPng('ham veri uzunlugu uyusmuyor');
        }

        $rgba = self::reconstruct($raw, $width, $height, $channels, $colorType);

        return new PngImage($width, $height, $rgba);
    }

    /**
     * Filtrelenmiş satırları çözer ve RGBA baytlarına dönüştürür.
     */
    private static function reconstruct(
        string $raw,
        int $width,
        int $height,
        int $channels,
        int $colorType
    ): string {
        $stride = $width * $channels;
        $previous = array_fill(0, $stride, 0);
        $rgba = '';

        for ($y = 0; $y < $height; $y++) {
            $rowStart = $y * ($stride + 1);
            $filter = ord($raw[$rowStart]);
            $line = substr($raw, $rowStart + 1, $stride);
            $current = [];

            for ($i = 0; $i < $stride; $i++) {
                $value = ord($line[$i]);
                $left = $i >= $channels ? $current[$i - $channels] : 0;
                $up = $previous[$i];

                $current[$i] = match ($filter) {
                    0 => $value,
                    1 => ($value + $left) & 0xFF,
                    2 => ($value + $up) & 0xFF,
                    3 => ($value + intdiv($left + $up, 2)) & 0xFF,
                    4 => ($value + self::paeth($left, $up, $i >= $channels ? $previous[$i - $channels] : 0)) & 0xFF,
                    default => throw SkinException::invalidPng("gecersiz filtre: {$filter}"),
                };
            }

            $rgba .= self::toRgba($current, $width, $channels, $colorType);
            $previous = $current;
        }

        return $rgba;
    }

    /**
     * @param list<int> $row
     */
    private static function toRgba(array $row, int $width, int $channels, int $colorType): string
    {
        $out = '';

        for ($x = 0; $x < $width; $x++) {
            $base = $x * $channels;

            switch ($colorType) {
                case 6:
                    $out .= chr($row[$base]) . chr($row[$base + 1])
                        . chr($row[$base + 2]) . chr($row[$base + 3]);

                    break;
                case 2:
                    $out .= chr($row[$base]) . chr($row[$base + 1])
                        . chr($row[$base + 2]) . "\xFF";

                    break;
                case 4:
                    $gray = $row[$base];
                    $out .= chr($gray) . chr($gray) . chr($gray) . chr($row[$base + 1]);

                    break;
                case 0:
                default:
                    $gray = $row[$base];
                    $out .= chr($gray) . chr($gray) . chr($gray) . "\xFF";

                    break;
            }
        }

        return $out;
    }

    private static function paeth(int $a, int $b, int $c): int
    {
        $p = $a + $b - $c;
        $pa = abs($p - $a);
        $pb = abs($p - $b);
        $pc = abs($p - $c);

        if ($pa <= $pb && $pa <= $pc) {
            return $a;
        }

        return $pb <= $pc ? $b : $c;
    }
}
