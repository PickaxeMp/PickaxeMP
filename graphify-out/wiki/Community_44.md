# Community 44

> 22 nodes · cohesion 0.25

## Key Concepts

- **Scheduler** (32 connections) — `src/PickaxeMP/scheduler/Scheduler.php`
- **.run()** (14 connections) — `src/PickaxeMP/scheduler/Scheduler.php`
- **.schedule()** (14 connections) — `src/PickaxeMP/scheduler/Scheduler.php`
- **SchedulerTest** (14 connections) — `tests/unit/SchedulerTest.php`
- **.testCancelAll()** (6 connections) — `tests/unit/SchedulerTest.php`
- **.count()** (5 connections) — `src/PickaxeMP/scheduler/Scheduler.php`
- **.testCancelPreventsExecution()** (5 connections) — `tests/unit/SchedulerTest.php`
- **.testOneShotTaskIsRemoved()** (5 connections) — `tests/unit/SchedulerTest.php`
- **.testPeriodicTaskRepeats()** (5 connections) — `tests/unit/SchedulerTest.php`
- **.testCallerReceivesSchedulerAndTask()** (4 connections) — `tests/unit/SchedulerTest.php`
- **.testExecutionOrderIsDeterministic()** (4 connections) — `tests/unit/SchedulerTest.php`
- **.testPeriodicTaskDoesNotBurstAfterLongGap()** (4 connections) — `tests/unit/SchedulerTest.php`
- **.testRunsTaskWhenDue()** (4 connections) — `tests/unit/SchedulerTest.php`
- **.testTaskExceptionDoesNotStopOthers()** (4 connections) — `tests/unit/SchedulerTest.php`
- **.invoke()** (3 connections) — `src/PickaxeMP/scheduler/Scheduler.php`
- **.testRejectsDuplicateId()** (3 connections) — `tests/unit/SchedulerTest.php`
- **.cancel()** (2 connections) — `src/PickaxeMP/scheduler/Scheduler.php`
- **.cancelAll()** (2 connections) — `src/PickaxeMP/scheduler/Scheduler.php`
- **.has()** (2 connections) — `src/PickaxeMP/scheduler/Scheduler.php`
- **.testRejectsZeroDelay()** (2 connections) — `tests/unit/SchedulerTest.php`
- **.testRejectsZeroPeriod()** (2 connections) — `tests/unit/SchedulerTest.php`
- **.pendingIds()** (1 connections) — `src/PickaxeMP/scheduler/Scheduler.php`

## Relationships

- [Community 67](Community_67.md) (7 shared connections)
- [Community 37](Community_37.md) (6 shared connections)
- [Community 52](Community_52.md) (2 shared connections)
- [Community 27](Community_27.md) (2 shared connections)
- [Community 59](Community_59.md) (2 shared connections)
- [Community 48](Community_48.md) (1 shared connections)
- [Community 25](Community_25.md) (1 shared connections)

## Source Files

- `src/PickaxeMP/scheduler/Scheduler.php`
- `tests/unit/SchedulerTest.php`

## Audit Trail

- EXTRACTED: 53 (67%)
- INFERRED: 26 (33%)
- AMBIGUOUS: 0 (0%)

---

*Part of the graphify knowledge wiki. See [index](index.md) to navigate.*