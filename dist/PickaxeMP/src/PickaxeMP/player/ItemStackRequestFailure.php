<?php

declare(strict_types=1);

namespace PickaxeMP\player;

use PickaxeMP\protocol\packet\types\ItemStackNetResult;

/**
 * @internal `ItemStackRequestManager` içinde kullanılan doğrulama hatası.
 *
 * Doğrulama sırasında **tek** bir `ItemStackNetResult` kodu taşır; işlem
 * kopya üzerinde yürüdüğü için bu istisna yalnızca "hiçbir şey uygulanmadı"
 * anlamına gelir.
 */
final class ItemStackRequestFailure extends \RuntimeException
{
    public function __construct(
        public readonly int $netResult,
        string $message
    ) {
        parent::__construct($message);
    }

    public static function of(int $netResult, string $message): self
    {
        return new self($netResult, $message);
    }
}
