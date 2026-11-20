# Community 17

> 37 nodes · cohesion 0.09

## Key Concepts

- **PacketContext** (20 connections) — `src/PickaxeMP/protocol/packet/PacketContext.php`
- **.decode()** (18 connections) — `src/PickaxeMP/protocol/ProtocolEngine.php`
- **PacketRegistry** (17 connections) — `src/PickaxeMP/protocol/packet/PacketRegistry.php`
- **ProtocolEngine** (17 connections) — `src/PickaxeMP/protocol/ProtocolEngine.php`
- **PacketDirection** (14 connections) — `src/PickaxeMP/protocol/packet/PacketDirection.php`
- **.encode()** (13 connections) — `src/PickaxeMP/protocol/ProtocolEngine.php`
- **.withSupportedPackets()** (9 connections) — `src/PickaxeMP/protocol/packet/PacketRegistry.php`
- **ProtocolEngine.php** (7 connections) — `src/PickaxeMP/protocol/ProtocolEngine.php`
- **.decodeCompressed()** (6 connections) — `src/PickaxeMP/protocol/ProtocolEngine.php`
- **.testCompressedRoundTripThroughEngine()** (6 connections) — `tests/integration/ProtocolPipelineTest.php`
- **.register()** (5 connections) — `src/PickaxeMP/protocol/packet/PacketRegistry.php`
- **.encodeCompressed()** (5 connections) — `src/PickaxeMP/protocol/ProtocolEngine.php`
- **.unknownPacket()** (5 connections) — `src/PickaxeMP/protocol/ProtocolException.php`
- **.testEngineDoesNotDependOnTransport()** (5 connections) — `tests/integration/ProtocolPipelineTest.php`
- **.assertCompatible()** (4 connections) — `src/PickaxeMP/protocol/packet/PacketContext.php`
- **.directionFor()** (4 connections) — `src/PickaxeMP/protocol/packet/PacketRegistry.php`
- **{closure#1}()** (4 connections) — `tests/integration/ProtocolPipelineTest.php`
- **.testRegistryContainsExactlyTheSupportedPackets()** (4 connections) — `tests/unit/protocol/PacketCodecTest.php`
- **.withDirection()** (3 connections) — `src/PickaxeMP/protocol/packet/PacketContext.php`
- **.classFor()** (3 connections) — `src/PickaxeMP/protocol/packet/PacketRegistry.php`
- **.maxSizeFor()** (3 connections) — `src/PickaxeMP/protocol/packet/PacketRegistry.php`
- **.__construct()** (3 connections) — `src/PickaxeMP/protocol/ProtocolEngine.php`
- **.testRegistryRejectsDuplicateId()** (3 connections) — `tests/unit/protocol/PacketCodecTest.php`
- **.__construct()** (2 connections) — `src/PickaxeMP/protocol/packet/PacketContext.php`
- **PacketRegistry.php** (2 connections) — `src/PickaxeMP/protocol/packet/PacketRegistry.php`
- *... and 12 more nodes in this community*

## Relationships

- [Community 0](Community_0.md) (14 shared connections)
- [Community 30](Community_30.md) (8 shared connections)
- [Community 23](Community_23.md) (8 shared connections)
- [Community 8](Community_8.md) (8 shared connections)
- [Community 15](Community_15.md) (7 shared connections)
- [Community 3](Community_3.md) (6 shared connections)
- [Community 14](Community_14.md) (6 shared connections)
- [Community 29](Community_29.md) (4 shared connections)
- [Community 2](Community_2.md) (4 shared connections)
- [Community 61](Community_61.md) (3 shared connections)
- [Community 19](Community_19.md) (2 shared connections)

## Source Files

- `src/PickaxeMP/protocol/ProtocolEngine.php`
- `src/PickaxeMP/protocol/ProtocolException.php`
- `src/PickaxeMP/protocol/packet/PacketContext.php`
- `src/PickaxeMP/protocol/packet/PacketDirection.php`
- `src/PickaxeMP/protocol/packet/PacketRegistry.php`
- `tests/integration/ProtocolPipelineTest.php`
- `tests/unit/protocol/PacketCodecTest.php`
- `tests/unit/world/WorldPacketTest.php`

## Audit Trail

- EXTRACTED: 110 (82%)
- INFERRED: 24 (18%)
- AMBIGUOUS: 0 (0%)

---

*Part of the graphify knowledge wiki. See [index](index.md) to navigate.*