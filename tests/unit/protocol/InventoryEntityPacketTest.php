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
use PickaxeMP\protocol\packet\PacketRegistry;
use PickaxeMP\protocol\packet\packets\AddActorPacket;
use PickaxeMP\protocol\packet\packets\AddPlayerPacket;
use PickaxeMP\protocol\packet\packets\ContainerClosePacket;
use PickaxeMP\protocol\packet\packets\ContainerOpenPacket;
use PickaxeMP\protocol\packet\packets\InventoryContentPacket;
use PickaxeMP\protocol\packet\packets\InventorySlotPacket;
use PickaxeMP\protocol\packet\packets\InventoryTransactionPacket;
use PickaxeMP\protocol\packet\packets\MobEquipmentPacket;
use PickaxeMP\protocol\packet\packets\MoveActorAbsolutePacket;
use PickaxeMP\protocol\packet\packets\RemoveActorPacket;
use PickaxeMP\protocol\packet\types\AbilityLayer;
use PickaxeMP\protocol\packet\types\BlockPos;
use PickaxeMP\protocol\packet\types\EntityLink;
use PickaxeMP\protocol\packet\types\FullContainerName;
use PickaxeMP\protocol\packet\types\InventoryAction;
use PickaxeMP\protocol\packet\types\InventorySource;
use PickaxeMP\protocol\packet\types\NetworkItemStackDescriptor;
use PickaxeMP\protocol\packet\types\PropertySyncData;
use PickaxeMP\protocol\packet\types\SerializedAbilitiesData;
use PickaxeMP\protocol\packet\types\SyncedAttribute;
use PickaxeMP\protocol\packet\types\SynchedActorData;
use PickaxeMP\protocol\packet\types\SynchedActorDataEntry;
use PickaxeMP\protocol\packet\types\Vec3;
use PickaxeMP\protocol\ProtocolEngine;
use PickaxeMP\protocol\ProtocolException;
use PickaxeMP\player\PlayerMetadata;

final class InventoryEntityPacketTest extends TestCase
{
    private function engine(): ProtocolEngine
    {
        return new ProtocolEngine(
            PacketRegistry::withSupportedPackets(),
            new CompressionManager()
        );
    }

    private function c2s(): PacketContext
    {
        return new PacketContext('test', PacketDirection::ClientToServer);
    }

    private function s2c(): PacketContext
    {
        return new PacketContext('test', PacketDirection::ServerToClient);
    }

    private function emptyItem(): NetworkItemStackDescriptor
    {
        return NetworkItemStackDescriptor::empty();
    }

    private function stone(int $count = 1): NetworkItemStackDescriptor
    {
        return new NetworkItemStackDescriptor(
            id: 264,
            count: $count,
            auxValue: 0,
            blockRuntimeId: 1,
            netId: null,
            userData: NetworkItemStackDescriptor::EMPTY_USER_DATA
        );
    }

    private function abilities(): SerializedAbilitiesData
    {
        return new SerializedAbilitiesData(
            targetPlayerRawId: 1,
            playerPermissions: 1,
            commandPermissions: 0,
            layers: [new AbilityLayer(0, 0, 0, 0.05, 0.05, 0.1)]
        );
    }

    // --- Packet IDs -----------------------------------------------------

    public function testInventoryEntityPacketIdsMatchOfficialSchema(): void
    {
        self::assertSame(12, PacketId::ADD_PLAYER);
        self::assertSame(13, PacketId::ADD_ACTOR);
        self::assertSame(14, PacketId::REMOVE_ACTOR);
        self::assertSame(18, PacketId::MOVE_ACTOR_ABSOLUTE);
        self::assertSame(30, PacketId::INVENTORY_TRANSACTION);
        self::assertSame(31, PacketId::MOB_EQUIPMENT);
        self::assertSame(46, PacketId::CONTAINER_OPEN);
        self::assertSame(47, PacketId::CONTAINER_CLOSE);
        self::assertSame(49, PacketId::INVENTORY_CONTENT);
        self::assertSame(50, PacketId::INVENTORY_SLOT);
    }

