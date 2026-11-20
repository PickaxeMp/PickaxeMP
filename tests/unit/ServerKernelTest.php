<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit;

use PHPUnit\Framework\TestCase;
use PickaxeMP\exception\LifecycleException;
use PickaxeMP\kernel\ModuleRegistry;
use PickaxeMP\kernel\ServerKernel;
use PickaxeMP\scheduler\Scheduler;
use PickaxeMP\Tests\support\FakeModule;
use PickaxeMP\utils\Config;
use PickaxeMP\utils\Logger;
use PickaxeMP\utils\ServerConfig;

final class ServerKernelTest extends TestCase
{
    /** @var resource */
    private $logStream;

    private function kernel(ModuleRegistry $registry): ServerKernel
    {
        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);
        $this->logStream = $stream;

        $config = Config::fromString('server-name: Test');
        $logger = new Logger('Test', $stream, Logger::DEBUG);

        return new ServerKernel(
            $logger,
            $config,
            ServerConfig::fromConfig($config),
            new Scheduler($logger),
            $registry
        );
    }

    public function testBootsModulesInDependencyOrder(): void
    {
        $registry = new ModuleRegistry();
        $plugin = new FakeModule('plugin', ['core']);
        $core = new FakeModule('core');

        $registry->register($plugin);
        $registry->register($core);

        $kernel = $this->kernel($registry);
        $kernel->boot();

        self::assertTrue($kernel->isBooted());
        self::assertTrue($core->booted);
        self::assertTrue($plugin->booted);
        self::assertSame(['core', 'plugin'], array_map(
            static fn (FakeModule $m): string => $m->id,
            [$core, $plugin]
        ));
    }

    public function testShutdownRunsInReverseOrder(): void
    {
        $registry = new ModuleRegistry();
        $core = new FakeModule('core');
        $plugin = new FakeModule('plugin', ['core']);

        $registry->register($core);
        $registry->register($plugin);

        $kernel = $this->kernel($registry);
        $kernel->boot();
        $kernel->shutdown();

        self::assertFalse($kernel->isBooted());
        self::assertTrue($plugin->shutdownDone);
        self::assertTrue($core->shutdownDone);
        self::assertSame(['boot:plugin', 'shutdown:plugin'], $plugin->events);
    }

    public function testShutdownFlushesSaveableModules(): void
    {
        $registry = new ModuleRegistry();
        $world = new FakeModule('world', [], true, true);
        $world->pendingWrites = true;

        $registry->register($world);

        $kernel = $this->kernel($registry);
        $kernel->boot();
        $kernel->shutdown();

        self::assertSame(1, $world->flushCount);
        self::assertFalse($world->pendingWrites);
    }

    public function testShutdownSkipsFlushWhenNoPendingWrites(): void
    {
        $registry = new ModuleRegistry();
        $world = new FakeModule('world', [], true, true);

        $registry->register($world);

        $kernel = $this->kernel($registry);
        $kernel->boot();
        $kernel->shutdown();

        self::assertSame(0, $world->flushCount);
    }

    public function testShutdownIsIdempotent(): void
    {
        $registry = new ModuleRegistry();
        $registry->register(new FakeModule('core'));

        $kernel = $this->kernel($registry);
        $kernel->boot();
        $kernel->shutdown();
        $kernel->shutdown();

        self::assertFalse($kernel->isBooted());
    }

    public function testBootTwiceThrows(): void
    {
        $kernel = $this->kernel(new ModuleRegistry());
        $kernel->boot();

        $this->expectException(LifecycleException::class);
        $kernel->boot();
    }

    public function testTickBeforeBootThrows(): void
    {
        $kernel = $this->kernel(new ModuleRegistry());

        $this->expectException(LifecycleException::class);
        $kernel->tick(1);
    }

    public function testFailedBootRollsBackAlreadyBootedModules(): void
    {
        $registry = new ModuleRegistry();
        $core = new FakeModule('core');
        $broken = new FakeModule('broken', ['core']);
        $broken->failBoot = true;

        $registry->register($core);
        $registry->register($broken);

        $kernel = $this->kernel($registry);

        try {
            $kernel->boot();
            self::fail('Boot istisna firlatmaliydi.');
        } catch (LifecycleException $exception) {
            self::assertStringContainsString('broken', $exception->getMessage());
        }

        self::assertFalse($kernel->isBooted());
        self::assertTrue($core->booted);
        self::assertTrue($core->shutdownDone);
    }

    public function testTickRunsScheduledTasks(): void
    {
        $registry = new ModuleRegistry();
        $kernel = $this->kernel($registry);
        $kernel->boot();

        $ran = false;
        $kernel->getScheduler()->schedule(function () use (&$ran): void {
            $ran = true;
        }, 2);

        self::assertSame(0, $kernel->tick(1));
        self::assertFalse($ran);

        self::assertSame(1, $kernel->tick(2));
        self::assertTrue($ran);
    }

    public function testModuleShutdownErrorDoesNotPreventOthers(): void
    {
        $registry = new ModuleRegistry();

        $broken = new class ('broken') extends FakeModule {
            public function shutdown(): void
            {
                throw new \RuntimeException('shutdown patladi');
            }
        };

        $healthy = new FakeModule('healthy');

        $registry->register($healthy);
        $registry->register($broken);

        $kernel = $this->kernel($registry);
        $kernel->boot();
        $kernel->shutdown();

        self::assertTrue($healthy->shutdownDone);
    }

    public function testAccessorsReturnDependencies(): void
    {
        $kernel = $this->kernel(new ModuleRegistry());

        self::assertSame('Test', $kernel->getLogger()->getChannel());
        self::assertSame('Test', $kernel->getSettings()->serverName);
        self::assertSame(0, $kernel->getModules()->count());
        self::assertSame(0, $kernel->getScheduler()->count());
    }
}
