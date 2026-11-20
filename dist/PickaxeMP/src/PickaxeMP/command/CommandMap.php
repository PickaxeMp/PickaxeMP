<?php

declare(strict_types=1);

namespace PickaxeMP\command;

/**
 * Komut kayıt ve çalıştırma merkezi.
 *
 * Tüm komutlar buraya kaydedilir ve isim üzerinden çözümlenir.
 */
final class CommandMap
{
    /** @var array<string, CommandInterface> */
    private array $commands = [];

    /** @var array<string, string> alias => commandName */
    private array $aliases = [];

    /** Komutu kaydeder. Aynı isimde bir komut varsa hata fırlatır. */
    public function register(CommandInterface $command): void
    {
        $name = $command->getName();

        if (isset($this->commands[$name])) {
            throw new \LogicException("Komut zaten kayitli: {$name}");
        }

        $this->commands[$name] = $command;
    }

    /** Bir komuta takma ad ekler. */
    public function registerAlias(string $alias, string $commandName): void
    {
        if (isset($this->aliases[$alias])) {
            throw new \LogicException("Takma ad zaten var: {$alias}");
        }

        $this->aliases[$alias] = $commandName;
    }

    /** İsim veya takma ad üzerinden komutu döndürür, yoksa null. */
    public function get(string $name): ?CommandInterface
    {
        if (isset($this->commands[$name])) {
            return $this->commands[$name];
        }

        if (isset($this->aliases[$name])) {
            return $this->commands[$this->aliases[$name]] ?? null;
        }

        return null;
    }

    /** Tüm kayıtlı komutları döndürür. */
    public function all(): array
    {
        return $this->commands;
    }

    /** Komutun kayıtlı olup olmadığını döndürür. */
    public function has(string $name): bool
    {
        return $this->get($name) !== null;
    }

    /** Komutu kaldırır. */
    public function unregister(string $name): void
    {
        unset($this->commands[$name]);

        foreach ($this->aliases as $alias => $cmdName) {
            if ($cmdName === $name) {
                unset($this->aliases[$alias]);
            }
        }
    }
}