    public function testRegistryContainsInventoryEntityPacketsWithDirections(): void
    {
        $registry = PacketRegistry::withSupportedPackets();

        self::assertSame(PacketDirection::ClientToServer, $registry->directionFor(PacketId::INVENTORY_TRANSACTION));

        // Phase 10.5: MobEquipment iki yonludur (istemci secimini bildirir,
        // sunucu digerlerine yayinlar).
        self::assertSame(PacketDirection::Both, $registry->directionFor(PacketId::MOB_EQUIPMENT));

        foreach ([
            PacketId::CONTAINER_OPEN,
            PacketId::CONTAINER_CLOSE,
            PacketId::INVENTORY_CONTENT,
            PacketId::INVENTORY_SLOT,
            PacketId::ADD_PLAYER,
            PacketId::ADD_ACTOR,
            PacketId::REMOVE_ACTOR,
            PacketId::MOVE_ACTOR_ABSOLUTE,
        ] as $id) {
            self::assertTrue($registry->has($id), "Kayitli olmali: {$id}");
            self::assertSame(PacketDirection::ServerToClient, $registry->directionFor($id));
        }
    }

    // --- NetworkItemStackDescriptor -------------------------------------

    public function testEmptyDescriptorWireFormat(): void
    {
        $writer = new BinaryWriter();
        $this->emptyItem()->encode($writer);

        // int16 0 + uint16 0 + varuint 0 + bool 0 + varuint 0 + string length 0
        self::assertSame("\x00\x00\x00\x00\x00\x00\x00\x00", $writer->bytes());
    }

    public function testDescriptorRoundTripWithNetIdAndUserData(): void
    {
        $original = new NetworkItemStackDescriptor(
            id: 264,
            count: 5,
            auxValue: 3,
            blockRuntimeId: 7,
            netId: 9,
            userData: 'AB'
        );

        $writer = new BinaryWriter();
        $original->encode($writer);

        $decoded = NetworkItemStackDescriptor::decode(new BinaryReader($writer->bytes()));

        self::assertSame(264, $decoded->id);
        self::assertSame(5, $decoded->count);
        self::assertSame(3, $decoded->auxValue);
        self::assertSame(7, $decoded->blockRuntimeId);
        self::assertSame(9, $decoded->netId);
        self::assertSame('AB', $decoded->userData);
        self::assertFalse($decoded->isEmpty());
    }

    public function testDescriptorFieldOrderMatchesWire(): void
    {
        $writer = new BinaryWriter();
        $this->stone(5)->encode($writer);

        // id 264 LE = 08 01, count 5 LE = 05 00, aux 0, no net id, block runtime 1, userdata 4 bytes
        self::assertSame("\x08\x01\x05\x00\x00\x00\x01\x04", substr($writer->bytes(), 0, 8));
    }

    public function testDescriptorRejectsOutOfRangeId(): void
    {
        $this->expectException(ProtocolException::class);
        new NetworkItemStackDescriptor(id: 40000, count: 1);
    }

    // --- InventoryTransaction -------------------------------------------

    public function testInventoryTransactionRoundTripNormal(): void
    {
        $packet = new InventoryTransactionPacket(
            legacyRequestId: 0,
            legacySetItemSlots: [],
            actions: [
                new InventoryAction(
                    new InventorySource(InventorySource::TYPE_CONTAINER, containerId: 0),
                    5,
                    $this->emptyItem(),
                    $this->stone(2)
                ),
            ]
        );

        $engine = $this->engine();
        $decoded = $engine->decode($engine->encode($packet), $this->c2s());

        self::assertInstanceOf(InventoryTransactionPacket::class, $decoded);
        self::assertCount(1, $decoded->actions);
        self::assertSame(5, $decoded->actions[0]->slot);
        self::assertSame(InventorySource::TYPE_CONTAINER, $decoded->actions[0]->source->type);
        self::assertSame(0, $decoded->actions[0]->source->containerId);
        self::assertTrue($decoded->actions[0]->fromItem->isEmpty());
        self::assertSame(264, $decoded->actions[0]->toItem->id);
    }

    public function testInventoryTransactionRejectsNonNormalTransactionType(): void
    {
        $writer = new BinaryWriter();
        $writer->writeVarUInt(PacketId::INVENTORY_TRANSACTION);
        $writer->writeVarInt(0);          // Legacy Request ID
        $writer->writeBool(false);        // no legacy slots
        $writer->writeVarUInt(2);         // ItemUse — Phase 6 disi

        $this->expectException(ProtocolException::class);
        $this->engine()->decode($writer->bytes(), $this->c2s());
    }

