<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit;

use PHPUnit\Framework\TestCase;
use PickaxeMP\exception\DependencyException;
use PickaxeMP\kernel\DependencyGraph;
use PickaxeMP\kernel\ModuleRegistry;
use PickaxeMP\Tests\support\FakeModule;
use PickaxeMP\Tests\support\PlainModule;

final class DependencyGraphTest extends TestCase
{
    public function testResolvesLinearChain(): void
    {
        $a = new FakeModule('a');
        $b = new FakeModule('b', ['a']);
        $c = new FakeModule('c', ['b']);

        $graph = DependencyGraph::fromModules([$c, $b, $a]);

        self::assertSame(['a', 'b', 'c'], $graph->resolve());
    }

    public function testResolvesDiamond(): void
    {
        $root = new FakeModule('root');
        $left = new FakeModule('left', ['root']);
        $right = new FakeModule('right', ['root']);
        $leaf = new FakeModule('leaf', ['left', 'right']);

        $graph = DependencyGraph::fromModules([$leaf, $right, $left, $root]);

        $resolved = $graph->resolve();

        self::assertSame('root', $resolved[0]);
        self::assertContains('leaf', $resolved);
        self::assertGreaterThan(
            array_search('left', $resolved, true),
            array_search('leaf', $resolved, true)
        );
        self::assertGreaterThan(
            array_search('right', $resolved, true),
            array_search('leaf', $resolved, true)
        );
    }

    public function testIsDeterministicForIndependentModules(): void
    {
        $first = new FakeModule('first');
        $second = new FakeModule('second');
        $third = new FakeModule('third');

        $graph = DependencyGraph::fromModules([$first, $second, $third]);

        self::assertSame(['first', 'second', 'third'], $graph->resolve());
    }

    public function testDetectsMissingDependency(): void
    {
        $module = new FakeModule('orphan', ['ghost']);

        $this->expectException(DependencyException::class);
        DependencyGraph::fromModules([$module])->resolve();
    }

    public function testDetectsDirectCycle(): void
    {
        $a = new FakeModule('a', ['b']);
        $b = new FakeModule('b', ['a']);

        $this->expectException(DependencyException::class);
        DependencyGraph::fromModules([$a, $b])->resolve();
    }

    public function testDetectsSelfDependency(): void
    {
        $a = new FakeModule('a', ['a']);

        $this->expectException(DependencyException::class);
        DependencyGraph::fromModules([$a])->resolve();
    }

    public function testEmptyGraphResolvesToEmptyList(): void
    {
        self::assertSame([], DependencyGraph::fromModules([])->resolve());
    }

    public function testRegistryRejectsDuplicateId(): void
    {
        $registry = new ModuleRegistry();
        $registry->register(new FakeModule('dup'));

        $this->expectException(DependencyException::class);
        $registry->register(new FakeModule('dup'));
    }

    public function testRegistryBootOrder(): void
    {
        $registry = new ModuleRegistry();
        $registry->register(new FakeModule('plugin', ['core']));
        $registry->register(new FakeModule('core'));

        $ids = array_map(
            static fn ($module): string => $module->getId(),
            $registry->inBootOrder()
        );

        self::assertSame(['core', 'plugin'], $ids);
    }

    public function testRegistryUnknownModuleThrows(): void
    {
        $this->expectException(DependencyException::class);
        (new ModuleRegistry())->get('yok');
    }

    public function testRegistryOrderCacheIsInvalidatedOnRegister(): void
    {
        $registry = new ModuleRegistry();
        $registry->register(new FakeModule('a'));

        self::assertCount(1, $registry->inBootOrder());

        $registry->register(new FakeModule('b', ['a']));

        self::assertCount(2, $registry->inBootOrder());
    }

    public function testSaveableModulesAreListedInBootOrder(): void
    {
        $registry = new ModuleRegistry();
        $registry->register(new FakeModule('world', [], true, true));
        $registry->register(new PlainModule('plain'));
        $registry->register(new FakeModule('player', ['world'], true, true));

        $ids = array_map(
            static fn ($module): string => $module->getId(),
            $registry->saveableInBootOrder()
        );

        self::assertSame(['world', 'player'], $ids);
    }
}

