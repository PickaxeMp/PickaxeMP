<?php

declare(strict_types=1);

namespace PickaxeMP\protocol;

use PickaxeMP\protocol\compression\CompressionSettings;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\packets\LoginPacket;
use PickaxeMP\protocol\packet\packets\NetworkSettingsPacket;
use PickaxeMP\protocol\packet\packets\PlayStatusPacket;
use PickaxeMP\protocol\packet\packets\RequestNetworkSettingsPacket;
use PickaxeMP\protocol\packet\packets\ResourcePackClientResponsePacket;
use PickaxeMP\protocol\packet\packets\ResourcePacksInfoPacket;
use PickaxeMP\protocol\packet\packets\ResourcePackStackPacket;
use PickaxeMP\protocol\packet\types\PlayStatus;
use PickaxeMP\protocol\packet\types\ResourcePackResponse;

/**
 * Bedrock bağlantı el sıkışması — açık durum makinesi.
 *
 * Sıra (doğrulanmış paket kimlikleriyle):
 *   1. C→S RequestNetworkSettings (193) → sürüm kontrolü
 *   2. S→C NetworkSettings (143)         → sıkıştırma ayarları
 *   3. C→S Login (1)                     → kimlik doğrulamaya devredilir
 *   4. S→C PlayStatus(LoginSuccess) + ResourcePacksInfo + ResourcePackStack
 *   5. C→S ResourcePackClientResponse(4) → Ready
 *
 * DÜRÜST SINIR — EN ÖNEMLİSİ:
 * Bu sınıfın hiçbir yolu, kimlik doğrulanmadan `Ready` durumuna geçmez.
 * Login paketi gelince akış `AwaitingAuthentication`'da **durur** ve
 * `HandshakeResult::needsAuthentication()` döner. `Ready`'ye geçiş yalnızca
 * `completeAuthentication()` ile mümkündür; onu da Phase 3'teki
 * AuthenticationEngine çağıracaktır. Sahte başarı yolu yoktur.
 */
final class ProtocolHandshake
{
    private ConnectionState $state = ConnectionState::AwaitingRequestNetworkSettings;

    public function __construct(
        private readonly CompressionSettings $serverCompression = new CompressionSettings()
    ) {
    }

    public function getState(): ConnectionState
    {
        return $this->state;
    }

    public function isReady(): bool
    {
        return $this->state === ConnectionState::Ready;
    }

    /**
     * Gelen paketi işler.
     */
    public function handle(PacketInterface $packet): HandshakeResult
    {
        if ($this->state->isTerminal()) {
            throw HandshakeException::unexpectedPacket($this->state->value, $packet->getId());
        }

        return match ($this->state) {
            ConnectionState::AwaitingRequestNetworkSettings => $this->onRequestNetworkSettings($packet),
            ConnectionState::AwaitingLogin => $this->onLogin($packet),
            ConnectionState::AwaitingAuthentication => $this->onWhileAuthenticating($packet),
            ConnectionState::AwaitingResourcePacks => $this->onResourcePackResponse($packet),
            ConnectionState::Ready => HandshakeResult::continue(ConnectionState::Ready),
            ConnectionState::Closed => throw HandshakeException::unexpectedPacket(
                $this->state->value,
                $packet->getId()
            ),
        };
    }

    /**
     * Phase 3 Authentication Engine, kimliği doğruladıktan SONRA çağırır.
     *
     * Bu metot yalnızca `AwaitingAuthentication` durumunda çalışır; başka bir
     * durumda çağrılırsa el sıkışma sırası ihlal edilmiş demektir.
     *
     * @return list<PacketInterface> İstemciye gönderilecek paketler
     */
    public function completeAuthentication(): array
    {
        if ($this->state !== ConnectionState::AwaitingAuthentication) {
            throw HandshakeException::illegalTransition(
                $this->state->value,
                ConnectionState::AwaitingResourcePacks->value
            );
        }

        $this->state = ConnectionState::AwaitingResourcePacks;

        return [
            PlayStatusPacket::of(PlayStatus::LoginSuccess),
            new ResourcePacksInfoPacket(),
            new ResourcePackStackPacket(
                baseGameVersion: ProtocolVersion::GAME_VERSION
            ),
        ];
    }

