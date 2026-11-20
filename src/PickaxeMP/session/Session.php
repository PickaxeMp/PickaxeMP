<?php

declare(strict_types=1);

namespace PickaxeMP\session;

use PickaxeMP\auth\PlayerIdentity;
use PickaxeMP\auth\SkinData;

/**
 * Tek bir oyuncu oturumu.
 *
 * ## Değişmez kurallar
 *
 * 1. `identity` yalnızca `markAuthenticated()` ile atanır; başka hiçbir yol
 *    kimlik atayamaz.
 * 2. Durum geçişleri `SessionState::allowedNext()` ile sınırlıdır.
 * 3. `Playing` durumuna geçmek için `identity !== null` **zorunludur**.
 * 4. Terminal duruma geçen oturum yeniden açılamaz.
 */
final class Session
{
    private SessionState $state = SessionState::Connecting;

    private ?PlayerIdentity $identity = null;

    private ?SkinData $skin = null;

    private int $lastActivityAt;

    private int $createdAt;

    private string $closeReason = '';

    public function __construct(
        public readonly string $id,
        public readonly string $connectionId,
        public readonly string $remoteAddress,
        ?int $now = null
    ) {
        $this->lastActivityAt = $now ?? time();
        $this->createdAt = $this->lastActivityAt;
    }

    public function getState(): SessionState
    {
        return $this->state;
    }

    public function getIdentity(): ?PlayerIdentity
    {
        return $this->identity;
    }

    public function hasIdentity(): bool
    {
        return $this->identity !== null;
    }

    /**
     * Doğrulanmış `extraData` derisi; yoksa `null`.
     *
     * Deri **kimlik kanıtı değildir**; yalnızca görünüm verisidir. Kimlik
     * zincir token'larından gelir (`markAuthenticated`).
     */
    public function getSkin(): ?SkinData
    {
        return $this->skin;
    }

    public function createdAt(): int
    {
        return $this->createdAt;
    }

    public function lastActivityAt(): int
    {
        return $this->lastActivityAt;
    }

    public function closeReason(): string
    {
        return $this->closeReason;
    }

    public function touch(?int $now = null): void
    {
        $this->lastActivityAt = $now ?? time();
    }

    /**
     * Durum geçişi.
     *
     * @throws SessionException geçiş tanımsızsa
     */
    public function transitionTo(SessionState $next, ?int $now = null): void
    {
        if (!$this->state->canTransitionTo($next)) {
            throw SessionException::illegalTransition(
                $this->state->value,
                $next->value,
                $this->id
            );
        }

        // Authenticated ve Playing durumları kimlik olmadan imkansizdir.
        // `markAuthenticated()` kimligi once atar; genel gecis yolu atlayamaz.
        if (
            ($next === SessionState::Authenticated || $next === SessionState::Playing)
            && $this->identity === null
        ) {
            throw SessionException::illegalTransition(
                $this->state->value,
                $next->value . ' (kimlik yok)',
                $this->id
            );
        }

        $this->state = $next;
        $this->touch($now);
    }

    /**
     * Kimlik doğrulandı — tek atama yolu.
     */
    public function markAuthenticated(PlayerIdentity $identity, ?int $now = null, ?SkinData $skin = null): void
    {
        if ($this->state !== SessionState::Authenticating) {
            throw SessionException::illegalTransition(
                $this->state->value,
                'authenticated (markAuthenticated)',
                $this->id
            );
        }

        $this->identity = $identity;
        $this->skin = $skin;
        $this->transitionTo(SessionState::Authenticated, $now);
    }

    /**
     * Başarısız kimlik doğrulama.
     *
     * ## Değişmez kurallar
     *
     * 1. Durum geçişi `SessionState` tablosuna uyar; durum makinesi
     *    atlanmaz. Terminal `Closed` durumdan `Failed`'a geçilemez —
     *    `SessionException` fırlatılır.
     * 2. `Failed` durumuna tekrar girmek **idempotenttir**: son neden
     *    güncellenir, durum değişmez, istisna fırlatılmaz.
     * 3. `markAuthenticated()` ile atanmış kimlik **korunur**. Kimlik
     *    yalnızca `markAuthenticated()` ile atanır; burada temizlenmesi
     *    `SessionManager` XUID indeksiyle tutarsızlık yaratırdı.
     */
    public function markAuthenticationFailed(string $reason, ?int $now = null): void
    {
        if ($this->state === SessionState::Failed) {
            // İdempotent: son nedeni güncelle, terminal durumu koru.
            $this->closeReason = $reason;
            $this->touch($now);

            return;
        }

        if ($this->state === SessionState::Closed) {
            throw SessionException::illegalTransition(
                $this->state->value,
                SessionState::Failed->value,
                $this->id
            );
        }

        $this->closeReason = $reason;
        $this->transitionTo(SessionState::Failed, $now);
    }

    public function isPlaying(): bool
    {
        return $this->state->isPlaying();
    }

    public function isAuthenticated(): bool
    {
        return $this->state->isAuthenticated() && $this->identity !== null;
    }
    public function isTerminal(): bool
    {
        return $this->state->isTerminal();
    }

    /**
     * Geliştirici logu için güvenli özet.
     */
    public function describe(): string
    {
        return sprintf(
            'session(%s, conn=%s, state=%s, player=%s)',
            $this->id,
            $this->connectionId,
            $this->state->value,
            $this->identity?->displayName ?? '-'
        );
    }
}
