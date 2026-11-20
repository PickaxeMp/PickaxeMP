<?php

declare(strict_types=1);

namespace PickaxeMP\command;

use PickaxeMP\console\ConsoleCommandSender;

/**
 * Sunucu mesajını konsola yazar.
 *
 * Gerçek oyuncu broadcast sistemi henüz yok, bu yüzden mesaj yalnızca
 * konsola yansıtılır. Oyuncu olmayan bir oturum olarak davranılır;
 * broadcast başarısız olduğu durumda bunu konsola yazar.
 */
final class SayCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'say';
    }

    public function getDescription(): string
    {
        return 'Sunucu mesajı yayınlar (sohbet sistemi destekliyorsa).';
    }

    public function getUsage(): string
    {
        return 'say <mesaj>';
    }

    public function execute(array $args): int
    {
        if ($args === []) {
            $this->sender->sendUsage($this);

            return 1;
        }

        $message = implode(' ', $args);

        // Gerçek broadcast sistemi henüz yok; mesajı konsola yansıt.
        $this->sender->sendMessage('[Sunucu] ' . $message);
        $this->sender->sendMessage('[WARN] Oyuncu broadcast sistemi henüz etkin degil; mesaj yalnızca konsola yazildi.');

        return 0;
    }
}
