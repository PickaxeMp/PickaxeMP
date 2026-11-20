<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\types\EntityLink;
use PickaxeMP\protocol\packet\types\PassengerOfBlockArguments;
use PickaxeMP\protocol\packet\types\PropertySyncData;
use PickaxeMP\protocol\packet\types\SyncedAttribute;
use PickaxeMP\protocol\packet\types\SynchedActorData;
use PickaxeMP\protocol\packet\types\Vec3;
use PickaxeMP\protocol\ProtocolException;

/**
 * `AddActorPacket` (id **13**, sunucu → istemci).
 *
 * Sema (Mojang v1.26.65, 2225; `AddActorPacketPayload.json`, ordinal 0..12):
 *
 * ```
 *  0 Target Actor ID      int64 (Compression → varlong)
 *  1 Target Runtime ID    varuint64
 *  2 Actor Type           string
 *  3 Position             Vec3
 *  4 Velocity             Vec3
 *  5 Rotation             Vec2 (pitch, yaw)
 *  6 Y Head Rotation      float
 *  7 Y Body Rotation      float
 *  8 Attributes List      varuint32 sayi + SyncedAttribute
 *  9 Actor Data           SynchedActorDataList (varuint32 sayi)
 * 10 Synched Properties   PropertySyncData
 * 11 Actor Links          varuint32 sayi + ActorLink
 * 12 Passenger Block Data istege bagli (varlik bayti)
 * ```
 *
 * `Actor Data` (metadata) Phase 6 kapsaminda uretilmez; yalnizca bos liste
 * kabul edilir, aksi halde acik `ProtocolException` uretilir.
 */
final class AddActorPacket implements PacketInterface
{
    public const MAX_LINKS = 64;
    public const MAX_ATTRIBUTES = 64;

    /**
     * @param list<SyncedAttribute> $attributes
     * @param list<EntityLink> $actorLinks
     */
    public function __construct(
        public readonly int $targetActorId,
        public readonly int $targetRuntimeId,
        public readonly string $actorType,
        public readonly Vec3 $position,
        public readonly Vec3 $velocity,
        public readonly float $pitch,
        public readonly float $yaw,
        public readonly float $headYaw,
        public readonly float $bodyYaw,
        public readonly array $attributes,
        public readonly PropertySyncData $synchedProperties,
        public readonly array $actorLinks,
        public readonly SynchedActorData $actorData = new SynchedActorData(),
        public readonly ?PassengerOfBlockArguments $passengerBlockData = null
    ) {
        if (count($attributes) > self::MAX_ATTRIBUTES) {
            throw ProtocolException::lengthTooLarge(
                'add actor attributes',
                count($attributes),
                self::MAX_ATTRIBUTES
            );
        }

        if (count($actorLinks) > self::MAX_LINKS) {
            throw ProtocolException::lengthTooLarge('add actor links', count($actorLinks), self::MAX_LINKS);
        }
    }

    public function getId(): int
    {
        return PacketId::ADD_ACTOR;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeVarLong($this->targetActorId);
        $writer->writeVarULong($this->targetRuntimeId);
        $writer->writeString($this->actorType, 256);
        $this->position->encode($writer);
        $this->velocity->encode($writer);
        $writer->writeFloat($this->pitch);
        $writer->writeFloat($this->yaw);
        $writer->writeFloat($this->headYaw);
        $writer->writeFloat($this->bodyYaw);

        $writer->writeVarUInt(count($this->attributes));

        foreach ($this->attributes as $attribute) {
            $attribute->encode($writer);
        }

        // 9 Actor Data — varlik meta verisi (bos olabilir)
        $this->actorData->encode($writer);

        // 10 Synched Properties
        $this->synchedProperties->encode($writer);

        // 11 Actor Links
        $writer->writeVarUInt(count($this->actorLinks));

        foreach ($this->actorLinks as $link) {
            $link->encode($writer);
        }

        // 12 Passenger Block Data — istege bagli
        $writer->writeOptional($this->passengerBlockData !== null, function (BinaryWriter $w): void {
            $this->passengerBlockData?->encode($w);
        });
    }

    public static function decode(BinaryReader $reader): static
    {
        $targetActorId = $reader->readVarLong();
        $targetRuntimeId = $reader->readVarULong();
        $actorType = $reader->readString(256);
        $position = Vec3::decode($reader);
        $velocity = Vec3::decode($reader);
        $pitch = $reader->readFloat();
        $yaw = $reader->readFloat();
        $headYaw = $reader->readFloat();
        $bodyYaw = $reader->readFloat();

        $attributeCount = $reader->readVarUInt();

        if ($attributeCount > self::MAX_ATTRIBUTES) {
            throw ProtocolException::lengthTooLarge(
                'add actor attributes',
                $attributeCount,
                self::MAX_ATTRIBUTES
            );
        }

        $attributes = [];

        for ($i = 0; $i < $attributeCount; $i++) {
            $attributes[] = SyncedAttribute::decode($reader);
        }

        $actorData = SynchedActorData::decode($reader);

        $synchedProperties = PropertySyncData::decode($reader);

        $linkCount = $reader->readVarUInt();

        if ($linkCount > self::MAX_LINKS) {
            throw ProtocolException::lengthTooLarge('add actor links', $linkCount, self::MAX_LINKS);
        }

        $actorLinks = [];

        for ($i = 0; $i < $linkCount; $i++) {
            $actorLinks[] = EntityLink::decode($reader);
        }

        $passengerBlockData = $reader->readBool() ? PassengerOfBlockArguments::decode($reader) : null;

        return new static(
            targetActorId: $targetActorId,
            targetRuntimeId: $targetRuntimeId,
            actorType: $actorType,
            position: $position,
            velocity: $velocity,
            pitch: $pitch,
            yaw: $yaw,
            headYaw: $headYaw,
            bodyYaw: $bodyYaw,
            attributes: $attributes,
            synchedProperties: $synchedProperties,
            actorLinks: $actorLinks,
            actorData: $actorData,
            passengerBlockData: $passengerBlockData
        );
    }
}
