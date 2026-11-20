<?php

declare(strict_types=1);

namespace PickaxeMP\nbt;

/**
 * Bedrock **little-endian** NBT yazıcısı (ağ biçimi).
 *
 * Sadece blok durumu hash'i için gereken en küçük alt küme uygulanır:
 * compound, string, byte, int, end. Fazlası eklenmedi — kullanılmayan kod
 * bakım yüküdür.
 *
 * ## Biçim farkı (Kritik)
 *
 * Bedrock iki NBT biçimi kullanır:
 *  - **Disk (LevelDB)**: big-endian, varint yok.
 *  - **Ağ (network)**: little-endian, tam sayılar ve uzunluklar **varint**,
 *    string uzunlukları **zigzag'sız varuint**.
 *
 * Blok durumu hash'i **ağ biçimini** kullanır (Mojang `block-network-ids-are-hashes`).
 */
final class NbtWriter
{
    public const TAG_END = 0;
    public const TAG_BYTE = 1;
    public const TAG_SHORT = 2;
    public const TAG_INT = 3;
    public const TAG_LONG = 4;
    public const TAG_FLOAT = 5;
    public const TAG_DOUBLE = 6;
    public const TAG_BYTE_ARRAY = 7;
    public const TAG_STRING = 8;
    public const TAG_LIST = 9;
    public const TAG_COMPOUND = 10;

    private string $buffer = '';

    public function bytes(): string
    {
        return $this->buffer;
    }

    public function writeByte(int $value): self
    {
        $this->buffer .= chr($value & 0xFF);

        return $this;
    }

    public function writeShort(int $value): self
    {
        $this->buffer .= pack('v', $value & 0xFFFF);

        return $this;
    }

    public function writeInt(int $value): self
    {
        $this->buffer .= pack('V', $value & 0xFFFFFFFF);

        return $this;
    }

    public function writeLong(int $value): self
    {
        $this->buffer .= pack('P', $value);

        return $this;
    }

    public function writeFloat(float $value): self
    {
        $this->buffer .= pack('g', $value);

        return $this;
    }

    public function writeDouble(float $value): self
    {
        $this->buffer .= pack('e', $value);

        return $this;
    }

    /**
     * Ağ NBT'sinde string uzunluğu **zigzag'sız varuint32**'dir.
     */
    public function writeString(string $value): self
    {
        $this->writeUnsignedVarInt(strlen($value));
        $this->buffer .= $value;

        return $this;
    }

    /**
     * ZigZag'sız varuint32 (uzunluklar için).
     */
    public function writeUnsignedVarInt(int $value): self
    {
        if ($value < 0 || $value > 0xFFFFFFFF) {
            throw new \InvalidArgumentException("Gecersiz varuint: {$value}");
        }

        do {
            $byte = $value & 0x7F;
            $value >>= 7;

            if ($value !== 0) {
                $byte |= 0x80;
            }

            $this->buffer .= chr($byte);
        } while ($value !== 0);

        return $this;
    }

    /**
     * Adlandırılmış boş compound açar (ağ biçimi: ad uzunluğu varuint).
     */
    public function beginCompound(string $name = ''): self
    {
        $this->buffer .= chr(self::TAG_COMPOUND);
        $this->writeString($name);

        return $this;
    }

    /**
     * Compound kapatır: TAG_End.
     */
    public function endCompound(): self
    {
        $this->buffer .= chr(self::TAG_END);

        return $this;
    }

    public function writeStringTag(string $name, string $value): self
    {
        $this->buffer .= chr(self::TAG_STRING);
        $this->writeString($name);
        $this->writeString($value);

        return $this;
    }

    public function writeByteTag(string $name, int $value): self
    {
        $this->buffer .= chr(self::TAG_BYTE);
        $this->writeString($name);
        $this->writeByte($value);

        return $this;
    }

    public function writeIntTag(string $name, int $value): self
    {
        $this->buffer .= chr(self::TAG_INT);
        $this->writeString($name);
        $this->writeInt($value);

        return $this;
    }
}
