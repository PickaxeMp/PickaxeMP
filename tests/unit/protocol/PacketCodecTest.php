<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\protocol;

use PHPUnit\Framework\TestCase;
use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\binary\Uuid;
use PickaxeMP\protocol\compression\CompressionManager;
use PickaxeMP\protocol\packet\PacketContext;
use PickaxeMP\protocol\packet\PacketDirection;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\PacketRegistry;
use PickaxeMP\protocol\packet\packets\LoginPacket;
use PickaxeMP\protocol\packet\packets\NetworkSettingsPacket;
use PickaxeMP\protocol\packet\packets\PlayStatusPacket;
use PickaxeMP\protocol\packet\packets\RequestNetworkSettingsPacket;
use PickaxeMP\protocol\packet\packets\ResourcePackClientResponsePacket;
use PickaxeMP\protocol\packet\packets\ResourcePacksInfoPacket;
use PickaxeMP\protocol\packet\packets\ResourcePackStackPacket;
use PickaxeMP\protocol\packet\types\PackIdVersion;
use PickaxeMP\protocol\packet\types\PackInfoData;
use PickaxeMP\protocol\packet\types\PackInstanceId;
use PickaxeMP\protocol\packet\types\PlayStatus;
use PickaxeMP\protocol\packet\types\ResourcePackResponse;
use PickaxeMP\protocol\ProtocolEngine;
use PickaxeMP\protocol\ProtocolException;
use PickaxeMP\protocol\ProtocolVersion;

final class PacketCodecTest extends TestCase
{
    private function engine(bool $strict = true): ProtocolEngine
    {
        return new ProtocolEngine(
            PacketRegistry::withSupportedPackets(),
            new CompressionManager(),
            $strict
        );
    }

    private function contextC2s(): PacketContext
    {
        return new PacketContext('test', PacketDirection::ClientToServer);
    }

    private function contextS2c(): PacketContext
    {
        return new PacketContext('test', PacketDirection::ServerToClient);
    }

    // --- Registry -------------------------------------------------------

    public function testRegistryContainsExactlyTheSupportedPackets(): void
    {
        $registry = PacketRegistry::withSupportedPackets();

        // Phase 2: 7 login-öncesi paket. Phase 4: +3 dünya paketi.
        // Phase 3: +2 handshake paketi. Phase 5: +2 hareket paketi.
        // Phase 6: +10 envanter/varlık paketi. Phase 7: +5 oynanış paketi.
        // Phase 7.1: +4 (PlayerList, ActorEvent, LevelSoundEvent, MoveActorDelta).
        // Phase 10.3: +2 (ItemStackRequest, ItemStackResponse).
        self::assertSame(46, $registry->count());
        self::assertTrue($registry->has(PacketId::LOGIN));
        self::assertTrue($registry->has(PacketId::REQUEST_NETWORK_SETTINGS));
        self::assertTrue($registry->has(PacketId::NETWORK_SETTINGS));
        self::assertTrue($registry->has(PacketId::PLAY_STATUS));
        self::assertTrue($registry->has(PacketId::RESOURCE_PACKS_INFO));
        self::assertTrue($registry->has(PacketId::RESOURCE_PACK_STACK));
        self::assertTrue($registry->has(PacketId::RESOURCE_PACK_CLIENT_RESPONSE));
        self::assertTrue($registry->has(PacketId::START_GAME));
        self::assertTrue($registry->has(PacketId::LEVEL_CHUNK));
        self::assertTrue($registry->has(PacketId::SUB_CHUNK));
        self::assertTrue($registry->has(PacketId::SERVER_TO_CLIENT_HANDSHAKE));
        self::assertTrue($registry->has(PacketId::CLIENT_TO_SERVER_HANDSHAKE));
        self::assertTrue($registry->has(PacketId::PLAYER_AUTH_INPUT));
        self::assertTrue($registry->has(PacketId::MOVE_PLAYER));
    }

