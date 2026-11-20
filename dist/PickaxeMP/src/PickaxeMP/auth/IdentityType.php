<?php

declare(strict_types=1);

namespace PickaxeMP\auth;

/**
 * Kimlik doğrulama türü.
 *
 * `Offline` yalnızca geliştirme/test içindir ve üretim yapılandırmasında
 * **reddedilir**.
 */
enum IdentityType: string
{
    /** Xbox Live / Minecraft auth servisi üzerinden doğrulandı. */
    case XboxLive = 'xbox';

    /** Yerel test kimliği — üretimde yasak. */
    case Offline = 'offline';
}