    /**
     * Kimlik doğrulaması başarısız olduğunda çağrılır.
     */
    public function failAuthentication(PlayStatus $status): PlayStatusPacket
    {
        $this->state = ConnectionState::Closed;

        return PlayStatusPacket::of($status);
    }

    private function onRequestNetworkSettings(PacketInterface $packet): HandshakeResult
    {
        if (!$packet instanceof RequestNetworkSettingsPacket) {
            throw HandshakeException::unexpectedPacket($this->state->value, $packet->getId());
        }

        if (!$packet->isCompatible()) {
            $this->state = ConnectionState::Closed;

            return HandshakeResult::fail(
                sprintf(
                    'Uyumsuz istemci protokolu: %d (sunucu: %d)',
                    $packet->clientNetworkVersion,
                    ProtocolVersion::PROTOCOL
                ),
                [PlayStatusPacket::of(PlayStatus::LoginFailedServerOld)]
            );
        }

        $this->state = ConnectionState::AwaitingLogin;

        return HandshakeResult::continue(
            ConnectionState::AwaitingLogin,
            [NetworkSettingsPacket::fromSettings($this->serverCompression)]
        );
    }

    private function onLogin(PacketInterface $packet): HandshakeResult
    {
        if (!$packet instanceof LoginPacket) {
            throw HandshakeException::unexpectedPacket($this->state->value, $packet->getId());
        }

        if (!ProtocolVersion::isCompatible($packet->clientNetworkVersion)) {
            $this->state = ConnectionState::Closed;

            return HandshakeResult::fail(
                sprintf('Login surumu uyumsuz: %d', $packet->clientNetworkVersion),
                [PlayStatusPacket::of(PlayStatus::LoginFailedServerOld)]
            );
        }

        // Yapısal kontrol bile başarısızsa kimlik doğrulamaya hiç gitmeyiz.
        if (!$packet->hasPlausibleJwtShape()) {
            $this->state = ConnectionState::Closed;

            return HandshakeResult::fail(
                'Login paketi gecerli bir JWT zinciri tasimiyor.',
                [PlayStatusPacket::of(PlayStatus::LoginFailedInvalidTenant)]
            );
        }

        $this->state = ConnectionState::AwaitingAuthentication;

        return HandshakeResult::needsAuthentication($packet);
    }

    private function onWhileAuthenticating(PacketInterface $packet): HandshakeResult
    {
        // Kimlik doğrulanmadan istemciden gelen hiçbir paket kabul edilmez.
        throw HandshakeException::unexpectedPacket($this->state->value, $packet->getId());
    }

    private function onResourcePackResponse(PacketInterface $packet): HandshakeResult
    {
        if (!$packet instanceof ResourcePackClientResponsePacket) {
            throw HandshakeException::unexpectedPacket($this->state->value, $packet->getId());
        }

        $response = $packet->responseEnum();

        if ($response === null) {
            return HandshakeResult::fail(
                "Bilinmeyen kaynak paketi yaniti: {$packet->response}",
                [PlayStatusPacket::of(PlayStatus::LoginFailedClientOld)]
            );
        }

        return match ($response) {
            ResourcePackResponse::Cancel => HandshakeResult::fail(
                'Istemci kaynak paketi indirmeyi iptal etti.'
            ),
            // İndirme akışı Phase 9 (Advanced UI) kapsamındadır.
            ResourcePackResponse::Downloading,
            ResourcePackResponse::DownloadingFinished => HandshakeResult::continue(
                ConnectionState::AwaitingResourcePacks
            ),
            ResourcePackResponse::ResourcePackStackFinished => $this->onStackFinished(),
        };
    }

    private function onStackFinished(): HandshakeResult
    {
        $this->state = ConnectionState::Ready;

        return HandshakeResult::continue(ConnectionState::Ready);
    }

    /**
     * Teşhis için insan okunabilir özet.
     */
    public function describe(): string
    {
        return sprintf(
            'state=%s protocol=%d',
            $this->state->value,
            ProtocolVersion::PROTOCOL
        );
    }

    /**
     * Paket kimliğinden ad (log için).
     */
    public static function packetName(int $id): string
    {
        return PacketId::name($id);
    }
}
