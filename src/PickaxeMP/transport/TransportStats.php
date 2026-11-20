<?php

declare(strict_types=1);

namespace PickaxeMP\transport;

/**
 * Taşıyıcı istatistikleri. İzleme ve teşhis için kullanılır.
 */
final class TransportStats
{
    public function __construct(
        public readonly int $activeConnections = 0,
        public readonly int $totalConnections = 0,
        public readonly int $bytesReceived = 0,
        public readonly int $bytesSent = 0,
        public readonly int $payloadsReceived = 0,
        public readonly int $payloadsSent = 0,
        public readonly int $rejectedConnections = 0,
        public readonly int $droppedPayloads = 0
    ) {
    }

    public static function empty(): self
    {
        return new self();
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'active_connections' => $this->activeConnections,
            'total_connections' => $this->totalConnections,
            'bytes_received' => $this->bytesReceived,
            'bytes_sent' => $this->bytesSent,
            'payloads_received' => $this->payloadsReceived,
            'payloads_sent' => $this->payloadsSent,
            'rejected_connections' => $this->rejectedConnections,
            'dropped_payloads' => $this->droppedPayloads,
        ];
    }
}
