# Community 3

> 73 nodes · cohesion 0.09

## Key Concepts

- **BinaryWriter** (120 connections) — `src/PickaxeMP/protocol/binary/BinaryWriter.php`
- **.bytes()** (34 connections) — `src/PickaxeMP/protocol/binary/BinaryWriter.php`
- **BinaryRoundTripTest** (34 connections) — `tests/unit/protocol/BinaryRoundTripTest.php`
- **self** (23 connections)
- **.writeVarUInt()** (22 connections) — `src/PickaxeMP/protocol/binary/BinaryWriter.php`
- **.writeString()** (21 connections) — `src/PickaxeMP/protocol/binary/BinaryWriter.php`
- **.encode()** (17 connections) — `src/PickaxeMP/protocol/packet/packets/StartGamePacket.php`
- **.writeBool()** (16 connections) — `src/PickaxeMP/protocol/binary/BinaryWriter.php`
- **.encode()** (14 connections) — `src/PickaxeMP/protocol/packet/types/LevelSettings.php`
- **.writeInt32()** (12 connections) — `src/PickaxeMP/protocol/binary/BinaryWriter.php`
- **.varIntOverflow()** (12 connections) — `src/PickaxeMP/protocol/ProtocolException.php`
- **BinaryFuzzTest** (12 connections) — `tests/unit/protocol/BinaryFuzzTest.php`
- **.writeByte()** (11 connections) — `src/PickaxeMP/protocol/binary/BinaryWriter.php`
- **.count()** (11 connections) — `src/PickaxeMP/protocol/packet/PacketRegistry.php`
- **.testSubChunkRejectsInvalidResultByte()** (11 connections) — `tests/unit/world/WorldPacketTest.php`
- **.wrap()** (10 connections) — `src/PickaxeMP/protocol/PacketBatch.php`
- **.writeFloat()** (9 connections) — `src/PickaxeMP/protocol/binary/BinaryWriter.php`
- **.writeInt32BigEndian()** (9 connections) — `src/PickaxeMP/protocol/binary/BinaryWriter.php`
- **.encode()** (9 connections) — `src/PickaxeMP/protocol/packet/packets/ResourcePacksInfoPacket.php`
- **.encode()** (9 connections) — `src/PickaxeMP/protocol/packet/packets/ResourcePackStackPacket.php`
- **.encode()** (9 connections) — `src/PickaxeMP/protocol/packet/packets/SubChunkPacket.php`
- **.encode()** (9 connections) — `src/PickaxeMP/protocol/packet/packets/SubChunkPacketData.php`
- **.writeUint64()** (8 connections) — `src/PickaxeMP/protocol/binary/BinaryWriter.php`
- **.encode()** (8 connections) — `src/PickaxeMP/protocol/packet/packets/LevelChunkPacket.php`
- **.testLoginRejectsOversizedPayload()** (8 connections) — `tests/unit/protocol/PacketCodecTest.php`
- *... and 48 more nodes in this community*

## Relationships

- [Community 12](Community_12.md) (42 shared connections)
- [Community 2](Community_2.md) (41 shared connections)
- [Community 29](Community_29.md) (26 shared connections)
- [Community 8](Community_8.md) (15 shared connections)
- [Community 0](Community_0.md) (13 shared connections)
- [Community 58](Community_58.md) (9 shared connections)
- [Community 68](Community_68.md) (9 shared connections)
- [Community 17](Community_17.md) (6 shared connections)
- [Community 23](Community_23.md) (5 shared connections)
- [Community 61](Community_61.md) (4 shared connections)
- [Community 53](Community_53.md) (3 shared connections)
- [Community 76](Community_76.md) (3 shared connections)

## Source Files

- `src/PickaxeMP/protocol/PacketBatch.php`
- `src/PickaxeMP/protocol/ProtocolException.php`
- `src/PickaxeMP/protocol/binary/BinaryWriter.php`
- `src/PickaxeMP/protocol/binary/VarInt.php`
- `src/PickaxeMP/protocol/packet/PacketRegistry.php`
- `src/PickaxeMP/protocol/packet/packets/LevelChunkPacket.php`
- `src/PickaxeMP/protocol/packet/packets/LoginPacket.php`
- `src/PickaxeMP/protocol/packet/packets/NetworkSettingsPacket.php`
- `src/PickaxeMP/protocol/packet/packets/PlayStatusPacket.php`
- `src/PickaxeMP/protocol/packet/packets/RequestNetworkSettingsPacket.php`
- `src/PickaxeMP/protocol/packet/packets/ResourcePackClientResponsePacket.php`
- `src/PickaxeMP/protocol/packet/packets/ResourcePackStackPacket.php`
- `src/PickaxeMP/protocol/packet/packets/ResourcePacksInfoPacket.php`
- `src/PickaxeMP/protocol/packet/packets/StartGamePacket.php`
- `src/PickaxeMP/protocol/packet/packets/SubChunkPacket.php`
- `src/PickaxeMP/protocol/packet/packets/SubChunkPacketData.php`
- `src/PickaxeMP/protocol/packet/types/LevelSettings.php`
- `src/PickaxeMP/protocol/packet/types/PackInfoData.php`
- `src/PickaxeMP/protocol/packet/types/SpawnSettings.php`
- `tests/unit/protocol/BinaryFuzzTest.php`

## Audit Trail

- EXTRACTED: 266 (63%)
- INFERRED: 155 (37%)
- AMBIGUOUS: 0 (0%)

---

*Part of the graphify knowledge wiki. See [index](index.md) to navigate.*