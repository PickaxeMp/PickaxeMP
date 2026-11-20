<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\ProtocolException;

/**
 * `DataItemEntry` — tek bir senkronize varlik verisi girdisi.
 *
 * Tel bicimi (Mojang `DataItemEntry.json` + `DataItemType.json`, gophertunnel
 * `Writer.EntityMetadata` ile dogrulandi):
 *
 * ```
 *  ID             varuint32
 *  Selector Type  varuint32   (x-control-value-type)
 *  Legacy Type    uint8       (Ref'li payload'in Type alani; selector ile ayni olmali)
 *  Value          turune gore
 * ```
 *
 * Selector ve legacy bayt **ayni** degeri tasimak zorundadir. Istemci
 * uyusmazligi gorurse paketi reddeder; bu yuzden cozucu de uyusmazligi
 * hataya cevirir (sessizce duzeltmez).
 *
 * `CompoundTag` (tip 5) NBT cozucusu gerektirdigi icin Phase 7.2 kapsami
 * disindadir; ayristirilirsa acik `ProtocolException` firlatilir.
 */
final class SynchedActorDataEntry
{
    public const TYPE_BYTE = 0;
    public const TYPE_SHORT = 1;
    public const TYPE_INT = 2;
    public const TYPE_FLOAT = 3;
    public const TYPE_STRING = 4;
    public const TYPE_COMPOUND_TAG = 5;
    public const TYPE_POS = 6;
    public const TYPE_INT64 = 7;
    public const TYPE_VEC3 = 8;

    public function __construct(
        public readonly int $id,
        public readonly int $type,
        public readonly int|float|string|BlockPos|Vec3 $value
    ) {
        if ($id < 0 || $id > 0xFFFFFFFF) {
            throw ProtocolException::varIntOverflow('synched actor data id (uint32)');
        }

        if ($type < self::TYPE_BYTE || $type > self::TYPE_VEC3) {
            throw ProtocolException::invalidEnum('synched actor data type', $type);
        }

        $this->assertValueType();
    }

    public static function byte(int $id, int $value): self
    {
        if ($value < -128 || $value > 127) {
            throw ProtocolException::varIntOverflow('synched actor data byte (int8)');
        }

        return new self($id, self::TYPE_BYTE, $value);
    }

    public static function short(int $id, int $value): self
    {
        if ($value < -32768 || $value > 32767) {
            throw ProtocolException::varIntOverflow('synched actor data short (int16)');
        }

        return new self($id, self::TYPE_SHORT, $value);
    }

    public static function int(int $id, int $value): self
    {
        return new self($id, self::TYPE_INT, $value);
    }

    public static function float(int $id, float $value): self
    {
        return new self($id, self::TYPE_FLOAT, $value);
    }

    public static function string(int $id, string $value): self
    {
        return new self($id, self::TYPE_STRING, $value);
    }

    public static function pos(int $id, BlockPos $value): self
    {
        return new self($id, self::TYPE_POS, $value);
    }

    public static function int64(int $id, int $value): self
    {
        return new self($id, self::TYPE_INT64, $value);
    }

    public static function vec3(int $id, Vec3 $value): self
    {
        return new self($id, self::TYPE_VEC3, $value);
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeVarUInt($this->id);
        $writer->writeVarUInt($this->type);

        // Legacy selector bayti: ref'li payload'in `Type` alanidir. Tel
        // biciminde selector'un ardindan tek bayt olarak yeniden yazilir.
        $writer->writeByte($this->type);

        switch ($this->type) {
            case self::TYPE_BYTE:
                $writer->writeSignedByte($this->intValue());
                break;

            case self::TYPE_SHORT:
                $writer->writeInt16($this->intValue());
                break;

            case self::TYPE_INT:
                $writer->writeVarInt($this->intValue());
                break;

            case self::TYPE_FLOAT:
                $writer->writeFloat($this->floatValue());
                break;

            case self::TYPE_STRING:
                $writer->writeString($this->stringValue());
                break;

            case self::TYPE_POS:
                $this->posValue()->encode($writer);
                break;

            case self::TYPE_INT64:
                $writer->writeVarLong($this->intValue());
                break;

            case self::TYPE_VEC3:
                $this->vec3Value()->encode($writer);
                break;
        }
    }

    public static function decode(BinaryReader $reader): self
    {
        $id = $reader->readVarUInt();
        $selectorType = $reader->readVarUInt();
        $legacyType = $reader->readByte();

        if ($selectorType !== $legacyType) {
            throw new ProtocolException(sprintf(
                'SynchedActorData: selector tipi (%d) legacy tip ile (%d) uyusmuyor',
                $selectorType,
                $legacyType
            ));
        }

        $value = match ($legacyType) {
            self::TYPE_BYTE => $reader->readSignedByte(),
            self::TYPE_SHORT => $reader->readInt16(),
            self::TYPE_INT => $reader->readVarInt(),
            self::TYPE_FLOAT => $reader->readFloat(),
            self::TYPE_STRING => $reader->readString(),
            self::TYPE_POS => BlockPos::decode($reader),
            self::TYPE_INT64 => $reader->readVarLong(),
            self::TYPE_VEC3 => Vec3::decode($reader),
            self::TYPE_COMPOUND_TAG => throw new ProtocolException(
                'SynchedActorData: CompoundTag (5) Phase 7.2 kapsaminda cozulmez'
            ),
            default => throw ProtocolException::invalidEnum('synched actor data type', $legacyType),
        };

        return new self($id, $legacyType, $value);
    }

    private function assertValueType(): void
    {
        $valid = match ($this->type) {
            self::TYPE_BYTE,
            self::TYPE_SHORT,
            self::TYPE_INT,
            self::TYPE_INT64 => is_int($this->value),
            self::TYPE_FLOAT => is_float($this->value),
            self::TYPE_STRING => is_string($this->value),
            self::TYPE_POS => $this->value instanceof BlockPos,
            self::TYPE_VEC3 => $this->value instanceof Vec3,
            default => false,
        };

        if (!$valid) {
            throw new ProtocolException(sprintf(
                'SynchedActorData: tip %d icin gecersiz PHP degeri (%s)',
                $this->type,
                get_debug_type($this->value)
            ));
        }

        if ($this->type === self::TYPE_FLOAT && !is_finite($this->value)) {
            throw new ProtocolException('SynchedActorData: float degeri sonlu olmali');
        }
    }

    private function intValue(): int
    {
        return is_int($this->value) ? $this->value : 0;
    }

    private function floatValue(): float
    {
        return is_float($this->value) ? $this->value : 0.0;
    }

    private function stringValue(): string
    {
        return is_string($this->value) ? $this->value : '';
    }

    private function posValue(): BlockPos
    {
        return $this->value instanceof BlockPos ? $this->value : new BlockPos(0, 0, 0);
    }

    private function vec3Value(): Vec3
    {
        return $this->value instanceof Vec3 ? $this->value : Vec3::zero();
    }
}
