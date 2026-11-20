<?php

declare(strict_types=1);

namespace PickaxeMP\console;

use PickaxeMP\command\CommandInterface;

/**
 * Sunucu konsolu olarak komut gönderici.
 *
 * Komutların çıkışını konsola yazar ve konsolun yönetici yetkilerini taşır.
 */
final class ConsoleCommandSender
{
    private readonly ConsoleRenderer $renderer;

    public function __construct(ConsoleRenderer $renderer)
    {
        $this->renderer = $renderer;
    }

    /** Komut çıktısını konsola yazar. */
    public function sendMessage(string $message): void
    {
        $this->renderer->writeLine($message);
    }

    /** Hata mesajını konsola yazar. */
    public function sendError(string $message): void
    {
        $this->renderer->writeError($message);
    }

    /** Komutun kullanım örneğini gösterir. */
    public function sendUsage(CommandInterface $command): void
    {
        $this->renderer->writeLine('Kullanim: ' . $command->getUsage());
    }
}
