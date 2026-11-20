<?php

declare(strict_types=1);

namespace PickaxeMP\transport\sidecar;

/**
 * IPC soketi sözleşmesi.
 *
 * Taşıyıcının I/O yapması gereken asgari yüzey. Gerçek uygulama
 * `StreamIpcSocket` (PHP stream); testler sahte (fake) uygulamalar
 * kullanarak kısmi yazma, soket dolması ve okuma hatası gibi
 * hata yollarını deterministik olarak tetikleyebilir.
 */
interface IpcSocketInterface
{
    /**
     * Okunabilir baytları döndürür.
     *
     * @param int $length En fazla kaç bayt okunacak
     *
     * @return string|false Okunan baytlar; okuma hatasında false,
     *         akış bittiyse boş string
     */
    public function read(int $length): string|false;

    /**
     * Baytları yazar.
     *
     * @return int|false Yazılan bayt sayısı (kısmi yazma mümkündür);
     *         hata durumunda false
     */
    public function write(string $data): int|false;

    /**
     * Uzak uç bağlantıyı kapattı mı?
     */
    public function eof(): bool;

    /**
     * Soketi kapatır. Idempotent olmalıdır.
     */
    public function close(): void;
}
