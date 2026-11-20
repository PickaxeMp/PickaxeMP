<?php

declare(strict_types=1);

namespace PickaxeMP\transport\sidecar;

/**
 * Rust sidecar sürecinin yaşam döngüsü sözleşmesi.
 *
 * `SidecarTransport` yalnızca süreci başlatır, durdurur ve el sıkışma
 * bilgilerini okur. Gerçek süreç yönetimi `SidecarProcess`'in işidir;
 * bu arayüz o bağımlılığı gevşetir ve hata yollarının (ör. IPC kopması)
 * Rust ikilisi olmadan da test edilmesini sağlar.
 */
interface SidecarProcessInterface
{
    /**
     * Süreci başlatır ve el sıkışma bilgilerini okur.
     *
     * @throws \PickaxeMP\exception\TransportException başlatma başarısızsa
     */
    public function start(): void;

    /**
     * Süreci durdurur ve geçici dosyaları siler. Idempotent olmalıdır.
     */
    public function stop(): void;

    /**
     * Süreç hâlâ ayakta mı?
     */
    public function isRunning(): bool;

    /**
     * IPC kontrol kanalının TCP portu.
     *
     * @throws \PickaxeMP\exception\TransportException port bilinmiyorsa
     */
    public function getIpcPort(): int;

    /**
     * Paylaşılan IPC token'ı.
     *
     * @throws \PickaxeMP\exception\TransportException token bilinmiyorsa
     */
    public function getToken(): string;

    /**
     * NetherNet signaling adresi (bilinmiyorsa null).
     */
    public function getSignalingAddress(): ?string;
}
