<?php

declare(strict_types=1);

namespace PickaxeMP\auth;

/**
 * Bir oyuncunun **doğrulanmış** kimliği.
 *
 * Bu nesnenin var olması, zincir ve imza doğrulamasının geçtiği anlamına
 * gelir. Doğrulanmamış claim'lerden bu nesne **oluşturulmaz** —
 * `PlayerIdentityFactory` bunu zorunlu kılar.
 *
 * `verifiedAt` alanı, doğrulamanın yapıldığı Unix zamanıdır; oturum
 * yenilemesi gerekip gerekmediğini anlamak için kullanılır.
 */
final class PlayerIdentity
{
    public function __construct(
        public readonly string $xuid,
        public readonly string $uuid,
        public readonly string $displayName,
        public readonly IdentityType $type,
        public readonly int $verifiedAt,
        /** İstemci açık anahtarı (base64 DER veya JWK), ECDH için */
        public readonly string|array|null $clientPublicKey = null,
        /** Zincir parmak izi (teşhis için) */
        public readonly string $chainFingerprint = ''
    ) {
    }

    public function isOnline(): bool
    {
        return $this->type === IdentityType::XboxLive;
    }

    /**
     * Log için güvenli özet: XUID'yi maskeler.
     */
    public function describe(): string
    {
        return sprintf(
            'player("%s", uuid=%s, type=%s)',
            $this->displayName,
            $this->uuid,
            $this->type->value
        );
    }

    public function equals(self $other): bool
    {
        return $this->xuid === $other->xuid && $this->uuid === $other->uuid;
    }
}
