<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\support;

use PickaxeMP\transport\ConnectionInterface;

final class FakeConnection implements ConnectionInterface
{
    public bool $closed = false;

    public bool $reliableOpen = true;

    /** @var list<string> */
    public array $sentReliable = [];

    /** @var list<string> */
    public array $sentUnreliable = [];

    /**
     * @param array<string, mixed>|null $identity
     */
    public function __construct(
        private readonly string $id,
        private readonly ?array $identity = null,
        private readonly string $remoteAddress = '127.0.0.1:19132'
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
        return $this->reliableOpen && !$this->closed;
    }

    public function sendReliable(string $payload): void
    {
        $this->sentReliable[] = $payload;
    }

    public function sendUnreliable(string $payload): void
    {
        $this->sentUnreliable[] = $payload;
    }

    public function close(string $reason = ''): void
    {
        $this->closed = true;
    }
}
