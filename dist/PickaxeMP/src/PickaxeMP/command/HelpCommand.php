<?php

declare(strict_types=1);

namespace PickaxeMP\command;

use PickaxeMP\console\ConsoleCommandSender;

final class HelpCommand extends AbstractCommand
{
    public function __construct(
        ConsoleCommandSender $sender,
        private readonly \PickaxeMP\command\CommandMap $commandMap
    ) {
        parent::__construct($sender);
    }

    public function getName(): string
    {
        return 'help';
    }

    public function getDescription(): string
    {
        return 'Komutları gösterir.';
    }

    public function getUsage(): string
    {
        return 'help';
    }

    public function execute(array $args): int
    {
        $commands = $this->commandMap->all();

        if ($commands === []) {
            $this->sender->sendMessage('Kayıtlı komut yok.');

            return 0;
        }

        $this->sender->sendMessage('Kullanılabilir komutlar:');

        foreach ($commands as $name => $command) {
            $this->sender->sendMessage(
                sprintf('  %-16s %s', $name, $command->getDescription())
            );
        }

        $this->sender->sendMessage(
            'Komut kullanımı için: help <komut>'
        );

        return 0;
    }
}
