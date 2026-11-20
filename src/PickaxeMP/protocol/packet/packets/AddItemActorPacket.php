<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\types\NetworkItemStackDescriptor;
use PickaxeMP\protocol\packet\types\SynchedActorData;
use PickaxeMP\protocol\packet\types\Vec3;

/**
 * `AddItemActorPacket` (id **15**, sunucu → istemci).
 *
 * Sema (Mojang v1.26.65, 2225; `AddItemActorPacketPayload.json`, ordinal 0..6):
 *
 * ```
 *  0 Target Actor ID    int64   (Compression → varlong)
 *  1 Target Runtime ID  varuint64
 *  2 Item               NetworkItemStackDescriptor
 *  3 Position           Vec3
 *  4 Velocity           Vec3
 *  5 Entity Data        SynchedActorDataList
 *  6 Is From Fishing    bool
 * ```
 *
 * Yere düşen bir eşya varlığını istemciye tanıtır. `Target Actor ID`
 * (`ActorUniqueID`) otoriter kimliktir; `Target Runtime ID` oturum içi geçici
 * kimliktir ve `RemoveActor`/`TakeItemActor` bu alanla çalışır.
 */
final class AddItemActorPacket implements PacketInterface
{
    public function __construct(
        public readonly int $targetActorId,
        public readonly int $targetRuntimeId,
        public readonly NetworkItemStackDescriptor $item,
        public readonly Vec3 $position,
        public readonly Vec3 $velocity,
        public readonly SynchedActorData $entityData = new SynchedActorData(),
        public readonly bool $isFromFishing = false
    ) {
    }

    public function getId(): int
    {
        return PacketId::ADD_ITEM_ACTOR;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeVarLong($this->targetActorId);
        $writer->writeVarULong($this->targetRuntimeId);
        $this->item->encode($writer);
        $this->position->encode($writer);
        $this->velocity->encode($writer);
        $this->entityData->encode($writer);
        $writer->writeBool($this->isFromFishing);
    }

    public static function decode(BinaryReader $reader): static
    {
        return new static(
            targetActorId: $reader->readVarLong(),
            targetRuntimeId: $reader->readVarULong(),
            item: NetworkItemStackDescriptor::decode($reader),
            position: Vec3::decode($reader),
            velocity: Vec3::decode($reader),
            entityData: SynchedActorData::decode($reader),
            isFromFishing: $reader->readBool()
        );
    }
}
