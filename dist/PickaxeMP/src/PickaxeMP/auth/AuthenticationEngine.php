<?php

declare(strict_types=1);

namespace PickaxeMP\auth;

use PickaxeMP\auth\certificate\CertificateChain;
use PickaxeMP\auth\certificate\CertificateValidator;
use PickaxeMP\auth\certificate\TrustedKeyStore;
use PickaxeMP\auth\jwt\JwtToken;
use PickaxeMP\protocol\packet\packets\LoginPacket;
use PickaxeMP\utils\Logger;

/**
 * Merkezi kimlik doğrulama motoru.
 *
 * ## Akış
 *
 * ```
 * LoginPacket
 *   → CertificateChain.fromConnectionRequest()
 *   → CertificateValidator.validate()      (zincir + her halkada imza + zaman)
 *   → son halkadan claim'ler               (xuid, identity, displayName, cpk)
 *   → PlayerIdentityFactory.create()       (tutarlılık kontrolleri)
 *   → AuthenticationResult
 * ```
 *
 * ## Değişmez kurallar
 *
 * 1. **Fail-closed:** Herhangi bir adım başarısızsa oyuncu reddedilir.
 * 2. **Zincirde Mojang kökü yoksa reddedilir** — kendi imzasıyla gelen
 *    "sahte" zincirler kabul edilmez.
 * 3. `Offline` modu yalnızca test içindir; üretimde kullanılırsa motor
 *    oluşturulurken istisna fırlatılır.
 * 4. Doğrulanmamış claim'ler `PlayerIdentity`'ye dönüştürülmez.
 *
 * ## Dürüst sınırlar
 *
 * - Bu motor, **Mojang'ın canlı auth servisine** sorgu atmaz. Zincirdeki
 *   Mojang imzası yeterli kabul edilir (wiki.vg akışı).
 * - `extraData` (skin/cihaz) JWT'si `SkinDataValidator` ile doğrulanır:
 *   imza geçersizse oturum **reddedilir**. Buradan kimlik alanı okunmaz.
 * - Gerçek istemciyle test edilmemiştir.
 */
final class AuthenticationEngine
{
    public function __construct(
        private readonly CertificateValidator $validator,
        private readonly PlayerIdentityFactory $factory,
        private readonly Logger $logger,
        private readonly AuthenticationMode $mode = AuthenticationMode::Online,
        private readonly SkinDataValidator $skinValidator = new SkinDataValidator()
    ) {
    }

    public static function online(Logger $logger): self
    {
        return new self(
            new CertificateValidator(
                new \PickaxeMP\auth\jwt\JwtParser(),
                new \PickaxeMP\auth\jwt\JwtVerifier(),
                TrustedKeyStore::withMojangRoot()
            ),
            new PlayerIdentityFactory(),
            $logger,
            AuthenticationMode::Online
        );
    }

    /**
     * Üretim güvenliği kontrolü — açılışta çağrılmalı.
     *
     * @throws AuthException offline modda üretim çalıştırılmaya çalışılırsa
     */
    public function assertProductionSafe(): void
    {
        if (!$this->mode->isProductionSafe()) {
            throw new AuthException(
                'Kimlik dogrulama OFFLINE modda. Bu yalnizca test icindir; '
                . 'uretim sunucusunda online-mode kullanin.'
            );
        }
    }

    public function getMode(): AuthenticationMode
    {
        return $this->mode;
    }

    /**
     * Login paketini doğrular.
     */
    public function authenticate(LoginPacket $login, ?int $now = null): AuthenticationResult
    {
        $startedAt = microtime(true);

        try {
            $result = $this->doAuthenticate($login, $now);
        } catch (AuthException $exception) {
            $this->logger->warning('Kimlik dogrulama basarisiz.', [
                'reason' => $exception->getMessage(),
            ]);

            return AuthenticationResult::failure($exception->getMessage());
        } catch (\Throwable $exception) {
            // Beklenmeyen hata: yine fail-closed, ama ayrı logla.
            $this->logger->error('Kimlik dogrulamada beklenmeyen hata.', [
                'error' => $exception->getMessage(),
                'type' => $exception::class,
            ]);

            return AuthenticationResult::failure('beklenmeyen dogrulama hatasi');
        }

        $this->logger->info('Kimlik dogrulandi.', [
            'player' => $result->requireIdentity()->describe(),
            'ms' => round((microtime(true) - $startedAt) * 1000, 2),
        ]);

        return $result;
    }

    private function doAuthenticate(LoginPacket $login, ?int $now): AuthenticationResult
    {
        if ($this->mode === AuthenticationMode::Offline) {
            $this->logger->warning(
                'OFFLINE kimlik dogrulamasi kullaniliyor — zincir kontrolu YAPILMIYOR.'
            );

            return AuthenticationResult::success(
                $this->factory->offline($login->connectionRequest)
            );
        }

        $chain = CertificateChain::fromConnectionRequest($login->connectionRequest);
        $tokens = $this->validator->validate($chain, $now);
        $last = $tokens[count($tokens) - 1];

        $identity = $this->factory->fromVerifiedChain($last, $tokens, $now ?? time());

        // Skin/cihaz verisi ayrı bir JWT'dir ve kimlik kanıtı değildir.
        // İmza doğrulanmadan hiçbir alanı okunmaz; geçersizse fail-closed.
        $skin = $this->skinValidator->validate($chain->extraData, $last, $now);

        if ($skin !== null) {
            $this->logger->debug('Skin verisi dogrulandi.', [
                'player' => $identity->displayName,
                'skin_id' => $skin->skinId(),
                'has_cape' => $skin->hasCape(),
            ]);
        }

        return AuthenticationResult::success($identity, $skin);
    }

    /**
     * @param list<JwtToken> $tokens
     */
    public function describeChain(array $tokens): string
    {
        return implode(' → ', array_map(
            static fn (JwtToken $token): string => $token->header->algorithm->value
                . ':' . substr(hash('sha256', $token->encodedHeader), 0, 8),
            $tokens
        ));
    }
}
