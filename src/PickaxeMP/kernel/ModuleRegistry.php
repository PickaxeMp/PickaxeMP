<?php

declare(strict_types=1);

namespace PickaxeMP\kernel;

use PickaxeMP\exception\DependencyException;

/**
 * Kayıtlı modüllerin tek kaynağı.
 *
 * Sıralama her zaman bağımlılık grafiğinden gelir; kayıt sırası yalnızca
 * eşitlik bozucu olarak kullanılır.
 */
final class ModuleRegistry
{
    /** @var array<string, ModuleInterface> */
    private array $modules = [];

    /** @var list<string>|null */
    private ?array $cachedOrder = null;

    public function register(ModuleInterface $module): void
    {
        $id = $module->getId();

        if (isset($this->modules[$id])) {
            throw DependencyException::duplicate($id);
        }

        $this->modules[$id] = $module;
        $this->cachedOrder = null;
    }

    public function has(string $id): bool
    {
        return isset($this->modules[$id]);
    }

    public function get(string $id): ModuleInterface
    {
        if (!isset($this->modules[$id])) {
            throw DependencyException::unknown($id);
        }

        return $this->modules[$id];
    }

    /**
     * Kayıt sırasına göre tüm modüller.
     *
     * @return list<ModuleInterface>
     */
    public function all(): array
    {
        return array_values($this->modules);
    }

    public function count(): int
    {
        return count($this->modules);
    }

    /**
     * Bağımlılık sırasına göre modüller.
     *
     * @return list<ModuleInterface>
     *
     * @throws DependencyException
     */
    public function inBootOrder(): array
    {
        if ($this->cachedOrder === null) {
            $this->cachedOrder = DependencyGraph::fromModules($this->all())->resolve();
        }

        $ordered = [];

        foreach ($this->cachedOrder as $id) {
            $ordered[] = $this->modules[$id];
        }

        return $ordered;
    }

    /**
     * Kapatmadan önce veri boşaltması gereken modüller (bağımlılık sırasıyla).
     *
     * @return list<ModuleInterface&SaveableInterface>
     */
    public function saveableInBootOrder(): array
    {
        $saveable = [];

        foreach ($this->inBootOrder() as $module) {
            if ($module instanceof SaveableInterface) {
                $saveable[] = $module;
            }
        }

        return $saveable;
    }

    /**
     * Belirtilen kimliklerin var olduğunu doğrular.
     *
     * @param list<string> $ids
     *
     * @throws DependencyException
     */
    public function require(array $ids): void
    {
        foreach ($ids as $id) {
            if (!isset($this->modules[$id])) {
                throw DependencyException::unknown($id);
            }
        }
    }
}
