<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\ProtocolException;

/**
 * `ItemRegistry` (162) içindeki tek eşya girişi.
 *
 * Tel biçimi (gophertunnel `protocol.ItemEntry.Marshal`):
 *
 * ```
 * 0 Name             string
 * 1 Runtime ID       int16  (sabit LE, zigzag DEGIL)
 * 2 Component Based  bool
 * 3 Version          varint32
 * 4 Data             ag NBT (her zaman yazilir)
 * ```
 *
 * ## `Runtime ID` neden `int16`?
 *
 * İstemci eşya kimlikleri tablosunu `int16` olarak tutar ve hava
 * `-158`'dir. Bu yüzden alan işaretli okunur/yazılır; zigzag varint
 * kullanılsaydı `285` iki bayt yerine farklı bir değere çözülürdü.
 *
 * ## `Data` neden ham bayt?
 *
 * NBT yazıcısı bu projede ağ NBT'si için genel bir API sunmuyor; giriş
 * verisi pratikte boş compound'tır (`0A 00 00`). Ham bayt tutmak, ileride
 * bileşen tabanlı özel eşya eklenirse veriyi olduğu gibi taşır.
 */
final class ItemEntry
{
    /** Boş ağ NBT compound'u: TAG_Compound + boş ad + TAG_End. */
    public const EMPTY_COMPOUND = "\x0A\x00\x00";

    public const MIN_RUNTIME_ID = -32768;

    public const MAX_RUNTIME_ID = 32767;

    public function __construct(
        public readonly string $name,
        public readonly int $runtimeId,
        public readonly bool $componentBased = false,
        public readonly int $version = 2,
        public readonly string $data = self::EMPTY_COMPOUND
    ) {
        if (preg_match('~^[a-z0-9_.-]+:[a-z0-9_./-]+$~', $name) !== 1) {
            throw new \InvalidArgumentException("Gecersiz esya kimligi: {$name}");
        }

        if ($runtimeId < self::MIN_RUNTIME_ID || $runtimeId > self::MAX_RUNTIME_ID) {
            throw new \InvalidArgumentException("Esya runtime id int16 siniri disinda: {$runtimeId}");
        }

        if ($version < 0) {
            throw new \InvalidArgumentException("Esya girisi surumu negatif olamaz: {$version}");
        }

        if ($data === '') {
            throw new \InvalidArgumentException('Esya girisi NBT verisi bos olamaz');
        }
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeString($this->name, 256);
        $writer->writeInt16($this->runtimeId);
        $writer->writeBool($this->componentBased);
        $writer->writeVarInt($this->version);
        $writer->write($this->data);
    }

    public static function decode(BinaryReader $reader): self
    {
        $name = $reader->readString(256);
        $runtimeId = $reader->readInt16();
        $componentBased = $reader->readBool();
        $version = $reader->readVarInt();

        // NBT: yalnizca tek baytlik tur etiketi cozulur. Bos compound
        // (0x0A 0x00 0x00) disindaki veri, ileride desteklenene kadar
        // acikca reddedilir - sessizce yutmak akisi bozardi.
        $tag = $reader->readByte();

        if ($tag !== 0x0A) {
            throw new ProtocolException('ItemRegistry: esya verisi compound bekleniyordu');
        }

        $nameLength = $reader->readVarUInt();

        if ($nameLength !== 0) {
            throw new ProtocolException('ItemRegistry: adli compound desteklenmiyor');
        }

        $end = $reader->readByte();

        if ($end !== 0x00) {
            throw new ProtocolException('ItemRegistry: gecersiz NBT sonu');
        }

        return new self($name, $runtimeId, $componentBased, $version);
    }
}
