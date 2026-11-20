# Graph Report - PickaxeMP  (2026-11-20)

## Corpus Check
- 630 files · ~269,736 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 12 file(s) not represented in the graph (top: (none) 5, .cmd 3, . PHP_EOL; } } foreach ($j[hyperedges] as $h) { foreach ($h[nodes] as $n) { if (!isset($set[$n])) { $bad++; echo dangling 1)

## Summary
- 5100 nodes · 15031 edges · 386 communities (76 shown, 310 thin omitted)
- Extraction: 83% EXTRACTED · 17% INFERRED · 0% AMBIGUOUS · INFERRED: 2544 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `f457b54c`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- CompressionManager
- Config
- PlayerAttributesPacketTest
- .consume
- SidecarMessageTest
- ItemTable
- ChunkStreamer
- WorldPacketTest
- message.rs
- server.rs
- SubChunk
- RemoveActorPacket
- PHPUnit\Framework\TestCase
- .getChunk
- Entity
- .parse
- PacketContext
- nether/mod.rs
- RequestNetworkSettingsPacket
- frame.rs
- AesCtrCipher
- PacketCodecTest
- GameplayLoopTest
- PlayerActionType
- .serialize
- SidecarProcess
- WorldStreamPacketTest
- ConnectionInterface
- ReloadManager
- .generate
- JwtAlgorithm
- ActorEventType
- rust
- FullContainerName
- PieceType
- steps
- ModuleInterface
- composer.json
- SessionManagerTest
- .fromConnectionRequest
- Scheduler
- SidecarIntegrationTest
- TransportInterface
- DependencyException
- Server
- .from_args
- PacketInterface
- BinaryReader
- ModuleRegistry
- ProtocolHandshake
- ConnectionPipelineTest
- .writeVarUInt
- ItemEntry
- .decode
- BlockRegistry
- SidecarTransport
- ref-NetworkSession.php
- PlayerIdentityFactory
- main.rs
- .fromString
- VarIntTest
- BlockPosition
- AuthException
- PlayerMetadataTest
- EcdhKeyExchange
- Vec3
- GameType
- FoodTable
- NetworkSession
- signaling.rs
- FakeTransport
- item_stack.go
- LoginRequestFixture
- php.sh
- CommandMap.php
- EventDispatcher.php
- HandshakePacketHandler
- RakNetListener.php
- GameplayTicker
- PluginManager.php
- start.sh script
- composer.sh
- pickaxe-transport
- Inventory
- ItemStack
- ItemRegistry
- PngDecoder
- ConnectionPipeline
- PlayerInventory
- omniroute
- PickaxeMP\world\World
- PlayerAttributes
- IpcSocketInterface
- pocketmine\lang\Translatable
- .decode
- SidecarTransportTest
- MessageError
- EncryptionContext
- Logger
- .createProof
- .flushGamePacketQueue
- Session
- Uuid
- .__construct
- Player
- BlockPos
- .health
- SessionManager
- NbtWriter
- .handle
- .deriveEncryptionKey
- .fromConfig
- JwtParser
- BlockActionManagerTest
- StartGame
- .invalidKey
- .fromArray
- InventoryEntityPacketTest
- .setHandler
- FoodNutrition
- PngFixture
- FakeConnection
- AuthException
- PlayerException
- SidecarProcessInterface
- gopher_item_descriptor.go
- PickaxeException
- Vec2
- gopher_item.go
- ExperimentToggle
- BinaryWriter
- SetHealthPacket
- SkinImage
- GameMode
- .varIntOverflow
- SerializedSkin
- StackResponseContainerInfo
- PlayerSnapshotTest
- StackRequestAction
- ItemTypeIds

## God Nodes (most connected - your core abstractions)
1. `BinaryWriter` - 331 edges
2. `BinaryReader` - 315 edges
3. `ItemStack` - 183 edges
4. `PacketInterface` - 152 edges
5. `Player` - 148 edges
6. `Logger` - 139 edges
7. `World` - 117 edges
8. `ProtocolException` - 116 edges
9. `NetworkSession` - 106 edges
10. `Vec3` - 93 edges

## Surprising Connections (you probably didn't know these)
- `{closure#3}()` --references--> `ActorEventPacket`  [EXTRACTED]
  tests/integration/ConnectionPipelineTest.php → src/PickaxeMP/protocol/packet/packets/ActorEventPacket.php
- `HandshakePacketHandler` --inherits--> `PacketHandler`  [EXTRACTED]
  .team/ref-HandshakePacketHandler.php → src/PickaxeMP/network/PacketHandler.php
- `NetworkSession` --references--> `PacketBroadcaster`  [EXTRACTED]
  .team/ref-NetworkSession.php → src/PickaxeMP/connection/PacketBroadcaster.php
- `EcdsaSignatureTest` --references--> `JwtAlgorithm`  [EXTRACTED]
  tests/unit/auth/EcdsaSignatureTest.php → src/PickaxeMP/auth/jwt/JwtAlgorithm.php
- `JwtParserTest` --references--> `JwtParser`  [EXTRACTED]
  tests/unit/auth/JwtParserTest.php → src/PickaxeMP/auth/jwt/JwtParser.php

## Import Cycles
- None detected.

## Communities (386 total, 310 thin omitted)

### Community 0 - "CompressionManager"
Cohesion: 0.06
Nodes (10): CompressionAlgorithm, None, Snappy, Zlib, CompressionManager, CompressionSettings, ZlibCompressor, PacketBatch (+2 more)

### Community 1 - "Config"
Cohesion: 0.11
Nodes (3): ConfigException, Config, ServerConfig

### Community 3 - "PlayerAttributesPacketTest"
Cohesion: 0.07
Nodes (10): PlayerAbilities, HandshakeException, UpdateAbilitiesPacket, UpdateAttributesPacket, AbilityLayer, Attribute, AttributeModifier, SerializedAbilitiesData (+2 more)

### Community 5 - "SidecarMessageTest"
Cohesion: 0.12
Nodes (3): IpcProtocolException, SidecarMessage, SidecarMessageTest

### Community 8 - "WorldPacketTest"
Cohesion: 0.09
Nodes (12): LevelChunkPacket, SubChunkPacketData, LevelSettings, SpawnSettings, SubChunkRequestResult, IndexOutOfBounds, LevelChunkDoesntExist, PlayerDoesntExist (+4 more)

### Community 9 - "message.rs"
Cohesion: 0.07
Nodes (28): CLOSE_CONNECTION, CONNECTION_CLOSED, CONNECTION_OPEN, connection_open_omits_absent_identity(), connection_open_uses_camel_case(), ConnectionClosed, ConnectionOpen, from_json() (+20 more)

### Community 10 - "server.rs"
Cohesion: 0.09
Nodes (39): Command, Close, SendPayload, generate_nonce(), generated_nonce_is_canonical_32_bytes(), handle_frame(), handshake_accepts_correct_proof_and_reads_payload(), handshake_hello_omits_token_and_contains_canonical_nonce() (+31 more)

### Community 11 - "SubChunk"
Cohesion: 0.05
Nodes (5): Chunk, SubChunk, SubChunkSerializer, WorldException, ChunkTest

### Community 12 - "RemoveActorPacket"
Cohesion: 0.20
Nodes (4): {closure#1}(), ItemPickupResult, GameplayTickResult, RemoveActorPacket

### Community 13 - "PHPUnit\Framework\TestCase"
Cohesion: 0.07
Nodes (12): IdentityType, Offline, XboxLive, PlayerIdentity, BlockActionManager, BlockRegistry, FlatGenerator, FileWorldStorage (+4 more)

### Community 15 - "Entity"
Cohesion: 0.05
Nodes (7): Entity, {closure#1}(), {closure#2}(), EntityManager, EntityTracker, EntityTest, TrackerVisibilityTest

### Community 16 - ".parse"
Cohesion: 0.10
Nodes (3): Base64UrlTest, JwtParserTest, JwtVerifierTest

### Community 17 - "PacketContext"
Cohesion: 0.06
Nodes (12): PacketContext, PacketDirection, Both, ClientToServer, ServerToClient, PacketRegistry, MovePlayerPacket, ProtocolEngine (+4 more)

### Community 18 - "nether/mod.rs"
Cohesion: 0.08
Nodes (14): connection_ids_are_unique_and_bounded(), generate_connection_id(), generate_network_id(), NetherError, Identity, Inner, Jwks, Tls (+6 more)

### Community 19 - "RequestNetworkSettingsPacket"
Cohesion: 0.14
Nodes (5): LoginPacket, RequestNetworkSettingsPacket, __construct(), ProtocolPipelineTest, HandshakeTest

### Community 21 - "frame.rs"
Cohesion: 0.14
Nodes (21): binary_payload_survives(), Frame, FrameError, BadMagic, EncodePayloadTooLarge, PayloadTooLarge, UnsupportedVersion, HEADER_SIZE (+13 more)

### Community 22 - "AesCtrCipher"
Cohesion: 0.11
Nodes (3): AesCtrCipher, EncryptionTest, HandshakeEncryptionIntegrationTest

### Community 23 - "PacketCodecTest"
Cohesion: 0.06
Nodes (13): NetworkSettingsPacket, PlayStatusPacket, ResourcePacksInfoPacket, ResourcePackStackPacket, PackIdVersion, PackInfoData, PackInstanceId, ResourcePackResponse (+5 more)

### Community 24 - "GameplayLoopTest"
Cohesion: 0.08
Nodes (4): BlockActionResult, BlockChange, BlockSound, GameplayLoopTest

### Community 25 - "PlayerActionType"
Cohesion: 0.04
Nodes (37): PlayerActionType, AbortDestroyBlock, ChangeDimensionAck, ContinueDestroyBlock, Count, CrackBlock, CreativeDestroyBlock, DenyDestroyBlock (+29 more)

### Community 27 - ".serialize"
Cohesion: 0.17
Nodes (3): SkinSerializer, SkinData, SkinSerializerTest

### Community 30 - "ConnectionInterface"
Cohesion: 0.07
Nodes (13): ConnectionInterface, SidecarConnection, TransportListenerInterface, onConnectionClosed(), onConnectionOpen(), onConnectionClosed(), onConnectionOpen(), onPayload() (+5 more)

### Community 31 - "ReloadManager"
Cohesion: 0.06
Nodes (14): ReloadableInterface, ReloadManager, ReloadPlan, {closure#1}(), ReloadReport, ReloadRequest, ReloadResult, ReloadStatus (+6 more)

### Community 33 - "JwtAlgorithm"
Cohesion: 0.10
Nodes (9): JwtAlgorithm, ES256, ES384, RS256, RS384, JwtHeader, OpenSslJwtVerifier, SignatureVerifierInterface (+1 more)

### Community 34 - "ActorEventType"
Cohesion: 0.03
Nodes (62): ActorEventType, ActorGrowUp, AgentSwingArm, AirSupply, BabyAge, BalloonPop, CaravanUpdated, Death (+54 more)

### Community 35 - "rust"
Cohesion: 0.08
Nodes (25): run, jobs, rust, os, name, on, pull_request, push (+17 more)

### Community 36 - "FullContainerName"
Cohesion: 0.07
Nodes (8): FullContainerName, ItemStackRequest, ItemStackRequestAction, ItemStackRequestSlotInfo, ItemStackResponseContainerInfo, ItemStackResponseSlotInfo, UnsupportedPacketFieldException, ItemStackRequestTest

### Community 38 - "PieceType"
Cohesion: 0.06
Nodes (31): PieceType, Arms, Back, Body, Bottom, Capes, ClassicSkin, CoCo (+23 more)

### Community 39 - "steps"
Cohesion: 0.08
Nodes (24): OPENSSL_CONF, jobs, lint-and-test, name, runs-on, steps, strategy, os (+16 more)

### Community 40 - "ModuleInterface"
Cohesion: 0.11
Nodes (3): LifecyclePolicy, ModuleInterface, PlainModule

### Community 41 - "composer.json"
Cohesion: 0.08
Nodes (23): autoload, autoload-dev, psr-4, psr-4, config, allow-plugins, sort-packages, description (+15 more)

### Community 44 - "Scheduler"
Cohesion: 0.13
Nodes (5): SchedulerException, {closure#1}(), Scheduler, Task, SchedulerTest

### Community 47 - "DependencyException"
Cohesion: 0.13
Nodes (4): DependencyException, DependencyGraph, {closure#1}(), {closure#2}()

### Community 48 - "Server"
Cohesion: 0.08
Nodes (4): LifecycleException, ServerKernel, Server, ServerBootTest

### Community 49 - ".from_args"
Cohesion: 0.17
Nodes (14): Config, ConfigError, IncompleteTls, InvalidValue, MissingValue, UnknownArgument, defaults_are_valid(), media_can_be_disabled() (+6 more)

### Community 50 - "PacketInterface"
Cohesion: 0.04
Nodes (36): {closure#10}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}() (+28 more)

### Community 52 - "ModuleRegistry"
Cohesion: 0.10
Nodes (7): ModuleRegistry, FakeModule, DependencyGraphTest, ReloadManagerTest, {closure#1}(), ServerKernelTest, shutdown()

### Community 53 - "ProtocolHandshake"
Cohesion: 0.08
Nodes (21): ConnectionState, AwaitingAuthentication, AwaitingLogin, AwaitingRequestNetworkSettings, AwaitingResourcePacks, Closed, Ready, HandshakeResult (+13 more)

### Community 54 - "ConnectionPipelineTest"
Cohesion: 0.06
Nodes (19): ClientToServerHandshakePacket, InventoryTransactionPacket, MobEquipmentPacket, PlayerAuthInputPacket, SetLocalPlayerAsInitializedPacket, HandSlot, MainHand, OffHand (+11 more)

### Community 56 - "ItemEntry"
Cohesion: 0.17
Nodes (6): ItemRegistryPacket, ItemEntry, {closure#1}(), {closure#2}(), {closure#3}(), ItemRegistryPacketTest

### Community 59 - "BlockRegistry"
Cohesion: 0.07
Nodes (4): Fnv1a32, BlockState, BlockType, BlockStateTest

### Community 60 - "SidecarTransport"
Cohesion: 0.12
Nodes (3): TransportException, SidecarTransport, {closure#1}()

### Community 61 - "ref-NetworkSession.php"
Cohesion: 0.02
Nodes (3): {closure#19}(), {closure#3}(), {closure#4}()

### Community 62 - "PlayerIdentityFactory"
Cohesion: 0.08
Nodes (10): AuthenticationEngine, {closure#1}(), AuthenticationMode, Offline, Online, AuthenticationResult, JwtToken, {closure#1}() (+2 more)

### Community 63 - "main.rs"
Cohesion: 0.11
Nodes (9): failed_handshake_does_not_consume_the_ipc_slot(), generate_token(), main(), read_frame(), run(), run_ipc_accept_loop(), token_is_64_hex_chars(), tokens_differ() (+1 more)

### Community 70 - "Vec3"
Cohesion: 0.05
Nodes (13): AddActorPacket, AddItemActorPacket, AddPlayerPacket, MoveActorAbsolutePacket, EntityLink, PassengerOfBlockArguments, PropertySyncData, SyncedAttribute (+5 more)

### Community 71 - "GameType"
Cohesion: 0.20
Nodes (7): GameType, Adventure, Creative, Default, Spectator, Survival, Undefined

### Community 73 - "NetworkSession"
Cohesion: 0.07
Nodes (4): {closure#10}(), {closure#11}(), {closure#6}(), NetworkSession

### Community 74 - "signaling.rs"
Cohesion: 0.62
Nodes (4): http_get(), signaling_status_endpoint_serves_server_data(), spawn_signaling_only(), unknown_path_is_not_server_status()

### Community 76 - "item_stack.go"
Cohesion: 0.09
Nodes (20): BeaconPaymentStackRequestAction, ConsumeStackRequestAction, CraftCreativeStackRequestAction, CraftGrindstoneRecipeStackRequestAction, CraftLoomRecipeStackRequestAction, CraftNonImplementedStackRequestAction, CraftRecipeOptionalStackRequestAction, CraftRecipeStackRequestAction (+12 more)

### Community 90 - "Inventory"
Cohesion: 0.08
Nodes (4): Inventory, InventoryTransaction, AtomicTransactionsTest, InventoryTest

### Community 91 - "ItemStack"
Cohesion: 0.10
Nodes (4): {closure#1}(), ItemStack, FoodConsumptionResult, ItemStackTest

### Community 92 - "ItemRegistry"
Cohesion: 0.10
Nodes (4): Item, {closure#1}(), ItemRegistry, ItemRegistryTest

### Community 93 - "PngDecoder"
Cohesion: 0.17
Nodes (3): PngDecoder, PngImage, SkinException

### Community 97 - "omniroute"
Cohesion: 0.09
Nodes (25): input, output, tools, capabilities, modelID, name, context, output (+17 more)

### Community 101 - "IpcSocketInterface"
Cohesion: 0.09
Nodes (5): IpcSocketInterface, {closure#1}(), StreamIpcSocket, FakeIpcSocket, {closure#1}()

### Community 102 - "pocketmine\lang\Translatable"
Cohesion: 0.16
Nodes (5): {closure#12}(), {closure#14}(), {closure#17}(), {closure#2}(), {closure#9}()

### Community 105 - "MessageError"
Cohesion: 0.16
Nodes (16): Channel, Reliable, Unreliable, close_body_allows_empty_reason(), close_body_round_trip(), CloseBody, MessageError, IdTooLong (+8 more)

### Community 107 - "Logger"
Cohesion: 0.06
Nodes (9): ItemActorManager, MovementManager, PlayerJoinService, PlayerManager, PlayerPositionSync, Logger, ChunkSerializer, PlayerJoinIntegrationTest (+1 more)

### Community 110 - "Session"
Cohesion: 0.05
Nodes (16): PlayerJoin, Session, SessionException, SessionState, Authenticated, Authenticating, AwaitingLogin, AwaitingNetworkSettings (+8 more)

### Community 111 - "Uuid"
Cohesion: 0.07
Nodes (9): Uuid, ActorEventPacket, MoveActorDeltaPacket, PlayerListPacket, StartGamePacket, MoveActorDeltaData, PlayerListEntry, SerializedPersonaPieceHandle (+1 more)

### Community 114 - "Player"
Cohesion: 0.11
Nodes (3): Player, PlayerAuthInput, InventorySlotPacket

### Community 115 - "BlockPos"
Cohesion: 0.05
Nodes (22): ContainerOpenPacket, InteractPacket, NetworkChunkPublisherUpdatePacket, PlayerActionPacket, RespawnPacket, SetSpawnPositionPacket, UpdateBlockPacket, BlockPos (+14 more)

### Community 118 - ".health"
Cohesion: 0.17
Nodes (7): PlayerState, Dead, Disconnected, Joining, Spawned, PlayerSurvival, PlayerSurvivalTest

### Community 119 - "SessionManager"
Cohesion: 0.14
Nodes (3): {closure#1}(), SessionManager, SessionAuthenticatorTest

### Community 149 - "JwtParser"
Cohesion: 0.07
Nodes (8): CertificateValidator, TrustedKeyStore, JwtParser, JwtVerifier, TrustedPublicKey, SkinDataValidator, CryptoFixture, SkinDataValidatorTest

### Community 151 - "StartGame"
Cohesion: 0.08
Nodes (3): ItemRegistry, LevelSoundEvent, StartGame

### Community 166 - "gopher_item_descriptor.go"
Cohesion: 0.16
Nodes (10): AutoCraftRecipeStackRequestAction, DefaultItemDescriptor, InvalidItemDescriptor, ItemDescriptor, ItemDescriptorCount, ItemTagItemDescriptor, MoLangItemDescriptor, itemDescriptorFromType() (+2 more)

### Community 170 - "gopher_item.go"
Cohesion: 0.18
Nodes (11): CraftResultsDeprecatedStackRequestAction, ItemEntry, ItemInstance, ItemStack, ItemType, itemUserData, MaterialReducer, MaterialReducerOutput (+3 more)

### Community 175 - "BinaryWriter"
Cohesion: 0.05
Nodes (13): BinaryWriter, {closure#1}(), {closure#1}(), {closure#1}(), {closure#2}(), {closure#1}(), {closure#1}(), {closure#2}() (+5 more)

### Community 179 - "SkinImage"
Cohesion: 0.13
Nodes (9): AnimatedImageData, AnimatedTextureType, Body128x128, Body32x32, Face, None, AnimationExpression, Blinking (+1 more)

### Community 180 - "GameMode"
Cohesion: 0.12
Nodes (6): GameMode, Adventure, Creative, Spectator, Survival, PlayerAbilitiesTest

### Community 187 - ".varIntOverflow"
Cohesion: 0.10
Nodes (4): ItemStackNetResult, {closure#1}(), ItemStackResponseInfo, LegacySetSlot

### Community 188 - "SerializedSkin"
Cohesion: 0.06
Nodes (12): ArmSize, Slim, Wide, Color, PieceTintColor, SerializedSkin, SkinImage, TintMapColor (+4 more)

### Community 189 - "StackResponseContainerInfo"
Cohesion: 0.25
Nodes (3): ItemStackResponse, StackResponseContainerInfo, StackResponseSlotInfo

### Community 192 - "StackRequestAction"
Cohesion: 0.33
Nodes (4): ItemStackRequest, StackRequestAction, lookupStackRequestAction(), lookupStackRequestActionType()

## Knowledge Gaps
- **240 isolated node(s):** `Online`, `Offline`, `XboxLive`, `Offline`, `ES384` (+235 more)
  These have ≤1 connection - possible missing edges. (Counts symbols only; 1257 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **310 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `BinaryWriter` connect `BinaryWriter` to `CompressionManager`, `PlayerAttributesPacketTest`, `WorldPacketTest`, `RemoveActorPacket`, `PacketContext`, `.readVarUInt`, `PacketCodecTest`, `PlayerActionType`, `InventoryEntityPacketTest`, `.writeByte`, `WorldStreamPacketTest`, `FullContainerName`, `Vec2`, `ExperimentToggle`, `PacketInterface`, `BinaryReader`, `SkinImage`, `ConnectionPipelineTest`, `.writeVarUInt`, `ItemEntry`, `.writeVarInt`, `.varIntOverflow`, `SerializedSkin`, `Vec3`, `Uuid`, `BlockPos`?**
  _High betweenness centrality (0.065) - this node is a cross-community bridge._
- **What connects `Online`, `Offline`, `XboxLive` to the rest of the system?**
  _240 weakly-connected nodes found - possible missing edges._
- **Should `CompressionManager` be split into smaller, more focused modules?**
  _Cohesion score 0.06148088746324512 - nodes in this community are weakly interconnected._
- **Why does `Player` connect `Player` to `.consume`, `PHPUnit\Framework\TestCase`, `BlockActionManagerTest`, `GameplayLoopTest`, `.serialize`, `PlayerException`, `PacketInterface`, `SetHealthPacket`, `GameMode`, `ConnectionPipelineTest`, `PlayerSnapshotTest`, `PlayerMetadataTest`, `GameplayTicker`, `ConnectionPipeline`, `PlayerInventory`, `PickaxeMP\world\World`, `PlayerAttributes`, `pocketmine\lang\Translatable`, `Logger`, `Session`, `Uuid`, `.health`, `.handle`?**
  _High betweenness centrality (0.051) - this node is a cross-community bridge._
- **Should `Config` be split into smaller, more focused modules?**
  _Cohesion score 0.10897435897435898 - nodes in this community are weakly interconnected._
- **Why does `BinaryReader` connect `BinaryReader` to `CompressionManager`, `.lengthTooLarge`, `PlayerAttributesPacketTest`, `WorldPacketTest`, `RemoveActorPacket`, `PacketContext`, `.readVarUInt`, `PacketCodecTest`, `PlayerActionType`, `InventoryEntityPacketTest`, `.writeByte`, `WorldStreamPacketTest`, `.decode`, `FullContainerName`, `Vec2`, `.readByte`, `ExperimentToggle`, `BinaryWriter`, `PacketInterface`, `SkinImage`, `ConnectionPipelineTest`, `ItemEntry`, `.writeVarInt`, `.varIntOverflow`, `SerializedSkin`, `VarIntTest`, `Vec3`, `Uuid`, `BlockPos`?**
  _High betweenness centrality (0.048) - this node is a cross-community bridge._
- **Should `.lengthTooLarge` be split into smaller, more focused modules?**
  _Cohesion score 0.06802721088435375 - nodes in this community are weakly interconnected._