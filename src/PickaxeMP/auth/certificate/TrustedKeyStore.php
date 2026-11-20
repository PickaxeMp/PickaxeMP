<?php

declare(strict_types=1);

namespace PickaxeMP\auth\certificate;

use PickaxeMP\auth\jwt\TrustedPublicKey;

/**
 * Güvenilen kök anahtarlar (trust anchors).
 *
 * ## Mojang kök anahtarı — kaynak ve doğrulama
 *
 * | Alan | Değer |
 * |---|---|
 * | Anahtar | EC secp384r1 (P-384), 384 bit, SPKI DER 120 bayt |
 * | Base64 | `MHYwEAYHKoZIzj0CAQYFK4EEACIDYgAE8ELk...V1V` |
 * | SHA-256 (DER) | `fa52209a3b4f317f448889c0ddd47fecc7e02e0e406ba20b08d35493bbfb1c81` |
 * | Kaynak | wiki.vg "Bedrock Login Sequence" (Minecraft Wiki'ye taşındı), CloudburstMC `EncryptionUtils.MOJANG_PUBLIC_KEY` |
 * | Doğrulama | `openssl_pkey_get_public` ile yüklendi; curve `secp384r1` doğrulandı (tools/verify-es384.php) |
 * | Not | Bu anahtar Bedrock istemcilerinin login zincirinde **her zaman** görünür |
 *
 * ## Dürüst uyarılar
 *
 * 1. Bu anahtar Mojang'ın resmî bir "JWKS" uç noktasından **alınmamıştır**;
 *    topluluk tarafından belgelenmiş sabit bir değerdir. Mojang bu anahtarı
 *    değiştirirse burada güncellenmesi gerekir.
 * 2. Anahtar değişimi otomatik tespit edilmez. Zincir doğrulaması başarısız
 *    olursa log'a düşer ve **fail-closed** davranılır (oyuncu reddedilir).
 * 3. `x5u` alanı Bedrock'ta URL değil, anahtarın kendisidir.
 */
final class TrustedKeyStore
{
    /**
     * Mojang'ın Bedrock login zinciri kök açık anahtarı (base64 SPKI DER).
     *
     * Doğrulanmış SHA-256: fa52209a3b4f317f448889c0ddd47fecc7e02e0e406ba20b08d35493bbfb1c81
     */
    public const MOJANG_ROOT_KEY_BASE64 = 'MHYwEAYHKoZIzj0CAQYFK4EEACIDYgAE8ELkixyLcwlZryUQcu1TvPOmI2B7vX83ndnWRUaXm74wFfa5f/lwQNTfrLVHa2PmenpGI6JhIMUJaWZrjmMj90NoKNFSNBuKdm8rYiXsfaz3K36x/1U26HpG0ZxK/V1V';

    /** Doğrulanmış beklenen parmak izi. Değişirse testler kırılır. */
    public const MOJANG_ROOT_FINGERPRINT = 'fa52209a3b4f317f448889c0ddd47fecc7e02e0e406ba20b08d35493bbfb1c81';

    /** @var array<string, TrustedPublicKey> fingerprint => anahtar */
    private array $keys = [];

    /** @var array<string, string> fingerprint => etiket */
    private array $labels = [];

    private function __construct()
    {
    }

    /**
     * Yalnızca Mojang kök anahtarını içeren varsayılan depo.
     */
    public static function withMojangRoot(): self
    {
        $store = new self();
        $store->add(
            TrustedPublicKey::fromBase64(self::MOJANG_ROOT_KEY_BASE64, 'mojang-root'),
            'mojang-root'
        );

        return $store;
    }

    /**
     * Boş depo — testlerde sahte kök için.
     */
    public static function empty(): self
    {
        return new self();
    }

    public function add(TrustedPublicKey $key, string $label = 'custom'): void
    {
        $this->keys[$key->fingerprint] = $key;
        $this->labels[$key->fingerprint] = $label;
    }

    public function isTrusted(TrustedPublicKey $key): bool
    {
        return isset($this->keys[$key->fingerprint]);
    }

    public function isTrustedFingerprint(string $fingerprint): bool
    {
        return isset($this->keys[$fingerprint]);
    }

    public function labelFor(TrustedPublicKey $key): ?string
    {
        return $this->labels[$key->fingerprint] ?? null;
    }

    /**
     * @return list<TrustedPublicKey>
     */
    public function all(): array
    {
        return array_values($this->keys);
    }

    public function count(): int
    {
        return count($this->keys);
    }

    /**
     * Mojang kök anahtarının beklenen parmak izine sahip olduğunu doğrular.
     *
     * Sabit kodlanmış parmak izi ile çalışma zamanındaki anahtar uyuşmazsa
     * depo bozulmuş demektir; bu bir yapılandırma hatasıdır.
     */
    public function verifyMojangFingerprint(): bool
    {
        $key = $this->keys[self::MOJANG_ROOT_FINGERPRINT] ?? null;

        return $key !== null && hash_equals(self::MOJANG_ROOT_FINGERPRINT, $key->fingerprint);
    }
}
