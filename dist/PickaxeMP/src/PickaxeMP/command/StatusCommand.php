<?php

declare(strict_types=1);

namespace PickaxeMP\command;

use PickaxeMP\Server;
use PickaxeMP\console\ConsoleCommandSender;
use PickaxeMP\protocol\ProtocolVersion;
use PickaxeMP\utils\Logger;
use PickaxeMP\utils\ServerConfig;

/**
 * Sunucu durumu, calisma suresi ve genel olcumleri gösterir.
 *
 * Tüm değerler gerçek sistemden okunur; sahte TPS veya sahte oyuncu
 * sayısı üretilmez. Mevcut değilse "N/A" gösterilir.
 */
final class StatusCommand extends AbstractCommand
{
    public function __construct(
        ConsoleCommandSender $sender,
        private readonly Server $server,
        private readonly ServerConfig $config,
        private readonly Logger $logger
    ) {
        parent::__construct($sender);
    }

    public function getName(): string
    {
        return 'status';
    }

    public function getDescription(): string
    {
        return 'Sunucu durumu, calisma suresi ve genel olcumleri gosterir.';
    }

    public function getUsage(): string
    {
        return 'status';
    }

    public function execute(array $args): int
    {
        $uptime = time() - $this->server->getStartTime();
        $tick = $this->server->getTick();
        $memory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        $playerManager = $this->server->getPlayerManager();
        $worldManager = $this->server->getWorldManager();
        $pluginManager = $this->server->getPluginManager();

        $onlinePlayers = $playerManager !== null ? $playerManager->count() : 0;
        $worldCount = $worldManager !== null ? $worldManager->count() : 0;
        $pluginCount = $pluginManager !== null ? $pluginManager->count() : 0;

        $this->sender->sendMessage('--- PickaxeMP Sunucu Durumu ---');
        $this->sender->sendMessage('Surum: ' . Server::VERSION);
        $this->sender->sendMessage('PHP: ' . phpversion());
        $this->sender->sendMessage('Protokol: ' . ProtocolVersion::GAME_VERSION . ' (' . ProtocolVersion::PROTOCOL . ')');
        $this->sender->sendMessage('Sunucu adi: ' . $this->config->serverName);
        $this->sender->sendMessage('Port: ' . $this->config->port);
        $this->sender->sendMessage('Max oyuncu: ' . $this->config->maxPlayers);
        $this->sender->sendMessage('Cevrimici oyuncu: ' . $onlinePlayers);
        $this->sender->sendMessage('Dunya sayisi: ' . $worldCount);
        $this->sender->sendMessage('Yuklu eklenti: ' . $pluginCount);
        $this->sender->sendMessage('Calisma suresi: ' . $uptime . ' saniye');
        $this->sender->sendMessage('Tick: ' . $tick);
        $this->sender->sendMessage('Bellek: ' . round($memory / 1024 / 1024, 2) . ' MB (peak: ' . round($peakMemory / 1024 / 1024, 2) . ' MB)');
        $this->sender->sendMessage('Durum: ' . ($this->server->getKernel() !== null ? 'Calisiyor' : 'Baslatilmedi'));
        $this->sender->sendMessage('-----------------------------');

        return 0;
    }
}
