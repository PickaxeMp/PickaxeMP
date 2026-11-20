<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\binary\Uuid;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\types\LevelSettings;

/**
 * `StartGamePacket` (id 11, S→C) — **25 alan**, ordinal sırası korunur.
 *
 * Şema: Mojang `v1.26.65` / protocol 2225.
 *
 * ## Dürüst notlar (alan bazlı)
 *
 * | Alan | Not |
 * |---|---|
 * | 17 `Player Property Data` | Şemada tip yok; **boş ağ NBT compound** yazılır (`0A 00 00`) |
 * | 23 `Server Configuration Join Info` | 3 **opsiyonel** alt nesne; hepsi yok → 3 × `0x00` |
 * | 10 `Movement Settings` | `rewindHistorySize` varint32 + `serverAuthBlockBreaking` bool |
 * | 12 `Level Current Time` | uint64, **sıkıştırmasız** |
 * | 13 `Enchantment Seed` | int32, sıkıştırmalı → varint32 |
 * | 14 `Block Properties` | Boş dizi (blok özellikleri gönderilmez) |
 * | 21 `BlockNetworkIds Are Hashes` | PickaxeMP **true** gönderir (hash modu) |
 *
 * Sıra testlerle kilitlenmiştir (`StartGamePacketTest`).
 */
final class StartGamePacket implements PacketInterface
{
    public function __construct(
        // 0 Entity ID (int64, compression → varint64)
        public readonly int $entityId,
        // 1 Runtime ID (uint64, compression → varuint64)
        public readonly int $runtimeId,
        // 2 Game Type (int32, compression)
        public readonly int $gameType,
        // 3 Position (Vec3)
        public readonly float $x,
        public readonly float $y,
        public readonly float $z,
        // 4 Rotation (Vec2)
        public readonly float $pitch = 0.0,
        public readonly float $yaw = 0.0,
        // 5 Settings
        public readonly ?LevelSettings $settings = null,
        // 6 Level ID
        public readonly string $levelId = '',
        // 7 Level Name
        public readonly string $levelName = '',
        // 8 Template Content Identity
        public readonly string $templateContentIdentity = '',
        // 9 Is Trial
        public readonly bool $isTrial = false,
        // 10 Movement Settings
        public readonly int $rewindHistorySize = 40,
        public readonly bool $serverAuthBlockBreaking = true,
        // 11 Level Current Time (uint64, sıkıştırmasız)
        public readonly int $levelCurrentTime = 0,
        // 12 Enchantment Seed (int32, compression)
        public readonly int $enchantmentSeed = 0,
        // 13 Block Properties (boş)
        public readonly int $blockPropertyCount = 0,
        // 14 Multiplayer Correlation Id
        public readonly string $multiplayerCorrelationId = '',
        // 15 Enable Item Stack Net Manager
        public readonly bool $enableItemStackNetManager = false,
        // 16 Server Version
        public readonly string $serverVersion = 'PickaxeMP',
        // 18 Server Block Type Registry Checksum (uint64)
        public readonly int $blockTypeRegistryChecksum = 0,
        // 19 World Template ID
        public readonly ?Uuid $worldTemplateId = null,
        // 20 Server Enabled ClientSide Generation
        public readonly bool $serverEnabledClientSideGeneration = false,
        // 21 BlockNetworkIds Are Hashes
        public readonly bool $blockNetworkIdsAreHashes = true,
        // 22 NetworkPermissions
        public readonly bool $serverAuthSoundEnabled = false,
        // 24 Server Telemetry Data
        public readonly string $telemetryServerId = '',
        public readonly string $telemetryScenarioId = '',
        public readonly string $telemetryWorldId = '',
        public readonly string $telemetryOwnerId = ''
    ) {
    }

    public function getId(): int
    {
        return PacketId::START_GAME;
    }

    public function encode(BinaryWriter $writer): void
    {
        // 0 Entity ID — varint64
        $writer->writeVarLong($this->entityId);

        // 1 Runtime ID — varuint64
        $writer->writeVarULong($this->runtimeId);

        // 2 Game Type — varint32
        $writer->writeVarInt($this->gameType);

        // 3 Position
        $writer->writeFloat($this->x);
        $writer->writeFloat($this->y);
        $writer->writeFloat($this->z);

        // 4 Rotation
        $writer->writeFloat($this->pitch);
        $writer->writeFloat($this->yaw);

        // 5 Settings
        ($this->settings ?? new LevelSettings())->encode($writer);

        // 6-8 Strings
        $writer->writeString($this->levelId);
        $writer->writeString($this->levelName);
        $writer->writeString($this->templateContentIdentity);

        // 9 Is Trial
        $writer->writeBool($this->isTrial);

        // 10 Movement Settings
        $writer->writeVarInt($this->rewindHistorySize);
        $writer->writeBool($this->serverAuthBlockBreaking);

        // 11 Level Current Time — uint64 sıkıştırmasız
        $writer->writeUint64($this->levelCurrentTime);

        // 12 Enchantment Seed — varint32
        $writer->writeVarInt($this->enchantmentSeed);

        // 13 Block Properties — boş dizi
        $writer->writeVarUInt($this->blockPropertyCount);

        // 14 Multiplayer Correlation Id
        $writer->writeString($this->multiplayerCorrelationId);

        // 15 Enable Item Stack Net Manager
        $writer->writeBool($this->enableItemStackNetManager);

        // 16 Server Version
        $writer->writeString($this->serverVersion);

        // 17 Player Property Data — boş ağ NBT compound
        $writer->write("\x0A\x00\x00");

        // 18 Server Block Type Registry Checksum — uint64 sıkıştırmasız
        $writer->writeUint64($this->blockTypeRegistryChecksum);

        // 19 World Template ID
        $writer->writeUuid($this->worldTemplateId ?? Uuid::empty());

        // 20 Server Enabled ClientSide Generation
        $writer->writeBool($this->serverEnabledClientSideGeneration);

        // 21 BlockNetworkIds Are Hashes
        $writer->writeBool($this->blockNetworkIdsAreHashes);

        // 22 NetworkPermissions
        $writer->writeBool($this->serverAuthSoundEnabled);

        // 23 Server Configuration Join Info — 3 opsiyonel nesne, hepsi yok
        $writer->writeByte(0);
        $writer->writeByte(0);
        $writer->writeByte(0);

        // 24 Server Telemetry Data
        $writer->writeString($this->telemetryServerId);
        $writer->writeString($this->telemetryScenarioId);
        $writer->writeString($this->telemetryWorldId);
        $writer->writeString($this->telemetryOwnerId);
    }

