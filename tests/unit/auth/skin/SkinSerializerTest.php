<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\auth\skin;

use PHPUnit\Framework\TestCase;
use PickaxeMP\auth\SkinData;
use PickaxeMP\auth\skin\SkinSerializer;
use PickaxeMP\protocol\packet\types\SerializedSkin;
use PickaxeMP\Tests\support\PngFixture;

/**
 * `SkinData` (JWT, base64) → `SerializedSkin` (tel, ham RGBA) donusumu.
 *
 * Testler gercek PNG uretir; "base64'u cozup tekrar base64 yazma" gibi
 * kendini dogrulayan bir tuzaga dusulmez.
 */
final class SkinSerializerTest extends TestCase
{
    public function testConvertsBase64PngToRawRgba(): void
    {
        $rows = PngFixture::randomRgbaRows(64, 32, seed: 11);
        $skin = $this->skin($rows);

        $serialized = SkinSerializer::serialize($skin);

        self::assertSame('custom.skin', $serialized->id);
        self::assertSame(64, $serialized->image->width);
        self::assertSame(32, $serialized->image->height);
        self::assertSame(PngFixture::toRgba($rows), $serialized->image->data);
        self::assertSame(strlen(PngFixture::toRgba($rows)), $serialized->image->length);
    }

    public function testSerializesGeometryAndResourcePatch(): void
    {
        $geometry = '{"format_version":"1.12.0","minecraft:geometry":[]}';
        $skin = $this->skin(
            [[[1, 2, 3, 255]]],
            geometryName: 'geometry.humanoid.custom',
            geometryData: base64_encode($geometry)
        );

        $serialized = SkinSerializer::serialize($skin);

        self::assertSame($geometry, $serialized->geometryData);
        self::assertSame('1.12.0', $serialized->geometryDataMinEngineVersion);
        self::assertSame(
            '{"geometry":{"default":"geometry.humanoid.custom"}}',
            $serialized->resourcePatch
        );
    }

    public function testCapeIsDecodedWhenPresent(): void
    {
        $capeRows = [[[9, 8, 7, 255], [6, 5, 4, 255]]];
        $skin = new SkinData(
            'custom.skin',
            base64_encode(PngFixture::encode([[[1, 2, 3, 255]]])),
            base64_encode(PngFixture::encode($capeRows)),
            '',
            ''
        );

        $serialized = SkinSerializer::serialize($skin);

        self::assertSame(2, $serialized->capeImage->width);
        self::assertSame(1, $serialized->capeImage->height);
        self::assertSame(PngFixture::toRgba($capeRows), $serialized->capeImage->data);
        self::assertNotSame('', $serialized->capeId);
    }

    public function testBrokenCapeDoesNotDropTheSkin(): void
    {
        $rows = [[[4, 5, 6, 255]]];
        $skin = new SkinData(
            'custom.skin',
            base64_encode(PngFixture::encode($rows)),
            base64_encode('not-a-png'),
            '',
            ''
        );

        $serialized = SkinSerializer::serialize($skin);

        self::assertSame(PngFixture::toRgba($rows), $serialized->image->data);
        self::assertSame(0, $serialized->capeImage->width);
        self::assertSame(0, $serialized->capeImage->length);
    }

    public function testBrokenSkinFallsBackToBlank(): void
    {
        $skin = new SkinData('custom.skin', 'not-base64-or-png', '', '', '');

        $serialized = SkinSerializer::serialize($skin);

        self::assertInstanceOf(SerializedSkin::class, $serialized);
        self::assertSame('custom.skin', $serialized->id);
        self::assertSame(0, $serialized->image->width);
        self::assertSame('', $serialized->image->data);
    }

    public function testMissingSkinIsBlank(): void
    {
        $serialized = SkinSerializer::serialize(null);

        self::assertSame(0, $serialized->image->width);
        self::assertSame('Standard_Custom', $serialized->id);
    }

    /**
     * @param list<list<list<int>>> $rows
     */
    private function skin(array $rows, string $geometryName = '', string $geometryData = ''): SkinData
    {
        return new SkinData(
            'custom.skin',
            base64_encode(PngFixture::encode($rows, colorType: count($rows[0][0]) === 3 ? 2 : 6)),
            '',
            $geometryName,
            $geometryData
        );
    }
}
