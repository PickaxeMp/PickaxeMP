<?php

declare(strict_types=1);

namespace PickaxeMP\command;

use PickaxeMP\console\ConsoleCommandSender;

final class SaveAllCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'save-all';
    }

    public function getDescription(): string
    {
        return 'Dünya ve oyuncu verilerini kaydeder.';
    }

    public function getUsage(): string
    {
        return 'save-all';
    }

    public function execute(array $args): int
    {
        $this->sender->sendMessage('Kayıt sistemi henüz Phase 0 dışındadır.');
        $this->sender->sendMessage('Kaydedilecek veri yok.');

        return 0;
    }
}
