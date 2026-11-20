<?php

declare(strict_types=1);

namespace PickaxeMP\command;

use PickaxeMP\console\ConsoleCommandSender;

final class StopCommand extends AbstractCommand
{
    public function __construct(
        ConsoleCommandSender $sender,
        private readonly \PickaxeMP\console\ConsoleManager $consoleManager
    ) {
        parent::__construct($sender);
    }

    public function getName(): string
    {
        return 'stop';
    }

    public function getDescription(): string
    {
        return 'Güvenli sunucu kapanışını başlatır.';
    }

    public function getUsage(): string
    {
        return 'stop';
    }

    public function execute(array $args): int
    {
        $this->sender->sendMessage('Sunucu kapatılıyor...');
        $this->consoleManager->requestStop();

        return 0;
    }
}
