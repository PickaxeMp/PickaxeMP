<?php

declare(strict_types=1);

namespace PickaxeMP\command;

use PickaxeMP\console\ConsoleCommandSender;
use PickaxeMP\player\PlayerManager;
use PickaxeMP\world\WorldManager;

/**
 * Dunya ve oyuncu verilerini gerçek persistence sistemlerine kaydeder.
 *
 * Her bileşenin sonucu ayrı ayrı raporlanır. Bileşenden biri başarısızsa
 * işlem "kısmi başarı" olarak işaretlenir ve hata mesajı gösterilir.
 */
final class SaveAllCommand extends AbstractCommand
{
    public function __construct(
        ConsoleCommandSender $sender,
        private readonly ?WorldManager $worldManager = null,
        private readonly ?PlayerManager $playerManager = null
    ) {
        parent::__construct($sender);
    }

    public function getName(): string
    {
        return 'save-all';
    }

    public function getDescription(): string
    {
        return 'Dunya ve oyuncu verilerini kaydeder.';
    }

    public function getUsage(): string
    {
        return 'save-all';
    }

    public function execute(array $args): int
    {
        $this->sender->sendMessage('Tum veriler kaydediliyor...');

        $success = true;
        $savedWorlds = 0;
        $savedPlayers = 0;

        // 1) Dunyaları kaydet.
        if ($this->worldManager === null) {
            $this->sender->sendError('WorldManager mevcut degil. Dunya kaydedilemedi.');
            $success = false;
        } else {
            try {
                foreach ($this->worldManager->all() as $world) {
                    $savedWorlds += $world->saveDirtyChunks();
                }
                $this->sender->sendMessage(sprintf('[OK] Dunya kaydi: %d chunk kaydedildi.', $savedWorlds));
            } catch (\Throwable $throwable) {
                $this->sender->sendError('Dunya kaydi basarisiz: ' . $throwable->getMessage());
                $success = false;
            }
        }

        // 2) Oyuncu envanterlerini kaydet.
        if ($this->playerManager === null) {
            $this->sender->sendError('PlayerManager mevcut degil. Oyuncu envanteri kaydedilemedi.');
            $success = false;
        } else {
            foreach ($this->playerManager->all() as $player) {
                try {
                    $this->playerManager->saveInventory($player);
                    $savedPlayers++;
                } catch (\Throwable $throwable) {
                    $this->sender->sendError(
                        sprintf('Oyuncu envanter kaydi basarisiz (%s): %s', $player->identity->displayName, $throwable->getMessage())
                    );
                    $success = false;
                }
            }
            $this->sender->sendMessage(sprintf('[OK] Oyuncu envanteri: %d oyuncu kaydedildi.', $savedPlayers));
        }

        if ($success) {
            $this->sender->sendMessage('[OK] Tum veri kaydedildi.');

            return 0;
        }

        $this->sender->sendError('Bazı bileşenler kaydedilemedi. Yukarıdaki hataları kontrol edin.');

        return 1;
    }
}
