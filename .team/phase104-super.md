The user wants a ruthless code review of Phase 10.4 for PickaxeMP (PHP 8.2, Bedrock 1.26.65 / protocol 2225). They provided detailed scope and questions. I need to produce a markdown file content at `.team/phase104-review.md` with findings table (max 15 findings), unverified section, approved section. Each finding one sentence, concrete, no fluff. Weight definitions: critical = client disconnect or security; medium = wrong behavior but connection stays; low = maintenance/readability.

I am a senior Bedrock protocol + PHP expert. I need to answer based on the provided descriptions, but I don't have actual file contents. I must infer from the descriptions and known Bedrock protocol specs (protocol 2225, Bedrock 1.26.65). I should be careful not to fabricate specific line numbers unless I can infer them, but the user asks for path:satır. Since I don't have the actual files, I should use plausible paths and maybe line numbers as placeholders? The instruction says "path:satır". I can use paths and approximate line numbers or just path if unknown. But better to be honest: since I cannot see the actual code, I should mark unverified where I can't confirm. However, the user wants me to review based on the provided critical decisions. I can still identify likely issues based on protocol knowledge.

Let's analyze each area deeply.

### 1. ItemRegistryPacket (162) + ItemTable

Bedrock protocol details:
- ItemRegistryPacket (0xA2? Actually 162 decimal = 0xA2). It contains entries.
- ItemEntry: Name (string), RuntimeID (int16? signed?), ComponentBased (bool), Version (varint? ZigZag?), NBT (byte array).
- In Bedrock, ItemEntryVersion: None = 0? Let's recall. In Bedrock protocol, ItemEntry has:
  - name: string
  - runtimeId: int16 (signed? usually int16)
  - componentBased: bool
  - version: varint (ItemEntryVersion). Values: None = 0, JSON = 1? Or something. Actually in Bedrock, ItemEntryVersion is an enum: None = 0, JSON = 1? Wait.
  - nbt: byte array (LittleEndianByteArray? or just bytes).

The user says: `Version` (varint32, ZigZag). Is that correct? In Bedrock, varints are not ZigZag typically; they are unsigned varint. However, some fields use ZigZag for signed integers. The user claims `Version` is varint32 ZigZag. Need to compare with gophertunnel `protocol/item_registry.go`.

From gophertunnel (Bedrock protocol library):
ItemRegistryPacket entry:
```
type ItemEntry struct {
    Name             string
    RuntimeID        int16
    ComponentBased   bool
    Version          uint32 // varint
    NBT              []byte
}
```
I think `Version` is just varuint32, not ZigZag. ZigZag is used for signed varints (like in some packets). But `ItemEntryVersion` is an enum, likely unsigned. So claiming ZigZag is likely wrong. That's a protocol incompatibility (medium or critical? Client may reject packet if version decoding is wrong. If they write ZigZag varint but client expects plain varint, the bytes will be misaligned, causing disconnect. So critical.

- `componentBased = false`, `version = 2` (`ItemEntryVersionNone`). Wait, if None is 0, then version=2 is wrong. In Bedrock, ItemEntryVersionNone might be 0. The user says version=2 (`ItemEntryVersionNone`). That seems contradictory. If None is 0, sending 2 is wrong. Need to verify. In gophertunnel, `ItemEntryVersionNone = 0`, `ItemEntryVersionJSON = 1`? Not sure. But sending version=2 when None=0 is likely wrong. Client may ignore or disconnect. Medium/critical.

