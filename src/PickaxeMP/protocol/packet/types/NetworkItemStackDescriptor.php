<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\ProtocolException;

/**
 * `NetworkItemStackDescriptor` — ag uzerindeki esya tanimlayicisi.
 *
 * Tel bicimi (Mojang `cerealizer<NetworkItemStackDescriptor>::SerializedData`,
 * ordinal 0,1,2,3,4,5):
 *
 * ```
 *  0 Id                int16  (LE), 0 = gecersiz esya
 *  1 Stack size        uint16 (LE)
 *  2 Aux value         varuint32 (en fazla 32767)
 *  3 Net Id Variant    istege bagli: varlik bayti + varint32
 *  4 Block Runtime Id  varuint32
 *  5 User Data Buffer  string (varuint32 uzunluk + ham bayt)
 * ```
 *
 * `User Data Buffer`, `ItemInstanceUserData` ikili blob'udur; Phase 6
 * kapsaminda NBT uretilmez, cagiran ham bayt verir. Bos tampon "ozel
 * ad/bicim yok" demektir.
 */
final class NetworkItemStackDescriptor
{
    public const MAX_AUX_VALUE = 32767;

    public const MAX_USER_DATA = 1024 * 1024;

    /**
     * Bos `ItemInstanceUserData` blob'u (4 bayt).
     *
     * Alan opak bir ikili tampondur; Phase 6 icerigini yorumlamaz. Sabit
     * uzunluk, tel bicimi testinde (`0x04` uzunluk on eki) dogrulanir.
     */
    public const EMPTY_USER_DATA = "\x00\x00\x00\x00";

    public function __construct(
        public readonly int $id,
        public readonly int $count = 0,
        public readonly int $auxValue = 0,
        public readonly int $blockRuntimeId = 0,
        public readonly ?int $netId = null,
        public readonly string $userData = self::EMPTY_USER_DATA
    ) {
        if ($id < -32768 || $id > 32767) {
            throw ProtocolException::varIntOverflow('item id (int16)');
        }

        if ($count < 0 || $count > 0xFFFF) {
            throw ProtocolException::varIntOverflow('item count (uint16)');
        }

        if ($auxValue < 0 || $auxValue > self::MAX_AUX_VALUE) {
            throw ProtocolException::varIntOverflow('item aux value (uint32, <=32767)');
        }

        if ($blockRuntimeId < 0 || $blockRuntimeId > 0xFFFFFFFF) {
            throw ProtocolException::varIntOverflow('block runtime id (uint32)');
        }

        if (strlen($userData) > self::MAX_USER_DATA) {
            throw ProtocolException::lengthTooLarge(
                'item user data',
                strlen($userData),
                self::MAX_USER_DATA
            );
        }
    }

    public static function empty(): self
    {
        return new self(id: 0, count: 0, userData: '');
    }

    public function isEmpty(): bool
    {
        return $this->id === 0;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeInt16($this->id);
        $writer->writeUint16($this->count);
        $writer->writeVarUInt($this->auxValue);

        $writer->writeOptional($this->netId !== null, function (BinaryWriter $w): void {
            $w->writeVarInt($this->netId ?? 0);
        });

        $writer->writeVarUInt($this->blockRuntimeId);
        $writer->writeString($this->userData);
    }

    public static function decode(BinaryReader $reader): self
    {
        $id = $reader->readInt16();
        $count = $reader->readUint16();
        $auxValue = $reader->readVarUInt();

        $netId = $reader->readBool() ? $reader->readVarInt() : null;

        $blockRuntimeId = $reader->readVarUInt();
        $userData = $reader->readString(self::MAX_USER_DATA);

        return new self(
            id: $id,
            count: $count,
            auxValue: $auxValue,
            blockRuntimeId: $blockRuntimeId,
            netId: $netId,
            userData: $userData
        );
    }
}