    public function testInventoryTransactionRejectsMoreThanOneHundredActions(): void
    {
        $writer = new BinaryWriter();
        $writer->writeVarUInt(PacketId::INVENTORY_TRANSACTION);
        $writer->writeVarInt(0);
        $writer->writeBool(false);
        $writer->writeVarUInt(0);   // normal
        $writer->writeVarUInt(101); // action count

        $this->expectException(ProtocolException::class);
        $this->engine()->decode($writer->bytes(), $this->c2s());
    }

    // --- MobEquipment / containers --------------------------------------

    public function testMobEquipmentRoundTrip(): void
    {
        $packet = new MobEquipmentPacket(
            targetRuntimeId: 42,
            item: $this->stone(1),
            slot: 3,
            selectedSlot: 3,
            containerId: 0
        );

        $engine = $this->engine();
        $decoded = $engine->decode($engine->encode($packet), $this->s2c());

        self::assertInstanceOf(MobEquipmentPacket::class, $decoded);
        self::assertSame(42, $decoded->targetRuntimeId);
        self::assertSame(3, $decoded->slot);
        self::assertSame(3, $decoded->selectedSlot);
        self::assertSame(0, $decoded->containerId);
        self::assertSame(264, $decoded->item->id);
    }

    public function testContainerOpenRoundTrip(): void
    {
        $packet = new ContainerOpenPacket(1, 2, new BlockPos(10, 64, -5), 7);

        $engine = $this->engine();
        $decoded = $engine->decode($engine->encode($packet), $this->s2c());

        self::assertInstanceOf(ContainerOpenPacket::class, $decoded);
        self::assertSame(1, $decoded->containerId);
        self::assertSame(2, $decoded->containerType);
        self::assertSame(10, $decoded->position->x);
        self::assertSame(64, $decoded->position->y);
        self::assertSame(-5, $decoded->position->z);
        self::assertSame(7, $decoded->targetActorId);
    }

    public function testContainerCloseRoundTrip(): void
    {
        $packet = new ContainerClosePacket(1, 2, true);

        $engine = $this->engine();
        $decoded = $engine->decode($engine->encode($packet), $this->s2c());

        self::assertInstanceOf(ContainerClosePacket::class, $decoded);
        self::assertSame(1, $decoded->containerId);
        self::assertSame(2, $decoded->containerType);
        self::assertTrue($decoded->serverInitiatedClose);
    }

    public function testInventoryContentRoundTripWith36Slots(): void
    {
        $slots = array_fill(0, 36, $this->emptyItem());
        $slots[0] = $this->stone(64);

        $packet = new InventoryContentPacket(
            containerId: 0,
            slots: $slots,
            fullContainerName: new FullContainerName(12),
            storageItem: $this->emptyItem()
        );

        $engine = $this->engine();
        $decoded = $engine->decode($engine->encode($packet), $this->s2c());

        self::assertInstanceOf(InventoryContentPacket::class, $decoded);
        self::assertSame(0, $decoded->containerId);
        self::assertCount(36, $decoded->slots);
        self::assertSame(264, $decoded->slots[0]->id);
        self::assertSame(64, $decoded->slots[0]->count);
        self::assertSame(12, $decoded->fullContainerName->containerName);
    }

    public function testInventoryContentContainerIdIsUnsignedVarUInt(): void
    {
        $packet = new InventoryContentPacket(
            containerId: 300,
            slots: [],
            fullContainerName: new FullContainerName(0),
            storageItem: $this->emptyItem()
        );

        $engine = $this->engine();
        $decoded = $engine->decode($engine->encode($packet), $this->s2c());

        self::assertSame(300, $decoded->containerId);
    }

    public function testInventorySlotRoundTripWithoutOptionalFields(): void
    {
        $packet = new InventorySlotPacket(0, 5, $this->stone(1));

        $engine = $this->engine();
        $decoded = $engine->decode($engine->encode($packet), $this->s2c());

        self::assertInstanceOf(InventorySlotPacket::class, $decoded);
        self::assertSame(0, $decoded->containerId);
        self::assertSame(5, $decoded->slot);
        self::assertSame(264, $decoded->item->id);
        self::assertNull($decoded->fullContainerName);
        self::assertNull($decoded->storageItem);
    }

    public function testInventorySlotRoundTripWithOptionalFields(): void
    {
        $packet = new InventorySlotPacket(
            containerId: 1,
            slot: 9,
            item: $this->stone(4),
            fullContainerName: new FullContainerName(12, 44),
            storageItem: $this->emptyItem()
        );

        $engine = $this->engine();
        $decoded = $engine->decode($engine->encode($packet), $this->s2c());

        self::assertSame(9, $decoded->slot);
        self::assertSame(12, $decoded->fullContainerName?->containerName);
        self::assertSame(44, $decoded->fullContainerName?->dynamicId);
        self::assertNotNull($decoded->storageItem);
        self::assertTrue($decoded->storageItem->isEmpty());
    }

