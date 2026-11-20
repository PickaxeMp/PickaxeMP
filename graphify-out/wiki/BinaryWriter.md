# BinaryWriter

> God node · 120 connections · `src/PickaxeMP/protocol/binary/BinaryWriter.php`

**Community:** [Community 3](Community_3.md)

## Connections by Relation

### calls
- .testMixedStreamRoundTrip() `EXTRACTED`
- .testEveryPrefixOfAPacketFailsOrIsShorter() `EXTRACTED`
- .encode() `EXTRACTED`
- .testSubChunkRejectsInvalidResultByte() `INFERRED`
- .wrap() `EXTRACTED`
- .testLoginRejectsOversizedPayload() `EXTRACTED`
- .testUuidRoundTripOnRandomValues() `EXTRACTED`
- .testVarIntRoundTripOnRandomValues() `EXTRACTED`
- .testStringRejectsOversizedLength() `EXTRACTED`
- .testUuidRoundTrip() `EXTRACTED`
- .testVarIntRoundTrip() `EXTRACTED`
- .testVarLongRoundTrip() `EXTRACTED`
- .testBoolRoundTrip() `EXTRACTED`
- .testStringLengthIsByteCountNotCharCount() `EXTRACTED`
- .testUintRoundTrips() `EXTRACTED`
- .testUuidEmptyIsZero() `EXTRACTED`
- .testDocumentedExampleFor64() `EXTRACTED`
- .testDocumentedExampleForUnsigned255() `EXTRACTED`
- .testInt32RoundTripOnRandomValues() `EXTRACTED`
- .testRandomByteStringsSurviveAsPayload() `EXTRACTED`
- *…and 24 more `calls` connection(s) not listed (lowest-degree first to go)*

### contains
- BinaryWriter.php `EXTRACTED`

### imports
- PacketCodecTest.php `EXTRACTED`
- PlayerAuthInputPacket.php `EXTRACTED`
- ResourcePacksInfoPacket.php `EXTRACTED`
- NetworkSettingsPacket.php `EXTRACTED`
- ResourcePackStackPacket.php `EXTRACTED`
- StartGamePacket.php `EXTRACTED`
- ProtocolEngine.php `EXTRACTED`
- BinaryRoundTripTest.php `EXTRACTED`
- VarIntTest.php `EXTRACTED`
- PlayStatusPacket.php `EXTRACTED`
- RequestNetworkSettingsPacket.php `EXTRACTED`
- ResourcePackClientResponsePacket.php `EXTRACTED`
- SubChunkPacket.php `EXTRACTED`
- BinaryFuzzTest.php `EXTRACTED`
- ClientToServerHandshakePacket.php `EXTRACTED`
- LevelChunkPacket.php `EXTRACTED`
- LoginPacket.php `EXTRACTED`
- ServerToClientHandshakePacket.php `EXTRACTED`
- SubChunkPacketData.php `EXTRACTED`
- PackIdVersion.php `EXTRACTED`
- *…and 8 more `imports` connection(s) not listed (lowest-degree first to go)*

### method
- .bytes() `EXTRACTED`
- .writeVarUInt() `EXTRACTED`
- .writeString() `EXTRACTED`
- .writeBool() `EXTRACTED`
- .writeVarInt() `EXTRACTED`
- .writeInt32() `EXTRACTED`
- .writeByte() `EXTRACTED`
- .writeInt32BigEndian() `EXTRACTED`
- .writeFloat() `EXTRACTED`
- .writeVarLong() `EXTRACTED`
- .writeUint64() `EXTRACTED`
- .writeUuid() `EXTRACTED`
- .writeUint32() `EXTRACTED`
- .writeUint16() `EXTRACTED`
- .length() `EXTRACTED`
- .write() `EXTRACTED`
- .writeSignedByte() `EXTRACTED`
- .writeVarULong() `EXTRACTED`
- .writeDouble() `EXTRACTED`
- .writeOptional() `EXTRACTED`
- *…and 5 more `method` connection(s) not listed (lowest-degree first to go)*

### references
- .encode() `EXTRACTED`
- .encode() `EXTRACTED`
- .encode() `EXTRACTED`
- .encode() `EXTRACTED`
- .encode() `EXTRACTED`
- .encode() `EXTRACTED`
- .encode() `EXTRACTED`
- .encode() `EXTRACTED`
- .encode() `EXTRACTED`
- .encode() `EXTRACTED`
- .encode() `EXTRACTED`
- .encode() `EXTRACTED`
- .encode() `EXTRACTED`
- .encode() `EXTRACTED`
- .encode() `EXTRACTED`
- .encode() `EXTRACTED`
- .encode() `EXTRACTED`
- .encode() `EXTRACTED`
- .encode() `EXTRACTED`
- .encode() `EXTRACTED`
- *…and 2 more `references` connection(s) not listed (lowest-degree first to go)*

---

*Part of the graphify knowledge wiki. See [index](index.md) to navigate.*