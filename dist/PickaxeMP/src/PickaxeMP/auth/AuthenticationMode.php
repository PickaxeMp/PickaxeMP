<?php

declare(strict_types=1);

namespace PickaxeMP\auth;

/**
 * Kimlik doğrulama modu.
 *
 * `Online`   — JWT zinciri + imza zorunlu. Üretim varsayılanı.
 * `Offline`  — **yalnızca** geliştirme/test. Zincir doğrulanmaz.
 *
 * `Offline` modu üretim yapılandırmasında etkinleştirilirse
 * `AuthenticationEngine` açılışta istisna fırlatır (`assertSafeForProduction`).
 */
enum AuthenticationMode: string
{
    case Online = 'online';
    case Offline = 'offline';

    /**
     * Üretimde güvenli mi?
     */
    public function isProductionSafe(): bool
    {
        return $this === self::Online;
    }

    public function label(): string
    {
        return $this === self::Online ? 'online' : 'OFFLINE (test)';
    }
}
