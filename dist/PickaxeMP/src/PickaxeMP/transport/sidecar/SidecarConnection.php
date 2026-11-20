<?php

declare(strict_types=1);

namespace PickaxeMP\transport\sidecar;

use PickaxeMP\transport\ConnectionInterface;

/**
 * Sidecar'ın bildirdiği tek bir bağlantı.
 *
 * Gönderim metotları taşıyıcıya delege edilmez; doğrudan sokağa yazmak yerine
 * `SidecarTransport` üzerinden gitmek gerekir. Bu sınıf yalnızca durumu tutar.
 */
final class SidecarConnection implements ConnectionInterface
{
    private bool $closed = false;

    /**
     * @param array<string, mixed>|null $identity
     */
    public function __construct(
        private readonly string $id,
        private readonly string $remoteAddress,
        private readonly ?array $identity
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getRemoteAddress(): string
    {
        return $this->remoteAddress;
    }

    public function getIdentity(): ?array
    {
        return $this->identity;
    }

    public function isReliableChannelOpen(): bool
    {
        return !$this->closed;
    }

    public function sendReliable(string $payload): void
    {
        throw new \LogicException(
            'SidecarConnection dogrudan gonderemez; SidecarTransport::sendReliable() kullanin.'
        );
    }

    public function sendUnreliable(string $payload): void
    {
        throw new \LogicException(
            'SidecarConnection dogrudan gonderemez; SidecarTransport::sendUnreliable() kullanin.'
        );
    }

    public function close(string $reason = ''): void
    {
        throw new \LogicException(
            'SidecarConnection dogrudan kapatamaz; SidecarTransport::close() kullanin.'
        );
    }

    /**
     * Taşıyıcı tarafından çağrılır.
     */
    public function markClosed(string $reason): void
    {
        $this->closed = true;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }
}
