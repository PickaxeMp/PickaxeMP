<?php

declare(strict_types=1);

namespace PickaxeMP\connection;

use PickaxeMP\auth\crypto\AesCtrCipher;
use PickaxeMP\auth\crypto\EcdhKeyExchange;
use PickaxeMP\protocol\ProtocolHandshake;
use PickaxeMP\session\Session;
use PickaxeMP\transport\ConnectionInterface;

/**
 * Tek bir bağlantının pipeline durumu.
 *
 * Her bağlantının kendi el sıkışma durumu, anahtarı ve oturumu vardır; bunlar
 * bağlantılar arasında **paylaşılmaz**.
 *
 * ## İki ayrı şifreleme bayrağı
 *
 * `cipherAvailable` ile `encryptionEstablished` **aynı şey değildir** ve
 * birbirinin yerine kullanılamaz:
 *
 * - `cipherAvailable`: sunucu anahtarı türetip `ServerToClientHandshake`
 *   gönderdi. Bu andan itibaren gelen **ve** giden tüm yükler şifrelidir.
 *   Karşı taraf anahtarı henüz kanıtlamamış olabilir.
 * - `encryptionEstablished`: istemci şifreli `ClientToServerHandshake`
 *   gönderdi ve sunucu onu **çözebildi**. Bu, istemcinin doğru anahtarı
 *   türettiğinin kanıtıdır.
 *
 * `ClientToServerHandshake` şifreli geldiği için çözülmesi
 * `cipherAvailable`'a bağlıdır; `encryptionEstablished` ise yalnızca bu
 * çözümlemenin **sonucudur**. Bayrağı ön koşul yapmak kilitlenme üretir.
 */
final class PipelineConnection
{
    /**
     * @param bool $compressionNegotiated NetworkSettings gönderildikten sonra
     *        true olur; bu andan önceki paketler sıkıştırma zarfı taşımaz.
     * @param bool $spawned Dünya girişi bir kez denendi mi? (tekrar denememek için)
     * @param bool $cipherAvailable Cipher kurulu mu? Kurulduysa tüm yükler şifreli.
     * @param bool $encryptionEstablished İstemci anahtarı kanıtladı mı?
     */
    public function __construct(
        public readonly ConnectionInterface $connection,
        public readonly Session $session,
        public readonly ProtocolHandshake $handshake,
        public bool $compressionNegotiated = false,
        public bool $spawned = false,
        public ?EcdhKeyExchange $ecdh = null,
        public ?string $handshakeSalt = null,
        public ?AesCtrCipher $cipher = null,
        public bool $cipherAvailable = false,
        public bool $encryptionEstablished = false
    ) {
    }
}
