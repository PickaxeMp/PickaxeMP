<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\binary;

use PickaxeMP\protocol\ProtocolException;

/**
 * mce::UUID — 128 bit, MSB + LSB uint64 çifti olarak taşınır.
 *
 * Tel formatı (Mojang şeması `mce::UUID`): 16 bayt, önce Most Significant
 * Bits, sonra Least Significant Bits; her biri uint64 ve "native byte order"
 * (little-endian).
 *
 * DÜRÜST NOT: Bayt sırası şemadan türetilmiştir (uint64 = native/LE), gerçek
 * bir Bedrock istemcisiyle doğrulanmamıştır. Round-trip testleri geçerlidir;
 * istemci uyumu Phase 4'te dünya paketleriyle sınanmalıdır.
 */
final class Uuid
{
    public function __construct(
        public readonly int $mostSignificant,
        public readonly int $leastSignificant
    ) {
    }

    public static function fromString(string $uuid): self
    {
        $hex = str_replace(['-', '{', '}'], '', trim($uuid));

        if (preg_match('/^[0-9a-fA-F]{32}$/', $hex) !== 1) {
            throw new ProtocolException("Gecersiz UUID metni: {$uuid}");
        }

        $bytes = (string) hex2bin($hex);

        /** @var array{1: int} $msb */
        $msb = unpack('P', strrev(substr($bytes, 0, 8)));
        /** @var array{1: int} $lsb */
        $lsb = unpack('P', strrev(substr($bytes, 8, 8)));

        return new self($msb[1], $lsb[1]);
    }

    public static function empty(): self
    {
        return new self(0, 0);
    }

    public function isZero(): bool
    {
        return $this->mostSignificant === 0 && $this->leastSignificant === 0;
    }

    /**
     * Kanonik 8-4-4-4-12 biçimi.
     */
    public function toString(): string
    {
        $hex = bin2hex(strrev(pack('P', $this->mostSignificant)) . strrev(pack('P', $this->leastSignificant)));

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    public function equals(self $other): bool
    {
        return $this->mostSignificant === $other->mostSignificant
            && $this->leastSignificant === $other->leastSignificant;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
