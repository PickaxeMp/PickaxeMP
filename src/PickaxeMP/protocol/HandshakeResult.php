<?php

declare(strict_types=1);

namespace PickaxeMP\protocol;

use PickaxeMP\protocol\packet\PacketInterface;

/**
 * El sıkışma adımının sonucu.
 *
 * `authenticationRequired` işaretlenmiş bir sonuç, **girişin tamamlandığı
 * anlamına GELMEZ**: yalnızca "sıradaki katman devralmalı" demektir. Phase 3
 * Authentication Engine olmadan hiçbir oyuncu `Ready` durumuna geçemez.
 */
final class HandshakeResult
{
    /**
     * @param list<PacketInterface> $responses Gönderilecek paketler
     */
    public function __construct(
        public readonly ConnectionState $state,
        public readonly array $responses = [],
        public readonly bool $authenticationRequired = false,
        public readonly string $reason = ''
    ) {
    }

    public static function continue(ConnectionState $state, array $responses = []): self
    {
        return new self($state, $responses);
    }

    public static function needsAuthentication(PacketInterface $login): self
    {
        return new self(
            ConnectionState::AwaitingAuthentication,
            [],
            true,
            'Kimlik dogrulamasi Phase 3 Authentication Engine tarafindan yapilmalidir.'
        );
    }

    public static function fail(string $reason, array $responses = []): self
    {
        return new self(ConnectionState::Closed, $responses, false, $reason);
    }

    public function isClosed(): bool
    {
        return $this->state === ConnectionState::Closed;
    }
}
