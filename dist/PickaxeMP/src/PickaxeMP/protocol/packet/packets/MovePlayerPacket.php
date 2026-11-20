<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\ProtocolException;

/**
 * `MovePlayerPacket` (id **19**, S→C).
 *
 * Şema (Mojang v1.26.65, 2225; `MovePlayerPacket.json` →
 * `MovePlayerPacketPayload.json`, `[cereal:packet]` = 19):
 *
 * ```
 *  0 Player Runtime ID   uint64 (Compression)  varuint64
 *  1 Position            Vec3                 float × 3
 *  2 Rotation            Vec2                 float × 2 (X=pitch, Y=yaw)
 *  3 Y-Head Rotation     float
 *  4 Position Mode       uint8                Normal|Respawn|Teleport|OnlyHeadRot
 *  5 On Ground           boolean
 *  6 Riding Runtime ID   uint64 (Compression)  varuint64
 *  7 Teleport Data       KOŞULLU (yalnız Teleport modunda)
 *                         Teleportation Cause   int32
 *                         Source Actor Type     int32
 *  8 Tick                uint64 (Compression)  varuint64
 * ```
 *
 * Paket **sunucu → istemci** yönündedir: sunucu otoritesi pozisyonu dayatır.
 * `Position Mode` = `Teleport` iken istemci ara enterpolasyon yapmaz; bu yüzden
 * sunucu düzeltmeleri için doğru mod budur.
 */
final class MovePlayerPacket implements PacketInterface
{
    public const MODE_NORMAL = 0;
    public const MODE_RESPAWN = 1;
    public const MODE_TELEPORT = 2;
    public const MODE_ONLY_HEAD_ROT = 3;

    /**
     * @param int $runtimeId       Hedef oyuncunun çalışma zamanı kimliği
     * @param int $mode            `MODE_*` sabitlerinden biri
     * @param int $teleportCause   Şema `MovePlayerTeleportData.Teleportation Cause`
     * @param int $teleportSource  Şema `MovePlayerTeleportData.Source Actor Type`
     */
    public function __construct(
        public readonly int $runtimeId,
        public readonly float $x,
        public readonly float $y,
        public readonly float $z,
        public readonly float $pitch,
        public readonly float $yaw,
        public readonly float $headYaw,
        public readonly int $mode = self::MODE_NORMAL,
        public readonly bool $onGround = false,
        public readonly int $ridingRuntimeId = 0,
        public readonly int $teleportCause = 0,
        public readonly int $teleportSource = 0,
        public readonly int $tick = 0
    ) {
        if (!in_array($mode, [
            self::MODE_NORMAL,
            self::MODE_RESPAWN,
            self::MODE_TELEPORT,
            self::MODE_ONLY_HEAD_ROT,
        ], true)) {
            throw new ProtocolException("MovePlayer: gecersiz pozisyon modu: {$mode}");
        }
    }

    public function getId(): int
    {
        return PacketId::MOVE_PLAYER;
    }

    public function encode(BinaryWriter $writer): void
    {
        // 0 Player Runtime ID
        $writer->writeVarULong($this->runtimeId);

        // 1 Position
        $writer->writeFloat($this->x);
        $writer->writeFloat($this->y);
        $writer->writeFloat($this->z);

        // 2 Rotation (Vec2: X = pitch, Y = yaw)
        $writer->writeFloat($this->pitch);
        $writer->writeFloat($this->yaw);

        // 3 Y-Head Rotation
        $writer->writeFloat($this->headYaw);

        // 4 Position Mode
        $writer->writeByte($this->mode);

        // 5 On Ground
        $writer->writeBool($this->onGround);

        // 6 Riding Runtime ID
        $writer->writeVarULong($this->ridingRuntimeId);

        // 7 Teleport Data — yalnızca Teleport modunda
        if ($this->mode === self::MODE_TELEPORT) {
            $writer->writeInt32($this->teleportCause);
            $writer->writeInt32($this->teleportSource);
        }

        // 8 Tick
        $writer->writeVarULong($this->tick);
    }

    public static function decode(BinaryReader $reader): static
    {
        $runtimeId = $reader->readVarULong();

        $x = $reader->readFloat();
        $y = $reader->readFloat();
        $z = $reader->readFloat();

        $pitch = $reader->readFloat();
        $yaw = $reader->readFloat();

        $headYaw = $reader->readFloat();

        $mode = $reader->readByte();

        if ($mode > self::MODE_ONLY_HEAD_ROT) {
            throw new ProtocolException("MovePlayer: gecersiz pozisyon modu: {$mode}");
        }

        $onGround = $reader->readBool();

        $ridingRuntimeId = $reader->readVarULong();

        $teleportCause = 0;
        $teleportSource = 0;

        if ($mode === self::MODE_TELEPORT) {
            $teleportCause = $reader->readInt32();
            $teleportSource = $reader->readInt32();
        }

        $tick = $reader->readVarULong();

        return new static(
            runtimeId: $runtimeId,
            x: $x,
            y: $y,
            z: $z,
            pitch: $pitch,
            yaw: $yaw,
            headYaw: $headYaw,
            mode: $mode,
            onGround: $onGround,
            ridingRuntimeId: $ridingRuntimeId,
            teleportCause: $teleportCause,
            teleportSource: $teleportSource,
            tick: $tick
        );
    }
}
