# Community 13

> 40 nodes · cohesion 0.12

## Key Concepts

- **FlatGenerator** (25 connections) — `src/PickaxeMP/world/generator/FlatGenerator.php`
- **FileWorldStorage** (23 connections) — `src/PickaxeMP/world/storage/FileWorldStorage.php`
- **WorldStorageTest** (21 connections) — `tests/unit/world/WorldStorageTest.php`
- **.saveChunk()** (15 connections) — `src/PickaxeMP/world/storage/FileWorldStorage.php`
- **.loadChunk()** (12 connections) — `src/PickaxeMP/world/storage/FileWorldStorage.php`
- **.worldDirectory()** (11 connections) — `src/PickaxeMP/world/storage/FileWorldStorage.php`
- **.saveProperties()** (10 connections) — `src/PickaxeMP/world/storage/FileWorldStorage.php`
- **.loadProperties()** (9 connections) — `src/PickaxeMP/world/storage/FileWorldStorage.php`
- **WorldStorageTest.php** (9 connections) — `tests/unit/world/WorldStorageTest.php`
- **.testChunkRoundTrip()** (8 connections) — `tests/unit/world/WorldStorageTest.php`
- **.testBadMagicIsRejected()** (7 connections) — `tests/unit/world/WorldStorageTest.php`
- **.testChunkRoundTripPreservesAllBlocks()** (7 connections) — `tests/unit/world/WorldStorageTest.php`
- **.testCorruptChunkIsRejected()** (7 connections) — `tests/unit/world/WorldStorageTest.php`
- **.testTruncatedChunkIsRejected()** (7 connections) — `tests/unit/world/WorldStorageTest.php`
- **.chunksDirectory()** (6 connections) — `src/PickaxeMP/world/storage/FileWorldStorage.php`
- **.testListChunks()** (6 connections) — `tests/unit/world/WorldStorageTest.php`
- **.saveFailed()** (5 connections) — `src/PickaxeMP/world/WorldException.php`
- **WorldManager.php** (5 connections) — `src/PickaxeMP/world/WorldManager.php`
- **.__construct()** (5 connections) — `src/PickaxeMP/world/WorldManager.php`
- **.properties()** (5 connections) — `tests/unit/world/WorldStorageTest.php`
- **.testSaveAndLoadProperties()** (5 connections) — `tests/unit/world/WorldStorageTest.php`
- **.testSaveMarksChunkClean()** (5 connections) — `tests/unit/world/WorldStorageTest.php`
- **.ensureDirectory()** (4 connections) — `src/PickaxeMP/world/storage/FileWorldStorage.php`
- **.writeAtomically()** (4 connections) — `src/PickaxeMP/world/storage/FileWorldStorage.php`
- **.removeDirectory()** (4 connections) — `tests/unit/world/WorldStorageTest.php`
- *... and 15 more nodes in this community*

## Relationships

- [Community 33](Community_33.md) (20 shared connections)
- [Community 24](Community_24.md) (17 shared connections)
- [Community 20](Community_20.md) (10 shared connections)
- [Community 56](Community_56.md) (9 shared connections)
- [Community 32](Community_32.md) (9 shared connections)
- [Community 11](Community_11.md) (2 shared connections)
- [Community 27](Community_27.md) (2 shared connections)
- [Community 25](Community_25.md) (2 shared connections)

## Source Files

- `src/PickaxeMP/world/WorldException.php`
- `src/PickaxeMP/world/WorldManager.php`
- `src/PickaxeMP/world/WorldProperties.php`
- `src/PickaxeMP/world/generator/FlatGenerator.php`
- `src/PickaxeMP/world/storage/FileWorldStorage.php`
- `tests/unit/world/FlatGeneratorTest.php`
- `tests/unit/world/WorldStorageTest.php`

## Audit Trail

- EXTRACTED: 127 (75%)
- INFERRED: 42 (25%)
- AMBIGUOUS: 0 (0%)

---

*Part of the graphify knowledge wiki. See [index](index.md) to navigate.*