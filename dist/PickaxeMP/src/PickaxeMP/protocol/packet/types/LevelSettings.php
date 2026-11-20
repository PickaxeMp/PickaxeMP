<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;

/**
 * `LevelSettings` (Mojang şeması, 2225) — **51 alan**, ordinal sırası korunur.
 *
 * ## Dürüst notlar (alan bazlı)
 *
 * - `Generator Type` int32: 1 = flat (eski `GeneratorType::Flat`). Şemada
 *   `"Compression"` yok → düz int32.
 * - `Rule Data`: `GameRule[]`. Boş liste geçerlidir ve PickaxeMP Phase 4'te
 *   boş gönderir (`count = 0`). Kural göndermek Phase 5+ işidir.
 * - `Experiments`: `"No size compression"` → sayım sabit **uint32**.
 * - `Education Product ID`: şemada tip belirtilmemiş; **string** varsayıldı.
 * - `Player Permissions` int8: 0=visitor, 1=member, 2=operator, 3=custom.
 * - `Editor Level Migration Version`: int8, 0=Legacy, 1=EditorAchievements.
 *
 * Bu sınıf **tam sırayı** uygular; tek bir alanın atlanması istemcide
 * ayrıştırmayı kaydırır ve bağlantı düşer. Sıra testlerle kilitlenmiştir.
 */
final class LevelSettings
{
    /** Generator type: flat dünya. */
    public const GENERATOR_FLAT = 1;

    public function __construct(
        // 0
        public readonly int $seed = 0,
        // 1
        public readonly SpawnSettings $spawnSettings = new SpawnSettings(),
        // 2
        public readonly int $generatorType = self::GENERATOR_FLAT,
        // 3
        public readonly int $gameType = 0,
        // 4
        public readonly bool $isHardcore = false,
        // 5
        public readonly int $gameDifficulty = 1,
        // 6
        public readonly int $defaultSpawnX = 0,
        public readonly int $defaultSpawnY = 64,
        public readonly int $defaultSpawnZ = 0,
        // 7
        public readonly bool $achievementsDisabled = true,
        // 8
        public readonly int $editorWorldType = 0,
        // 9
        public readonly bool $isCreatedInEditor = false,
        // 10
        public readonly bool $isExportedFromEditor = false,
        // 11
        public readonly int $editorLevelMigrationVersion = 0,
        // 12
        public readonly int $dayCycleStopTime = -1,   // -1 = normal döngü
        // 13
        public readonly int $educationEditionOffer = 0,
        // 14
        public readonly bool $educationFeaturesEnabled = false,
        // 15
        public readonly string $educationProductId = '',
        // 16-17
        public readonly float $rainLevel = 0.0,
        public readonly float $lightningLevel = 0.0,
        // 18
        public readonly bool $hasConfirmedPlatformLockedContent = false,
        // 19
        public readonly bool $multiplayerGameIntent = true,
        // 20
        public readonly bool $lanBroadcastIntent = true,
        // 21
        public readonly int $xboxLiveBroadcastSetting = 0,
        // 22
        public readonly int $platformBroadcastSetting = 0,
        // 23
        public readonly bool $commandsEnabled = true,
        // 24
        public readonly bool $texturePacksRequired = false,
        // 25 — boş kural listesi (Phase 5'te doldurulacak)
        public readonly int $ruleCount = 0,
        // 26 — Experiments
        public readonly int $experimentToggleCount = 0,
        public readonly bool $experimentsEverToggled = false,
        // 27
        public readonly bool $hasBonusChestEnabled = false,
        // 28
        public readonly bool $startWithMapEnabled = false,
        // 29
        public readonly int $playerPermissions = 1,   // member
        // 30
        public readonly int $serverChunkTickRange = 4,
        // 31-36
        public readonly bool $hasLockedBehaviorPack = false,
        public readonly bool $hasLockedResourcePack = false,
        public readonly bool $isFromLockedTemplate = false,
        public readonly bool $useMsaGamertagsOnly = false,
        public readonly bool $isFromWorldTemplate = false,
        public readonly bool $isWorldTemplateOptionLocked = false,
        // 37-40
        public readonly bool $onlySpawnV1Villagers = false,
        public readonly bool $personaDisabled = false,
        public readonly bool $customSkinsDisabled = false,
        public readonly bool $emoteChatMuted = false,
        // 41
        public readonly string $baseGameVersion = '',
        // 42-43
        public readonly int $limitedWorldWidth = 0,
        public readonly int $limitedWorldDepth = 0,
        // 44
        public readonly bool $netherType = false,
        // 45
        public readonly string $eduButtonName = '',
        public readonly string $eduLinkUri = '',
        // 46
        public readonly bool $overrideForceExperimentalGameplay = false,
        // 47
        public readonly int $chatRestrictionLevel = 0,
        // 48
        public readonly bool $disablePlayerInteractions = false,
        // 49
        public readonly int $serverEditorConnectionPolicy = 0,
        // 50
        public readonly bool $allowAnonymousBlockDropsInEditorWorlds = false
    ) {
    }

