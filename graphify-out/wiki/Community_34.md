# Community 34

> 27 nodes · cohesion 0.13

## Key Concepts

- **FakeModule** (46 connections) — `tests/support/FakeModule.php`
- **DependencyGraphTest** (14 connections) — `tests/unit/DependencyGraphTest.php`
- **.fromModules()** (10 connections) — `src/PickaxeMP/kernel/DependencyGraph.php`
- **.inBootOrder()** (8 connections) — `src/PickaxeMP/kernel/ModuleRegistry.php`
- **.record()** (6 connections) — `tests/support/FakeModule.php`
- **.testRegistryBootOrder()** (5 connections) — `tests/unit/DependencyGraphTest.php`
- **.testRegistryOrderCacheIsInvalidatedOnRegister()** (5 connections) — `tests/unit/DependencyGraphTest.php`
- **.saveableInBootOrder()** (4 connections) — `src/PickaxeMP/kernel/ModuleRegistry.php`
- **.testRegistryRejectsDuplicateId()** (4 connections) — `tests/unit/DependencyGraphTest.php`
- **.commitReload()** (3 connections) — `tests/support/FakeModule.php`
- **.rollbackReload()** (3 connections) — `tests/support/FakeModule.php`
- **.testDetectsDirectCycle()** (3 connections) — `tests/unit/DependencyGraphTest.php`
- **.testDetectsMissingDependency()** (3 connections) — `tests/unit/DependencyGraphTest.php`
- **.testDetectsSelfDependency()** (3 connections) — `tests/unit/DependencyGraphTest.php`
- **.testIsDeterministicForIndependentModules()** (3 connections) — `tests/unit/DependencyGraphTest.php`
- **.testResolvesDiamond()** (3 connections) — `tests/unit/DependencyGraphTest.php`
- **.testResolvesLinearChain()** (3 connections) — `tests/unit/DependencyGraphTest.php`
- **.all()** (2 connections) — `src/PickaxeMP/kernel/ModuleRegistry.php`
- **.boot()** (2 connections) — `tests/support/FakeModule.php`
- **.flush()** (2 connections) — `tests/support/FakeModule.php`
- **.shutdown()** (2 connections) — `tests/support/FakeModule.php`
- **.testEmptyGraphResolvesToEmptyList()** (2 connections) — `tests/unit/DependencyGraphTest.php`
- **.testRegistryUnknownModuleThrows()** (2 connections) — `tests/unit/DependencyGraphTest.php`
- **self** (1 connections)
- **.__construct()** (1 connections) — `tests/support/FakeModule.php`
- *... and 2 more nodes in this community*

## Relationships

- [Community 52](Community_52.md) (16 shared connections)
- [Community 60](Community_60.md) (12 shared connections)
- [Community 38](Community_38.md) (8 shared connections)
- [Community 47](Community_47.md) (4 shared connections)
- [Community 40](Community_40.md) (4 shared connections)
- [Community 37](Community_37.md) (2 shared connections)
- [Community 31](Community_31.md) (1 shared connections)
- [Community 25](Community_25.md) (1 shared connections)

## Source Files

- `src/PickaxeMP/kernel/DependencyGraph.php`
- `src/PickaxeMP/kernel/ModuleRegistry.php`
- `tests/support/FakeModule.php`
- `tests/unit/DependencyGraphTest.php`

## Audit Trail

- EXTRACTED: 86 (91%)
- INFERRED: 9 (9%)
- AMBIGUOUS: 0 (0%)

---

*Part of the graphify knowledge wiki. See [index](index.md) to navigate.*