<?php

declare(strict_types=1);

namespace PickaxeMP\session;

use PickaxeMP\auth\PlayerIdentity;
use PickaxeMP\auth\SkinData;
use PickaxeMP\utils\Logger;

/**
 * Tüm oturumların tek sahibi.
 *
 * ## Sorumluluklar
 *
 * - Bağlantı ↔ oturum eşlemesi
 * - Oyuncu (XUID) başına **tek** oturum kuralı
 * - Zaman aşımı tespiti
 * - Güvenli kapatma
 *
 * ## Değişmez kurallar
 *
 * 1. Aynı `connectionId` için ikinci oturum açılamaz.
 * 2. Aynı XUID ile ikinci oturum açılamaz (çakışma).
 * 3. Bir oturumun hatası diğerlerini etkilemez — her metot yalnızca hedef
 *    oturumla çalışır.
 * 4. Kapanan oturumlara referans tutulmaz; `close()` sonrası erişilemez.
 */
final class SessionManager
{
    /** @var array<string, Session> sessionId => oturum */
    private array $sessions = [];

    /** @var array<string, string> connectionId => sessionId */
    private array $byConnection = [];

    /** @var array<string, string> xuid => sessionId */
    private array $byXuid = [];

    /**
     * Oturumun kimlik ataması temizlense bile XUID indeksini çözebilmek için
     * sessionId => xuid eşlemesi tutulur.
     *
     * @var array<string, string>
     */
    private array $xuidBySession = [];

    private int $counter = 0;

    public function __construct(
        private readonly Logger $logger,
        /** Zaman aşımı saniye cinsinden; 0 = kapalı */
        private readonly int $timeoutSeconds = 300
    ) {
    }

    /**
     * Yeni oturum kaydeder.
     *
     * @throws SessionException aynı bağlantı için oturum varsa
     */
    public function create(
        string $connectionId,
        string $remoteAddress,
        ?int $now = null
    ): Session {
        if (isset($this->byConnection[$connectionId])) {
            throw SessionException::alreadyRegistered($connectionId);
        }

        $id = 's' . (++$this->counter);
        $session = new Session($id, $connectionId, $remoteAddress, $now);

        $this->sessions[$id] = $session;
        $this->byConnection[$connectionId] = $id;

        $this->logger->debug('Oturum olusturuldu.', ['session' => $id, 'conn' => $connectionId]);

        return $session;
    }

    public function get(string $sessionId): ?Session
    {
        return $this->sessions[$sessionId] ?? null;
    }

    public function require(string $sessionId): Session
    {
        $session = $this->sessions[$sessionId] ?? null;

        if ($session === null) {
            throw SessionException::unknownSession($sessionId);
        }

        return $session;
    }

    public function byConnection(string $connectionId): ?Session
    {
        $id = $this->byConnection[$connectionId] ?? null;

        return $id === null ? null : ($this->sessions[$id] ?? null);
    }

    public function byXuid(string $xuid): ?Session
    {
        $id = $this->byXuid[$xuid] ?? null;

        return $id === null ? null : ($this->sessions[$id] ?? null);
    }

    /**
     * Kimliği oturuma bağlar; çakışan oyuncu varsa reddeder.
     *
     * @throws SessionException aynı XUID başka oturumda ise
     */
    public function bindIdentity(Session $session, PlayerIdentity $identity, ?int $now = null, ?SkinData $skin = null): void
    {
        if (($this->sessions[$session->id] ?? null) !== $session) {
            throw SessionException::unknownSession($session->id);
        }

        $existingId = $this->byXuid[$identity->xuid] ?? null;

        if ($existingId !== null && $existingId !== $session->id) {
            $existing = $this->sessions[$existingId] ?? null;

            // Terminal (ör. Failed/Closed) eski oturum artık oyuncuyu
            // kilitlememeli; yeniden bağlanmaya izin ver.
            if ($existing !== null && !$existing->isTerminal()) {
                throw SessionException::duplicatePlayer($identity->xuid, $existingId);
            }

            unset($this->byXuid[$identity->xuid], $this->xuidBySession[$existingId]);
        }

        $previousXuid = $this->xuidBySession[$session->id] ?? null;

        if (
            $previousXuid !== null
            && ($this->byXuid[$previousXuid] ?? null) === $session->id
        ) {
            unset($this->byXuid[$previousXuid]);
        }

        $session->markAuthenticated($identity, $now, $skin);
        $this->byXuid[$identity->xuid] = $session->id;
        $this->xuidBySession[$session->id] = $identity->xuid;

        $this->logger->info('Oturum kimlik dogruladi.', [
            'session' => $session->id,
            'player' => $identity->displayName,
        ]);
    }

    /**
     * Oturumu kapatır ve kayıtları temizler. Idempotenttir.
     */
    public function close(string $sessionId, string $reason = ''): void
    {
        $session = $this->sessions[$sessionId] ?? null;

        if ($session === null) {
            return;
        }

        if (!$session->isTerminal()) {
            try {
                $session->transitionTo(SessionState::Closing);
            } catch (SessionException) {
                // Zaten Closing ise sorun değil.
            }

            try {
                $session->transitionTo(SessionState::Closed);
            } catch (SessionException) {
                // Failed gibi terminal durumdan kapanış: kaydı yine temizle.
            }
        }

        $this->forget($session);

        $this->logger->debug('Oturum kapatildi.', [
            'session' => $sessionId,
            'reason' => $reason,
        ]);
    }

    /**
     * Bağlantı koptu — oturumu güvenli kapatır.
     */
    public function onConnectionLost(string $connectionId, string $reason = 'connection_lost'): void
    {
        $session = $this->byConnection($connectionId);

        if ($session === null) {
            return;
        }

        $this->close($session->id, $reason);
    }

    /**
     * Zaman aşımına uğramış oturumları kapatır.
     *
     * @return int Kapatılan oturum sayısı
     */
    public function reapExpired(?int $now = null): int
    {
        if ($this->timeoutSeconds <= 0) {
            return 0;
        }

        $now ??= time();
        $closed = 0;

        foreach ($this->sessions as $session) {
            if ($session->isTerminal()) {
                continue;
            }

            if ($now - $session->lastActivityAt() > $this->timeoutSeconds) {
                $this->close($session->id, 'timeout');
                $closed++;
            }
        }

        return $closed;
    }

    /**
     * Oyun içindeki oturumlar.
     *
     * @return list<Session>
     */
    public function playing(): array
    {
        return array_values(array_filter(
            $this->sessions,
            static fn (Session $session): bool => $session->isPlaying()
        ));
    }

    /**
     * Tüm canlı oturumlar.
     *
     * @return list<Session>
     */
    public function all(): array
    {
        return array_values($this->sessions);
    }

    public function count(): int
    {
        return count($this->sessions);
    }

    public function playerCount(): int
    {
        return count($this->byXuid);
    }

    /**
     * Tüm oturumları kapatır (sunucu kapanışı).
     */
    public function closeAll(string $reason = 'server_shutdown'): int
    {
        $ids = array_keys($this->sessions);

        foreach ($ids as $id) {
            $this->close($id, $reason);
        }

        return count($ids);
    }

    private function forget(Session $session): void
    {
        unset($this->sessions[$session->id]);
        unset($this->byConnection[$session->connectionId]);

        $xuid = $this->xuidBySession[$session->id] ?? null;

        if ($xuid !== null && ($this->byXuid[$xuid] ?? null) === $session->id) {
            unset($this->byXuid[$xuid]);
        }

        unset($this->xuidBySession[$session->id]);
    }
}
