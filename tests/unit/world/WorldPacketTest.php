<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\world;

use PHPUnit\Framework\TestCase;
use PickaxeMP\protocol\packet\PacketContext;
use PickaxeMP\protocol\packet\PacketDirection;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketRegistry;
use PickaxeMP\protocol\packet\packets\LevelChunkPacket;
use PickaxeMP\protocol\packet\packets\StartGamePacket;
use PickaxeMP\protocol\packet\packets\SubChunkPacket;
use PickaxeMP\protocol\packet\packets\SubChunkPacketData;
use PickaxeMP\protocol\packet\types\LevelSettings;
use PickaxeMP\protocol\packet\types\SpawnSettings;
use PickaxeMP\protocol\packet\types\SubChunkRequestResult;
use PickaxeMP\protocol\compression\CompressionManager;
use PickaxeMP\protocol\ProtocolEngine;
use PickaxeMP\protocol\ProtocolVersion;

final class WorldPacketTest extends TestCase
{
    private function engine(): ProtocolEngine
    {
        return new ProtocolEngine(PacketRegistry::withSupportedPackets(), new CompressionManager());
    }

    private function s2c(): PacketContext
    {
        return new PacketContext('test', PacketDirection::ServerToClient);
    }

    public function testPacketIdsMatchOfficialSchema(): void
    {
        self::assertSame(11, PacketId::START_GAME);
        self::assertSame(58, PacketId::LEVEL_CHUNK);
        self::assertSame(174, PacketId::SUB_CHUNK);
    }

    public function testRegistryKnowsWorldPackets(): void
    {
        $registry = PacketRegistry::withSupportedPackets();

        // Registry artik handshake, hareket, envanter, varlik ve oynanis paketlerini de iceriyor.
        // 41 (Phase 1-10.2) + 2 (Phase 10.3) + 1 (Phase 10.4: ItemRegistry)
        // + 2 (Phase 10.5: NetworkChunkPublisherUpdate, SetSpawnPosition) = 46.
        self::assertSame(46, $registry->count());
        self::assertTrue($registry->has(PacketId::START_GAME));
        self::assertTrue($registry->has(PacketId::MOVE_PLAYER));
        self::assertTrue($registry->has(PacketId::LEVEL_CHUNK));
        self::assertTrue($registry->has(PacketId::SUB_CHUNK));
        self::assertSame(PacketDirection::ServerToClient, $registry->directionFor(PacketId::START_GAME));
    }

    // --- StartGame ------------------------------------------------------

    public function testStartGameRoundTrip(): void
    {
        $packet = new StartGamePacket(
            entityId: 1,
            runtimeId: 1,
            gameType: 1,
            x: 8.5,
            y: -57.0,
            z: 8.5,
            settings: new LevelSettings(
                seed: 424242,
                spawnSettings: SpawnSettings::at(8, -57, 8),
                gameType: 1,
                defaultSpawnX: 8,
                defaultSpawnY: -57,
                defaultSpawnZ: 8,
                baseGameVersion: ProtocolVersion::GAME_VERSION
            ),
            levelId: 'pickaxe',
            levelName: 'PickaxeMP',
            serverVersion: 'PickaxeMP 0.1',
            multiplayerCorrelationId: 'corr-1'
        );

        $engine = $this->engine();
        $decoded = $engine->decode($engine->encode($packet), $this->s2c());

        self::assertInstanceOf(StartGamePacket::class, $decoded);
        self::assertSame(1, $decoded->entityId);
        self::assertSame(1, $decoded->runtimeId);
        self::assertSame(1, $decoded->gameType);
        self::assertEqualsWithDelta(8.5, $decoded->x, 1.0e-6);
        self::assertEqualsWithDelta(-57.0, $decoded->y, 1.0e-6);
        self::assertSame('pickaxe', $decoded->levelId);
        self::assertSame('PickaxeMP', $decoded->levelName);
        self::assertSame('corr-1', $decoded->multiplayerCorrelationId);
        self::assertTrue($decoded->blockNetworkIdsAreHashes);
        self::assertTrue($decoded->serverAuthBlockBreaking);
        self::assertSame(40, $decoded->rewindHistorySize);
    }

