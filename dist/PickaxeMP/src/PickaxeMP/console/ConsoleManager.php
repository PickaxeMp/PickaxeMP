<?php

declare(strict_types=1);

namespace PickaxeMP\console;

use PickaxeMP\command\CommandMap;
use PickaxeMP\command\CommandInterface;

/**
 * Konsol döngüsünü yönetir: giriş okuma, komut dağıtımını ve çıkışı.
 *
 * Giriş okuma ana tick döngüsünü bloklamamalıdır. Bu sınıf,
 * ConsoleInputReader ile koordinasyon sağlar.
 */
final class ConsoleManager
{
    /** @var list<string> Bekleyen komut satırları */
    private array $pendingLines = [];

    private ?ConsoleInputReaderInterface $reader = null;
    private bool $stopRequested = false;

    public function __construct(
        private readonly CommandMap $commandMap,
        private readonly ConsoleRenderer $renderer,
        private readonly ConsoleCommandSender $sender,
        private readonly ConsoleCommandDispatcher $dispatcher
    ) {
    }

    /** Konsol okuyucuyu bağlar. */
    public function setReader(ConsoleInputReaderInterface $reader): void
    {
        $this->reader = $reader;
    }

    /** Konsol okuyucudan yeni satırları toplar. */
    public function pollInput(): void
    {
        if ($this->reader === null) {
            return;
        }

        while (($line = $this->reader->readLine()) !== null) {
            $this->pendingLines[] = $line;
        }
    }

    /** Bekleyen tüm komutları çalıştırır. */
    public function processPendingCommands(): int
    {
        $executed = 0;

        while ($this->pendingLines !== []) {
            $line = array_shift($this->pendingLines);
            $this->dispatcher->dispatch($line);
            $executed++;
        }

        return $executed;
    }

    /** Komut istemini gösterir. */
    public function showPrompt(): void
    {
        $this->renderer->writePrompt('PickaxeMP > ');
    }

    /** Sunucu kapanışını ister. */
    public function requestStop(): void
    {
        $this->stopRequested = true;
    }

    /** Kapanış istenmiş mi? */
    public function isStopRequested(): bool
    {
        return $this->stopRequested;
    }

    /** Konsol okuyucuyu durdurur. */
    public function shutdown(): void
    {
        if ($this->reader !== null) {
            $this->reader->close();
        }
    }
}

/**
 * Konsol giriş okuyucusu sözleşmesi.
 *
 * Ana tick döngüsünü bloklamamalıdır. readLine() bir satır hazırsa döner,
 * yoksa null döner.
 */
interface ConsoleInputReaderInterface
{
    /** Bir komut satırı döndürür, mevcut değilse null. */
    public function readLine(): ?string;

    /** Kaynakları serbest bırakır. */
    public function close(): void;
}
