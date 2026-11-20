<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit;

use PHPUnit\Framework\TestCase;
use PickaxeMP\exception\SchedulerException;
use PickaxeMP\scheduler\Scheduler;

final class SchedulerTest extends TestCase
{
    public function testRunsTaskWhenDue(): void
    {
        $scheduler = new Scheduler();
        $calls = 0;

        $scheduler->schedule(function () use (&$calls): void {
            $calls++;
        }, 5);

        self::assertSame(0, $scheduler->run(4));
        self::assertSame(0, $calls);

        self::assertSame(1, $scheduler->run(5));
        self::assertSame(1, $calls);
    }

    public function testOneShotTaskIsRemoved(): void
    {
        $scheduler = new Scheduler();
        $scheduler->schedule(static function (): void {
        }, 1);

        $scheduler->run(1);

        self::assertSame(0, $scheduler->count());
        self::assertSame(0, $scheduler->run(2));
    }

    public function testPeriodicTaskRepeats(): void
    {
        $scheduler = new Scheduler();
        $calls = 0;

        $scheduler->schedule(function () use (&$calls): void {
            $calls++;
        }, 1, 10);

        $scheduler->run(1);
        $scheduler->run(5);
        $scheduler->run(11);

        self::assertSame(2, $calls);
        self::assertTrue($scheduler->has('task-1'));
    }

    public function testPeriodicTaskDoesNotBurstAfterLongGap(): void
    {
        $scheduler = new Scheduler();
        $calls = 0;

        $scheduler->schedule(function () use (&$calls): void {
            $calls++;
        }, 1, 10);

        // 1000 tick boyunca hiç çalıştırılmadı; tek seferde 100 kez patlamamalı.
        $executed = $scheduler->run(1000);

        self::assertSame(1, $executed);
        self::assertSame(1, $calls);
    }

    public function testCancelPreventsExecution(): void
    {
        $scheduler = new Scheduler();
        $called = false;

        $id = $scheduler->schedule(function () use (&$called): void {
            $called = true;
        }, 3);

        self::assertTrue($scheduler->cancel($id));
        $scheduler->run(10);

        self::assertFalse($called);
        self::assertFalse($scheduler->cancel($id));
    }

    public function testTaskExceptionDoesNotStopOthers(): void
    {
        $scheduler = new Scheduler();
        $second = false;

        $scheduler->schedule(static function (): void {
            throw new \RuntimeException('patladi');
        }, 1);

        $scheduler->schedule(function () use (&$second): void {
            $second = true;
        }, 1);

        $executed = $scheduler->run(1);

        self::assertSame(2, $executed);
        self::assertTrue($second);
    }

    public function testRejectsZeroDelay(): void
    {
        $this->expectException(SchedulerException::class);
        (new Scheduler())->schedule(static fn (): null => null, 0);
    }

    public function testRejectsZeroPeriod(): void
    {
        $this->expectException(SchedulerException::class);
        (new Scheduler())->schedule(static fn (): null => null, 1, 0);
    }

    public function testRejectsDuplicateId(): void
    {
        $scheduler = new Scheduler();
        $scheduler->schedule(static fn (): null => null, 1, null, 'same');

        $this->expectException(SchedulerException::class);
        $scheduler->schedule(static fn (): null => null, 1, null, 'same');
    }

    public function testExecutionOrderIsDeterministic(): void
    {
        $scheduler = new Scheduler();
        $order = [];

        $scheduler->schedule(function () use (&$order): void {
            $order[] = 'b';
        }, 5);

        $scheduler->schedule(function () use (&$order): void {
            $order[] = 'a';
        }, 5);

        $scheduler->run(5);

        self::assertSame(['b', 'a'], $order);
    }

    public function testCancelAll(): void
    {
        $scheduler = new Scheduler();
        $scheduler->schedule(static fn (): null => null, 1);
        $scheduler->schedule(static fn (): null => null, 2);

        $scheduler->cancelAll();

        self::assertSame(0, $scheduler->count());
        self::assertSame(0, $scheduler->run(10));
    }

    public function testCallerReceivesSchedulerAndTask(): void
    {
        $scheduler = new Scheduler();
        $receivedId = null;
        $receivedScheduler = null;

        $scheduler->schedule(function ($passedScheduler, $task) use (&$receivedId, &$receivedScheduler): void {
            $receivedScheduler = $passedScheduler;
            $receivedId = $task->id;
        }, 1);

        $scheduler->run(1);

        self::assertSame('task-1', $receivedId);
        self::assertSame($scheduler, $receivedScheduler);
    }
}