    /**
     * StartGame, 25 alanı **sabit sırada** yazar. Her alanı farklı bir değere
     * ayarlayıp round-trip yaparak sıranın bozulmadığını kanıtlarız.
     */
    public function testStartGameFieldOrderIsLocked(): void
    {
        $packet = new StartGamePacket(
            entityId: 101,
            runtimeId: 202,
            gameType: 2,
            x: 1.0,
            y: 2.0,
            z: 3.0,
            pitch: 4.0,
            yaw: 5.0,
            settings: new LevelSettings(
                seed: 606,
                spawnSettings: SpawnSettings::at(7, 8, 9),
                gameType: 2,
                isHardcore: true,
                gameDifficulty: 3,
                defaultSpawnX: 10,
                defaultSpawnY: 11,
                defaultSpawnZ: 12,
                achievementsDisabled: false,
                dayCycleStopTime: 13000,
                rainLevel: 0.25,
                lightningLevel: 0.5,
                multiplayerGameIntent: false,
                lanBroadcastIntent: false,
                commandsEnabled: false,
                texturePacksRequired: true,
                hasBonusChestEnabled: true,
                startWithMapEnabled: true,
                playerPermissions: 2,
                serverChunkTickRange: 8,
                useMsaGamertagsOnly: true,
                baseGameVersion: '1.26.65',
                limitedWorldWidth: 100,
                limitedWorldDepth: 200,
                chatRestrictionLevel: 2,
                disablePlayerInteractions: true
            ),
            levelId: 'level-id',
            levelName: 'level-name',
            templateContentIdentity: 'template',
            isTrial: true,
            rewindHistorySize: 20,
            serverAuthBlockBreaking: false,
            levelCurrentTime: 6000,
            enchantmentSeed: 777,
            multiplayerCorrelationId: 'corr',
            enableItemStackNetManager: true,
            serverVersion: 'srv',
            blockTypeRegistryChecksum: 123456,
            serverEnabledClientSideGeneration: true,
            blockNetworkIdsAreHashes: false,
            serverAuthSoundEnabled: true,
            telemetryServerId: 'tsid',
            telemetryScenarioId: 'tscen',
            telemetryWorldId: 'twid',
            telemetryOwnerId: 'towid'
        );

        $engine = $this->engine();
        $decoded = $engine->decode($engine->encode($packet), $this->s2c());

        self::assertInstanceOf(StartGamePacket::class, $decoded);

        self::assertSame(101, $decoded->entityId);
        self::assertSame(202, $decoded->runtimeId);
        self::assertSame(2, $decoded->gameType);
        self::assertEqualsWithDelta(1.0, $decoded->x, 1.0e-6);
        self::assertEqualsWithDelta(2.0, $decoded->y, 1.0e-6);
        self::assertEqualsWithDelta(3.0, $decoded->z, 1.0e-6);
        self::assertEqualsWithDelta(4.0, $decoded->pitch, 1.0e-6);
        self::assertEqualsWithDelta(5.0, $decoded->yaw, 1.0e-6);

        $settings = $decoded->settings;
        self::assertNotNull($settings);
        self::assertSame(606, $settings->seed);
        self::assertSame(7, $settings->spawnSettings->spawnX);
        self::assertSame(2, $settings->gameType);
        self::assertTrue($settings->isHardcore);
        self::assertSame(3, $settings->gameDifficulty);
        self::assertSame(10, $settings->defaultSpawnX);
        self::assertSame(11, $settings->defaultSpawnY);
        self::assertSame(12, $settings->defaultSpawnZ);
        self::assertFalse($settings->achievementsDisabled);
        self::assertSame(13000, $settings->dayCycleStopTime);
        self::assertEqualsWithDelta(0.25, $settings->rainLevel, 1.0e-6);
        self::assertEqualsWithDelta(0.5, $settings->lightningLevel, 1.0e-6);
        self::assertFalse($settings->multiplayerGameIntent);
        self::assertFalse($settings->lanBroadcastIntent);
        self::assertFalse($settings->commandsEnabled);
        self::assertTrue($settings->texturePacksRequired);
        self::assertTrue($settings->hasBonusChestEnabled);
        self::assertTrue($settings->startWithMapEnabled);
        self::assertSame(2, $settings->playerPermissions);
        self::assertSame(8, $settings->serverChunkTickRange);
        self::assertTrue($settings->useMsaGamertagsOnly);
        self::assertSame('1.26.65', $settings->baseGameVersion);
        self::assertSame(100, $settings->limitedWorldWidth);
        self::assertSame(200, $settings->limitedWorldDepth);
        self::assertSame(2, $settings->chatRestrictionLevel);
        self::assertTrue($settings->disablePlayerInteractions);

        self::assertSame('level-id', $decoded->levelId);
        self::assertSame('level-name', $decoded->levelName);
        self::assertSame('template', $decoded->templateContentIdentity);
        self::assertTrue($decoded->isTrial);
        self::assertSame(20, $decoded->rewindHistorySize);
        self::assertFalse($decoded->serverAuthBlockBreaking);
        self::assertSame(6000, $decoded->levelCurrentTime);
        self::assertSame(777, $decoded->enchantmentSeed);
        self::assertSame('corr', $decoded->multiplayerCorrelationId);
        self::assertTrue($decoded->enableItemStackNetManager);
        self::assertSame('srv', $decoded->serverVersion);
        self::assertSame(123456, $decoded->blockTypeRegistryChecksum);
        self::assertTrue($decoded->serverEnabledClientSideGeneration);
        self::assertFalse($decoded->blockNetworkIdsAreHashes);
        self::assertTrue($decoded->serverAuthSoundEnabled);
        self::assertSame('tsid', $decoded->telemetryServerId);
        self::assertSame('tscen', $decoded->telemetryScenarioId);
        self::assertSame('twid', $decoded->telemetryWorldId);
        self::assertSame('towid', $decoded->telemetryOwnerId);
    }

