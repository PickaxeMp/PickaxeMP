<?php

declare(strict_types=1);

namespace PickaxeMP\kernel;

/**
 * Bir modülün yaşam döngüsü politikası.
 *
 * Bu nesne, modülün ne yapabileceğini değil, çekirdeğin onu nasıl ele alacağını
 * söyler. Politika modül tarafından beyan edilir; çekirdek tahmin etmez.
 */
final class LifecyclePolicy
{
    public function __construct(
        public readonly bool $reloadable = false,
        public readonly bool $saveOnShutdown = false,
        public readonly bool $critical = false
    ) {
    }

    /**
     * Sıradan, yeniden yüklenemeyen modül.
     */
    public static function standard(): self
    {
        return new self();
    }

    /**
     * Yapılandırması/menüsü canlı yenilenebilen modül.
     */
    public static function reloadable(): self
    {
        return new self(reloadable: true);
    }

    /**
     * Kapatmadan önce bekleyen yazılarını boşaltması gereken modül.
     */
    public static function persistent(bool $reloadable = false): self
    {
        return new self(reloadable: $reloadable, saveOnShutdown: true, critical: true);
    }

    /**
     * Çekirdeğin çalışması için zorunlu modül.
     */
    public static function critical(): self
    {
        return new self(critical: true);
    }
}
