<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\support;

/**
 * Testler icin elle uretilmis PNG dosyalari.
 *
 * Gercek istemci derisi bir PNG'dir; sunucu tarafinda GD/Imagick olmadan
 * cozulmesi gerektigi icin `PngDecoder` testleri bu yardimciyla **bayt bayt**
 * bilinen PNG'ler uretir. Her parca (IHDR, IDAT, IEND) ve CRC dogru yazilir;
 * boylece "gercek bir PNG" cozuldugu kanitlanir.
 */
final class PngFixture
{
    public const SIGNATURE = "\x89PNG\r\n\x1a\n";

    /**
     * Ham piksel verisinden PNG uretir.
     *
     * @param list<list<list<int>>> $rows Her satir icin piksel listesi; her piksel kanal listesi
     * @param int                   $colorType 6 = RGBA, 2 = RGB, 0 = gri, 4 = gri+alpha
     * @param int                   $filter    Tum satirlarda kullanilacak filtre (0..4)
     */
    public static function encode(array $rows, int $colorType = 6, int $filter = 0): string
    {
        $height = count($rows);
        $width = $height > 0 ? count($rows[0]) : 0;

        $channels = match ($colorType) {
            6 => 4,
            2 => 3,
            4 => 2,
            0 => 1,
            default => throw new \InvalidArgumentException('desteklenmeyen renk tipi'),
        };

        $raw = '';

        foreach ($rows as $y => $row) {
            foreach ($row as $pixel) {
                if (count($pixel) !== $channels) {
                    throw new \InvalidArgumentException("satir {$y}: kanal sayisi uyusmuyor");
                }
            }

            $raw .= chr($filter) . self::filteredRow($row, $channels, $filter, $y === 0 ? null : $rows[$y - 1]);
        }

        return self::SIGNATURE
            . self::chunk('IHDR', pack('NNCCCCC', $width, $height, 8, $colorType, 0, 0, 0))
            . self::chunk('IDAT', (string) gzcompress($raw, 9))
            . self::chunk('IEND', '');
    }

    /**
     * Verilen chunk'i (uzunluk + tip + veri + CRC) uretir.
     */
    public static function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data))
            . $type
            . $data
            . pack('N', crc32($type . $data));
    }

    /**
     * Tek bir satiri secilen filtreyle kodlar (dekoderin tersi).
     *
     * @param list<list<int>>      $row
     * @param list<list<int>>|null $previous
     */
    private static function filteredRow(array $row, int $channels, int $filter, ?array $previous): string
    {
        $bytes = '';

        foreach ($row as $x => $pixel) {
            foreach ($pixel as $channel => $value) {
                $i = $x * $channels + $channel;
                $left = $x > 0 ? $row[$x - 1][$channel] : 0;
                $up = $previous !== null ? $previous[$x][$channel] : 0;
                $upLeft = ($previous !== null && $x > 0) ? $previous[$x - 1][$channel] : 0;

                $encoded = match ($filter) {
                    0 => $value,
                    1 => $value - $left,
                    2 => $value - $up,
                    3 => $value - intdiv($left + $up, 2),
                    4 => $value - self::paeth($left, $up, $upLeft),
                    default => throw new \InvalidArgumentException("desteklenmeyen filtre: {$filter}"),
                };

                $bytes .= chr((($encoded % 256) + 256) % 256);
                unset($i);
            }
        }

        return $bytes;
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

    /**
     * Rastgele RGBA piksellerden olusan satirlar.
     *
     * @return list<list<list<int>>>
     */
    public static function randomRgbaRows(int $width, int $height, int $seed = 1): array
    {
        mt_srand($seed);
        $rows = [];

        for ($y = 0; $y < $height; $y++) {
            $row = [];

            for ($x = 0; $x < $width; $x++) {
                $row[] = [mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)];
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * RGBA satirlarini ham bayt dizisine cevirir (beklenen cikti).
     *
     * @param list<list<list<int>>> $rows
     */
    public static function toRgba(array $rows): string
    {
        $bytes = '';

        foreach ($rows as $row) {
            foreach ($row as $pixel) {
                foreach ($pixel as $channel) {
                    $bytes .= chr($channel);
                }
            }
        }

        return $bytes;
    }
}
