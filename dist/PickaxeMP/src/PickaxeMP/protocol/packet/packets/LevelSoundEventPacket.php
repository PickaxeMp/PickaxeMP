<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\types\Vec3;

/**
 * `LevelSoundEventPacket` (id **123**, iki yonlu).
 *
 * Şema (Mojang v1.26.65, 2225) - `LevelSoundEventPacketPayload`, ordinal
 * sırası:
 *
 * ```
 * 0 Sound Event        string  (SoundEventIdentifier - duz string, enum DEGIL)
 * 1 Position           Vec3
 * 2 Data               int32 (Compression -> zigzag varint32)
 * 3 Actor Identifier   string
 * 4 Is Baby            bool
 * 5 Is Global          bool
 * 6 Actor Unique Id    int64 (sabit, Compression YOK)
 * 7 Fire At Position   Vec3 (zorunlu DEGIL)
 * ```
 *
 * `Actor Unique Id` şemada **sabit** `int64`'tir (Compression yok); zigzag
 * yazılırsa paket kayar. `Fire At Position` ise hiç yazılmaz - gerekçesi
 * {@see ActorEventPacket}.
 */
final class LevelSoundEventPacket implements PacketInterface
{
    public function __construct(
        public readonly string $soundEvent,
        public readonly Vec3 $position,
        public readonly int $data = 0,
        public readonly string $actorIdentifier = '',
        public readonly bool $isBaby = false,
        public readonly bool $isGlobal = false,
        public readonly int $actorUniqueId = 0,
        public readonly ?Vec3 $fireAtPosition = null
    ) {
    }

    public function getId(): int
    {
        return PacketId::LEVEL_SOUND_EVENT;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeString($this->soundEvent, 256);
        $this->position->encode($writer);
        $writer->writeVarInt($this->data);
        $writer->writeString($this->actorIdentifier, 256);
        $writer->writeBool($this->isBaby);
        $writer->writeBool($this->isGlobal);
        $writer->writeInt64($this->actorUniqueId);

        if ($this->fireAtPosition !== null) {
            $this->fireAtPosition->encode($writer);
        }
    }

    public static function decode(BinaryReader $reader): static
    {
        $soundEvent = $reader->readString(256);
        $position = Vec3::decode($reader);
        $data = $reader->readVarInt();
        $actorIdentifier = $reader->readString(256);
        $isBaby = $reader->readBool();
        $isGlobal = $reader->readBool();
        $actorUniqueId = $reader->readInt64();
        $fireAt = null;

        // Uzunluk oneki yok; yalnizca bayt kaldiysa ve 12 baytlik bir Vec3
        // sigiyorsa okunur.
        if ($reader->remaining() >= 12) {
            $fireAt = Vec3::decode($reader);
        }

        return new static(
            $soundEvent,
            $position,
            $data,
            $actorIdentifier,
            $isBaby,
            $isGlobal,
            $actorUniqueId,
            $fireAt
        );
    }
}
