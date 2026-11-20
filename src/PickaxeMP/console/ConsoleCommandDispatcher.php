<?php

declare(strict_types=1);

namespace PickaxeMP\console;

use PickaxeMP\command\CommandInterface;
use PickaxeMP\command\CommandMap;

/**
 * Komut satırını çözümler, komutu bulur ve çalıştırır.
 */
final class ConsoleCommandDispatcher
{
    public function __construct(
        private readonly CommandMap $commandMap,
        private readonly ConsoleCommandSender $sender
    ) {
    }

    /**
     * Girdi satırını çalıştırır.
     *
     * @return int Çıkış kodu (0 = başarı, 1 = bilinmeyen komut, 2 = hata)
     */
    public function dispatch(string $input): int
    {
        $input = trim($input);

        if ($input === '') {
            return 0;
        }

        $parts = $this->parseArguments($input);
        $commandName = array_shift($parts);
        $args = $parts;

        $command = $this->commandMap->get($commandName);

        if ($command === null) {
            $this->sender->sendError("Bilinmeyen komut: {$commandName}");
            $this->sender->sendMessage("Komut listesi icin 'help' yazin.");

            return 1;
        }

        try {
            return $command->execute($args);
        } catch (\Throwable $throwable) {
            $this->sender->sendError('Komut calistirilirken hata: ' . $throwable->getMessage());

            return 2;
        }
    }

    /**
     * Girdiyi boşluklara göre ayırır. Çift tırnaklı bölümler tek eleman olarak korunur.
     *
     * @return string[]
     */
    private function parseArguments(string $input): array
    {
        $parts = [];
        $length = strlen($input);
        $i = 0;
        $quote = null;
        $buffer = '';

        while ($i < $length) {
            $char = $input[$i];

            if ($quote !== null) {
                if ($char === '\\' && $i + 1 < $length) {
                    $buffer .= $input[++$i];
                    continue;
                }

                if ($char === $quote) {
                    $parts[] = $buffer;
                    $buffer = '';
                    $quote = null;
                    continue;
                }

                $buffer .= $char;
                $i++;
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $i++;
                continue;
            }

            if ($char === ' ' || $char === "\t") {
                if ($buffer !== '') {
                    $parts[] = $buffer;
                    $buffer = '';
                }

                $i++;
                continue;
            }

            $buffer .= $char;
            $i++;
        }

        if ($buffer !== '') {
            $parts[] = $buffer;
        }

        if ($quote !== null) {
            $parts[] = $buffer;
        }

        return $parts;
    }
}