    public function testStartGameStartsWithPacketId11(): void
    {
        $bytes = $this->engine()->encode(new StartGamePacket(entityId: 0, runtimeId: 0, gameType: 0, x: 0.0, y: 0.0, z: 0.0));

        self::assertSame(11, ord($bytes[0]), 'VarUInt 11 tek bayttir');
    }

    // --- LevelChunk -----------------------------------------------------

    public function testLevelChunkRoundTrip(): void
    {
        $packet = new LevelChunkPacket(
            chunkX: 3,
            chunkZ: -7,
            dimensionId: 0,
            subChunkCount: 1,
            serializedChunkData: 'RAW-SUBCHUNK-DATA',
            isClientBiomeUpdate: true
        );

        $engine = $this->engine();
        $decoded = $engine->decode($engine->encode($packet), $this->s2c());

        self::assertInstanceOf(LevelChunkPacket::class, $decoded);
        self::assertSame(3, $decoded->chunkX);
        self::assertSame(-7, $decoded->chunkZ);
        self::assertSame(0, $decoded->dimensionId);
        self::assertSame(1, $decoded->subChunkCount);
        self::assertSame('RAW-SUBCHUNK-DATA', $decoded->serializedChunkData);
        self::assertTrue($decoded->isClientBiomeUpdate);
        self::assertNull($decoded->clientRequestSubChunkLimit);
    }

    public function testLevelChunkNegativeCoordinates(): void
    {
        $packet = new LevelChunkPacket(chunkX: -1000, chunkZ: -2000, serializedChunkData: '');

        $engine = $this->engine();
        $decoded = $engine->decode($engine->encode($packet), $this->s2c());

        self::assertInstanceOf(LevelChunkPacket::class, $decoded);
        self::assertSame(-1000, $decoded->chunkX);
        self::assertSame(-2000, $decoded->chunkZ);
    }

