<?php

declare(strict_types=1);

namespace PickaxeMP\session;

use PickaxeMP\auth\AuthenticationEngine;
use PickaxeMP\protocol\ConnectionState;
use PickaxeMP\protocol\HandshakeException;
use PickaxeMP\protocol\HandshakeResult;
use PickaxeMP\protocol\packet\packets\LoginPacket;
use PickaxeMP\protocol\packet\packets\PlayStatusPacket;
use PickaxeMP\protocol\packet\types\PlayStatus;
use PickaxeMP\protocol\ProtocolHandshake;
use PickaxeMP\utils\Logger;

/**
 * El sıkışma + kimlik doğrulama + oturum köprüsü.
 *
 * ## Neden ayrı sınıf?
 *
 * `ProtocolHandshake` **protokol** sırasını bilir ama kimlik doğrulamayı
 * bilmez. `AuthenticationEngine` kimliği doğrular ama oturum tutmaz.
 * `SessionManager` oturum tutar ama protokol bilmez. Bu sınıf üçünü
 * birleştirir ve **tek** bir doğru yol sunar.
 *
 * ## Değişmez kural
 *
 * `LoginPacket` geldiğinde:
 *   1. doğrulama **zorunlu**,
 *   2. başarısızsa oturum `Failed`, el sıkışma `Closed`, istemciye
 *      `PlayStatus` hatası,
 *   3. başarılıysa `Session::markAuthenticated`,
 *   4. ancak ondan sonra `ProtocolHandshake::completeAuthentication()` çağrılır.
 *
 * Başka hiçbir yol `Playing`'e çıkarmaz.
 */
final class SessionAuthenticator
{
    public function __construct(
        private readonly AuthenticationEngine $engine,
        private readonly SessionManager $sessions,
        private readonly Logger $logger
    ) {
    }

    /**
     * Login paketini işler ve sonucu döndürür.
     *
     * @param ProtocolHandshake $handshake Bağlantının el sıkışma durumu
     *        (bu metot onu ilerletir)
     */
    public function handleLogin(
        ProtocolHandshake $handshake,
        Session $session,
        LoginPacket $login,
        ?int $now = null
    ): HandshakeResult {
        $now ??= time();

        // 1) Önce protokol el sıkışma Login'i kabul etmeli. Bu adım
        //    sürüm ve JWT-yapı kontrolünü yapar ve durumu
        //    AwaitingAuthentication'a taşır. Sıra ihlali (örn. Login
        //    AwaitingLogin olmadan gelirse) istisna fırlatır; bu da
        //    fail-closed olarak ele alınır.
        try {
            $protocolResult = $handshake->handle($login);
        } catch (HandshakeException $exception) {
            $this->logger->warning('Login paketi el sikisma sirasina uymadi.', [
                'session' => $session->id,
                'reason' => $exception->getMessage(),
            ]);

            return $this->failClosed(
                $handshake,
                $session,
                $exception->getMessage(),
                PlayStatus::LoginFailedInvalidTenant,
                $now
            );
        }

        if ($protocolResult->isClosed()) {
            // Protokol el sıkışması zaten reddetti (uyumsuz sürüm, bozuk JWT
            // biçimi). Oturum da terminal duruma düşmeli.
            return $this->failClosed(
                $handshake,
                $session,
                $protocolResult->reason,
                $this->statusForFailure($protocolResult->reason),
                $now,
                $protocolResult->responses
            );
        }

        // 2) Protokol durumu artık AwaitingAuthentication; oturum durumunu
        //    hizala.
        try {
            $session->transitionTo(SessionState::Authenticating, $now);
        } catch (SessionException $exception) {
            $this->logger->warning('Oturum kimlik dogrulamaya gecmedi.', [
                'session' => $session->id,
                'reason' => $exception->getMessage(),
            ]);

            return $this->failClosed(
                $handshake,
                $session,
                $exception->getMessage(),
                PlayStatus::LoginFailedInvalidTenant,
                $now
            );
        }

        $result = $this->engine->authenticate($login, $now);

        if ($result->failed()) {
            return $this->failClosed(
                $handshake,
                $session,
                $result->reason,
                $this->statusForFailure($result->reason),
                $now
            );
        }

        try {
            $identity = $result->requireIdentity();
        } catch (\Throwable $exception) {
            return $this->failClosed(
                $handshake,
                $session,
                $exception->getMessage(),
                PlayStatus::LoginFailedInvalidTenant,
                $now
            );
        }

        try {
            $this->sessions->bindIdentity($session, $identity, $now, $result->skin);
        } catch (SessionException $exception) {
            // Aynı oyuncu zaten bağlı.
            $this->logger->warning('Kimlik oturuma baglanamadi.', [
                'session' => $session->id,
                'reason' => $exception->getMessage(),
            ]);

            return $this->failClosed(
                $handshake,
                $session,
                $exception->getMessage(),
                PlayStatus::LoginFailedServerFullSubClient,
                $now
            );
        }

        // 3) Artık güvenli: protokol el sıkışması ilerleyebilir.
        try {
            $packets = $handshake->completeAuthentication();
        } catch (HandshakeException $exception) {
            return $this->failClosed(
                $handshake,
                $session,
                $exception->getMessage(),
                PlayStatus::LoginFailedInvalidTenant,
                $now
            );
        }

        return HandshakeResult::continue(
            ConnectionState::AwaitingResourcePacks,
            $packets
        );
    }

