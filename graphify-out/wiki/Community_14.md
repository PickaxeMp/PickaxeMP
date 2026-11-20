# Community 14

> 39 nodes · cohesion 0.11

## Key Concepts

- **FakeTransport** (23 connections) — `tests/support/FakeTransport.php`
- **TransportContractTest** (19 connections) — `tests/unit/TransportContractTest.php`
- **ProtocolPipelineTest** (16 connections) — `tests/integration/ProtocolPipelineTest.php`
- **.setUp()** (11 connections) — `tests/integration/ProtocolPipelineTest.php`
- **.connect()** (11 connections) — `tests/support/FakeTransport.php`
- **.clientSends()** (7 connections) — `tests/integration/ProtocolPipelineTest.php`
- **.getStats()** (6 connections) — `tests/support/FakeTransport.php`
- **.injectPayload()** (6 connections) — `tests/support/FakeTransport.php`
- **.requireConnection()** (6 connections) — `tests/support/FakeTransport.php`
- **.sendReliable()** (5 connections) — `tests/support/FakeTransport.php`
- **.start()** (5 connections) — `tests/support/FakeTransport.php`
- **.setUp()** (5 connections) — `tests/unit/TransportContractTest.php`
- **.unknownConnection()** (4 connections) — `src/PickaxeMP/exception/TransportException.php`
- **.testCorruptPayloadThroughTransportIsRejected()** (4 connections) — `tests/integration/ProtocolPipelineTest.php`
- **.setListener()** (4 connections) — `tests/support/FakeTransport.php`
- **.testCloseNotifiesListenerAndRemovesConnection()** (4 connections) — `tests/unit/TransportContractTest.php`
- **.testSendReliableRecordsPayload()** (4 connections) — `tests/unit/TransportContractTest.php`
- **.testStatsAccumulate()** (4 connections) — `tests/unit/TransportContractTest.php`
- **.getIdentity()** (3 connections) — `src/PickaxeMP/transport/ConnectionInterface.php`
- **.listener()** (3 connections) — `tests/integration/ProtocolPipelineTest.php`
- **.serverPackets()** (3 connections) — `tests/integration/ProtocolPipelineTest.php`
- **.close()** (3 connections) — `tests/support/FakeTransport.php`
- **.getConnections()** (3 connections) — `tests/support/FakeTransport.php`
- **.sendUnreliable()** (3 connections) — `tests/support/FakeTransport.php`
- **.tick()** (3 connections) — `tests/support/FakeTransport.php`
- *... and 14 more nodes in this community*

## Relationships

- [Community 30](Community_30.md) (13 shared connections)
- [Community 17](Community_17.md) (6 shared connections)
- [Community 19](Community_19.md) (6 shared connections)
- [Community 28](Community_28.md) (3 shared connections)
- [Community 15](Community_15.md) (3 shared connections)
- [Community 46](Community_46.md) (3 shared connections)
- [Community 57](Community_57.md) (2 shared connections)
- [Community 25](Community_25.md) (2 shared connections)
- [Community 0](Community_0.md) (2 shared connections)
- [Community 72](Community_72.md) (2 shared connections)

## Source Files

- `src/PickaxeMP/exception/TransportException.php`
- `src/PickaxeMP/transport/ConnectionInterface.php`
- `tests/integration/ProtocolPipelineTest.php`
- `tests/support/FakeTransport.php`
- `tests/unit/TransportContractTest.php`

## Audit Trail

- EXTRACTED: 84 (69%)
- INFERRED: 37 (31%)
- AMBIGUOUS: 0 (0%)

---

*Part of the graphify knowledge wiki. See [index](index.md) to navigate.*