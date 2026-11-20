<?php

declare(strict_types=1);

namespace PickaxeMP\command;

use PickaxeMP\console\ConsoleCommandSender;

final class SayCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'say';
    }

    public function getDescription(): string
    {
        return 'Sunucu mesajı yayınlar (sohbet sistemi destekliyorsa).';
    }

    public function getUsage(): string
    {
        return 'say <mesaj>';
    }

    public function execute(array $args): int
    {
        if ($args === []) {
            $this->sender->sendUsage($this);

            return 1;
        }

        $message = implode(' ', $args);
        $this->sender->sendMessage('[Sunucu] ' . $message);

        return 0;
    }
}
