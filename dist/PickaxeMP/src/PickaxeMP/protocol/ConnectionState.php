<?php

declare(strict_types=1);

namespace PickaxeMP\protocol;

/**
 * Bedrock bağlantısının protokol durumu.
 *
 * Geçişler açıktır; tanımsız geçiş `HandshakeException` üretir.
 *
 *   AwaitingRequestNetworkSettings
 *        │ RequestNetworkSettings (sürüm uyumlu)
 *        ▼
 *   AwaitingLogin
 *        │ Login
 *        ▼
 *   AwaitingAuthentication   ← Phase 3 burada devralır
 *        │ (doğrulama başarılı)
 *        ▼
 *   AwaitingResourcePacks
 *        │ ResourcePackClientResponse (stack finished)
 *        ▼
 *   Ready
 */
enum ConnectionState: string
{
    case AwaitingRequestNetworkSettings = 'awaiting_request_network_settings';
    case AwaitingLogin = 'awaiting_login';
    case AwaitingAuthentication = 'awaiting_authentication';
    case AwaitingResourcePacks = 'awaiting_resource_packs';
    case Ready = 'ready';
    case Closed = 'closed';

    public function isTerminal(): bool
    {
        return $this === self::Closed;
    }
}
