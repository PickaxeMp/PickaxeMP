<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit;

use PHPUnit\Framework\TestCase;
use PickaxeMP\kernel\ModuleRegistry;
use PickaxeMP\kernel\ReloadManager;
use PickaxeMP\kernel\ReloadRequest;
use PickaxeMP\kernel\ReloadStatus;
use PickaxeMP\Tests\support\FakeModule;
use PickaxeMP\utils\Logger;

final class ReloadManagerTest extends TestCase
{
    private Logger $logger;

    protected function setUp(): void
    {
        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);
        $this->logger = new Logger('Test', $stream, Logger::DEBUG);
    }

    private function manager(ModuleRegistry $registry, int $timeoutMs = 5000): ReloadManager
    {
        return new ReloadManager($registry, $this->logger, null, $timeoutMs);
    }

    public function testSuccessfulReloadCommitsAllModules(): void
    {
        $registry = new ModuleRegistry();
        $a = new FakeModule('a');
        $b = new FakeModule('b', ['a']);

        $registry->register($a);
        $registry->register($b);

        $report = $this->manager($registry)->reload(ReloadRequest::all());

        self::assertTrue($report->success);
        self::assertSame(2, $report->committedCount());
        self::assertSame(1, $a->prepareCount);
        self::assertSame(1, $a->validateCount);
        self::assertSame(1, $a->commitCount);
        self::assertSame(0, $a->rollbackCount);
    }

    public function testReloadFollowsDependencyOrder(): void
    {
        $registry = new ModuleRegistry();
        $plugin = new FakeModule('plugin', ['core']);
        $core = new FakeModule('core');

        $registry->register($plugin);
        $registry->register($core);

        $this->manager($registry)->reload(ReloadRequest::all());

        self::assertSame(['commit:core'], $core->events);
        self::assertSame(['commit:plugin'], $plugin->events);
    }

    public function testNonReloadableModulesAreSkipped(): void
    {
        $registry = new ModuleRegistry();
        $fixed = new FakeModule('fixed', [], false);
        $reloadable = new FakeModule('reloadable');

        $registry->register($fixed);
        $registry->register($reloadable);

        $report = $this->manager($registry)->reload(ReloadRequest::all());

        self::assertTrue($report->success);
        self::assertSame(0, $fixed->prepareCount);
        self::assertSame(1, $reloadable->prepareCount);
    }

    public function testPrepareFailureKeepsPreviousState(): void
    {
        $registry = new ModuleRegistry();
        $good = new FakeModule('good');
        $bad = new FakeModule('bad', ['good']);
        $bad->failPrepare = true;

        $registry->register($good);
        $registry->register($bad);

        $report = $this->manager($registry)->reload(ReloadRequest::all());

        self::assertFalse($report->success);
        self::assertSame(0, $good->commitCount);
        self::assertSame(0, $bad->commitCount);
        self::assertStringContainsString('bad', $report->failureReason);
    }

    public function testValidateFailureCommitsNothing(): void
    {
        $registry = new ModuleRegistry();
        $first = new FakeModule('first');
        $second = new FakeModule('second', ['first']);
        $second->failValidate = true;

        $registry->register($first);
        $registry->register($second);

        $report = $this->manager($registry)->reload(ReloadRequest::all());

        self::assertFalse($report->success);
        self::assertSame(0, $first->commitCount);
        self::assertSame(0, $second->commitCount);
    }

    public function testCommitFailureRollsBackAlreadyCommittedModules(): void
    {
        $registry = new ModuleRegistry();
        $first = new FakeModule('first');
        $second = new FakeModule('second', ['first']);
        $second->failCommit = true;

        $registry->register($first);
        $registry->register($second);

        $report = $this->manager($registry)->reload(ReloadRequest::all());

        self::assertFalse($report->success);
        self::assertSame(1, $first->commitCount);
        self::assertGreaterThanOrEqual(1, $first->rollbackCount);

        $statuses = array_map(
            static fn ($result) => $result->status,
            $report->results()
        );

        self::assertContains(ReloadStatus::RolledBack, $statuses);
        self::assertContains(ReloadStatus::Failed, $statuses);
    }

    public function testRollbackFailureIsReported(): void
    {
        $registry = new ModuleRegistry();
        $first = new FakeModule('first');
        $first->failRollback = true;
        $second = new FakeModule('second', ['first']);
        $second->failCommit = true;

        $registry->register($first);
        $registry->register($second);

        $report = $this->manager($registry)->reload(ReloadRequest::all());

        self::assertFalse($report->success);
        self::assertStringContainsString('Geri alma basarisiz', implode(' ', $report->toLines()));
    }

    public function testSecondReloadIsRejectedWhileRunning(): void
    {
        $registry = new ModuleRegistry();

        $reentrant = new class ('reentrant') extends FakeModule {
            public ?ReloadManager $manager = null;

            public ?bool $rejected = null;

            public function prepareReload(ReloadRequest $request): \PickaxeMP\kernel\ReloadPlan
            {
                $this->rejected = !$this->manager?->reload($request)->success;

                return parent::prepareReload($request);
            }
        };

        $registry->register($reentrant);

        $manager = $this->manager($registry);
        $reentrant->manager = $manager;
        $manager->reload(ReloadRequest::all());

        self::assertTrue($reentrant->rejected);
    }

    public function testEmptyRegistryReloadSucceeds(): void
    {
        $report = $this->manager(new ModuleRegistry())->reload(ReloadRequest::all());

        self::assertTrue($report->success);
        self::assertSame([], $report->results());
    }

    public function testReportLinesAreReadable(): void
    {
        $registry = new ModuleRegistry();
        $registry->register(new FakeModule('config'));
        $registry->register(new FakeModule('messages'));

        $lines = $this->manager($registry)->reload(ReloadRequest::all())->toLines();

        self::assertSame(['[OK] config', '[OK] messages'], $lines);
    }

    public function testReportContainsFailureReason(): void
    {
        $registry = new ModuleRegistry();
        $module = new FakeModule('bad');
        $module->failPrepare = true;
        $registry->register($module);

        $report = $this->manager($registry)->reload(ReloadRequest::all());

        self::assertFalse($report->success);
        self::assertNotSame('', $report->failureReason);
        self::assertFalse($this->manager(new ModuleRegistry())->isRunning());
    }
}
