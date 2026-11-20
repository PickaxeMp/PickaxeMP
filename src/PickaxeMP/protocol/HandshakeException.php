<?php

declare(strict_types=1);

namespace PickaxeMP\protocol;

/**
 * El sıkışma sırası ihlali (beklenmeyen paket, tanımsız geçiş).
 */
final class HandshakeException extends ProtocolException
{
    public static function unexpectedPacket(string $state, int $packetId): self
    {
        return new self(sprintf(
            "Beklenmeyen paket %s (0x%02X); durum: %s",
            \PickaxeMP\protocol\packet\PacketId::name($packetId),
            $packetId,
            $state
        ));
    }

    public static function illegalTransition(string $from, string $to): self
    {
        return new self("Gecersiz durum gecisi: {$from} → {$to}");
    }
}