    public function testLevelChunkWithRequestLimit(): void
    {
        $packet = new LevelChunkPacket(chunkX: 0, chunkZ: 0, clientRequestSubChunkLimit: 4);

        $engine = $this->engine();
        $decoded = $engine->decode($engine->encode($packet), $this->s2c());

        self::assertInstanceOf(LevelChunkPacket::class, $decoded);
        self::assertSame(4, $decoded->clientRequestSubChunkLimit);
    }

    public function testLevelChunkWithCacheMetadata(): void
    {
        $packet = new LevelChunkPacket(
            chunkX: 0,
            chunkZ: 0,
            cacheEnabled: true,
            cacheMetadata: [111, 222]
        );

        $engine = $this->engine();
        $decoded = $engine->decode($engine->encode($packet), $this->s2c());

        self::assertInstanceOf(LevelChunkPacket::class, $decoded);
        self::assertTrue($decoded->cacheEnabled);
        self::assertSame([111, 222], $decoded->cacheMetadata);
    }

    public function testLevelChunkStartsWithPacketId58(): void
    {
        $bytes = $this->engine()->encode(new LevelChunkPacket(chunkX: 0, chunkZ: 0, serializedChunkData: ''));

        self::assertSame(58, ord($bytes[0]));
    }

    // --- SubChunk -------------------------------------------------------

    public function testSubChunkRoundTrip(): void
    {
        $packet = new SubChunkPacket(
            cacheEnabled: false,
            dimension: 0,
            centerX: 2,
            centerY: -4,
            centerZ: 3,
            entries: [
                new SubChunkPacketData(
                    offsetX: 0,
                    offsetY: 0,
                    offsetZ: 0,
                    result: SubChunkRequestResult::Success,
                    serializedSubChunk: 'DATA'
                ),
            ]
        );

        $engine = $this->engine();
        $decoded = $engine->decode($engine->encode($packet), $this->s2c());

        self::assertInstanceOf(SubChunkPacket::class, $decoded);
        self::assertSame(2, $decoded->centerX);
        self::assertSame(-4, $decoded->centerY);
        self::assertSame(3, $decoded->centerZ);
        self::assertCount(1, $decoded->entries);
        self::assertSame(SubChunkRequestResult::Success, $decoded->entries[0]->result);
        self::assertSame('DATA', $decoded->entries[0]->serializedSubChunk);
    }

    public function testSubChunkRequestResultValuesMatchSchema(): void
    {
        self::assertSame(1, SubChunkRequestResult::Success->value);
        self::assertSame(2, SubChunkRequestResult::LevelChunkDoesntExist->value);
        self::assertSame(3, SubChunkRequestResult::WrongDimension->value);
        self::assertSame(4, SubChunkRequestResult::PlayerDoesntExist->value);
        self::assertSame(5, SubChunkRequestResult::IndexOutOfBounds->value);
        self::assertSame(6, SubChunkRequestResult::SuccessAllAir->value);
    }

    public function testSubChunkStartsWithPacketId(): void
    {
        $bytes = $this->engine()->encode(new SubChunkPacket());

        // 174 → varuint iki bayt: 0xAE 0x01
        self::assertSame("\xAE\x01", substr($bytes, 0, 2));
    }

    public function testSubChunkRejectsInvalidResultByte(): void
    {
        // El ile bozuk bir SubChunkPacketData gövdesi kur.
        $writer = new \PickaxeMP\protocol\binary\BinaryWriter();
        $writer->writeVarUInt(PacketId::SUB_CHUNK);
        $writer->writeBool(false);        // cache
        $writer->writeVarInt(0);          // dimension
        $writer->writeInt32(0);           // centerX
        $writer->writeInt32(0);           // centerY
        $writer->writeInt32(0);           // centerZ
        $writer->writeVarUInt(1);         // entries
        $writer->writeSignedByte(0);      // offX
        $writer->writeSignedByte(0);      // offY
        $writer->writeSignedByte(0);      // offZ
        $writer->writeByte(99);           // GECERSIZ result

        $this->expectException(\PickaxeMP\protocol\ProtocolException::class);
        $this->engine()->decode($writer->bytes(), $this->s2c());
    }
}
