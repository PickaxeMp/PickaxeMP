<?php

declare(strict_types=1);

namespace PickaxeMP\transport;

/**
 * Tek bir istemci bağlantısı.
 *
 * Transport bu arayüzü uygular; oyun mantığı yalnızca bunu görür. Böylece
 * NetherNet, RakNet (legacy) veya test taşıyıcısı birbirinin yerine geçebilir.
 *
 * DÜRÜST SINIR: Bu arayüz Bedrock paketlerini ANLAMAZ. Yalnızca ham bayt taşır.
 */
interface ConnectionInterface
{
    /**
     * Taşıyıcı içinde benzersiz bağlantı kimliği.
     */
    public function getId(): string;

    /**
     * İstemcinin uzak adresi (örn. "1.2.3.4:54321").
     */
    public function getRemoteAddress(): string;

    /**
     * Taşıyıcının doğruladığı oyuncu kimliği; doğrulanmadıysa null.
     *
     * NetherNet için bu, SDP offer'daki `a=identity` doğrulamasından gelir.
     * Bu değer sağlanmadan oyun katmanı oyuncuyu kabul etmemelidir.
     *
     * @return array<string, mixed>|null
     */
    public function getIdentity(): ?array;

    /**
     * Güvenilir kanal açık mı?
     */
    public function isReliableChannelOpen(): bool;

    /**
     * Güvenilir kanaldan gönderir (sıralı, kayıpsız).
     */
    public function sendReliable(string $payload): void;

    /**
     * Güvenilmez kanaldan gönderir (gecikmeye duyarlı, kayıp olabilir).
     *
     * NetherNet'te bu kanal parçalama desteklemez; aşırı büyük mesaj atılır.
     */
    public function sendUnreliable(string $payload): void;

    /**
     * Bağlantıyı kapatır. Idempotent olmalıdır.
     */
    public function close(string $reason = ''): void;
}