    /**
     * Kimlikler Mojang'ın v1.26.65 şemasındaki `[cereal:packet]`
     * değerleriyle birebir aynı olmalı.
     */
    public function testPacketIdsMatchOfficialSchema(): void
    {
        self::assertSame(1, PacketId::LOGIN);
        self::assertSame(2, PacketId::PLAY_STATUS);
        self::assertSame(6, PacketId::RESOURCE_PACKS_INFO);
        self::assertSame(7, PacketId::RESOURCE_PACK_STACK);
        self::assertSame(8, PacketId::RESOURCE_PACK_CLIENT_RESPONSE);
        self::assertSame(143, PacketId::NETWORK_SETTINGS);
        self::assertSame(193, PacketId::REQUEST_NETWORK_SETTINGS);
    }

    public function testRegistryRejectsDuplicateId(): void
    {
        $registry = new PacketRegistry();
        $registry->register(1, LoginPacket::class, PacketDirection::ClientToServer);

        $this->expectException(ProtocolException::class);
        $registry->register(1, LoginPacket::class, PacketDirection::ClientToServer);
    }

    public function testUnknownPacketIsRejected(): void
    {
        $payload = (new BinaryWriter())->writeVarUInt(199)->bytes();

        $this->expectException(ProtocolException::class);
        $this->engine()->decode($payload, $this->contextC2s());
    }

    public function testWrongDirectionIsRejected(): void
    {
        // Login yalnızca C→S; S→C bağlamında reddedilmeli.
        $packet = new LoginPacket(ProtocolVersion::PROTOCOL, 'a.b.c');
        $bytes = $this->engine()->encode($packet);

        $this->expectException(ProtocolException::class);
        $this->engine()->decode($bytes, $this->contextS2c());
    }

    public function testEncodePrefixesVarUIntPacketId(): void
    {
        $bytes = $this->engine()->encode(new PlayStatusPacket(0));

        // PlayStatus kimliği 2 (varuint tek bayt), sonra int32 BE 0 → 4 sıfır.
        self::assertSame("\x02\x00\x00\x00\x00", $bytes);
    }

    public function testLargePacketIdUsesMultipleVarUIntBytes(): void
    {
        // 193 → 0xC1 0x01
        $bytes = $this->engine()->encode(new RequestNetworkSettingsPacket());

        self::assertSame("\xC1\x01", substr($bytes, 0, 2));
    }

    // --- RequestNetworkSettings ----------------------------------------

    public function testRequestNetworkSettingsRoundTrip(): void
    {
        $packet = new RequestNetworkSettingsPacket(2225);
        $engine = $this->engine();

        $decoded = $engine->decode($engine->encode($packet), $this->contextC2s());

        self::assertInstanceOf(RequestNetworkSettingsPacket::class, $decoded);
        self::assertSame(2225, $decoded->clientNetworkVersion);
        self::assertTrue($decoded->isCompatible());
    }

    public function testRequestNetworkSettingsIsBigEndianInt32(): void
    {
        $packet = new RequestNetworkSettingsPacket(2225);
        $bytes = $this->engine()->encode($packet);

        // Kimlik (2 bayt) + 2225 = 0x000008B1 big-endian
        self::assertSame("\x00\x00\x08\xB1", substr($bytes, 2));
    }

    public function testIncompatibleClientVersionIsDetected(): void
    {
        $packet = new RequestNetworkSettingsPacket(2193);

        self::assertFalse($packet->isCompatible());
    }

    // --- NetworkSettings ----------------------------------------------

    /**
     * 2225 şeması: 5 alan, `SCTP Recovery Profile` YOK.
     * Toplam gövde: uint16(2) + uint16(2) + bool(1) + uint8(1) + float(4) = 10.
     */
    public function testNetworkSettingsBodyIsTenBytesForProtocol2225(): void
    {
        $packet = new NetworkSettingsPacket(256, \PickaxeMP\protocol\compression\CompressionAlgorithm::Zlib);
        $bytes = $this->engine()->encode($packet);

        // 143 → varuint 2 bayt (0x8F 0x01), gövde 10 bayt.
        self::assertSame(2, strlen((new BinaryWriter())->writeVarUInt(143)->bytes()));
        self::assertSame(2 + 10, strlen($bytes));
    }

