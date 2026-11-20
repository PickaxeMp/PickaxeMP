<?php

declare(strict_types=1);

namespace PickaxeMP\kernel;

/**
 * Kapatmadan önce bekleyen verisini diske yazması gereken modüller.
 */
interface SaveableInterface
{
    /**
     * Yazılmayı bekleyen veri var mı?
     */
    public function hasPendingWrites(): bool;

    /**
     * Bekleyen veriyi yazar. Hata durumunda istisna fırlatmalıdır; çekirdek
     * bunu "kayıt başarısız" olarak raporlar.
     */
    public function flush(): void;
}
