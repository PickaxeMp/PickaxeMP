<?php

declare(strict_types=1);

namespace PickaxeMP\command;

use PickaxeMP\console\ConsoleCommandSender;

/**
 * Komutların ortak temel sınıfı.
 *
 * Konsol çıkışına doğrudan yazar.
 */
abstract class AbstractCommand implements CommandInterface
{
    public function __construct(
        private readonly ConsoleCommandSender $sender
    ) {
    }

    protected function sender(): ConsoleCommandSender
    {
        return $this->sender;
    }

    protected function success(string $message): int
    {
        $this->sender->sendMessage($message);

        return 0;
    }

    protected function error(string $message): int
    {
        $this->sender->sendError($message);

        return 1;
    }

    protected function info(string $message): int
    {
        $this->sender->sendMessage($message);

        return 0;
    }
}