    /**
     * Tek fail-closed hunisi.
     *
     * Her başarısızlıkta üç şey birlikte garanti edilir:
     *   1. Oturum `Failed` durumuna düşer,
     *   2. Protokol el sıkışması `Closed` durumuna düşer,
     *   3. İstemciye en az bir `PlayStatus` hatası gönderilir.
     *
     * @param list<\PickaxeMP\protocol\packet\PacketInterface> $responses
     *        Protokolün ürettiği (varsa) yanıtlar
     */
    private function failClosed(
        ProtocolHandshake $handshake,
        Session $session,
        string $reason,
        PlayStatus $status,
        int $now,
        array $responses = []
    ): HandshakeResult {
        $this->markSessionFailed($session, $reason, $now);

        // `failAuthentication()` hem el sıkışmayı kapatır hem de istemciye
        // gönderilecek PlayStatus paketini üretir.
        $statusPacket = $handshake->failAuthentication($status);

        if (!$this->containsPlayStatus($responses)) {
            $responses[] = $statusPacket;
        }

        return HandshakeResult::fail($reason, $responses);
    }

    /**
     * Oturumu terminal `Failed` durumuna düşürür.
     *
     * Oturum zaten terminal ise (örn. eşzamanlı kapanış) istisna fırlatmaz:
     * bağlantı yine de fail-closed kapatılır.
     */
    private function markSessionFailed(Session $session, string $reason, int $now): void
    {
        try {
            $session->markAuthenticationFailed($reason, $now);
        } catch (SessionException $exception) {
            $this->logger->warning('Oturum zaten terminal durumda.', [
                'session' => $session->id,
                'state' => $session->getState()->value,
                'reason' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param list<\PickaxeMP\protocol\packet\PacketInterface> $responses
     */
    private function containsPlayStatus(array $responses): bool
    {
        foreach ($responses as $response) {
            if ($response instanceof PlayStatusPacket) {
                return true;
            }
        }

        return false;
    }

    /**
     * Hata nedenini istemciye uygun `PlayStatus`'a çevirir.
     *
     * Not: Ayrıntı istemciye sızdırılmaz; log'da tam neden vardır.
     */
    private function statusForFailure(string $reason): PlayStatus
    {
        $lower = strtolower($reason);

        if (
            str_contains($lower, 'suresi dolmus')
            || str_contains($lower, 'expired')
            || str_contains($lower, 'exp=')
        ) {
            return PlayStatus::LoginFailedInvalidTenant;
        }

        if (str_contains($lower, 'surum') || str_contains($lower, 'protocol')) {
            return PlayStatus::LoginFailedServerOld;
        }

        if (str_contains($lower, 'zaten bagli') || str_contains($lower, 'duplicate')) {
            return PlayStatus::LoginFailedServerFullSubClient;
        }

        return PlayStatus::LoginFailedInvalidTenant;
    }
}
