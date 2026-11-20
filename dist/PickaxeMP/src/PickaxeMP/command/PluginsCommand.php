<?php

declare(strict_types=1);

namespace PickaxeMP\command;

use PickaxeMP\console\ConsoleCommandSender;
use PickaxeMP\plugin\PluginManager;

/**
 * Yüklenmiş eklentileri gösterir.
 *
 * PluginManager henüz TODO (boş iskelet) olduğu için eklenti sistemi
 * kullanılabilir değil. Bu komut bu durumu açıkça bildirir; sahte
 * eklenti listesi üretilmez.
 */
final class PluginsCommand extends AbstractCommand
{
    public function __construct(
        ConsoleCommandSender $sender,
        private readonly ?PluginManager $pluginManager = null
    ) {
        parent::__construct($sender);
    }

    public function getName(): string
    {
        return 'plugins';
    }

    public function getDescription(): string
    {
        return 'Yüklenmiş eklentileri gosterir.';
    }

    public function getUsage(): string
    {
        return 'plugins';
    }

    public function execute(array $args): int
    {
        if ($this->pluginManager === null) {
            $this->sender->sendMessage('Eklenti sistemi henüz etkin degil.');
            $this->sender->sendMessage('Yüklü eklenti yok.');

            return 0;
        }

        $this->sender->sendMessage('Eklenti sistemi mevcut ancak yüklü eklenti yok.');

        return 0;
    }
}
