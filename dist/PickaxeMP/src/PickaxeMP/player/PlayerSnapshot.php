<?php

declare(strict_types=1);

namespace PickaxeMP\player;

use PickaxeMP\auth\skin\SkinSerializer;
use PickaxeMP\protocol\binary\Uuid;
use PickaxeMP\protocol\packet\packets\AddPlayerPacket;
use PickaxeMP\protocol\packet\packets\PlayerListPacket;
use PickaxeMP\protocol\packet\packets\RemoveActorPacket;
use PickaxeMP\protocol\packet\types\NetworkItemStackDescriptor;
use PickaxeMP\protocol\packet\types\PlayerListEntry;
use PickaxeMP\protocol\packet\types\PropertySyncData;
use PickaxeMP\protocol\packet\types\Vec3;

/**
 * Bir `Player` nesnesinden **baska oyunculara** gonderilecek paketleri uretir.
 *
 * ## Neden ayri sinif?
 *
 * `AddPlayer` 17 alanli, hata yapmaya cok musait bir pakettir. Bu esleme tek
 * yerde tutulursa "bir yerde runtime id, baska yerde entity id" turu hatalar
 * cogalmaz.
 *
 * ## Bilincli sinirlar
 *
 * - `Entity Data` **bos** gonderilir (deri/isim etiketi Phase 8). `AddPlayer`
 *   bos olmayan veriyi zaten reddeder.
 * - `Carried Item` **bos** gonderilir: esya ag kimligi `ItemRegistry`'ye
 *   baglidir ve giris aninda oyuncunun elinde bir sey yoktur.
 * - `Abilities Data` **katmansiz** gonderilir; yetenek sistemi Phase 8.
 * - `Device Id` / `Build Platform` sunucuda bilinmez, bos/0 gonderilir.
 */
final class PlayerSnapshot
{
    public static function addPlayer(Player $player): AddPlayerPacket
    {
        return new AddPlayerPacket(
            uuid: Uuid::fromString($player->identity->uuid),
            playerName: $player->identity->displayName,
            targetRuntimeId: $player->runtimeId,
            platformChatId: '',
            position: new Vec3($player->x(), $player->y(), $player->z()),
            velocity: Vec3::zero(),
            pitch: $player->pitch(),
            yaw: $player->yaw(),
            headYaw: $player->yaw(),
            carriedItem: NetworkItemStackDescriptor::empty(),
            playerGameType: $player->gameMode()->value,
            synchedProperties: new PropertySyncData(),
            abilitiesData: PlayerAbilities::forPlayer($player),
            actorLinks: [],
            deviceId: '',
            buildPlatform: 0,
            entityData: PlayerMetadata::forPlayer($player)
        );
    }

    public static function removeActor(Player $player): RemoveActorPacket
    {
        return new RemoveActorPacket($player->runtimeId);
    }

    /**
     * `PlayerList` ekleme girişi.
     *
     * İstemci bir oyuncunun **derisini ve görünen adını** yalnızca bu
     * girişten okur; `AddPlayer` tek başına gönderilirse oyuncu listede
     * görünmez.
     *
     * ## Deri
     *
     * JWT'deki base64 PNG `PngDecoder` ile ham RGBA'ya çözülür ve
     * `SkinSerializer` tel tipine çevirir. Çözüm başarısız olursa
     * `SerializedSkin::blank()` döner: oyuncu **atılmaz**, istemci
     * varsayılan deriyi çizer.
     *
     * `Actor Unique ID` **`entityId`**'dir, `runtimeId` değil: liste kalıcı
     * kimlik taşır, `runtimeId` oturumluk.
     */
    public static function playerListEntry(Player $player): PlayerListEntry
    {
        return PlayerListEntry::add(
            uuid: Uuid::fromString($player->identity->uuid),
            actorUniqueId: $player->entityId,
            playerName: $player->identity->displayName,
            xuid: $player->identity->xuid,
            platformOnlineId: '',
            buildPlatform: 0,
            skin: SkinSerializer::serialize($player->skin)
        );
    }

    public static function playerListAdd(Player $player): PlayerListPacket
    {
        return new PlayerListPacket([self::playerListEntry($player)]);
    }

    public static function playerListRemove(Player $player): PlayerListPacket
    {
        return new PlayerListPacket([PlayerListEntry::remove(Uuid::fromString($player->identity->uuid))]);
    }
}
