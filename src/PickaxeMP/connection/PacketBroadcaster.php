<?php

declare(strict_types=1);

namespace PickaxeMP\connection;

use PickaxeMP\protocol\packet\PacketInterface;

/**
 * Oyunculara paket yollamanin **tek** arayuzu.
 *
 * Neden arayuz? Blok kirma/yayin mantigi `ConnectionPipeline` sinifini
 * tanimak zorunda kalmasin: birim testleri sahte bir yayinci ile calisir,
 * uretimde ise pipeline'in kendisi uygular.
 *
 * ## Kural: yayin yalnizca "oynayan" baglantilara gider
 *
 * `sendTo`/`broadcastExcept` **sessizce** basarisiz olabilir:
 *
 * - Cifrelemesi kanitlanmamis baglantiya dunya verisi sizdirilmaz.
 * - `Playing` durumuna gecmemis (henuz `StartGame` almamis) baglanti
 *   `UpdateBlock`/`AddPlayer` gibi paketleri anlamlandiramaz.
 * - Tek bir baglantinin gonderim hatasi **turu durdurmaz**; hata loglanir
 *   ve diger oyuncular paketi almaya devam eder.
 */
interface PacketBroadcaster
{
    /**
     * Belirli bir oturuma paket yollar.
     *
     * @return bool paket gercekten gonderildi mi? (uygun baglanti yoksa false)
     */
    public function sendTo(string $sessionId, PacketInterface $packet): bool;

    /**
     * Bir oturum haric tum uygun oyunculara paket yollar.
     *
     * @return int paketin basariyla gonderildigi baglanti sayisi
     */
    public function broadcastExcept(?string $exceptSessionId, PacketInterface $packet): int;
}
