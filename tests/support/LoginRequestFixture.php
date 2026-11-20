<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\support;

/**
 * Test verisi: geçerli Bedrock `connectionRequest` JSON'u.
 *
 * Not: bu **gerçek** bir imza değil; yalnızca yapısal testler içindir.
 * Kriptografik testler `CryptoFixture` kullanır.
 */
final class LoginRequestFixture
{
    /**
     * @param list<string>|null $chain
     */
    public static function request(?array $chain = null): string
    {
        return json_encode([
            'chain' => $chain ?? ['a.b.c', 'd.e.f'],
        ], JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
