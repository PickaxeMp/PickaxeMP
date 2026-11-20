<?php

declare(strict_types=1);

namespace PickaxeMP\command;

use PickaxeMP\console\ConsoleCommandSender;

final class StatusCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'status';
    }

    public function getDescription(): string
    {
        return 'Sunucu durumu, çalışma süresi ve genel ölçümleri gösterir.';
    }

    public function getUsage(): string
    {
        return 'status';
    }

    public function execute(array $args): int
    {
        $this->sender->sendMessage('--- PickaxeMP Sunucu Durumu ---');
        $this->sender->sendMessage('Sürüm: 0.1.0-dev');
        $this->sender->sendMessage('PHP: ' . phpversion());
        $this->sender->sendMessage('Çalışma süresi: ölçülmedi (tick tabanlı)');
        $this->sender->sendMessage('Online oyuncu: 0 / 0');
        $this->sender->sendMessage('Dünya: yüklü değil (Phase 0)');
        $this->sender->sendMessage('Ağ: kapalı (Phase 0)');
        $this->sender->sendMessage('-----------------------------');

        return 0;
    }
}
