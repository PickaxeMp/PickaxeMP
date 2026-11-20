<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\protocol;

use PHPUnit\Framework\TestCase;
use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\binary\Uuid;
use PickaxeMP\protocol\packet\PacketDirection;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\PacketRegistry;
use PickaxeMP\protocol\packet\packets\ActorEventPacket;
use PickaxeMP\protocol\packet\packets\LevelSoundEventPacket;
use PickaxeMP\protocol\packet\packets\MoveActorDeltaPacket;
use PickaxeMP\protocol\packet\packets\PlayerListPacket;
use PickaxeMP\protocol\packet\types\ActorEventType;
use PickaxeMP\protocol\packet\types\ArmSize;
use PickaxeMP\protocol\packet\types\Color;
use PickaxeMP\protocol\packet\types\MoveActorDeltaData;
use PickaxeMP\protocol\packet\types\PieceTintColor;
use PickaxeMP\protocol\packet\types\PieceType;
use PickaxeMP\protocol\packet\types\PlayerListEntry;
use PickaxeMP\protocol\packet\types\SerializedPersonaPieceHandle;
use PickaxeMP\protocol\packet\types\SerializedSkin;
use PickaxeMP\protocol\packet\types\SkinImage;
use PickaxeMP\protocol\packet\types\TintMapColor;
use PickaxeMP\protocol\packet\types\TrustedSkinFlag;
use PickaxeMP\protocol\packet\types\Vec3;
use PickaxeMP\protocol\ProtocolException;

/**
 * Phase 7.1 - oyuncu listesi, varlik olayi, ses olayi ve delta hareket.
 *
 * Altin baytlar Mojang `v1.26.65` (protokol 2225) semasindaki **ordinal
 * sirasindan** elle turetilmistir ve `gophertunnel` referans uygulamasiyla
 * capraz kontrol edilmistir. Testler kodlayicinin kendi ciktisini degil,
 * semadaki sirayi dogrular.
 */
final class PlayerListPacketTest extends TestCase
{
    private function bytes(PacketInterface $packet): string
    {
        $writer = new BinaryWriter();
        $packet->encode($writer);

        return $writer->bytes();
    }

    private function hex(PacketInterface $packet): string
    {
        return bin2hex($this->bytes($packet));
    }

    private static function uuid(): Uuid
    {
        return Uuid::fromString('01020304-0506-0708-090a-0b0c0d0e0f10');
    }

    private static function skinHex(SerializedSkin $skin): string
    {
        $writer = new BinaryWriter();
        $skin->encode($writer);

        return bin2hex($writer->bytes());
    }

    /** UUID tel bicimi: reverse(MSB8) + reverse(LSB8). */
    private const UUID_HEX = '0807060504030201100f0e0d0c0b0a09';

    // --- Kimlikler ve kayit -------------------------------------------------

    public function testPacketIdsMatchOfficialSchema(): void
    {
        self::assertSame(27, PacketId::ACTOR_EVENT);
        self::assertSame(63, PacketId::PLAYER_LIST);
        self::assertSame(111, PacketId::MOVE_ACTOR_DELTA);
        self::assertSame(123, PacketId::LEVEL_SOUND_EVENT);
    }

    public function testPacketIdNamesAreHumanReadable(): void
    {
        self::assertSame('ActorEvent', PacketId::name(PacketId::ACTOR_EVENT));
        self::assertSame('PlayerList', PacketId::name(PacketId::PLAYER_LIST));
        self::assertSame('MoveActorDelta', PacketId::name(PacketId::MOVE_ACTOR_DELTA));
        self::assertSame('LevelSoundEvent', PacketId::name(PacketId::LEVEL_SOUND_EVENT));
    }

