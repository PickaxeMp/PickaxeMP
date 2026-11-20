# Community 19

> 35 nodes · cohesion 0.17

## Key Concepts

- **LoginPacket** (38 connections) — `src/PickaxeMP/protocol/packet/packets/LoginPacket.php`
- **RequestNetworkSettingsPacket** (37 connections) — `src/PickaxeMP/protocol/packet/packets/RequestNetworkSettingsPacket.php`
- **.handle()** (31 connections) — `src/PickaxeMP/protocol/ProtocolHandshake.php`
- **HandshakeTest** (21 connections) — `tests/unit/protocol/HandshakeTest.php`
- **.handshake()** (19 connections) — `tests/unit/protocol/HandshakeTest.php`
- **.completeAuthentication()** (14 connections) — `src/PickaxeMP/protocol/ProtocolHandshake.php`
- **.of()** (8 connections) — `src/PickaxeMP/protocol/packet/packets/ResourcePackClientResponsePacket.php`
- **.testAuthenticationCompletionThenReady()** (8 connections) — `tests/integration/ProtocolPipelineTest.php`
- **.testResourcePackStackFinishedReachesReady()** (8 connections) — `tests/unit/protocol/HandshakeTest.php`
- **.testFullHandshakeStopsBeforeAuthentication()** (7 connections) — `tests/integration/ProtocolPipelineTest.php`
- **.testCompleteAuthenticationReleasesLoginSuccess()** (7 connections) — `tests/unit/protocol/HandshakeTest.php`
- **.testFailAuthenticationClosesConnection()** (7 connections) — `tests/unit/protocol/HandshakeTest.php`
- **.testResourcePackCancelClosesConnection()** (7 connections) — `tests/unit/protocol/HandshakeTest.php`
- **.getState()** (6 connections) — `src/PickaxeMP/protocol/ProtocolHandshake.php`
- **.isReady()** (6 connections) — `src/PickaxeMP/protocol/ProtocolHandshake.php`
- **.testCompleteAuthenticationTwiceThrows()** (6 connections) — `tests/unit/protocol/HandshakeTest.php`
- **.testLoginStopsAtAuthenticationAndDoesNotSucceed()** (6 connections) — `tests/unit/protocol/HandshakeTest.php`
- **.testPacketsDuringAuthenticationAreRejected()** (6 connections) — `tests/unit/protocol/HandshakeTest.php`
- **.testLoginWithBadJwtShapeFails()** (5 connections) — `tests/unit/protocol/HandshakeTest.php`
- **.testLoginWithIncompatibleVersionFails()** (5 connections) — `tests/unit/protocol/HandshakeTest.php`
- **.testNetworkSettingsCarriesServerCompression()** (5 connections) — `tests/unit/protocol/HandshakeTest.php`
- **.testUnexpectedPacketInLoginStateThrows()** (5 connections) — `tests/unit/protocol/HandshakeTest.php`
- **.testClosedStateRejectsFurtherPackets()** (4 connections) — `tests/unit/protocol/HandshakeTest.php`
- **.testIncompatibleProtocolClosesWithPlayStatus()** (4 connections) — `tests/unit/protocol/HandshakeTest.php`
- **.testRequestNetworkSettingsProducesNetworkSettings()** (4 connections) — `tests/unit/protocol/HandshakeTest.php`
- *... and 10 more nodes in this community*

## Relationships

- [Community 15](Community_15.md) (28 shared connections)
- [Community 22](Community_22.md) (11 shared connections)
- [Community 23](Community_23.md) (9 shared connections)
- [Community 53](Community_53.md) (9 shared connections)
- [Community 14](Community_14.md) (6 shared connections)
- [Community 26](Community_26.md) (4 shared connections)
- [Community 30](Community_30.md) (3 shared connections)
- [Community 29](Community_29.md) (3 shared connections)
- [Community 61](Community_61.md) (3 shared connections)
- [Community 50](Community_50.md) (3 shared connections)
- [Community 17](Community_17.md) (2 shared connections)
- [Community 25](Community_25.md) (2 shared connections)

## Source Files

- `src/PickaxeMP/protocol/ProtocolHandshake.php`
- `src/PickaxeMP/protocol/packet/packets/LoginPacket.php`
- `src/PickaxeMP/protocol/packet/packets/RequestNetworkSettingsPacket.php`
- `src/PickaxeMP/protocol/packet/packets/ResourcePackClientResponsePacket.php`
- `tests/integration/ProtocolPipelineTest.php`
- `tests/unit/protocol/HandshakeTest.php`

## Audit Trail

- EXTRACTED: 153 (81%)
- INFERRED: 37 (19%)
- AMBIGUOUS: 0 (0%)

---

*Part of the graphify knowledge wiki. See [index](index.md) to navigate.*