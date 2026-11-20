<?php

declare(strict_types=1);

namespace PickaxeMP\session;

/**
 * Oturum durumu.
 *
 * Geçişler `SessionState::canTransitionTo()` ile kontrol edilir. Tanımsız
 * geçiş istisna üretir — "sıra atlama" mümkün değildir.
 */
enum SessionState: string
{
    case Connecting = 'connecting';
    case AwaitingNetworkSettings = 'awaiting_network_settings';
    case AwaitingLogin = 'awaiting_login';
    case Authenticating = 'authenticating';
    case Authenticated = 'authenticated';
    case Initializing = 'initializing';
    case Playing = 'playing';
    case Closing = 'closing';
    case Closed = 'closed';
    case Failed = 'failed';

    /**
     * Geçerli geçiş tablosu.
     *
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Connecting => [self::AwaitingNetworkSettings, self::Closing, self::Failed],
            self::AwaitingNetworkSettings => [self::AwaitingLogin, self::Closing, self::Failed],
            self::AwaitingLogin => [self::Authenticating, self::Closing, self::Failed],
            self::Authenticating => [self::Authenticated, self::Closing, self::Failed],
            self::Authenticated => [self::Initializing, self::Closing, self::Failed],
            self::Initializing => [self::Playing, self::Closing, self::Failed],
            self::Playing => [self::Closing, self::Failed],
            self::Closing => [self::Closed, self::Failed],
            // Terminal durumlar: çıkış yok.
            self::Closed => [],
            self::Failed => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    public function isTerminal(): bool
    {
        return $this === self::Closed || $this === self::Failed;
    }

    /**
     * Oyuncu artık oyun içinde mi?
     */
    public function isPlaying(): bool
    {
        return $this === self::Playing;
    }

    /**
     * Kimlik doğrulaması tamamlandı mı?
     */
    public function isAuthenticated(): bool
    {
        return in_array($this, [
            self::Authenticated,
            self::Initializing,
            self::Playing,
            self::Closing,
        ], true);
    }
}