    public function testRegistryContainsAllPhase71Packets(): void
    {
        $registry = PacketRegistry::withSupportedPackets();

        self::assertSame(46, $registry->count());

        foreach ([
            PacketId::PLAYER_LIST,
            PacketId::ACTOR_EVENT,
            PacketId::LEVEL_SOUND_EVENT,
            PacketId::MOVE_ACTOR_DELTA,
        ] as $id) {
            self::assertTrue(
                $registry->directionFor($id)->allows(PacketDirection::ServerToClient),
                "paket {$id} sunucu->istemci olmali"
            );
        }
    }

    // --- Color --------------------------------------------------------------

    public function testColorIsBigEndianArgbWithAlphaInLowByte(): void
    {
        $writer = new BinaryWriter();
        Color::fromRgba(0x12, 0x34, 0x56, 0x78)->encode($writer);

        // A=0x78 | R=0x12<<8 | G=0x34<<16 | B=0x56<<24 = 0x56341278, BE yazilir.
        self::assertSame('56341278', bin2hex($writer->bytes()));
    }

    public function testColorWhiteAndBlackValues(): void
    {
        self::assertSame(-1, Color::white()->value);
        // Alfa dusuk bayttadir: A=0xFF, R=G=B=0 -> 255 (bu -16777216 DEGILDIR).
        self::assertSame(255, Color::black()->value);

        $writer = new BinaryWriter();
        Color::white()->encode($writer);
        self::assertSame('ffffffff', bin2hex($writer->bytes()));

        $writer = new BinaryWriter();
        Color::black()->encode($writer);
        self::assertSame('000000ff', bin2hex($writer->bytes()));
    }

    public function testColorRoundTripAndToRgba(): void
    {
        $decoded = Color::decode(new BinaryReader("\x56\x34\x12\x78"));

        self::assertSame(
            ['red' => 0x12, 'green' => 0x34, 'blue' => 0x56, 'alpha' => 0x78],
            $decoded->toRgba()
        );
    }

    // --- ActorEvent ---------------------------------------------------------

    public function testActorEventGoldenBytes(): void
    {
        // runtimeId 77 = 0x4D (varuint64), Death = 3 (uint8),
        // data 3 -> zigzag 6 (varint32). Fire At Position yazilmaz.
        self::assertSame(
            '4d0306',
            $this->hex(new ActorEventPacket(77, ActorEventType::Death, 3))
        );
    }

    public function testActorEventRoundTrip(): void
    {
        $packet = new ActorEventPacket(4321, ActorEventType::Hurt, -7);
        $decoded = ActorEventPacket::decode(new BinaryReader($this->bytes($packet)));

        self::assertSame(4321, $decoded->targetRuntimeId);
        self::assertSame(ActorEventType::Hurt, $decoded->event);
        self::assertSame(-7, $decoded->data);
        self::assertNull($decoded->fireAtPosition);
    }

    public function testActorEventFirePositionOnlyWrittenForFireworks(): void
    {
        self::assertTrue(ActorEventType::FireworksExplode->carriesFirePosition());

        $withPosition = new ActorEventPacket(1, ActorEventType::FireworksExplode, 0, new Vec3(1.0, 2.0, 3.0));
        $withoutPosition = new ActorEventPacket(1, ActorEventType::FireworksExplode, 0);

        // Olay tasiyorsa 12 baytlik Vec3 yazilir.
        self::assertSame(
            strlen($this->bytes($withoutPosition)) + 12,
            strlen($this->bytes($withPosition))
        );

        // Tasimayan bir olayda Vec3 verilse bile yazilmaz (simetri).
        $hurt = new ActorEventPacket(1, ActorEventType::Hurt, 0, new Vec3(1.0, 2.0, 3.0));
        self::assertSame(
            strlen($this->bytes(new ActorEventPacket(1, ActorEventType::Hurt, 0))),
            strlen($this->bytes($hurt))
        );
    }

    public function testActorEventFireworksRoundTripKeepsPosition(): void
    {
        $packet = new ActorEventPacket(9, ActorEventType::FireworksExplode, 0, new Vec3(1.5, 2.5, 3.5));
        $decoded = ActorEventPacket::decode(new BinaryReader($this->bytes($packet)));

        self::assertNotNull($decoded->fireAtPosition);
        self::assertSame(1.5, $decoded->fireAtPosition->x);
        self::assertSame(3.5, $decoded->fireAtPosition->z);
    }