    public static function decode(BinaryReader $reader): static
    {
        $entityId = $reader->readVarLong();
        $runtimeId = $reader->readVarULong();
        $gameType = $reader->readVarInt();

        $x = $reader->readFloat();
        $y = $reader->readFloat();
        $z = $reader->readFloat();
        $pitch = $reader->readFloat();
        $yaw = $reader->readFloat();

        $settings = LevelSettings::decode($reader);

        $levelId = $reader->readString();
        $levelName = $reader->readString();
        $templateContentIdentity = $reader->readString();
        $isTrial = $reader->readBool();

        $rewindHistorySize = $reader->readVarInt();
        $serverAuthBlockBreaking = $reader->readBool();

        $levelCurrentTime = $reader->readUint64();
        $enchantmentSeed = $reader->readVarInt();
        $blockPropertyCount = $reader->readVarUInt();

        // Block property girişleri: name + NBT. Boş dizi varsayımıyla
        // ayrıştırma; doluysa desteklenmez (Phase 4 kapsamı dışı).
        if ($blockPropertyCount !== 0) {
            throw new \PickaxeMP\protocol\ProtocolException(
                'Block Properties dolu geldi; Phase 4 kapsaminda degil'
            );
        }

        $multiplayerCorrelationId = $reader->readString();
        $enableItemStackNetManager = $reader->readBool();
        $serverVersion = $reader->readString();

        // 17 Player Property Data — boş NBT (0x0A 0x00 0x00)
        $nbtTag = $reader->readByte();

        if ($nbtTag !== 0x0A) {
            throw new \PickaxeMP\protocol\ProtocolException('Player Property Data: compound bekleniyordu');
        }

        $nameLength = $reader->readVarUInt();

        if ($nameLength !== 0) {
            $reader->read($nameLength);
        }

        $endTag = $reader->readByte();

        if ($endTag !== 0x00) {
            throw new \PickaxeMP\protocol\ProtocolException('Player Property Data: gecersiz NBT');
        }

        $blockTypeRegistryChecksum = $reader->readUint64();
        $worldTemplateId = $reader->readUuid();
        $serverEnabledClientSideGeneration = $reader->readBool();
        $blockNetworkIdsAreHashes = $reader->readBool();
        $serverAuthSoundEnabled = $reader->readBool();

        // 23 Server Configuration Join Info — 3 opsiyonel nesne
        $gatheringPresent = $reader->readBool();
        $clientStorePresent = $reader->readBool();
        $presencePresent = $reader->readBool();

        if ($gatheringPresent || $clientStorePresent || $presencePresent) {
            throw new \PickaxeMP\protocol\ProtocolException(
                'Server Configuration Join Info dolu geldi; Phase 4 kapsaminda degil'
            );
        }

        $telemetryServerId = $reader->readString();
        $telemetryScenarioId = $reader->readString();
        $telemetryWorldId = $reader->readString();
        $telemetryOwnerId = $reader->readString();

        return new static(
            entityId: $entityId,
            runtimeId: $runtimeId,
            gameType: $gameType,
            x: $x,
            y: $y,
            z: $z,
            pitch: $pitch,
            yaw: $yaw,
            settings: $settings,
            levelId: $levelId,
            levelName: $levelName,
            templateContentIdentity: $templateContentIdentity,
            isTrial: $isTrial,
            rewindHistorySize: $rewindHistorySize,
            serverAuthBlockBreaking: $serverAuthBlockBreaking,
            levelCurrentTime: $levelCurrentTime,
            enchantmentSeed: $enchantmentSeed,
            blockPropertyCount: $blockPropertyCount,
            multiplayerCorrelationId: $multiplayerCorrelationId,
            enableItemStackNetManager: $enableItemStackNetManager,            serverVersion: $serverVersion,
            blockTypeRegistryChecksum: $blockTypeRegistryChecksum,
            worldTemplateId: $worldTemplateId,
            serverEnabledClientSideGeneration: $serverEnabledClientSideGeneration,
            blockNetworkIdsAreHashes: $blockNetworkIdsAreHashes,
            serverAuthSoundEnabled: $serverAuthSoundEnabled,
            telemetryServerId: $telemetryServerId,
            telemetryScenarioId: $telemetryScenarioId,
            telemetryWorldId: $telemetryWorldId,
            telemetryOwnerId: $telemetryOwnerId
        );
    }
}
