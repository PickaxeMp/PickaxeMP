<?php

declare(strict_types=1);

namespace PickaxeMP\auth;

/**
 * Kimlik doğrulama sonucu.
 *
 * `authenticated === false` ise `identity` **null**'dır. Doğrulanmamış bir
 * kimliğin taşınması mümkün değildir; bu tasarım gereğidir.
 */
final class AuthenticationResult
{
    private function __construct(
        public readonly bool $authenticated,
        public readonly ?PlayerIdentity $identity = null,
        public readonly string $reason = '',
        public readonly ?SkinData $skin = null
    ) {
    }

    public static function success(PlayerIdentity $identity, ?SkinData $skin = null): self
    {
        return new self(true, $identity, '', $skin);
    }

    public static function failure(string $reason): self
    {
        return new self(false, null, $reason);
    }

    public function failed(): bool
    {
        return !$this->authenticated;
    }

    /**
     * Başarılı sonuçtan kimliği alır; başarısızsa istisna.
     */
    public function requireIdentity(): PlayerIdentity
    {
        if ($this->identity === null) {
            throw AuthException::malformedJwt('dogrulanmis kimlik yok: ' . $this->reason);
        }

        return $this->identity;
    }
}