    // --- LevelSoundEvent ----------------------------------------------------

    public function testLevelSoundEventGoldenBytes(): void
    {
        $packet = new LevelSoundEventPacket(
            soundEvent: 'hit.wood',
            position: new Vec3(1.5, 64.0, -2.5),
            data: 0,
            actorIdentifier: 'minecraft:player',
            isBaby: false,
            isGlobal: false,
            actorUniqueId: 4321
        );

        $expected = '08' . bin2hex('hit.wood')
            . bin2hex(pack('g', 1.5)) . bin2hex(pack('g', 64.0)) . bin2hex(pack('g', -2.5))
            . '00'
            . '10' . bin2hex('minecraft:player')
            . '00' . '00'
            // Actor Unique Id SABIT int64 LE (zigzag DEGIL): 4321 = 0x10E1.
            . 'e110000000000000';

        self::assertSame($expected, $this->hex($packet));
    }

    public function testLevelSoundEventActorUniqueIdIsFixedInt64NotZigZag(): void
    {
        // Zigzag yazilsaydi 4321 -> 8642 -> 'c243'. Sabit int64 beklenir.
        self::assertStringContainsString(
            'e110000000000000',
            $this->hex(new LevelSoundEventPacket('x', new Vec3(0.0, 0.0, 0.0), 0, '', false, false, 4321))
        );
    }

    public function testLevelSoundEventRoundTrip(): void
    {
        $packet = new LevelSoundEventPacket('ambient.cave', new Vec3(-1.0, 70.5, 4.25), 5, 'minecraft:cow', true, true, 99);
        $decoded = LevelSoundEventPacket::decode(new BinaryReader($this->bytes($packet)));

        self::assertSame('ambient.cave', $decoded->soundEvent);
        self::assertSame(5, $decoded->data);
        self::assertSame('minecraft:cow', $decoded->actorIdentifier);
        self::assertTrue($decoded->isBaby);
        self::assertTrue($decoded->isGlobal);
        self::assertSame(99, $decoded->actorUniqueId);
        self::assertNull($decoded->fireAtPosition);
    }

    // --- MoveActorDelta -----------------------------------------------------

    public function testMoveActorDeltaGoldenBytes(): void
    {
        $packet = new MoveActorDeltaPacket(new MoveActorDeltaData(
            actorRuntimeId: 55,
            positionX: 10.5,
            positionY: null,
            positionZ: -3.25,
            rotationX: MoveActorDeltaData::packRotation(90.0),
            rotationY: null,
            rotationYHead: null,
            isOnGround: false,
            forceMove: false,
            forceMoveLocalEntity: false,
            forceCompletion: false,
            ticks: 7
        ));

        $expected = '37'                       // runtimeId 55
            . '01' . bin2hex(pack('g', 10.5))  // X: var + float32
            . '00'                             // Y: yok
            . '01' . bin2hex(pack('g', -3.25)) // Z: var + float32
            . '01' . '40'                      // rotationX: var + byte-float (90/1.40625 = 64)
            . '00'                             // rotationY yok
            . '00'                             // rotationYHead yok
            . '00' . '00' . '00' . '00'        // onGround, forceMove, forceLocal, forceCompletion
            . '07';                            // ticks

        self::assertSame($expected, $this->hex($packet));
    }

