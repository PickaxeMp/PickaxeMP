<?php

declare(strict_types=1);

namespace PickaxeMP\command;

use PickaxeMP\console\ConsoleCommandSender;
use PickaxeMP\kernel\ReloadManager;
use PickaxeMP\utils\Logger;

/**
 * Yapılandırmayı gerçek ReloadManager ile yeniler.
 *
 * PHP sınıf kodu hot reload yapmaz; yalnızca yapılandırma/veri/menü
 * yenilenir. PHP yapılandırması dosyadan okunur, sunucu yeniden başlatılıncaya
 * kadar etkili olmaz.
 */
final class ReloadConfigCommand extends AbstractCommand
{
    public function __construct(
        ConsoleCommandSender $sender,
        private readonly Logger $logger,
        private readonly ReloadManager $reloadManager
    ) {
        parent::__construct($sender);
    }

    public function getName(): string
    {
        return 'reload';
    }

    public function getDescription(): string
    {
        return 'Yapilandirmayi yeniler.';
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

        if ($this->reloadManager->isRunning()) {
            $this->sender->sendError('Zaten bir reload islemi calisiyor.');

            return 1;
        }

        $report = $this->reloadManager->reload();

        if ($report->isSuccess()) {
            $this->sender->sendMessage(sprintf(
                '[OK] Yapilandirma yenilendi (%d modul, %.1f ms).',
                count($report->results()),
                $report->durationMs()
            ));

            return 0;
        }

        $this->sender->sendError(
            sprintf('Yapilandirma yenilenemedi: %s (%.1f ms)', $report->failureReason(), $report->durationMs())
        );

        return 1;
    }
}
