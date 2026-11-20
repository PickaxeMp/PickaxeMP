<?php

declare(strict_types=1);

namespace PickaxeMP\command;

use PickaxeMP\console\ConsoleCommandSender;
use PickaxeMP\console\ConsoleManager;
use PickaxeMP\Server;

/**
 * Güvenli sunucu kapanışını başlatır.
 *
 * Konsol kapanış isteğini yazar; Server::run() döngüsü bu bayrağı gördüğü
 * anda nazikçe çıkış yapar (Saveable modüller boşaltılır, scheduler ve
 * sidecar durdurulur).
 */
final class StopCommand extends AbstractCommand
{
    public function __construct(
        ConsoleCommandSender $sender,
        private readonly ConsoleManager $consoleManager,
        private readonly Server $server
    ) {
        parent::__construct($sender);
    }

    public function getName(): string
    {
        return 'stop';
    }

    public function getDescription(): string
    {
        return 'Guvenli sunucu kapanışini baslatir.';
    }

    public function getUsage(): string
    {
        return 'stop';
    }

    public function execute(array $args): int
    {
        $this->sender->sendMessage('Sunucu kapatiliyor...');
        $this->consoleManager->requestStop();
        $this->server->stop();

        return 0;
    }
}