    public function testMoveActorDeltaRoundTrip(): void
    {
        $data = new MoveActorDeltaData(
            actorRuntimeId: 1,
            positionX: 0.5,
            positionY: 1.5,
            positionZ: 2.5,
            rotationX: 10,
            rotationY: -20,
            rotationYHead: 30,
            isOnGround: true,
            forceMove: true,
            forceMoveLocalEntity: false,
            forceCompletion: true,
            ticks: 1234
        );

        $decoded = MoveActorDeltaPacket::decode(
            new BinaryReader($this->bytes(new MoveActorDeltaPacket($data)))
        );

        self::assertSame(1, $decoded->data->actorRuntimeId);
        self::assertSame(0.5, $decoded->data->positionX);
        self::assertSame(1.5, $decoded->data->positionY);
        self::assertSame(2.5, $decoded->data->positionZ);
        self::assertSame(10, $decoded->data->rotationX);
        self::assertSame(-20, $decoded->data->rotationY);
        self::assertSame(30, $decoded->data->rotationYHead);
        self::assertTrue($decoded->data->isOnGround);
        self::assertTrue($decoded->data->forceMove);
        self::assertFalse($decoded->data->forceMoveLocalEntity);
        self::assertTrue($decoded->data->forceCompletion);
        self::assertSame(1234, $decoded->data->ticks);
    }

    public function testMoveActorDeltaAbsentComponentsCostOneByteEach(): void
    {
        // 1 (runtimeId) + 6 (varlik bayraklari) + 4 (bool) + 1 (ticks) = 12.
        self::assertSame(
            12,
            strlen($this->bytes(new MoveActorDeltaPacket(new MoveActorDeltaData(actorRuntimeId: 1))))
        );
    }

    public function testPackRotationWrapsToSignedByte(): void
    {
        self::assertSame(0, MoveActorDeltaData::packRotation(0.0));
        self::assertSame(64, MoveActorDeltaData::packRotation(90.0));
        self::assertSame(-128, MoveActorDeltaData::packRotation(180.0));
        self::assertSame(-64, MoveActorDeltaData::packRotation(270.0));

        // 358 -> 254.6 -> 255 -> bayt icinde -1.
        self::assertSame(-1, MoveActorDeltaData::packRotation(358.0));
    }

    // --- TintMapColor -------------------------------------------------------

    public function testTintMapColorRequiresExactlyFourColours(): void
    {
        $this->expectException(ProtocolException::class);

        new TintMapColor([Color::white(), Color::white()]);
    }

    public function testTintMapColorWritesNoLengthPrefix(): void
    {
        $writer = new BinaryWriter();
        TintMapColor::empty()->encode($writer);

        // 4 renk * 4 bayt = 16 bayt; uzunluk oneki YOK.
        self::assertSame(16, strlen($writer->bytes()));
        self::assertSame(str_repeat('000000ff', 4), bin2hex($writer->bytes()));
    }

    // --- SerializedSkin -----------------------------------------------------

    public function testBlankSkinGoldenBytesMatchesSchemaOrder(): void
    {
        $expected = '0f' . bin2hex('Standard_Custom')  //  0 ID
            . '00'                                      //  1 ResourcePatch ''
            . '00000000' . '00000000' . '00'            //  2 ImageData (w, h, baytlar)
            . '00'                                      //  3 AnimatedImageData (0)
            . '00000000' . '00000000' . '00'            //  4 CapeImageData
            . '00'                                      //  5 GeometryData ''
            . '00'                                      //  6 GeometryDataMinEngineVersion ''
            . '00'                                      //  7 AnimationData ''
            . '00'                                      //  8 CapeID ''
            . '00'                                      //  9 FullID ''
            . '01'                                      // 10 ArmSize Wide
            . '000000ff'                                // 11 SkinColor (siyah, BE)
            . '00'                                      // 12 PersonaPieces (0)
            . '00'                                      // 13 PieceTintColors (0)
            . '00' . '00' . '00' . '00' . '00'          // 14-18 bes bool
            . '05' . bin2hex('Unset')                   // 19 TrustedSkinFlag
            . '00';                                     // 20 ProfileHash ''

        self::assertSame($expected, self::skinHex(SerializedSkin::blank()));
        self::assertSame(60, $this->bytesHexLength($expected));
    }