- Runtime ID signed int16. Bedrock runtime IDs are int16, can be negative (e.g., air = -1? Actually in Bedrock, air runtime id is -1? Or 0? In newer versions, runtime IDs can be negative for special items. The user says air is -158. That seems plausible in 1.26. Signed int16 range -32768 to 32767. 846 and -1125 fit. Future overflow risk if >32767 items. Bedrock has limited runtime IDs. Low/medium.

- 1934 entries * ~43 bytes = ~83 KiB. Packet ceiling 512 KiB. Fits. Client limit? Bedrock clients can handle large packets but there may be a limit. Probably fine. Low.

- `air` removed from in-memory registry but sent in packet. Inconsistent. If an item is compared to air, registry lookup fails. Could cause issues. Medium.

### 2. Gameplay feedback (ActorEvent + LevelSoundEvent)

ActorEventPacket: fields: EntityUniqueID (varint64? or uint64?), EventType (varint?), EventData (varint?). Actually ActorEventPacket in Bedrock:
- EntityUniqueID: uint64 (varint? maybe LittleEndian? In RakNet, it's uint64 big endian? Wait, Bedrock uses LittleEndian for most, but entity unique ID is often written as uint64 (8 bytes) or varint? Let's recall. In Bedrock protocol, ActorEventPacket:
```
type ActorEventPacket struct {
    EntityUniqueID uint64
    EventType      byte // or varint
    EventData      uint32 // varint?
}
```
Actually in gophertunnel:
```
type ActorEventPacket struct {
    EntityUniqueID uint64
    EventType      byte
    EventData      uint32
}
```
It writes EntityUniqueID as uint64 (little endian?), EventType as uint8, EventData as uint32 (little endian?). Not varint64. The user claims `actorUniqueId` is varint64? They ask if it's varint64 or fixed int64. Likely fixed uint64 (8 bytes). If they use varint64, it's wrong. Critical/medium.

LevelSoundEventPacket:
- Sound: string? Or varint? In Bedrock, LevelSoundEventPacket has:
  - Sound: string (or maybe varint for sound ID? Actually it's a string like "random.click"?)
  - Position: Vector3 (3 floats)
  - Volume: float
  - Pitch: float
  - EntityUniqueID: uint64? Or varint?
  - AdditionalData: byte array? Or varint?
Wait, in newer Bedrock, LevelSoundEventPacket changed. Let's recall gophertunnel `level_sound_event.go`:
```
type LevelSoundEventPacket struct {
    Sound             string
    Position          Vector3
    Volume            float32
    Pitch             float32
    EntityUniqueID    uint64
    AdditionalData    []byte // or varint?
}
```
Actually I think `EntityUniqueID` is `uint64` and `AdditionalData` is `varuint32`? Not sure. The user says `data` for `writeVarInt` (ZigZag). That seems suspicious. LevelSoundEvent data is often a varint (sound-specific data). ZigZag? Probably not.

- `break` vs `break.block`: In Bedrock, sound names are like `block.wood.break` or `random.pop`? The user says `break`/`place`. Need to check gophertunnel list. Likely `break` is not a valid sound; it should be `block.<material>.break` or similar. But maybe in 1.26 it's just `break`? Unlikely. Medium.

- Death: only ActorEvent(Death) + LevelSoundEvent('death'); no Hurt. Vanilla sends Hurt on damage, Death on death. Not sending Hurt on fatal damage might be okay, but if damage is non-fatal, send Hurt. The question: is omitting Hurt on death correct? Vanilla might send Hurt then Death? Or just Death. Probably okay. Medium.

- Block sound after UpdateBlock: order matters. If sound before block update, client might play sound for old block. After is correct. Approved maybe.

- Pickup sound sent to both picker and others: could cause double sound for picker if they also receive broadcast. Vanilla likely sends to all nearby including picker? Or only others? If picker gets both direct and broadcast, double sound. Medium.

- Death broadcast in tick() every tick: if `deaths` list not cleared, it will resend every tick, causing spam and possible client disconnect. Critical.

### 3. Real skin delivery (pure PHP PNG decoder)

- PNG decoder: filter implementations. Need to check Paeth. Paeth predictor: p = left + up - upLeft; dist to left, up, upLeft; choose closest. If tie, priority left, up, upLeft. The user asks about `bpp` multiplication and selection. If implemented wrong, decoded image corrupted. Medium.

- CRC validation: all chunks, IDAT concatenation. If not done, security risk (data corruption). Critical if missing.

- Untrusted base64 PNG from /v1/join: ZIP bomb via IDAT large but decompressed small? PNG decompression bomb: small IDAT that decompresses to huge data (e.g., 1x1 pixel with many filter bytes? Actually PNG can have huge dimensions but small compressed data? The decoder limits dimensions to 4096 and output to height*(stride+1). But memory usage during decompression could be high if zlib bomb. PHP's zlib output limit? Not sure. If they don't limit decompressed bytes, memory exhaustion. Critical/security.

- SkinImage length validation: width*height*4. If not, buffer overflow/truncation. Medium.

- geometryData base64 decoded vs sent as base64 text: Bedrock expects `geometryData` as JSON string? Actually in skin packet, geometry data is a JSON string encoded? The user asks. gophertunnel likely sends base64 decoded JSON? Need to check. If wrong, skin geometry not applied. Medium.

- resourcePatch `{"geometry":{"default":...}}`: structure might be wrong. Should be `{"geometry": {"default": "<geometry_name>"}}` or with data? Actually resource patch for skin: `{"geometry":{"default":"humanoid"}}`. If they put base64 or full JSON, wrong. Medium.

### 4. Game mode / ability switching

- GameMode abilities: CREATIVE_ABILITIES = SURVIVAL_ABILITIES | MAY_FLY | INSTANT_BUILD. Vanilla creative also has ATTACK_PLAYERS, ATTACK_MOBS? In Bedrock, PlayerAbilitiesPacket has flags: INVULNERABLE, MAY_FLY, AUTOMATIC_SPAWN, INSTANT_BUILD, MAY_FLY? Wait. Let's recall flags:
  - INVULNERABLE = 0x01
  - MAY_FLY = 0x02
  - AUTOMATIC_SPAWN = 0x04
  - INSTANT_BUILD = 0x08
  - ATTACK_PLAYERS = 0x10? (maybe)
  - ATTACK_MOBS = 0x20?
  - NO_CLIP = 0x40?
  - FLYING = 0x80?
  - WORLD_IMMUTABLE = 0x100?
  - etc.

In creative, players can fly, instant build, attack players/mobs. So CREATIVE_ABILITIES missing ATTACK_PLAYERS/MOBS might be wrong. Medium.

- ADVENTURE_ABILITIES: should not include BUILD/MINE. Should include ATTACK_MOBS? In adventure, players can attack mobs but not players? Actually adventure mode: can't break/place blocks, can attack mobs. So ATTACK_MOBS yes, ATTACK_PLAYERS maybe no. If they omitted ATTACK_MOBS, wrong. Medium.

- SPECTATOR_ABILITIES: NO_CLIP and FLYING together. Spectator flies and no clip. Probably correct.

- Flying disabled when switching to non-flying mode: correct.

- abilityValues (byte values) mod-independent SURVIVAL_VALUES: In Bedrock, PlayerAbilitiesPacket has `abilityValues` (float? or byte?) Actually there are two sets: `flags` (uint32) and `values` (float? maybe for fly speed, walk speed). The user says `abilityValues` (byte values) constant `SURVIVAL_VALUES`. If creative should have different values (e.g., fly speed), using survival values is wrong. Medium.

- AddPlayer `playerGameType` field: varint or fixed int? In Bedrock, AddPlayerPacket has `PlayerGameType` as `varint`? Or `int32`? I think it's `varint` (or `uint32`). Order matters. If wrong, client misparses. Critical/medium.

Now, I need to produce max 15 findings. Each one sentence, concrete. Weight, file, line (approx), finding, suggestion.

Since I don't have actual line numbers, I can use plausible line numbers or just path. But the format requires `path:satır`. I can use `path:?
