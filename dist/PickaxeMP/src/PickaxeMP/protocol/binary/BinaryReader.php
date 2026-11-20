<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\binary;

use PickaxeMP\protocol\ProtocolException;

/**
 * Sınır denetimli ikili okuyucu.
 *
 * Kurallar:
 *  - Kaynak tampon **değiştirilmez**; yalnızca içeride bir imleç ilerler.
 *  - Her okuma sınır denetimi yapar; eksik veri `ProtocolException` fırlatır.
 *  - Çok büyük uzunluklar (string/dizi) yapılandırılabilir sınırla reddedilir.
 *  - Tüm çok baytlı tam sayılar **little-endian**'dır; `*BigEndian` ekli
 *    metotlar açıkça büyük-endian okur (Mojang şemasında "Big Endian"
 *    işaretli alanlar için).
 *
 * Endian gerekçesi: Mojang "Primitives" belgesi "native byte order" der ve
 * örneklerde 300 değeri uint16 olarak `0x2C 0x01` (little-endian) gösterilir.
 */
final class BinaryReader
{
    private int $offset = 0;

    /** Varsayılan string üst sınırı: 4 MiB (Login ~100 KB). */
    public const DEFAULT_MAX_STRING = 4 * 1024 * 1024;

    public function __construct(
        private readonly string $buffer,
        private readonly int $maxStringBytes = self::DEFAULT_MAX_STRING
    ) {
    }

    public function length(): int
    {
        return strlen($this->buffer);
    }

    public function offset(): int
    {
        return $this->offset;
    }

    public function remaining(): int
    {
        return strlen($this->buffer) - $this->offset;
    }

    public function isAtEnd(): bool
    {
        return $this->offset >= strlen($this->buffer);
    }

    /**
     * Ham bayt okur.
     */
    public function read(int $length): string
    {
        if ($length < 0) {
            throw ProtocolException::negativeLength('read', $length);
        }

        if ($length > $this->remaining()) {
            throw ProtocolException::unexpectedEnd('bytes', $length, $this->remaining());
        }

        $chunk = substr($this->buffer, $this->offset, $length);
        $this->offset += $length;

        return $chunk;
    }

    public function readByte(): int
    {
        return ord($this->read(1));
    }

    public function readSignedByte(): int
    {
        $value = $this->readByte();

        return $value >= 0x80 ? $value - 0x100 : $value;
    }

    public function readBool(): bool
    {
        // Mojang: "Readers accept any nonzero byte as true."
        return $this->readByte() !== 0;
    }

    public function readUint16(): int
    {
        /** @var array{1: int} $value */
        $value = unpack('v', $this->read(2));

        return $value[1];
    }

    public function readInt16(): int
    {
        $value = $this->readUint16();

        return $value >= 0x8000 ? $value - 0x10000 : $value;
    }

    public function readUint16BigEndian(): int
    {
        /** @var array{1: int} $value */
        $value = unpack('n', $this->read(2));

        return $value[1];
    }

    public function readUint32(): int
    {
        /** @var array{1: int} $value */
        $value = unpack('V', $this->read(4));

        return $value[1];
    }

    public function readInt32(): int
    {
        $value = $this->readUint32();

        return $value >= 0x80000000 ? $value - 0x100000000 : $value;
    }

    public function readInt32BigEndian(): int
    {
        /** @var array{1: int} $value */
        $value = unpack('N', $this->read(4));

        // unpack('N') 64-bit PHP'de işaretsiz döner; işaretliye çevir.
        return $value[1] >= 0x80000000 ? $value[1] - 0x100000000 : $value[1];
    }

    public function readUint64(): int
    {
        /** @var array{1: int} $value */
        $value = unpack('P', $this->read(8));

        return $value[1];
    }

    public function readInt64(): int
    {
        return $this->readUint64();
    }

    public function readFloat(): float
    {
        /** @var array{1: float} $value */
        $value = unpack('g', $this->read(4));

        return $value[1];
    }

    public function readFloatBigEndian(): float
    {
        /** @var array{1: float} $value */
        $value = unpack('G', $this->read(4));

        return $value[1];
    }

    public function readDouble(): float
    {
        /** @var array{1: float} $value */
        $value = unpack('e', $this->read(8));

        return $value[1];
    }

    public function readDoubleBigEndian(): float
    {
        /** @var array{1: float} $value */
        $value = unpack('E', $this->read(8));

        return $value[1];
    }

    /**
     * `varint32` — ZigZag + base-128.
     */
    public function readVarInt(): int
    {
        $decoded = VarInt::decodeGroups($this->buffer, $this->offset, VarInt::MAX_BYTES_32);
        $this->offset += $decoded['length'];

        return VarInt::zigzagDecode32($decoded['value']);
    }

    /**
     * `varint64` — ZigZag + base-128.
     */
    public function readVarLong(): int
    {
        $decoded = VarInt::decodeGroups($this->buffer, $this->offset, VarInt::MAX_BYTES_64);
        $this->offset += $decoded['length'];

        return VarInt::zigzagDecode64($decoded['value']);
    }

    /**
     * `varuint32` — ZigZag YOK. String uzunlukları ve paket kimlikleri için.
     */
    public function readVarUInt(): int
    {
        $decoded = VarInt::decodeGroups($this->buffer, $this->offset, VarInt::MAX_BYTES_32);
        $this->offset += $decoded['length'];

        VarInt::assertFits32($decoded['value']);

        return $decoded['value'];
    }

    /**
     * `varuint64` — ZigZag YOK.
     */
    public function readVarULong(): int
    {
        $decoded = VarInt::decodeGroups($this->buffer, $this->offset, VarInt::MAX_BYTES_64);
        $this->offset += $decoded['length'];

        return $decoded['value'];
    }

    /**
     * `string` — varuint32 uzunluk + ham bayt.
     *
     * @param int|null $maxBytes Bu okuma için özel üst sınır
     */
    public function readString(?int $maxBytes = null): string
    {
        $limit = $maxBytes ?? $this->maxStringBytes;
        $length = $this->readVarUInt();

        if ($length > $limit) {
            throw ProtocolException::lengthTooLarge('string', $length, $limit);
        }

        return $this->read($length);
    }

    /**
     * UTF-8 doğrulaması yaparak string okur.
     */
    public function readUtf8String(?int $maxBytes = null): string
    {
        $raw = $this->readString($maxBytes);

        if (preg_match('//u', $raw) !== 1) {
            throw ProtocolException::invalidUtf8('string');
        }

        return $raw;
    }

    /**
     * mce::UUID — MSB uint64 (LE) + LSB uint64 (LE), 16 bayt.
     */
    public function readUuid(): Uuid
    {
        $mostSignificant = $this->readUint64();
        $leastSignificant = $this->readUint64();

        return new Uuid($mostSignificant, $leastSignificant);
    }

    /**
     * Kalan tüm baytları döndürür ve imleci sona taşır.
     */
    public function readRemaining(): string
    {
        return $this->read($this->remaining());
    }

    /**
     * Çözümden sonra artık bayt kalıp kalmadığını doğrular.
     */
    public function assertFullyConsumed(string $context): void
    {
        if (!$this->isAtEnd()) {
            throw ProtocolException::trailingBytes($context, $this->remaining());
        }
    }
}