    private function bytesHexLength(string $hex): int
    {
        return intdiv(strlen($hex), 2);
    }

    public function testSkinRoundTripPreservesAllFields(): void
    {
        $skin = new SerializedSkin(
            id: 'custom',
            resourcePatch: '{"geometry":{}}',
            image: new SkinImage(64, 64, str_repeat("\xAB", 64 * 64 * 4)),
            animatedImages: [],
            capeImage: new SkinImage(2, 1, str_repeat("\xCD", 8)),
            geometryData: '{"format_version":"1.12.0"}',
            geometryDataMinEngineVersion: '1.12.0',
            animationData: '{}',
            capeId: 'cape-1',
            fullId: 'full-1',
            armSize: ArmSize::Slim,
            skinColor: Color::fromRgba(1, 2, 3, 4),
            personaPieces: [
                new SerializedPersonaPieceHandle('piece-1', PieceType::Hands, self::uuid(), true, ''),
            ],
            pieceTintColors: [
                new PieceTintColor('hands', TintMapColor::empty()),
            ],
            isPremium: true,
            isPersona: false,
            isPersonaCapeOnClassicSkin: true,
            isPrimaryUser: false,
            overridesPlayerAppearance: true,
            trustedSkinFlag: TrustedSkinFlag::True,
            profileHash: 'hash'
        );

        $writer = new BinaryWriter();
        $skin->encode($writer);
        $decoded = SerializedSkin::decode(new BinaryReader($writer->bytes()));

        self::assertSame('custom', $decoded->id);
        self::assertSame('{"geometry":{}}', $decoded->resourcePatch);
        self::assertSame(64, $decoded->image->width);
        self::assertSame(64 * 64 * 4, $decoded->image->length);
        self::assertSame(2, $decoded->capeImage->width);
        self::assertSame('{"format_version":"1.12.0"}', $decoded->geometryData);
        self::assertSame('1.12.0', $decoded->geometryDataMinEngineVersion);
        self::assertSame('{}', $decoded->animationData);
        self::assertSame('cape-1', $decoded->capeId);
        self::assertSame('full-1', $decoded->fullId);
        self::assertSame(ArmSize::Slim, $decoded->armSize);
        self::assertSame(1, $decoded->skinColor->toRgba()['red']);
        self::assertCount(1, $decoded->personaPieces);
        self::assertSame(PieceType::Hands, $decoded->personaPieces[0]->pieceType);
        self::assertTrue($decoded->personaPieces[0]->isDefaultPiece);
        self::assertCount(1, $decoded->pieceTintColors);
        self::assertSame('hands', $decoded->pieceTintColors[0]->pieceType);
        self::assertTrue($decoded->isPremium);
        self::assertFalse($decoded->isPersona);
        self::assertTrue($decoded->isPersonaCapeOnClassicSkin);
        self::assertFalse($decoded->isPrimaryUser);
        self::assertTrue($decoded->overridesPlayerAppearance);
        self::assertSame(TrustedSkinFlag::True, $decoded->trustedSkinFlag);
        self::assertSame('hash', $decoded->profileHash);
    }

    public function testPersonaPieceTypeIsFixedUint32NotVarUint(): void
    {
        $writer = new BinaryWriter();
        (new SerializedPersonaPieceHandle('', PieceType::Unsupported, self::uuid(), false, ''))
            ->encode($writer);

        // PieceId '' -> '00', sonra PieceType = 29 SABIT uint32 LE -> '1d000000'.
        self::assertStringStartsWith('001d000000', bin2hex($writer->bytes()));
    }

    public function testPieceTintColorWritesStringKeyNotEnum(): void
    {
        $writer = new BinaryWriter();
        (new PieceTintColor('hands', TintMapColor::empty()))->encode($writer);

        self::assertSame(
            '05' . bin2hex('hands') . str_repeat('000000ff', 4),
            bin2hex($writer->bytes())
        );
    }

    // --- PlayerList ---------------------------------------------------------

