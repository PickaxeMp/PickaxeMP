<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

/**
 * `TrustedSkinFlag` - deri güven bayrağı.
 *
 * Mojang şeması hem `x-underlying-type: "object"` hem
 * `x-enum-binary-value: [0, 1, 2]` der; yani sayısal bir alan gibi görünür.
 * Gerçek tel biçimi **enum adıdır**: referans uygulama
 * (`gophertunnel` `Skin.Marshal`) `String` olarak "Unset"/"False"/"True"
 * yazar. String seçildi; sayı yazılırsa istemci deriyi reddeder
 * (doğrulanmadı, ama tek çalışan biçim bu).
 */
enum TrustedSkinFlag: string
{
    case Unset = 'Unset';
    case False = 'False';
    case True = 'True';

    public static function fromName(string $value): self
    {
        return self::tryFrom($value) ?? self::Unset;
    }
}
