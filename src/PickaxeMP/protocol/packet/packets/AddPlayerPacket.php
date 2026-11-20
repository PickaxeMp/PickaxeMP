<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\binary\Uuid;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\types\EntityLink;
use PickaxeMP\protocol\packet\types\NetworkItemStackDescriptor;
use PickaxeMP\protocol\packet\types\PassengerOfBlockArguments;
use PickaxeMP\protocol\packet\types\PropertySyncData;
use PickaxeMP\protocol\packet\types\SerializedAbilitiesData;
use PickaxeMP\protocol\packet\types\SynchedActorData;
use PickaxeMP\protocol\packet\types\Vec3;
use PickaxeMP\protocol\ProtocolException;

/**
 * `AddPlayerPacket` (id **12**, sunucu → istemci).
 *
 * Sema (Mojang v1.26.65, 2225; `AddPlayerPacketPayload.json`, ordinal 0..16):
 *
 * ```
 *  0 UUID                  mce::UUID (MSB uint64 LE + LSB uint64 LE)
 *  1 Player Name           string
 *  2 Target Runtime ID     varuint64
 *  3 Platform Chat Id      string
 *  4 Position              Vec3
 *  5 Velocity              Vec3
 *  6 Rotation              Vec2 (pitch, yaw)
 *  7 Y-Head Rotation       float
 *  8 Carried Item          NetworkItemStackDescriptor
 *  9 Player Game Type      varint32
 * 10 Entity Data           SynchedActorDataList (varuint32 sayi)
 * 11 Synched Properties    PropertySyncData
 * 12 Abilities Data        SerializedAbilitiesData
 * 13 Actor Links           varuint32 sayi + ActorLink
 * 14 Passenger Block Data  istege bagli (varlik bayti)
 * 15 Device Id             string
 * 16 Build Platform        varint32
 * ```
 *
 * `Entity Data` (metadata) Phase 10.5'te `SynchedActorData` olarak kabul
 * edilir; isim etiketi ve bayraklar `PlayerMetadata` tarafından üretilir.
 * Boş liste varsayılandır ve geriye dönük uyumludur.
 */
final class AddPlayerPacket implements PacketInterface
{
    public const MAX_LINKS = 64;

    /**
     * @param list<EntityLink> $actorLinks
     */
    public function __construct(
        public readonly Uuid $uuid,
        public readonly string $playerName,
        public readonly int $targetRuntimeId,
        public readonly string $platformChatId,
        public readonly Vec3 $position,
        public readonly Vec3 $velocity,
        public readonly float $pitch,
        public readonly float $yaw,
        public readonly float $headYaw,
        public readonly NetworkItemStackDescriptor $carriedItem,
        public readonly int $playerGameType,
        public readonly PropertySyncData $synchedProperties,
        public readonly SerializedAbilitiesData $abilitiesData,
        public readonly array $actorLinks,
        public readonly string $deviceId,
        public readonly int $buildPlatform,
        public readonly SynchedActorData $entityData = new SynchedActorData(),
        public readonly ?PassengerOfBlockArguments $passengerBlockData = null
    ) {
        if (count($actorLinks) > self::MAX_LINKS) {
            throw ProtocolException::lengthTooLarge('add player links', count($actorLinks), self::MAX_LINKS);
        }
    }

    public function getId(): int
    {
        return PacketId::ADD_PLAYER;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeUuid($this->uuid);
        $writer->writeString($this->playerName, 128);
        $writer->writeVarULong($this->targetRuntimeId);
        $writer->writeString($this->platformChatId, 128);
        $this->position->encode($writer);
        $this->velocity->encode($writer);
        $writer->writeFloat($this->pitch);
        $writer->writeFloat($this->yaw);
        $writer->writeFloat($this->headYaw);
        $this->carriedItem->encode($writer);
        $writer->writeVarInt($this->playerGameType);

        // 10 Entity Data — isim etiketi ve varlik bayraklari
        $this->entityData->encode($writer);

        // 11 Synched Properties
        $this->synchedProperties->encode($writer);

        // 12 Abilities Data
        $this->abilitiesData->encode($writer);

        // 13 Actor Links
        $writer->writeVarUInt(count($this->actorLinks));

        foreach ($this->actorLinks as $link) {
            $link->encode($writer);
        }

        // 14 Passenger Block Data — istege bagli
        $writer->writeOptional($this->passengerBlockData !== null, function (BinaryWriter $w): void {
            $this->passengerBlockData?->encode($w);
        });

        // 15 Device Id
        $writer->writeString($this->deviceId, 128);

        // 16 Build Platform
        $writer->writeVarInt($this->buildPlatform);
    }

    public static function decode(BinaryReader $reader): static
    {
        $uuid = $reader->readUuid();
        $playerName = $reader->readString(128);
        $targetRuntimeId = $reader->readVarULong();
        $platformChatId = $reader->readString(128);
        $position = Vec3::decode($reader);
        $velocity = Vec3::decode($reader);
        $pitch = $reader->readFloat();
        $yaw = $reader->readFloat();
        $headYaw = $reader->readFloat();
        $carriedItem = NetworkItemStackDescriptor::decode($reader);
        $playerGameType = $reader->readVarInt();

        $entityData = SynchedActorData::decode($reader);

        $synchedProperties = PropertySyncData::decode($reader);
        $abilitiesData = SerializedAbilitiesData::decode($reader);

        $linkCount = $reader->readVarUInt();

        if ($linkCount > self::MAX_LINKS) {
            throw ProtocolException::lengthTooLarge('add player links', $linkCount, self::MAX_LINKS);
        }

        $actorLinks = [];

        for ($i = 0; $i < $linkCount; $i++) {
            $actorLinks[] = EntityLink::decode($reader);
        }

        $passengerBlockData = $reader->readBool() ? PassengerOfBlockArguments::decode($reader) : null;
        $deviceId = $reader->readString(128);
        $buildPlatform = $reader->readVarInt();

        return new static(
            uuid: $uuid,
            playerName: $playerName,
            targetRuntimeId: $targetRuntimeId,
            platformChatId: $platformChatId,
            position: $position,
            velocity: $velocity,
            pitch: $pitch,
            yaw: $yaw,
            headYaw: $headYaw,
            carriedItem: $carriedItem,
            playerGameType: $playerGameType,
            synchedProperties: $synchedProperties,
            abilitiesData: $abilitiesData,
            actorLinks: $actorLinks,
            deviceId: $deviceId,
            buildPlatform: $buildPlatform,
            entityData: $entityData,
            passengerBlockData: $passengerBlockData
        );
    }
}
