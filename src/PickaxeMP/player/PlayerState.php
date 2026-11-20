<?php

declare(strict_types=1);

namespace PickaxeMP\player;

/**
 * Oyuncunun dünya giriş yaşam döngüsündeki durumu.
 */
enum PlayerState: string
{
    case Joining = 'joining';
    case Spawned = 'spawned';
    case Dead = 'dead';
    case Disconnected = 'disconnected';

    /**
     * Oyuncu hâlâ oturumda mı? (Ölü oyuncu da oturumdadır; yeniden doğabilir.)
     */
    public function isActive(): bool
    {
        return $this === self::Joining || $this === self::Spawned || $this === self::Dead;
    }

    /**
     * Bu durumda blok/envanter etkileşimi yapılabilir mi?
     *
     * Ölü oyuncu hareket edemez ve blok kıramaz; yalnızca yeniden doğma
     * eylemini gönderebilir.
     */
    public function canInteract(): bool
    {
        return $this === self::Spawned;
    }
}