    // --- Entity packets -------------------------------------------------

    public function testAddPlayerRoundTrip(): void
    {
        $packet = new AddPlayerPacket(
            uuid: Uuid::fromString('11111111-2222-3333-4444-555555555555'),
            playerName: 'Steve',
            targetRuntimeId: 99,
            platformChatId: '',
            position: new Vec3(1.5, 64.0, -3.25),
            velocity: new Vec3(0.0, 0.0, 0.0),
            pitch: 0.0,
            yaw: 90.0,
            headYaw: 90.0,
            carriedItem: $this->stone(1),
            playerGameType: 1,
            synchedProperties: new PropertySyncData([], []),
            abilitiesData: $this->abilities(),
            actorLinks: [new EntityLink(1, 2, 0, true, false, 0.0)],
            deviceId: 'device-1',
            buildPlatform: 7
        );

        $engine = $this->engine();
        $decoded = $engine->decode($engine->encode($packet), $this->s2c());

        self::assertInstanceOf(AddPlayerPacket::class, $decoded);
        self::assertSame('Steve', $decoded->playerName);
        self::assertSame(99, $decoded->targetRuntimeId);
        self::assertSame('11111111-2222-3333-4444-555555555555', $decoded->uuid->toString());
        self::assertEqualsWithDelta(1.5, $decoded->position->x, 1.0e-6);
        self::assertSame(264, $decoded->carriedItem->id);
        self::assertCount(1, $decoded->actorLinks);
        self::assertSame('device-1', $decoded->deviceId);
        self::assertSame(7, $decoded->buildPlatform);
        self::assertCount(1, $decoded->abilitiesData->layers);
    }

    public function testAddPlayerRoundTripPreservesEntityMetadata(): void
    {
        $metadata = new SynchedActorData([
            SynchedActorDataEntry::int64(PlayerMetadata::DATA_FLAGS, 0x4000 | 0x8000),
            SynchedActorDataEntry::string(PlayerMetadata::DATA_NAMETAG, 'Alex'),
        ]);

        $packet = new AddPlayerPacket(
            uuid: Uuid::fromString('11111111-2222-3333-4444-555555555555'),
            playerName: 'Alex',
            targetRuntimeId: 5,
            platformChatId: '',
            position: new Vec3(1.5, 64.0, -3.25),
            velocity: new Vec3(0.0, 0.0, 0.0),
            pitch: 0.0,
            yaw: 0.0,
            headYaw: 0.0,
            carriedItem: NetworkItemStackDescriptor::empty(),
            playerGameType: 0,
            synchedProperties: new PropertySyncData([], []),
            abilitiesData: $this->abilities(),
            actorLinks: [],
            deviceId: '',
            buildPlatform: 0,
            entityData: $metadata
        );

        $engine = $this->engine();
        $decoded = $engine->decode($engine->encode($packet), $this->s2c());

        self::assertInstanceOf(AddPlayerPacket::class, $decoded);
        self::assertSame(2, $decoded->entityData->count());

        $flags = $decoded->entityData->get(PlayerMetadata::DATA_FLAGS);
        self::assertInstanceOf(SynchedActorDataEntry::class, $flags);
        self::assertSame(SynchedActorDataEntry::TYPE_INT64, $flags->type);
        self::assertSame(0x4000 | 0x8000, $flags->value);

        $nameTag = $decoded->entityData->get(PlayerMetadata::DATA_NAMETAG);
        self::assertInstanceOf(SynchedActorDataEntry::class, $nameTag);
        self::assertSame(SynchedActorDataEntry::TYPE_STRING, $nameTag->type);
        self::assertSame('Alex', $nameTag->value);
    }

