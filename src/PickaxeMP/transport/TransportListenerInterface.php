<?php

declare(strict_types=1);

namespace PickaxeMP\transport;

/**
 * Taşıyıcı olaylarını dinleyen katman.
 *
 * Uygulayan sınıf (genelde SessionManager) ham bayt alır ve Bedrock protokol
 * katmanına devreder. Taşıyıcı bu sınıfın ne yaptığını bilmez.
 */
interface TransportListenerInterface
{
    /**
     * Yeni bağlantı kuruldu; kimlik henüz doğrulanmamış olabilir.
     */
    public function onConnectionOpen(ConnectionInterface $connection): void;

    /**
     * Ham Bedrock yükü geldi (tek SCTP mesajı, 1 baytlık NetherNet başlığı
     * zaten taşıyıcı tarafından çıkarılmış olmalıdır).
     */
    public function onPayload(ConnectionInterface $connection, string $payload): void;

    /**
     * Bağlantı kapandı. Idempotent olabilir; aynı bağlantı için birden fazla
     * çağrı gelebilir, uygulama buna dayanıklı olmalıdır.
     */
    public function onConnectionClosed(ConnectionInterface $connection, string $reason): void;
}
