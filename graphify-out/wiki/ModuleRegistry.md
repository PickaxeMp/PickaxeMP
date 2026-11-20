# ModuleRegistry

> God node · 49 connections · `src/PickaxeMP/kernel/ModuleRegistry.php`

**Community:** [Community 52](Community_52.md)

## Connections by Relation

### calls
- .boot() `EXTRACTED`
- .testSaveableModulesAreListedInBootOrder() `EXTRACTED`
- .testCommitFailureRollsBackAlreadyCommittedModules() `EXTRACTED`
- .testNonReloadableModulesAreSkipped() `EXTRACTED`
- .testPrepareFailureKeepsPreviousState() `EXTRACTED`
- .testReloadFollowsDependencyOrder() `EXTRACTED`
- .testReportContainsFailureReason() `EXTRACTED`
- .testReportLinesAreReadable() `EXTRACTED`
- .testRollbackFailureIsReported() `EXTRACTED`
- .testSecondReloadIsRejectedWhileRunning() `EXTRACTED`
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
- *…and 8 more `calls` connection(s) not listed (lowest-degree first to go)*

### contains
- ModuleRegistry.php `EXTRACTED`

### imports
- ServerKernelTest.php `EXTRACTED`
- ReloadManagerTest.php `EXTRACTED`
- Server.php `EXTRACTED`
- DependencyGraphTest.php `EXTRACTED`

### method
- .register() `EXTRACTED`
- .inBootOrder() `EXTRACTED`
- .count() `EXTRACTED`
- .get() `EXTRACTED`
- .saveableInBootOrder() `EXTRACTED`
- .require() `EXTRACTED`
- .all() `EXTRACTED`
- .has() `EXTRACTED`

### references
- ServerKernel `EXTRACTED`
- .kernel() `EXTRACTED`
- ReloadManager `EXTRACTED`
- .manager() `EXTRACTED`
- .__construct() `EXTRACTED`
- .__construct() `EXTRACTED`
- .registerCoreModules() `EXTRACTED`
- .getModules() `EXTRACTED`

---

*Part of the graphify knowledge wiki. See [index](index.md) to navigate.*