    public function testNetworkSettingsRoundTrip(): void
    {
        $packet = new NetworkSettingsPacket(
            compressionThreshold: 256,
            compressionAlgorithm: \PickaxeMP\protocol\compression\CompressionAlgorithm::Zlib,
            clientThrottleEnabled: true,
            clientThrottleThreshold: 42,
            clientThrottleScalar: 0.5
        );

        $engine = $this->engine();
        $decoded = $engine->decode($engine->encode($packet), $this->contextS2c());

        self::assertInstanceOf(NetworkSettingsPacket::class, $decoded);
        self::assertSame(256, $decoded->compressionThreshold);
        self::assertSame(\PickaxeMP\protocol\compression\CompressionAlgorithm::Zlib, $decoded->compressionAlgorithm);
        self::assertTrue($decoded->clientThrottleEnabled);
        self::assertSame(42, $decoded->clientThrottleThreshold);
        self::assertEqualsWithDelta(0.5, $decoded->clientThrottleScalar, 1.0e-6);
    }

    public function testNetworkSettingsThresholdIsLittleEndian(): void
    {
        $packet = new NetworkSettingsPacket(256);
        $bytes = $this->engine()->encode($packet);

        // 256 LE → 0x00 0x01, kimlikten hemen sonra.
        self::assertSame("\x00\x01", substr($bytes, 2, 2));
    }

    public function testCompressionAlgorithmValues(): void
    {
        self::assertSame(0, \PickaxeMP\protocol\compression\CompressionAlgorithm::Zlib->value);
        self::assertSame(1, \PickaxeMP\protocol\compression\CompressionAlgorithm::Snappy->value);
        self::assertSame(65535, \PickaxeMP\protocol\compression\CompressionAlgorithm::None->value);
    }

    // --- Login ---------------------------------------------------------

    public function testLoginPacketRoundTrip(): void
    {
        $jwt = '{"chain":["a.b.c","d.e.f"]}';
        $packet = new LoginPacket(ProtocolVersion::PROTOCOL, $jwt);

        $engine = $this->engine();
        $decoded = $engine->decode($engine->encode($packet), $this->contextC2s());

        self::assertInstanceOf(LoginPacket::class, $decoded);
        self::assertSame(ProtocolVersion::PROTOCOL, $decoded->clientNetworkVersion);
        self::assertSame($jwt, $decoded->connectionRequest);
        self::assertTrue($decoded->hasPlausibleJwtShape());
    }

    public function testLoginShapeCheckRejectsNonJwt(): void
    {
        self::assertFalse((new LoginPacket(2225, 'tek-parca'))->hasPlausibleJwtShape());
        self::assertFalse((new LoginPacket(2225, ''))->hasPlausibleJwtShape());
        self::assertFalse((new LoginPacket(2225, 'a.b.c.d'))->hasPlausibleJwtShape());
        // Bedrock: connectionRequest bir JSON nesnesi ve `chain` dizisi olmalidir.
        self::assertFalse((new LoginPacket(2225, '{"baska":1}'))->hasPlausibleJwtShape());
        self::assertFalse((new LoginPacket(2225, '{"chain":[]}'))->hasPlausibleJwtShape());
        self::assertFalse((new LoginPacket(2225, '{"chain":[1,2]}'))->hasPlausibleJwtShape());
        self::assertTrue((new LoginPacket(2225, '{"chain":["a.b.c"]}'))->hasPlausibleJwtShape());
    }

    public function testLoginRejectsOversizedPayload(): void
    {
        // Registry'de Login için üst sınır 512 KiB.
        $huge = str_repeat('a', 600 * 1024);
        $writer = new BinaryWriter();
        $writer->writeVarUInt(PacketId::LOGIN);
        $writer->writeInt32BigEndian(2225);
        $writer->writeString($huge);

        $this->expectException(ProtocolException::class);
        $this->engine()->decode($writer->bytes(), $this->contextC2s());
    }

