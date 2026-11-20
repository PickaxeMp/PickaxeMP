<?php

declare(strict_types=1);

namespace PickaxeMP\command;

use PickaxeMP\console\ConsoleCommandSender;
use PickaxeMP\player\PlayerManager;

/**
 * Cevrimici oyunculari gerçek PlayerManager kayıtlarından gösterir.
 *
 * Oyuncu sistemi henüz etkin degilse (PlayerManager yok veya bos),
 * bu durum acikca soylenir; sahte oyuncu listesi uretilmez.
 */
final class ListCommand extends AbstractCommand
{
    public function __construct(
        ConsoleCommandSender $sender,
        private readonly ?PlayerManager $playerManager = null
    ) {
        parent::__construct($sender);
    }

    public function getName(): string
    {
        return 'list';
    }

    public function getDescription(): string
    {
        return 'Cevrimici oyunculari gosterir.';
    }

    public function getUsage(): string
    {
        return 'list';
    }

    public function execute(array $args): int
    {
        if ($this->playerManager === null) {
            $this->sender->sendMessage('Oyuncu sistemi henüz etkin degil.');
            $this->sender->sendMessage('0 / 0');

            return 0;
        }

        $players = $this->playerManager->all();
        $count = count($players);
        $maxPlayers = 20; // ServerConfig.maxPlayers erisilemiyor; sabit deger.

        $this->sender->sendMessage('Cevrimici oyuncular:');
        $this->sender->sendMessage(sprintf('%d / %d', $count, $maxPlayers));

        if ($count === 0) {
            $this->sender->sendMessage('Cevrimici oyuncu yok.');

            return 0;
        }

        foreach ($players as $player) {
            $worldName = $player->worldName();
            $state = $player->state()->value;
            $this->sender->sendMessage(
                sprintf('  - %s (dunya: %s, durum: %s)', $player->identity->displayName, $worldName, $state)
            );
        }

        return 0;
    }
}
