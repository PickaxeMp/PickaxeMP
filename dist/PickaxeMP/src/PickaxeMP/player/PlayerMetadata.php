<?php

declare(strict_types=1);

namespace PickaxeMP\player;

use PickaxeMP\protocol\packet\types\SynchedActorData;
use PickaxeMP\protocol\packet\types\SynchedActorDataEntry;

/**
 * Bir oyuncunun `Entity Data` (varlık meta verisi).
 *
 * ## Neden gerekli?
 *
 * `AddPlayer` meta veri olmadan gönderildiğinde istemci diğer oyuncuların
 * **adını dünyada göstermez**; oyuncu listesinde görünür ama üstünde etiket
 * olmaz. Vanilla sunucu her zaman `NAMETAG` ve gerekli `FLAGS` bitlerini
 * yollar.
 *
 * ## Sabitler nereden?
 *
 * - `DATA_FLAGS = 0`, `DATA_NAMETAG = 4`: pmmp `ActorDataIds`.
 * - `CAN_SHOW_NAMETAG` = bit 14, `ALWAYS_SHOW_NAMETAG` = bit 15:
 *   pmmp `Entity::DATA_FLAG_*`.
 *
 * ## Bilinçli sınırlar
 *
 * - Yalnızca iki alan gönderilir. Sağlık, envanter, zırh vb. meta veri
 *   gönderilmez; istemci bunları ayrı paketlerden alır.
 * - `SCALE`, `BOUNDING_BOX_WIDTH` gibi alanlar gönderilmez; istemci varlık
 *   türüne göre varsayılanı uygular.
 */
final class PlayerMetadata
{
    /** `DATA_FLAGS` — varlık durum bayrakları (int64). */
    public const DATA_FLAGS = 0;

    /** `DATA_NAMETAG` — görünen isim (string). */
    public const DATA_NAMETAG = 4;

    /** Bayrak: isim etiketi gösterilebilir. */
    public const FLAG_CAN_SHOW_NAMETAG = 1 << 14;

    /** Bayrak: isim etiketi mesafeden bağımsız görünür. */
    public const FLAG_ALWAYS_SHOW_NAMETAG = 1 << 15;

    /**
     * Bedrock isim etiketi sınırı.
     *
     * `writeString` 128 baytta keser; daha uzun bir ad göndermek paketi
     * istemcide geçersiz kılar. Burada **bilinçli** olarak kesiyoruz:
     * görünen ad zaten `PlayerIdentity` tarafından doğrulanmıştır.
     */
    public const MAX_NAMETAG_LENGTH = 128;

    /**
     * Bir oyuncunun meta verisi.
     */
    public static function forPlayer(Player $player): SynchedActorData
    {
        return new SynchedActorData([
            SynchedActorDataEntry::int64(
                self::DATA_FLAGS,
                self::FLAG_CAN_SHOW_NAMETAG | self::FLAG_ALWAYS_SHOW_NAMETAG
            ),
            SynchedActorDataEntry::string(
                self::DATA_NAMETAG,
                self::truncate($player->identity->displayName, self::MAX_NAMETAG_LENGTH)
            ),
        ]);
    }

    /**
     * Adı bayt sınırına keser.
     *
     * `substr` çok baytlı bir karakteri ortadan bölebilir; istemci geçersiz
     * UTF-8 görürse adı çizmez. Bu yüzden kesim **karakter** sınırında
     * yapılır, sonra bayt uzunluğu doğrulanır.
     */
    private static function truncate(string $name, int $maxBytes): string
    {
        if (\strlen($name) <= $maxBytes) {
            return $name;
        }

        $truncated = \mb_substr($name, 0, $maxBytes, 'UTF-8');

        while ($truncated !== '' && \strlen($truncated) > $maxBytes) {
            $truncated = \mb_substr($truncated, 0, \mb_strlen($truncated, 'UTF-8') - 1, 'UTF-8');
        }

        return $truncated;
    }
}
