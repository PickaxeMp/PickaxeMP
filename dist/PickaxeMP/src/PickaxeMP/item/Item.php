<?php

declare(strict_types=1);

namespace PickaxeMP\item;

use PickaxeMP\player\PlayerException;

/**
 * Değişmez eşya tanımı.
 *
 * ## Neden ayrı sınıf?
 *
 * Ağ protokolü eşyayı **sayısal** kimlikle taşır (`NetworkItemStackDescriptor`
 * içindeki `Id`), oysa komutlar, kayıt dosyaları ve eklentiler
 * `minecraft:diamond_sword` gibi metin kimliği kullanır. İkisi arasındaki
 * eşlemeyi tek bir değişmez nesnede tutmak, "yarı yazılmış" tanımların
 * (kimlik var, ağ kimliği yok) dolaşmasını engeller.
 *
 * ## Doğrulama
 *
 * Kurucu tüm alanları doğrular. Geçersiz tanım **oluşturulamaz**; bu sayede
 * `ItemRegistry` içinde daima tutarlı nesneler bulunur.
 */
final class Item
{
    /** Varsayılan yığın sınırı (Minecraft: 64). */
    public const DEFAULT_MAX_STACK_SIZE = 64;

    /** Ağ kimliği için üst sınır (`int16` işaretli alan). */
    public const MAX_NETWORK_ID = 32767;

    public function __construct(
        private readonly string $identifier,
        private readonly int $networkId,
        private readonly int $maxStackSize = self::DEFAULT_MAX_STACK_SIZE
    ) {
        if (preg_match('~^[a-z0-9_.-]+:[a-z0-9_./-]+$~', $identifier) !== 1) {
            throw PlayerException::invalidItemIdentifier($identifier);
        }

        if ($networkId < 0 || $networkId > self::MAX_NETWORK_ID) {
            throw PlayerException::invalidNetworkId($networkId);
        }

        if ($maxStackSize < 1 || $maxStackSize > ItemStack::MAX_COUNT) {
            throw PlayerException::invalidAmount('maxStackSize', $maxStackSize);
        }
    }

    public function identifier(): string
    {
        return $this->identifier;
    }

    public function networkId(): int
    {
        return $this->networkId;
    }

    public function maxStackSize(): int
    {
        return $this->maxStackSize;
    }

    public function equals(self $other): bool
    {
        return $this->identifier === $other->identifier
            && $this->networkId === $other->networkId
            && $this->maxStackSize === $other->maxStackSize;
    }

    public function describe(): string
    {
        return sprintf('%s(%d, max=%d)', $this->identifier, $this->networkId, $this->maxStackSize);
    }
}