    public function encode(BinaryWriter $writer): void
    {
        // 0 Seed (uint64, sıkıştırmasız)
        $writer->writeUint64($this->seed);

        // 1 Spawn Settings
        $this->spawnSettings->encode($writer);

        // 2 Generator Type (int32 düz)
        $writer->writeInt32($this->generatorType);

        // 3 Game Type (int32 düz)
        $writer->writeInt32($this->gameType);

        // 4 Is Hardcore
        $writer->writeBool($this->isHardcore);

        // 5 Game Difficulty
        $writer->writeInt32($this->gameDifficulty);

        // 6 Default Spawn Block Position (BlockPos: varint32 × 3)
        $writer->writeVarInt($this->defaultSpawnX);
        $writer->writeVarInt($this->defaultSpawnY);
        $writer->writeVarInt($this->defaultSpawnZ);

        // 7
        $writer->writeBool($this->achievementsDisabled);

        // 8 Editor World Type (int32, Compression)
        $writer->writeVarInt($this->editorWorldType);

        // 9-10
        $writer->writeBool($this->isCreatedInEditor);
        $writer->writeBool($this->isExportedFromEditor);

        // 11 Editor Level Migration Version (int8, Compression)
        $writer->writeVarInt($this->editorLevelMigrationVersion);

        // 12 Day Cycle Stop Time (int32, Compression)
        $writer->writeVarInt($this->dayCycleStopTime);

        // 13 Education Edition Offer (uint32, Compression)
        $writer->writeVarUInt($this->educationEditionOffer);

        // 14
        $writer->writeBool($this->educationFeaturesEnabled);

        // 15 Education Product ID
        $writer->writeString($this->educationProductId);

        // 16-17
        $writer->writeFloat($this->rainLevel);
        $writer->writeFloat($this->lightningLevel);

        // 18-20
        $writer->writeBool($this->hasConfirmedPlatformLockedContent);
        $writer->writeBool($this->multiplayerGameIntent);
        $writer->writeBool($this->lanBroadcastIntent);

        // 21-22
        $writer->writeInt32($this->xboxLiveBroadcastSetting);
        $writer->writeInt32($this->platformBroadcastSetting);

        // 23-24
        $writer->writeBool($this->commandsEnabled);
        $writer->writeBool($this->texturePacksRequired);

        // 25 Rule Data — boş liste
        $writer->writeVarUInt($this->ruleCount);

        // 26 Experiments — "No size compression" → uint32 sayım
        $writer->writeUint32($this->experimentToggleCount);
        $writer->writeBool($this->experimentsEverToggled);

        // 27-28
        $writer->writeBool($this->hasBonusChestEnabled);
        $writer->writeBool($this->startWithMapEnabled);

        // 29 Player Permissions (int8)
        $writer->writeSignedByte($this->playerPermissions);

        // 30
        $writer->writeInt32($this->serverChunkTickRange);

        // 31-36
        $writer->writeBool($this->hasLockedBehaviorPack);
        $writer->writeBool($this->hasLockedResourcePack);
        $writer->writeBool($this->isFromLockedTemplate);
        $writer->writeBool($this->useMsaGamertagsOnly);
        $writer->writeBool($this->isFromWorldTemplate);
        $writer->writeBool($this->isWorldTemplateOptionLocked);

        // 37-40
        $writer->writeBool($this->onlySpawnV1Villagers);
        $writer->writeBool($this->personaDisabled);
        $writer->writeBool($this->customSkinsDisabled);
        $writer->writeBool($this->emoteChatMuted);

        // 41 Base Game Version
        $writer->writeString($this->baseGameVersion);

        // 42-43
        $writer->writeInt32($this->limitedWorldWidth);
        $writer->writeInt32($this->limitedWorldDepth);

        // 44
        $writer->writeBool($this->netherType);

        // 45 Edu Shared Uri Resource
        $writer->writeString($this->eduButtonName);
        $writer->writeString($this->eduLinkUri);

        // 46
        $writer->writeBool($this->overrideForceExperimentalGameplay);

        // 47 Chat Restriction Level (uint8)
        $writer->writeByte($this->chatRestrictionLevel);

        // 48
        $writer->writeBool($this->disablePlayerInteractions);

        // 49
        $writer->writeInt32($this->serverEditorConnectionPolicy);

        // 50
        $writer->writeBool($this->allowAnonymousBlockDropsInEditorWorlds);
    }

