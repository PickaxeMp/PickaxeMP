# FakeModule

> God node · 46 connections · `tests/support/FakeModule.php`

**Community:** [Community 34](Community_34.md)

## Connections by Relation

### calls
- .testSaveableModulesAreListedInBootOrder() `EXTRACTED`
- .testCommitFailureRollsBackAlreadyCommittedModules() `EXTRACTED`
- .testNonReloadableModulesAreSkipped() `EXTRACTED`
- .testPrepareFailureKeepsPreviousState() `EXTRACTED`
- .testReloadFollowsDependencyOrder() `EXTRACTED`
- .testReportContainsFailureReason() `EXTRACTED`
- .testReportLinesAreReadable() `EXTRACTED`
- .testRollbackFailureIsReported() `EXTRACTED`
- .testSuccessfulReloadCommitsAllModules() `EXTRACTED`
- .testValidateFailureCommitsNothing() `EXTRACTED`
- .testModuleShutdownErrorDoesNotPreventOthers() `EXTRACTED`
- .testShutdownFlushesSaveableModules() `EXTRACTED`
- .testShutdownIsIdempotent() `EXTRACTED`
- .testShutdownRunsInReverseOrder() `EXTRACTED`
- .testShutdownSkipsFlushWhenNoPendingWrites() `EXTRACTED`
- .testRegistryBootOrder() `EXTRACTED`
- .testRegistryOrderCacheIsInvalidatedOnRegister() `EXTRACTED`
- .testBootsModulesInDependencyOrder() `EXTRACTED`
- .testFailedBootRollsBackAlreadyBootedModules() `EXTRACTED`
- .testRegistryRejectsDuplicateId() `EXTRACTED`
- *…and 6 more `calls` connection(s) not listed (lowest-degree first to go)*

### contains
- FakeModule.php `EXTRACTED`

### implements
- ReloadableInterface `EXTRACTED`
- SaveableInterface `EXTRACTED`

### imports
- ServerKernelTest.php `EXTRACTED`
- ReloadManagerTest.php `EXTRACTED`
- DependencyGraphTest.php `EXTRACTED`

### method
- .record() `EXTRACTED`
- .prepareReload() `EXTRACTED`
- .commitReload() `EXTRACTED`
- .rollbackReload() `EXTRACTED`
- .getId() `EXTRACTED`
- .shutdown() `EXTRACTED`
- .validateReload() `EXTRACTED`
- .flush() `EXTRACTED`
- .getLifecyclePolicy() `EXTRACTED`
- .boot() `EXTRACTED`
- .hasPendingWrites() `EXTRACTED`
- .__construct() `EXTRACTED`
- .getDependencies() `EXTRACTED`

### references
- {closure#1}() `EXTRACTED`

---

*Part of the graphify knowledge wiki. See [index](index.md) to navigate.*