    // --- PlayStatus ----------------------------------------------------

    public function testPlayStatusRoundTrip(): void
    {
        $engine = $this->engine();
        $packet = PlayStatusPacket::of(PlayStatus::PlayerSpawn);

        $decoded = $engine->decode($engine->encode($packet), $this->contextS2c());

        self::assertInstanceOf(PlayStatusPacket::class, $decoded);
        self::assertSame(3, $decoded->status);
        self::assertSame(PlayStatus::PlayerSpawn, $decoded->statusEnum());
    }

    public function testPlayStatusIsBigEndian(): void
    {
        $bytes = $this->engine()->encode(PlayStatusPacket::of(PlayStatus::LoginSuccess));

        // kimlik 0x02, sonra 4 bayt BE sıfır
        self::assertSame("\x02\x00\x00\x00\x00", $bytes);
    }

    public function testUnknownPlayStatusIsPreservedNotThrown(): void
    {
        $engine = $this->engine();
        $decoded = $engine->decode(
            $engine->encode(new PlayStatusPacket(99)),
            $this->contextS2c()
        );

        self::assertSame(99, $decoded->status);
        self::assertNull($decoded->statusEnum());
    }

    // --- Resource packs ------------------------------------------------

    public function testResourcePacksInfoWithNoPacks(): void
    {
        $engine = $this->engine();
        $decoded = $engine->decode(
            $engine->encode(new ResourcePacksInfoPacket()),
            $this->contextS2c()
        );

        self::assertInstanceOf(ResourcePacksInfoPacket::class, $decoded);
        self::assertFalse($decoded->resourcePackRequired);
        self::assertSame([], $decoded->resourcePacks);
    }

    public function testResourcePacksInfoWithPacks(): void
    {
        $pack = new PackInfoData(
            packIdVersion: new PackIdVersion(
                Uuid::fromString('11111111-2222-3333-4444-555555555555'),
                '1.0.0'
            ),
            packSize: 4096,
            contentKey: '',
            subpackName: '',
            contentIdentity: 'abc',
            hasScripts: false,
            isAddonPack: false,
            isRayTracingCapable: false,
            cdnUrl: ''
        );

        $engine = $this->engine();
        $decoded = $engine->decode(
            $engine->encode(new ResourcePacksInfoPacket(resourcePacks: [$pack])),
            $this->contextS2c()
        );

        self::assertInstanceOf(ResourcePacksInfoPacket::class, $decoded);
        self::assertCount(1, $decoded->resourcePacks);
        self::assertSame(4096, $decoded->resourcePacks[0]->packSize);
        self::assertSame('1.0.0', $decoded->resourcePacks[0]->packIdVersion->version);
        self::assertSame('abc', $decoded->resourcePacks[0]->contentIdentity);
    }

    public function testResourcePackStackWithNoPacks(): void
    {
        $engine = $this->engine();
        $decoded = $engine->decode(
            $engine->encode(new ResourcePackStackPacket(baseGameVersion: ProtocolVersion::GAME_VERSION)),
            $this->contextS2c()
        );

        self::assertInstanceOf(ResourcePackStackPacket::class, $decoded);
        self::assertSame(ProtocolVersion::GAME_VERSION, $decoded->baseGameVersion);
        self::assertSame([], $decoded->texturePackList);
        self::assertFalse($decoded->includeEditorPacks);
    }