    public static function decode(BinaryReader $reader): self
    {
        return new self(
            seed: $reader->readUint64(),
            spawnSettings: SpawnSettings::decode($reader),
            generatorType: $reader->readInt32(),
            gameType: $reader->readInt32(),
            isHardcore: $reader->readBool(),
            gameDifficulty: $reader->readInt32(),
            defaultSpawnX: $reader->readVarInt(),
            defaultSpawnY: $reader->readVarInt(),
            defaultSpawnZ: $reader->readVarInt(),
            achievementsDisabled: $reader->readBool(),
            editorWorldType: $reader->readVarInt(),
            isCreatedInEditor: $reader->readBool(),
            isExportedFromEditor: $reader->readBool(),
            editorLevelMigrationVersion: $reader->readVarInt(),
            dayCycleStopTime: $reader->readVarInt(),
            educationEditionOffer: $reader->readVarUInt(),
            educationFeaturesEnabled: $reader->readBool(),
            educationProductId: $reader->readString(),
            rainLevel: $reader->readFloat(),
            lightningLevel: $reader->readFloat(),
            hasConfirmedPlatformLockedContent: $reader->readBool(),
            multiplayerGameIntent: $reader->readBool(),
            lanBroadcastIntent: $reader->readBool(),
            xboxLiveBroadcastSetting: $reader->readInt32(),
            platformBroadcastSetting: $reader->readInt32(),
            commandsEnabled: $reader->readBool(),
            texturePacksRequired: $reader->readBool(),
            ruleCount: $reader->readVarUInt(),
            experimentToggleCount: $reader->readUint32(),
            experimentsEverToggled: $reader->readBool(),
            hasBonusChestEnabled: $reader->readBool(),
            startWithMapEnabled: $reader->readBool(),
            playerPermissions: $reader->readSignedByte(),
            serverChunkTickRange: $reader->readInt32(),
            hasLockedBehaviorPack: $reader->readBool(),
            hasLockedResourcePack: $reader->readBool(),
            isFromLockedTemplate: $reader->readBool(),
            useMsaGamertagsOnly: $reader->readBool(),
            isFromWorldTemplate: $reader->readBool(),
            isWorldTemplateOptionLocked: $reader->readBool(),
            onlySpawnV1Villagers: $reader->readBool(),
            personaDisabled: $reader->readBool(),
            customSkinsDisabled: $reader->readBool(),
            emoteChatMuted: $reader->readBool(),
            baseGameVersion: $reader->readString(),
            limitedWorldWidth: $reader->readInt32(),
            limitedWorldDepth: $reader->readInt32(),
            netherType: $reader->readBool(),
            eduButtonName: $reader->readString(),
            eduLinkUri: $reader->readString(),
            overrideForceExperimentalGameplay: $reader->readBool(),
            chatRestrictionLevel: $reader->readByte(),
            disablePlayerInteractions: $reader->readBool(),
            serverEditorConnectionPolicy: $reader->readInt32(),
            allowAnonymousBlockDropsInEditorWorlds: $reader->readBool()
        );
    }
}
