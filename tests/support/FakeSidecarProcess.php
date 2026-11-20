<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\support;

use PickaxeMP\transport\sidecar\SidecarProcessInterface;

/**
 * Bellek içi sahte sidecar süreci.
 *
 * Amaç: `SidecarTransport`'ın çocuk süreç yaşam döngüsünü (başlatma,
 * durdurma) Rust ikilisi olmadan doğrulamak — özellikle IPC koptuğunda
 * veya yazma hatasında sürecin **durdurulduğunu** kanıtlamak.
 */
final class FakeSidecarProcess implements SidecarProcessInterface
{
    public bool $started = false;

    public bool $running = false;

    public int $stopCalls = 0;

    public ?string $signalingAddress = '127.0.0.1:19132';

    public function __construct(
        private readonly string $token = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
        private readonly int $port = 5555
    ) {
    }

    public function start(): void
    {
        $this->started = true;
        $this->running = true;
    }

    public function stop(): void
    {
        $this->stopCalls++;
        $this->running = false;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function getIpcPort(): int
    {
        return $this->port;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getSignalingAddress(): ?string
    {
        return $this->signalingAddress;
    }
}