    public function testPlayerListAddEntryGoldenBytes(): void
    {
        $packet = new PlayerListPacket([
            PlayerListEntry::add(
                uuid: self::uuid(),
                actorUniqueId: 1234,
                playerName: 'Steve',
                xuid: 'x1',
                platformOnlineId: 'p1',
                buildPlatform: 7
            ),
        ]);

        $expected = '01'                                    // Entries sayisi
            . '01'                                          // cereal variant: Add = 1
            . '00'                                          // Action: Add = 0
            . self::UUID_HEX                                // UUID
            . 'a413'                                        // ActorUniqueID 1234 (zigzag 2468 varint64)
            . '05' . bin2hex('Steve')                       // Player Name
            . '02' . bin2hex('x1')                          // XBL XUID
            . '00'                                          // PlayFab ID ''
            . '02' . bin2hex('p1')                          // Platform Online ID
            . '07000000'                                    // Build Platform (sabit int32 LE)
            . self::skinHex(SerializedSkin::blank())        // Serialized Skin
            . '00' . '00' . '00'                            // Is Teacher, Is Host, Is SubClient
            . 'ffffffff';                                   // Player Color (beyaz, BE)

        self::assertSame($expected, $this->hex($packet));
    }

    public function testPlayerListRemoveEntryIsMinimal(): void
    {
        $packet = new PlayerListPacket([PlayerListEntry::remove(self::uuid())]);

        // 1 (sayi) + 1 (variant) + 1 (action) + 16 (UUID) = 19 bayt.
        self::assertSame(19, strlen($this->bytes($packet)));
        self::assertSame('010001' . self::UUID_HEX, $this->hex($packet));
    }

    public function testPlayerListVariantAndLegacyActionAreBothWritten(): void
    {
        // Ilk uc bayt: sayi = 1, variant = 1 (Add), action = 0.
        self::assertSame(
            '010100',
            substr($this->hex(new PlayerListPacket([PlayerListEntry::add(self::uuid(), 1, 'a')])), 0, 6)
        );

        // Remove: sayi = 1, variant = 0, action = 1.
        self::assertSame(
            '010001',
            substr($this->hex(new PlayerListPacket([PlayerListEntry::remove(self::uuid())])), 0, 6)
        );
    }

    public function testPlayerListRejectsVariantActionMismatch(): void
    {
        // variant = 1 (Add) ama Action = 1 (Remove): paket bozuktur.
        $raw = "\x01" . "\x01" . "\x01" . hex2bin(self::UUID_HEX);

        $this->expectException(ProtocolException::class);

        PlayerListPacket::decode(new BinaryReader($raw));
    }

    public function testPlayerListRoundTripPreservesEntries(): void
    {
        $packet = new PlayerListPacket([
            PlayerListEntry::add(self::uuid(), 1234, 'Steve', 'x1', 'p1', 7),
            PlayerListEntry::remove(self::uuid()),
        ]);

        $decoded = PlayerListPacket::decode(new BinaryReader($this->bytes($packet)));

        self::assertCount(2, $decoded->entries);
        self::assertTrue($decoded->entries[0]->isAdd());
        self::assertSame('Steve', $decoded->entries[0]->playerName);
        self::assertSame(1234, $decoded->entries[0]->actorUniqueId);
        self::assertSame('x1', $decoded->entries[0]->xuid);
        self::assertSame('p1', $decoded->entries[0]->platformOnlineId);
        self::assertSame(7, $decoded->entries[0]->buildPlatform);
        self::assertNotNull($decoded->entries[0]->skin);
        self::assertFalse($decoded->entries[1]->isAdd());
        self::assertSame(self::uuid()->toString(), $decoded->entries[1]->uuid->toString());
    }

    public function testPlayerListEnforcesEntryLimit(): void
    {
        $this->expectException(ProtocolException::class);

        PlayerListPacket::decode(new BinaryReader(hex2bin('e907'))); // 1001 giris
    }
}