    public function testResourcePackStackWithInstance(): void
    {
        $packet = new ResourcePackStackPacket(
            texturePackRequired: true,
            texturePackList: [new PackInstanceId('pack-uuid', '2.0.0', 'sub')],
            baseGameVersion: '1.26.65',
            experimentToggles: [new \PickaxeMP\protocol\packet\types\ExperimentToggle('exp', true)],
            experimentsEverToggled: true,
            includeEditorPacks: false
        );

        $engine = $this->engine();
        $decoded = $engine->decode($engine->encode($packet), $this->contextS2c());

        self::assertInstanceOf(ResourcePackStackPacket::class, $decoded);
        self::assertTrue($decoded->texturePackRequired);
        self::assertCount(1, $decoded->texturePackList);
        self::assertSame('pack-uuid', $decoded->texturePackList[0]->packId);
        self::assertCount(1, $decoded->experimentToggles);
        self::assertSame('exp', $decoded->experimentToggles[0]->name);
        self::assertTrue($decoded->experimentsEverToggled);
    }

    public function testResourcePackClientResponseRoundTrip(): void
    {
        $engine = $this->engine();
        $packet = ResourcePackClientResponsePacket::of(
            ResourcePackResponse::ResourcePackStackFinished
        );

        $decoded = $engine->decode($engine->encode($packet), $this->contextC2s());

        self::assertInstanceOf(ResourcePackClientResponsePacket::class, $decoded);
        self::assertSame(4, $decoded->response);
        self::assertSame(ResourcePackResponse::ResourcePackStackFinished, $decoded->responseEnum());
    }

    public function testResourcePackClientResponseDownloadingCarriesList(): void
    {
        $engine = $this->engine();
        $packet = ResourcePackClientResponsePacket::of(
            ResourcePackResponse::Downloading,
            ['pack-a', 'pack-b']
        );

        $decoded = $engine->decode($engine->encode($packet), $this->contextC2s());

        self::assertInstanceOf(ResourcePackClientResponsePacket::class, $decoded);
        self::assertSame(['pack-a', 'pack-b'], $decoded->downloadingPacks);
    }

    // --- Hata senaryoları ----------------------------------------------

    public function testTruncatedPacketIsRejected(): void
    {
        // PlayStatus gövdesi 4 bayt olmalı; 2 bayt veriyoruz.
        $bytes = "\x02\x00\x00";

        $this->expectException(ProtocolException::class);
        $this->engine()->decode($bytes, $this->contextS2c());
    }

    public function testEmptyPayloadIsRejected(): void
    {
        $this->expectException(ProtocolException::class);
        $this->engine()->decode('', $this->contextC2s());
    }

    public function testTrailingBytesAreRejectedInStrictMode(): void
    {
        $engine = $this->engine();
        $bytes = $engine->encode(new PlayStatusPacket(0)) . "\xFF";

        $this->expectException(ProtocolException::class);
        $engine->decode($bytes, $this->contextS2c());
    }

    public function testTrailingBytesAreAllowedInLooseMode(): void
    {
        $engine = $this->engine();
        $bytes = $engine->encode(new PlayStatusPacket(0)) . "\xFF";

        $loose = new PacketContext('test', PacketDirection::ServerToClient, ProtocolVersion::PROTOCOL, false);
        $decoded = $engine->decode($bytes, $loose);

        self::assertInstanceOf(PlayStatusPacket::class, $decoded);
    }

    public function testIncompatibleProtocolContextIsRejected(): void
    {
        $context = new PacketContext('test', PacketDirection::ClientToServer, 2193);

        $this->expectException(ProtocolException::class);
        $this->engine()->decode("\xC1\x01\x00\x00\x08\xB1", $context);
    }

    public function testPacketIdNamesAreReadable(): void
    {
        self::assertSame('Login', PacketId::name(1));
        self::assertSame('NetworkSettings', PacketId::name(143));
        self::assertSame('unknown(0xF7)', PacketId::name(247));
    }

    public function testAllPacketsImplementInterface(): void
    {
        $engine = $this->engine();

        foreach ($engine->getRegistry()->ids() as $id) {
            $class = $engine->getRegistry()->classFor($id);
            self::assertTrue(
                is_subclass_of($class, PacketInterface::class),
                "{$class} PacketInterface uygulamali"
            );
        }
    }
}
