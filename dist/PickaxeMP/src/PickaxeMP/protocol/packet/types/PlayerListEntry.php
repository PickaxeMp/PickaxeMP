<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\binary\Uuid;
use PickaxeMP\protocol\ProtocolException;

/**
 * `PlayerList` girişi - tek bir oyuncunun ekleme/silme kaydı.
 *
 * Mojang şeması `PlayerListAddEntry.json` / `PlayerListRemoveEntry.json`.
 *
 * ## Tel biçimi: iki ayırıcı
 *
 * Şema dizinin öğelerine `x-control-value-type: uint32` der. Bu, cereal'in
 * `std::variant` indeksidir ve gerçekten yazılır:
 *
 * ```
 * variant  varuint32   1 = Add, 0 = Remove
 * Action   uint8       0 = Add, 1 = Remove
 * ```
 *
 * Yalnızca `Action` yazılırsa paket bir bayt eksik olur ve istemci
 * çözemez. Referans uygulama (`gophertunnel` `playerListAction`) ikisini de
 * yazar; bu sınıf onu izler.
 *
 * Add girişinin geri kalanı (ordinal sırası):
 *
 * ```
 *  1 UUID                mce::UUID (MSB, LSB - uint64 LE)
 *  2 Actor Unique ID     int64 (Compression -> zigzag varint64)
 *  3 Player Name         string
 *  4 XBL XUID            string
 *  5 PlayFab ID          string
 *  6 Platform Online ID  string
 *  7 Build Platform      int32 (sabit; Compression yok)
 *  8 Serialized Skin     SerializedSkin
 *  9 Is Teacher          bool
 * 10 Is Host             bool
 * 11 Is SubClient        bool
 * 12 Player Color        Color (buyuk-endian int32)
 * ```
 *
 * Remove girişi `variant`, `Action` ve `UUID` dışında bir şey taşımaz.
 */
final class PlayerListEntry
{
    public const ACTION_ADD = 0;
    public const ACTION_REMOVE = 1;

    public function __construct(
        public readonly int $action,
        public readonly Uuid $uuid,
        public readonly int $actorUniqueId = 0,
        public readonly string $playerName = '',
        public readonly string $xuid = '',
        public readonly string $playFabId = '',
        public readonly string $platformOnlineId = '',
        public readonly int $buildPlatform = 0,
        public readonly ?SerializedSkin $skin = null,
        public readonly bool $isTeacher = false,
        public readonly bool $isHost = false,
        public readonly bool $isSubClient = false,
        public readonly ?Color $playerColor = null
    ) {
    }

    public static function add(
        Uuid $uuid,
        int $actorUniqueId,
        string $playerName,
        string $xuid = '',
        string $platformOnlineId = '',
        int $buildPlatform = 0,
        ?SerializedSkin $skin = null
    ): self {
        return new self(
            action: self::ACTION_ADD,
            uuid: $uuid,
            actorUniqueId: $actorUniqueId,
            playerName: $playerName,
            xuid: $xuid,
            playFabId: '',
            platformOnlineId: $platformOnlineId,
            buildPlatform: $buildPlatform,
            skin: $skin ?? SerializedSkin::blank(),
            playerColor: Color::white()
        );
    }

    public static function remove(Uuid $uuid): self
    {
        return new self(action: self::ACTION_REMOVE, uuid: $uuid);
    }

    public function isAdd(): bool
    {
        return $this->action === self::ACTION_ADD;
    }

    public function encode(BinaryWriter $writer): void
    {
        // cereal variant indeksi: Add = 1, Remove = 0.
        $writer->writeVarUInt($this->isAdd() ? 1 : 0);
        $writer->writeByte($this->isAdd() ? self::ACTION_ADD : self::ACTION_REMOVE);
        $writer->writeUuid($this->uuid);

        if (!$this->isAdd()) {
            return;
        }

        $writer->writeVarLong($this->actorUniqueId);
        $writer->writeString($this->playerName, 128);
        $writer->writeString($this->xuid, 64);
        $writer->writeString($this->playFabId, 64);
        $writer->writeString($this->platformOnlineId, 64);
        $writer->writeInt32($this->buildPlatform);
        ($this->skin ?? SerializedSkin::blank())->encode($writer);
        $writer->writeBool($this->isTeacher);
        $writer->writeBool($this->isHost);
        $writer->writeBool($this->isSubClient);
        ($this->playerColor ?? Color::white())->encode($writer);
    }

    public static function decode(BinaryReader $reader): self
    {
        $variant = $reader->readVarUInt();
        $action = $reader->readByte();
        $uuid = $reader->readUuid();

        // variant 1 = Add, 0 = Remove; Action ile celisirse paket bozuktur.
        if (($variant === 1) !== ($action === self::ACTION_ADD)) {
            throw new ProtocolException(
                "PlayerListEntry: variant ({$variant}) ile Action ({$action}) uyusmuyor"
            );
        }

        if ($action === self::ACTION_REMOVE) {
            return new self(action: self::ACTION_REMOVE, uuid: $uuid);
        }

        return new self(
            action: self::ACTION_ADD,
            uuid: $uuid,
            actorUniqueId: $reader->readVarLong(),
            playerName: $reader->readString(128),
            xuid: $reader->readString(64),
            playFabId: $reader->readString(64),
            platformOnlineId: $reader->readString(64),
            buildPlatform: $reader->readInt32(),
            skin: SerializedSkin::decode($reader),
            isTeacher: $reader->readBool(),
            isHost: $reader->readBool(),
            isSubClient: $reader->readBool(),
            playerColor: Color::decode($reader)
        );
    }
}
