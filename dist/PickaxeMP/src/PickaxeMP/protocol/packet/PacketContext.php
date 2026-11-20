<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet;

use PickaxeMP\protocol\ProtocolException;
use PickaxeMP\protocol\ProtocolVersion;

/**
 * Paket çözme/kodlama sırasında taşınan bağlam.
 *
 * Paketler bu nesneye ihtiyaç duymaz; oturum katmanı ve teşhis için taşınır.
 */
final class PacketContext
{
    public function __construct(
        public readonly string $connectionId = '',
        public readonly PacketDirection $direction = PacketDirection::ClientToServer,
        public readonly int $protocolVersion = ProtocolVersion::PROTOCOL,
        /** Fazla baytları hata sayar mı? Geliştirme/test için açık tutulur. */
        public readonly bool $strict = true
    ) {
    }

    public function assertCompatible(): void
    {
        if (!ProtocolVersion::isCompatible($this->protocolVersion)) {
            throw ProtocolException::incompatibleProtocol(
                $this->protocolVersion,
                ProtocolVersion::PROTOCOL
            );
        }
    }

    public function withDirection(PacketDirection $direction): self
    {
        return new self(
            $this->connectionId,
            $direction,
            $this->protocolVersion,
            $this->strict
        );
    }
}
