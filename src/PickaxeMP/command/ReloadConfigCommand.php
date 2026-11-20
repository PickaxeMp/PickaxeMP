<?php

declare(strict_types=1);

namespace PickaxeMP\command;

use PickaxeMP\console\ConsoleCommandSender;

final class ReloadConfigCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'reload';
    }

    public function getDescription(): string
    {
        return 'Yapılandırmayı yeniler.';
    }

    public function getUsage(): string
    {
        return 'reload config';
    }

    public function execute(array $args): int
    {
        if ($args === [] || $args[0] !== 'config') {
            $this->sender->sendUsage($this);

            return 1;
        }

        $this->sender->sendMessage('Yapılandırma yenileme şu aşamada devre dışı.');
        $this->sender->sendMessage('Sunucuyu yeniden başlatın.');

        return 0;
    }
}
