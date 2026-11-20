# Community 12

> 40 nodes · cohesion 0.08

## Key Concepts

- **VarIntTest** (21 connections) — `tests/unit/protocol/VarIntTest.php`
- **.writeVarInt()** (15 connections) — `src/PickaxeMP/protocol/binary/BinaryWriter.php`
- **.writeVarLong()** (9 connections) — `src/PickaxeMP/protocol/binary/BinaryWriter.php`
- **VarInt** (9 connections) — `src/PickaxeMP/protocol/binary/VarInt.php`
- **.decodeGroups()** (9 connections) — `src/PickaxeMP/protocol/binary/VarInt.php`
- **.isAtEnd()** (7 connections) — `src/PickaxeMP/protocol/binary/BinaryReader.php`
- **.zigzagEncode32()** (7 connections) — `src/PickaxeMP/protocol/binary/VarInt.php`
- **.testVarIntRoundTripOnRandomValues()** (7 connections) — `tests/unit/protocol/BinaryFuzzTest.php`
- **BinaryRoundTripTest.php** (7 connections) — `tests/unit/protocol/BinaryRoundTripTest.php`
- **VarIntTest.php** (7 connections) — `tests/unit/protocol/VarIntTest.php`
- **.testVarIntRoundTrip()** (7 connections) — `tests/unit/protocol/VarIntTest.php`
- **.testVarLongRoundTrip()** (7 connections) — `tests/unit/protocol/VarIntTest.php`
- **.length()** (6 connections) — `src/PickaxeMP/protocol/binary/BinaryWriter.php`
- **.encodeGroups()** (6 connections) — `src/PickaxeMP/protocol/binary/VarInt.php`
- **.testStringLengthIsByteCountNotCharCount()** (6 connections) — `tests/unit/protocol/BinaryRoundTripTest.php`
- **.testDocumentedExampleFor64()** (6 connections) — `tests/unit/protocol/VarIntTest.php`
- **.testDocumentedExampleForUnsigned255()** (6 connections) — `tests/unit/protocol/VarIntTest.php`
- **ServerConfigTest.php** (6 connections) — `tests/unit/ServerConfigTest.php`
- **.writeVarULong()** (5 connections) — `src/PickaxeMP/protocol/binary/BinaryWriter.php`
- **.testVarLongRoundTripOnRandomValues()** (5 connections) — `tests/unit/protocol/BinaryFuzzTest.php`
- **.testZigZagZeroIsZero()** (5 connections) — `tests/unit/protocol/VarIntTest.php`
- **.testVarIntMaxEncodesInFiveBytes()** (4 connections) — `tests/unit/protocol/VarIntTest.php`
- **.testVarIntMinEncodesInFiveBytes()** (4 connections) — `tests/unit/protocol/VarIntTest.php`
- **.testVarLongMaxEncodesInTenBytes()** (4 connections) — `tests/unit/protocol/VarIntTest.php`
- **PHPUnit\Framework\Attributes\DataProvider** (3 connections)
- *... and 15 more nodes in this community*

## Relationships

- [Community 3](Community_3.md) (42 shared connections)
- [Community 2](Community_2.md) (27 shared connections)
- [Community 0](Community_0.md) (5 shared connections)
- [Community 25](Community_25.md) (4 shared connections)
- [Community 58](Community_58.md) (3 shared connections)
- [Community 1](Community_1.md) (3 shared connections)
- [Community 29](Community_29.md) (2 shared connections)
- [Community 37](Community_37.md) (1 shared connections)

## Source Files

- `src/PickaxeMP/protocol/binary/BinaryReader.php`
- `src/PickaxeMP/protocol/binary/BinaryWriter.php`
- `src/PickaxeMP/protocol/binary/VarInt.php`
- `tests/unit/ServerConfigTest.php`
- `tests/unit/protocol/BinaryFuzzTest.php`
- `tests/unit/protocol/BinaryRoundTripTest.php`
- `tests/unit/protocol/VarIntTest.php`

## Audit Trail

- EXTRACTED: 106 (72%)
- INFERRED: 42 (28%)
- AMBIGUOUS: 0 (0%)

---

*Part of the graphify knowledge wiki. See [index](index.md) to navigate.*