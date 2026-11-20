<?php

declare(strict_types=1);

namespace PickaxeMP\command;

use PickaxeMP\console\ConsoleCommandSender;

final class VersionCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'version';
    }

    public function getDescription(): string
    {
        return 'PickaxeMP ve protokol sürümünü gösterir.';
    }

    public function getUsage(): string
    {
        return 'version';
    }

    public function execute(array $args): int
    {
        $this->sender->sendMessage('PickaxeMP 0.1.0-dev');
        $this->sender->sendMessage('Bedrock Protokol: 1.26.65 (2225)');
        $this->sender->sendMessage('PHP: ' . phpversion());

        return 0;
    }
}
