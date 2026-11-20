<?php

declare(strict_types=1);

namespace PickaxeMP\kernel;

use PickaxeMP\exception\DependencyException;

/**
 * Modül bağımlılık grafiği.
 *
 * Kahn algoritması ile deterministik bir topolojik sıra üretir: aynı anda
 * hazır olan modüller kayıt sırasına göre işlenir.
 */
final class DependencyGraph
{
    /**
     * @param array<string, list<string>> $dependencies modül kimliği => bağımlılık kimlikleri
     * @param array<string, int> $order modül kimliği => kayıt sırası (eşitlik bozucu)
     */
    private function __construct(
        private readonly array $dependencies,
        private readonly array $order
    ) {
    }

    /**
     * @param list<ModuleInterface> $modules
     */
    public static function fromModules(array $modules): self
    {
        $dependencies = [];
        $order = [];

        foreach ($modules as $index => $module) {
            $dependencies[$module->getId()] = $module->getDependencies();
            $order[$module->getId()] = $index;
        }

        return new self($dependencies, $order);
    }

    /**
     * Bağımlılıkların önce geldiği sıralı modül kimlikleri.
     *
     * @return list<string>
     *
     * @throws DependencyException eksik bağımlılık veya döngü varsa
     */
    public function resolve(): array
    {
        $inDegree = [];
        $dependents = [];

        foreach ($this->dependencies as $id => $deps) {
            $inDegree[$id] ??= 0;
            $dependents[$id] ??= [];

            foreach ($deps as $dep) {
                if (!array_key_exists($dep, $this->dependencies)) {
                    throw DependencyException::missing($id, $dep);
                }

                if ($dep === $id) {
                    throw DependencyException::cycle([$id, $id]);
                }

                $inDegree[$id]++;
                $dependents[$dep][] = $id;
            }
        }

        $ready = [];

        foreach ($inDegree as $id => $degree) {
            if ($degree === 0) {
                $ready[] = $id;
            }
        }

        usort($ready, fn (string $a, string $b): int => $this->order[$a] <=> $this->order[$b]);

        $sorted = [];

        while ($ready !== []) {
            $current = array_shift($ready);
            $sorted[] = $current;

            foreach ($dependents[$current] as $dependent) {
                $inDegree[$dependent]--;

                if ($inDegree[$dependent] === 0) {
                    $ready[] = $dependent;
                    usort($ready, fn (string $a, string $b): int => $this->order[$a] <=> $this->order[$b]);
                }
            }
        }

        if (count($sorted) !== count($this->dependencies)) {
            $remaining = array_values(array_diff(array_keys($this->dependencies), $sorted));

            throw DependencyException::cycle($remaining);
        }

        return $sorted;
    }

    /**
     * @return array<string, list<string>>
     */
    public function dependencies(): array
    {
        return $this->dependencies;
    }
}
