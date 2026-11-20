<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;

/**
 * `MoveActorDeltaData` - tek bir varlığın kısmi hareket güncellemesi.
 *
 * Şema (Mojang v1.26.65, 2225) `MoveActorDeltaData.json` tel biçimini tam
 * vermez: şema yalnızca "opsiyonel" der, **nasıl** işaretlendiğini söylemez.
 * Çalışan referans (`gophertunnel` `MoveActorDelta.Marshal`) her opsiyonel
 * bileşen için `bool var` + değer yazar. Bu sınıf onu izler:
 *
 * ```
 *  Actor Runtime ID      varuint64
 *  New Position X        bool + float32
 *  New Position Y        bool + float32
 *  New Position Z        bool + float32
 *  Rotation X            bool + int8 (byte-float: derece / 1.40625)
 *  Rotation Y            bool + int8
 *  Rotation Y Head       bool + int8
 *  Is On Ground          bool
 *  Force Move            bool
 *  Force Move Local      bool
 *  Force Completion      bool
 *  Ticks                 varuint64
 * ```
 *
 * `null` = "bu bileşen değişmedi", alan hiç yazılmaz.
 *
 * ## Dürüst sınır
 *
 * Rotasyonun `float32` mi `int8` mi olduğu şema ile referans arasında
 * çelişir. Şema açıkça `int8` der ve `ByteFloat` ile uyumludur; bu yüzden
 * `int8` seçildi. Gerçek istemci kabulü **doğrulanmadı**.
 */
final class MoveActorDeltaData
{
    /** Derece -> int8 (360/256) çarpanı. */
    public const ROTATION_SCALE = 360.0 / 256.0;

    public function __construct(
        public readonly int $actorRuntimeId,
        public readonly ?float $positionX = null,
        public readonly ?float $positionY = null,
        public readonly ?float $positionZ = null,
        public readonly ?int $rotationX = null,
        public readonly ?int $rotationY = null,
        public readonly ?int $rotationYHead = null,
        public readonly bool $isOnGround = true,
        public readonly bool $forceMove = false,
        public readonly bool $forceMoveLocalEntity = false,
        public readonly bool $forceCompletion = false,
        public readonly int $ticks = 0
    ) {
    }

    /**
     * Derece cinsinden rotasyonu tel üzerindeki int8'e paketler.
     */
    public static function packRotation(float $degrees): int
    {
        $packed = (int) round($degrees / self::ROTATION_SCALE);

        // 128..255 araligini -128..-1'e sar (ikiye tamamlayan bayt).
        if ($packed > 127) {
            $packed -= 256;
        } elseif ($packed < -128) {
            $packed += 256;
        }

        return $packed;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeVarULong($this->actorRuntimeId);

        $this->writeOptionalFloat($writer, $this->positionX);
        $this->writeOptionalFloat($writer, $this->positionY);
        $this->writeOptionalFloat($writer, $this->positionZ);
        $this->writeOptionalRotation($writer, $this->rotationX);
        $this->writeOptionalRotation($writer, $this->rotationY);
        $this->writeOptionalRotation($writer, $this->rotationYHead);

        $writer->writeBool($this->isOnGround);
        $writer->writeBool($this->forceMove);
        $writer->writeBool($this->forceMoveLocalEntity);
        $writer->writeBool($this->forceCompletion);
        $writer->writeVarULong($this->ticks);
    }

    public static function decode(BinaryReader $reader): self
    {
        $runtimeId = $reader->readVarULong();

        return new self(
            actorRuntimeId: $runtimeId,
            positionX: $reader->readBool() ? $reader->readFloat() : null,
            positionY: $reader->readBool() ? $reader->readFloat() : null,
            positionZ: $reader->readBool() ? $reader->readFloat() : null,
            rotationX: $reader->readBool() ? $reader->readSignedByte() : null,
            rotationY: $reader->readBool() ? $reader->readSignedByte() : null,
            rotationYHead: $reader->readBool() ? $reader->readSignedByte() : null,
            isOnGround: $reader->readBool(),
            forceMove: $reader->readBool(),
            forceMoveLocalEntity: $reader->readBool(),
            forceCompletion: $reader->readBool(),
            ticks: $reader->readVarULong()
        );
    }

    private function writeOptionalFloat(BinaryWriter $writer, ?float $value): void
    {
        $writer->writeBool($value !== null);

        if ($value !== null) {
            $writer->writeFloat($value);
        }
    }

    private function writeOptionalRotation(BinaryWriter $writer, ?int $value): void
    {
        $writer->writeBool($value !== null);

        if ($value !== null) {
            $writer->writeSignedByte($value);
        }
    }
}
