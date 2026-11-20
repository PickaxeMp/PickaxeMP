<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\binary;

use PickaxeMP\protocol\ProtocolException;

/**
 * İkili yazıcı.
 *
 * Tüm çok baytlı tam sayılar **little-endian**'dır; `*BigEndian` ekli metotlar
 * Mojang şemasında "Big Endian" işaretli alanlar içindir.
 *
 * `BinaryReader` ile round-trip garantisi testlerle doğrulanır.
 */
final class BinaryWriter
{
    private string $buffer = '';

    public function length(): int
    {
        return strlen($this->buffer);
    }

    public function bytes(): string
    {
        return $this->buffer;
    }

    public function write(string $bytes): self
    {
        $this->buffer .= $bytes;

        return $this;
    }

    public function writeByte(int $value): self
    {
        if ($value < 0 || $value > 0xFF) {
            throw ProtocolException::varIntOverflow('uint8');
        }

        $this->buffer .= chr($value);

        return $this;
    }

    public function writeSignedByte(int $value): self
    {
        if ($value < -128 || $value > 127) {
            throw ProtocolException::varIntOverflow('int8');
        }

        $this->buffer .= chr($value & 0xFF);

        return $this;
    }

    public function writeBool(bool $value): self
    {
        $this->buffer .= $value ? "\x01" : "\x00";

        return $this;
    }

    public function writeUint16(int $value): self
    {
        if ($value < 0 || $value > 0xFFFF) {
            throw ProtocolException::varIntOverflow('uint16');
        }

        $this->buffer .= pack('v', $value);

        return $this;
    }

    public function writeInt16(int $value): self
    {
        if ($value < -32768 || $value > 32767) {
            throw ProtocolException::varIntOverflow('int16');
        }

        $this->buffer .= pack('v', $value & 0xFFFF);

        return $this;
    }

    public function writeUint16BigEndian(int $value): self
    {
        if ($value < 0 || $value > 0xFFFF) {
            throw ProtocolException::varIntOverflow('uint16');
        }

        $this->buffer .= pack('n', $value);

        return $this;
    }

    public function writeUint32(int $value): self
    {
        if ($value < 0 || $value > 0xFFFFFFFF) {
            throw ProtocolException::varIntOverflow('uint32');
        }

        $this->buffer .= pack('V', $value);

        return $this;
    }

    public function writeInt32(int $value): self
    {
        if ($value < VarInt::MIN_INT32 || $value > VarInt::MAX_INT32) {
            throw ProtocolException::varIntOverflow('int32');
        }

        $this->buffer .= pack('V', $value & 0xFFFFFFFF);

        return $this;
    }

    public function writeInt32BigEndian(int $value): self
    {
        if ($value < VarInt::MIN_INT32 || $value > VarInt::MAX_INT32) {
            throw ProtocolException::varIntOverflow('int32');
        }

        $this->buffer .= pack('N', $value & 0xFFFFFFFF);

        return $this;
    }

    public function writeUint64(int $value): self
    {
        $this->buffer .= pack('P', $value);

        return $this;
    }

    public function writeInt64(int $value): self
    {
        $this->buffer .= pack('P', $value);

        return $this;
    }

    public function writeFloat(float $value): self
    {
        $this->buffer .= pack('g', $value);

        return $this;
    }

    public function writeFloatBigEndian(float $value): self
    {
        $this->buffer .= pack('G', $value);

        return $this;
    }

    public function writeDouble(float $value): self
    {
        $this->buffer .= pack('e', $value);

        return $this;
    }

    public function writeDoubleBigEndian(float $value): self
    {
        $this->buffer .= pack('E', $value);

        return $this;
    }

    /**
     * `varint32` — ZigZag + base-128.
     */
    public function writeVarInt(int $value): self
    {
        $this->buffer .= VarInt::encodeGroups(VarInt::zigzagEncode32($value));

        return $this;
    }

    /**
     * `varint64` — ZigZag + base-128.
     */
    public function writeVarLong(int $value): self
    {
        $this->buffer .= VarInt::encodeGroups(VarInt::zigzagEncode64($value));

        return $this;
    }

    /**
     * `varuint32` — ZigZag YOK.
     */
    public function writeVarUInt(int $value): self
    {
        VarInt::assertFits32($value);
        $this->buffer .= VarInt::encodeGroups($value);

        return $this;
    }

    /**
     * `varuint64` — ZigZag YOK.
     */
    public function writeVarULong(int $value): self
    {
        $this->buffer .= VarInt::encodeGroups($value);

        return $this;
    }

    /**
     * `string` — varuint32 uzunluk + ham bayt.
     */
    public function writeString(string $value, ?int $maxBytes = null): self
    {
        $length = strlen($value);

        if ($maxBytes !== null && $length > $maxBytes) {
            throw ProtocolException::lengthTooLarge('string', $length, $maxBytes);
        }

        $this->writeVarUInt($length);
        $this->buffer .= $value;

        return $this;
    }

    /**
     * mce::UUID — MSB uint64 (LE) + LSB uint64 (LE).
     */
    public function writeUuid(Uuid $uuid): self
    {
        $this->buffer .= pack('P', $uuid->mostSignificant);
        $this->buffer .= pack('P', $uuid->leastSignificant);

        return $this;
    }

    /**
     * Opsiyonel alan: varlık baytı + değer.
     *
     * @param callable(self): void $writer
     */
    public function writeOptional(bool $present, callable $writer): self
    {
        $this->writeBool($present);

        if ($present) {
            $writer($this);
        }

        return $this;
    }
}