    public function testAddActorRoundTrip(): void
    {
        $packet = new AddActorPacket(
            targetActorId: 5,
            targetRuntimeId: 6,
            actorType: 'minecraft:pig',
            position: new Vec3(1.0, 2.0, 3.0),
            velocity: new Vec3(0.1, 0.0, -0.1),
            pitch: 0.0,
            yaw: 45.0,
            headYaw: 45.0,
            bodyYaw: 45.0,
            attributes: [new SyncedAttribute('minecraft:health', 0.0, 10.0, 20.0)],
            synchedProperties: new PropertySyncData([], []),
            actorLinks: []
        );

        $engine = $this->engine();
        $decoded = $engine->decode($engine->encode($packet), $this->s2c());

        self::assertInstanceOf(AddActorPacket::class, $decoded);
        self::assertSame(5, $decoded->targetActorId);
        self::assertSame(6, $decoded->targetRuntimeId);
        self::assertSame('minecraft:pig', $decoded->actorType);
        self::assertEqualsWithDelta(3.0, $decoded->position->z, 1.0e-6);
        self::assertCount(1, $decoded->attributes);
        self::assertSame('minecraft:health', $decoded->attributes[0]->name);
    }

    public function testAddActorRoundTripPreservesActorMetadata(): void
    {
        $packet = new AddActorPacket(
            targetActorId: 5,
            targetRuntimeId: 6,
            actorType: 'minecraft:pig',
            position: new Vec3(1.0, 2.0, 3.0),
            velocity: new Vec3(0.0, 0.0, 0.0),
            pitch: 0.0,
            yaw: 45.0,
            headYaw: 45.0,
            bodyYaw: 45.0,
            attributes: [],
            synchedProperties: new PropertySyncData([], []),
            actorLinks: [],
            actorData: new SynchedActorData([
                SynchedActorDataEntry::int64(0, 0xC000),
                SynchedActorDataEntry::string(4, 'Piggy'),
            ])
        );

        $engine = $this->engine();
        $decoded = $engine->decode($engine->encode($packet), $this->s2c());

        self::assertInstanceOf(AddActorPacket::class, $decoded);
        self::assertSame(2, $decoded->actorData->count());

        $nameTag = $decoded->actorData->get(4);
        self::assertInstanceOf(SynchedActorDataEntry::class, $nameTag);
        self::assertSame('Piggy', $nameTag->value);
    }

    public function testAddActorDecodeRejectsTruncatedMetadata(): void
    {
        $writer = new BinaryWriter();
        $writer->writeVarUInt(PacketId::ADD_ACTOR);
        $writer->writeVarLong(1);          // unique id
        $writer->writeVarULong(2);         // runtime id
        $writer->writeString('minecraft:pig');
        $writer->writeFloat(0.0);
        $writer->writeFloat(0.0);
        $writer->writeFloat(0.0);
        $writer->writeFloat(0.0);
        $writer->writeFloat(0.0);
        $writer->writeFloat(0.0);
        $writer->writeFloat(0.0);
        $writer->writeFloat(0.0);
        $writer->writeFloat(0.0);
        $writer->writeFloat(0.0);
        $writer->writeVarUInt(0); // attributes
        $writer->writeVarUInt(1); // metadata sayisi 1 — ama govde yok

        // Kesik govde sessizce kabul edilmemeli: paket sinirlari kayarsa
        // sonraki paketler bozulur.
        $this->expectException(ProtocolException::class);
        $this->engine()->decode($writer->bytes(), $this->s2c());
    }

    public function testRemoveActorRoundTrip(): void
    {
        $engine = $this->engine();
        $decoded = $engine->decode($engine->encode(new RemoveActorPacket(1234)), $this->s2c());

        self::assertInstanceOf(RemoveActorPacket::class, $decoded);
        self::assertSame(1234, $decoded->targetActorId);
    }

    public function testMoveActorAbsoluteRoundTrip(): void
    {
        $packet = new MoveActorAbsolutePacket(
            targetRuntimeId: 77,
            flags: 1,
            position: new Vec3(1.0, 2.0, 3.0),
            rotationX: 0,
            rotationY: 64,
            rotationYHead: 128
        );

        $engine = $this->engine();
        $decoded = $engine->decode($engine->encode($packet), $this->s2c());

        self::assertInstanceOf(MoveActorAbsolutePacket::class, $decoded);
        self::assertSame(77, $decoded->targetRuntimeId);
        self::assertSame(1, $decoded->flags);
        self::assertSame(64, $decoded->rotationY);
        self::assertSame(128, $decoded->rotationYHead);
    }

    public function testNewPacketsRejectTrailingBytesInStrictMode(): void
    {
        $engine = $this->engine();
        $bytes = $engine->encode(new RemoveActorPacket(1)) . "\xFF";

        $this->expectException(ProtocolException::class);
        $engine->decode($bytes, $this->s2c());
    }
}
