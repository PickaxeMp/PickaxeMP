<?php

declare(strict_types=1);

namespace PickaxeMP\transport;

/**
 * Taşıyıcı sözleşmesi.
 *
 * NetherNet (Rust sidecar), legacy RakNet veya test taşıyıcısı bu arayüzü
 * uygular. Oyun katmanı yalnızca bu arayüzü bilir.
 *
 * DÜRÜST SINIR: Bu katman Bedrock paketlerini çözmez; yalnızca bağlantı
 * yaşam döngüsünü ve ham bayt taşımayı yönetir.
 *
 * tick() non-blocking olmalıdır: ana döngüyü kilitlememeli, gelen olayları
 * sınırlı sürede işlemelidir.
 */
interface TransportInterface
{
    /**
     * Taşıyıcıyı başlatır (soket aç, sidecar başlat, signaling dinle).
     *
     * @throws \PickaxeMP\exception\TransportException başlatma başarısızsa
     */
    public function start(): void;

    /**
     * Taşıyıcıyı durdurur. Idempotent olmalıdır.
     */
    public function stop(): void;

    /**
     * Olayları işler. Ana tick döngüsünden çağrılır.
     *
     * @param float $deltaSeconds Son tick'ten bu yana geçen süre
     */
    public function tick(float $deltaSeconds): void;

    /**
     * Olay dinleyicisini bağlar.
     */
    public function setListener(TransportListenerInterface $listener): void;

    /**
     * Belirtilen bağlantıya güvenilir kanaldan gönderir.
     *
     * @throws \PickaxeMP\exception\TransportException bağlantı yoksa/kapalıysa
     */
    public function sendReliable(string $connectionId, string $payload): void;

    /**
     * Belirtilen bağlantıya güvenilmez kanaldan gönderir.
     */
    public function sendUnreliable(string $connectionId, string $payload): void;

    /**
     * Bağlantıyı kapatır.
     */
    public function close(string $connectionId, string $reason = ''): void;

    /**
     * Kurulu bağlantılar.
     *
     * @return array<string, ConnectionInterface>
     */
    public function getConnections(): array;

    public function getStats(): TransportStats;

    public function isRunning(): bool;

    /**
     * İnsan okunabilir taşıyıcı adı (örn. "nethernet", "raknet-legacy", "fake").
     */
    public function getName(): string;
}
