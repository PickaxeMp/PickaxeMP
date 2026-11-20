<?php

declare(strict_types=1);

namespace PickaxeMP\player;

/**
 * Bedrock oyun modu.
 *
 * Sayısal değerler `StartGame.Player Game Mode` ve `SetPlayerGameType`
 * şemasıyla aynıdır: Survival 0, Creative 1, Adventure 2, Spectator 6.
 *
 * ## Neden `enum`?
 *
 * Yetenek katmanı oyun moduna **bağlıdır**: yaratıcıda uçuş izni açılır,
 * macerada inşa/kazma kapanır. Serbest `int` taşımak, "3" gibi geçersiz bir
 * değerin yetenek hesabına sızmasına yol açardı.
 */
enum GameMode: int
{
    case Survival = 0;

    case Creative = 1;

    case Adventure = 2;

    case Spectator = 6;

    /**
     * Mod uçuşa izin veriyor mu? (`mayFly` bayrağı)
     */
    public function allowsFlight(): bool
    {
        return $this === self::Creative || $this === self::Spectator;
    }

    /**
     * Mod blok kırmaya/yerleştirmeye izin veriyor mu?
     */
    public function allowsBuilding(): bool
    {
        return $this === self::Survival || $this === self::Creative;
    }

    public function isCreative(): bool
    {
        return $this === self::Creative;
    }

    public function isSpectator(): bool
    {
        return $this === self::Spectator;
    }

    /**
     * İstemciye gösterilen ad (log/test).
     */
    public function label(): string
    {
        return match ($this) {
            self::Survival => 'survival',
            self::Creative => 'creative',
            self::Adventure => 'adventure',
            self::Spectator => 'spectator',
        };
    }
}
