<?php

declare(strict_types=1);

namespace PickaxeMP\command;

use PickaxeMP\console\ConsoleCommandSender;

final class PluginsCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'plugins';
    }

    public function getDescription(): string
    {
        return 'Yüklenmiş eklentileri gösterir.';
    }

    public function getUsage(): string
    {
        return 'plugins';
    }

    public function execute(array $args): int
    {
        $this->sender->sendMessage('Eklenti sistemi henüz etkin değil.');
        $this->sender->sendMessage('Yüklü eklenti yok.');

        return 0;
    }
}
