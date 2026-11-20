<?php

declare(strict_types=1);

namespace PickaxeMP\command;

use PickaxeMP\console\ConsoleCommandSender;

final class ListCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'list';
    }

    public function getDescription(): string
    {
        return 'Çevrimiçi oyuncuları gösterir.';
    }

    public function getUsage(): string
    {
        return 'list';
    }

    public function execute(array $args): int
    {
        $this->sender->sendMessage('Çevrimiçi oyuncu yok.');
        $this->sender->sendMessage('0 / 0');

        return 0;
    }
}
