<?php

declare(strict_types=1);

namespace PickaxeMP\auth\jwt;

/**
 * İmza doğrulama sonucu.
 *
 * Başarısızlıkta **neden** taşınır; "sessizce false" yoktur. Çağıran katman
 * bu nedeni loglayabilir ama oyuncuya ayrıntı sızdırmamalıdır.
 */
final class VerificationResult
{
    private function __construct(
        public readonly bool $verified,
        public readonly string $reason = ''
    ) {
    }

    public static function success(): self
    {
        return new self(true);
    }

    public static function failure(string $reason): self
    {
        return new self(false, $reason);
    }

    public function failed(): bool
    {
        return !$this->verified;
    }
}
