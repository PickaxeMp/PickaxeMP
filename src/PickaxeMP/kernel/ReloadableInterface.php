<?php

declare(strict_types=1);

namespace PickaxeMP\kernel;

/**
 * Canlı yenilenebilir modül sözleşmesi.
 *
 * Yaşam döngüsü:
 *   1. prepareReload()  — yeni durumu hazırla, UYGULAMA. Yan etkisiz olmalı.
 *   2. validateReload() — hazırlanan durumu doğrula. İstisna = commit yok.
 *   3. commitReload()   — hızlı ve geri alınamaz yan etki içermeyen takas.
 *   4. rollbackReload() — commit başarısızsa eski durumu koru/telafi et.
 *
 * DÜRÜST SINIR: rollbackReload() ACID geri alma değildir; telafi edici
 * (compensating) bir işlemdir. Bu nedenle commitReload() içinde dış dünyada
 * geri alınamaz yan etki (dosya silme, ağ isteği, ödeme vb.) yapılmamalıdır.
 *
 * DÜRÜST SINIR: PHP yüklenmiş sınıf kodunu çalışan süreçte değiştiremez.
 * Bu arayüz kod değişikliğini değil, yapılandırma/menü/veri yenilemeyi kapsar.
 */
interface ReloadableInterface extends ModuleInterface
{
    /**
     * Yeni durumu hazırlar. Uygulamaz, yan etki yaratmaz.
     */
    public function prepareReload(ReloadRequest $request): ReloadPlan;

    /**
     * Hazırlanan planı doğrular. Hata varsa uygun istisnayı fırlatır.
     */
    public function validateReload(ReloadPlan $plan): void;

    /**
     * Hazırlanan planı devreye alır. Hızlı olmalı, tick döngüsünü kilitlememelidir.
     */
    public function commitReload(ReloadPlan $plan): void;

    /**
     * Başarısız bir reload sonrası eski durumu korumak için çağrılır.
     */
    public function rollbackReload(ReloadPlan $plan): void;
}
