# Graph Report - PickaxeMP  (2026-11-20)

## Corpus Check
- 649 files · ~272,905 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 16 file(s) not represented in the graph (top: (none) 5, .cmd 4, .cnf 2)

## Summary
- 5224 nodes · 15267 edges · 384 communities (83 shown, 301 thin omitted)
- Extraction: 83% EXTRACTED · 17% INFERRED · 0% AMBIGUOUS · INFERRED: 2568 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `1ff303f1`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- CompressionManager
- Config
- TrustedPublicKey
- GameMode
- .consume
- SidecarMessageTest
- ConsoleCommandSender
- ChunkStreamer
- LevelChunkPacket
- message.rs
- server.rs
- SubChunk
- ItemEntry
- PHPUnit\Framework\TestCase
- .getChunk
- Entity
- .parse
- PacketContext
- nether/mod.rs
- RequestNetworkSettingsPacket
- .decode
- frame.rs
- AesCtrCipher
- PacketCodecTest
- GameplayLoopTest
- PlayerActionType
- BinaryWriter
- SidecarProcess
- ConsoleManager
- ConnectionPipeline
- ReloadManager
- .generate
- PlayerJoinService.php
- ActorEventType
- rust
- UpdateAttributesPacket
- .boot
- PieceType
- steps
- ModuleInterface
- composer.json
- SessionManagerTest
- .fromConnectionRequest
- Scheduler
- SidecarIntegrationTest
- .invalidState
- DependencyException
- ServerBootTest
- .from_args
- PacketInterface
- BinaryReader
- ModuleRegistry
- ProtocolHandshake
- ConnectionPipelineTest
- ServerKernel
- ItemTable
- SynchedActorDataEntry
- SkinData
- BlockRegistry
- SidecarTransport
- ref-NetworkSession.php
- PlayerIdentityFactory
- main.rs
- InteractAction
- SubChunkRequestResult
- BlockPosition
- AuthException
- FakeModule
- EcdhKeyExchange
- InventoryEntityPacketTest.php
- GameType
- ReloadRequest
- NetworkSession
- signaling.rs
- FakeTransport
- item_stack.go
- LoginRequestFixture
- php.sh
- CommandMap
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
- HelpCommand
- ItemActorPacketTest
- PlayerInventory
- omniroute
- PickaxeMP\world\World
- PlayerAttributes
- IpcSocketInterface
- pocketmine\lang\Translatable
- .decode
- SidecarTransportTest
- ListCommand
- EncryptionContext
- Logger
- .createProof
- .flushGamePacketQueue
- Session
- Vec3
- ReloadConfigCommand
- .__construct
- Player
- BlockPos
- .health
- SessionManager
- StatusCommand
- .inventory
- .deriveEncryptionKey
- VersionCommand
- JwtParser
- BlockActionManagerTest
- StartGame
- .invalidKey
- .fromArray
- .setHandler
- BlockState
- PackInstanceId
- AuthException
- PlayerException
- SidecarTransportTest.php
- gopher_item_descriptor.go
- ipc/mod.rs
- PickaxeException
- Vec2
- gopher_item.go
- ExperimentToggle
- .bytes
- SetHealthPacket
- PlayerMetadataTest
- ProtocolException
- SerializedSkin
- StackResponseContainerInfo
- StackRequestAction
- ItemTypeIds
- ItemActor
- Server.php
- Server
- Uuid
- SidecarConnection

## God Nodes (most connected - your core abstractions)
1. `BinaryWriter` - 331 edges
2. `BinaryReader` - 315 edges
3. `ItemStack` - 183 edges
4. `PacketInterface` - 152 edges
5. `Player` - 148 edges
6. `Logger` - 135 edges
7. `World` - 117 edges
8. `ProtocolException` - 116 edges
9. `NetworkSession` - 106 edges
10. `Vec3` - 93 edges

## Surprising Connections (you probably didn't know these)
- `onConnectionClosed()` --references--> `ConnectionInterface`  [EXTRACTED]
  tests/integration/ProtocolPipelineTest.php → src/PickaxeMP/transport/ConnectionInterface.php
- `onConnectionOpen()` --references--> `ConnectionInterface`  [EXTRACTED]
  tests/integration/ProtocolPipelineTest.php → src/PickaxeMP/transport/ConnectionInterface.php
- `onConnectionClosed()` --references--> `ConnectionInterface`  [EXTRACTED]
  tests/unit/SidecarTransportTest.php → src/PickaxeMP/transport/ConnectionInterface.php
- `onConnectionOpen()` --references--> `ConnectionInterface`  [EXTRACTED]
  tests/unit/SidecarTransportTest.php → src/PickaxeMP/transport/ConnectionInterface.php
- `onPayload()` --references--> `ConnectionInterface`  [EXTRACTED]
  tests/unit/SidecarTransportTest.php → src/PickaxeMP/transport/ConnectionInterface.php

## Import Cycles
- None detected.

## Communities (384 total, 301 thin omitted)

### Community 0 - "CompressionManager"
Cohesion: 0.07
Nodes (10): CompressionAlgorithm, None, Snappy, Zlib, CompressionManager, CompressionSettings, ZlibCompressor, PacketBatch (+2 more)

### Community 1 - "Config"
Cohesion: 0.06
Nodes (4): ConfigException, Config, ConfigTest, ServerConfigTest

### Community 3 - "GameMode"
Cohesion: 0.10
Nodes (7): GameMode, Adventure, Creative, Spectator, Survival, PlayerAbilities, PlayerAbilitiesTest

### Community 5 - "SidecarMessageTest"
Cohesion: 0.12
Nodes (3): IpcProtocolException, SidecarMessage, SidecarMessageTest

### Community 6 - "ConsoleCommandSender"
Cohesion: 0.11
Nodes (4): AbstractCommand, PluginsCommand, StopCommand, ConsoleCommandSender

### Community 8 - "LevelChunkPacket"
Cohesion: 0.18
Nodes (3): LevelChunkPacket, SubChunkPacket, WorldPacketTest

### Community 9 - "message.rs"
Cohesion: 0.07
Nodes (33): Channel, Reliable, Unreliable, close_body_allows_empty_reason(), close_body_round_trip(), CLOSE_CONNECTION, CloseBody, CONNECTION_CLOSED (+25 more)

### Community 10 - "server.rs"
Cohesion: 0.08
Nodes (40): Pong, Command, Close, SendPayload, generate_nonce(), generated_nonce_is_canonical_32_bytes(), handle_frame(), handshake_accepts_correct_proof_and_reads_payload() (+32 more)

### Community 11 - "SubChunk"
Cohesion: 0.05
Nodes (6): Chunk, ChunkSerializer, SubChunk, SubChunkSerializer, WorldException, ChunkTest

### Community 12 - "ItemEntry"
Cohesion: 0.17
Nodes (6): ItemRegistryPacket, ItemEntry, {closure#1}(), {closure#2}(), {closure#3}(), ItemRegistryPacketTest

### Community 13 - "PHPUnit\Framework\TestCase"
Cohesion: 0.07
Nodes (11): IdentityType, Offline, XboxLive, PlayerIdentity, BlockRegistry, FlatGenerator, FileWorldStorage, WorldStorageInterface (+3 more)

### Community 15 - "Entity"
Cohesion: 0.05
Nodes (7): Entity, {closure#1}(), {closure#2}(), EntityManager, EntityTracker, EntityTest, TrackerVisibilityTest

### Community 17 - "PacketContext"
Cohesion: 0.05
Nodes (14): PacketContext, PacketDirection, Both, ClientToServer, ServerToClient, PacketRegistry, MovePlayerPacket, ProtocolEngine (+6 more)

### Community 18 - "nether/mod.rs"
Cohesion: 0.08
Nodes (14): connection_ids_are_unique_and_bounded(), generate_connection_id(), generate_network_id(), NetherError, Identity, Inner, Jwks, Tls (+6 more)

### Community 19 - "RequestNetworkSettingsPacket"
Cohesion: 0.15
Nodes (4): LoginPacket, RequestNetworkSettingsPacket, ProtocolPipelineTest, HandshakeTest

### Community 21 - "frame.rs"
Cohesion: 0.17
Nodes (17): binary_payload_survives(), Frame, FrameError, BadMagic, EncodePayloadTooLarge, PayloadTooLarge, UnsupportedVersion, header_size_is_stable() (+9 more)

### Community 22 - "AesCtrCipher"
Cohesion: 0.10
Nodes (3): AesCtrCipher, EncryptionTest, HandshakeEncryptionIntegrationTest

### Community 23 - "PacketCodecTest"
Cohesion: 0.07
Nodes (11): NetworkSettingsPacket, PlayStatusPacket, ResourcePackClientResponsePacket, ResourcePacksInfoPacket, ResourcePackStackPacket, ResourcePackResponse, Cancel, Downloading (+3 more)

### Community 24 - "GameplayLoopTest"
Cohesion: 0.09
Nodes (4): BlockActionResult, BlockChange, BlockSound, GameplayLoopTest

### Community 25 - "PlayerActionType"
Cohesion: 0.05
Nodes (36): PlayerActionType, AbortDestroyBlock, ChangeDimensionAck, ContinueDestroyBlock, Count, CrackBlock, CreativeDestroyBlock, DenyDestroyBlock (+28 more)

### Community 26 - "BinaryWriter"
Cohesion: 0.05
Nodes (9): BinaryWriter, {closure#1}(), {closure#1}(), {closure#1}(), {closure#2}(), {closure#1}(), {closure#1}(), {closure#2}() (+1 more)

### Community 28 - "SidecarProcess"
Cohesion: 0.10
Nodes (3): TransportException, SidecarProcess, {closure#1}()

### Community 29 - "ConsoleManager"
Cohesion: 0.17
Nodes (3): ConsoleInputReaderInterface, ConsoleManager, StdinConsoleReader

### Community 30 - "ConnectionPipeline"
Cohesion: 0.06
Nodes (20): {closure#1}(), {closure#10}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}() (+12 more)

### Community 31 - "ReloadManager"
Cohesion: 0.10
Nodes (11): ReloadManager, {closure#1}(), ReloadReport, ReloadRequest, ReloadResult, ReloadStatus, Committed, Failed (+3 more)

### Community 33 - "PlayerJoinService.php"
Cohesion: 0.21
Nodes (3): StartGamePacket, LevelSettings, SpawnSettings

### Community 34 - "ActorEventType"
Cohesion: 0.03
Nodes (62): ActorEventType, ActorGrowUp, AgentSwingArm, AirSupply, BabyAge, BalloonPop, CaravanUpdated, Death (+54 more)

### Community 35 - "rust"
Cohesion: 0.08
Nodes (25): run, jobs, rust, os, name, on, pull_request, push (+17 more)

### Community 36 - "UpdateAttributesPacket"
Cohesion: 0.27
Nodes (3): UpdateAttributesPacket, Attribute, PlayerAttributesPacketTest

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

### Community 49 - ".from_args"
Cohesion: 0.18
Nodes (14): Config, ConfigError, IncompleteTls, InvalidValue, MissingValue, UnknownArgument, defaults_are_valid(), media_can_be_disabled() (+6 more)

### Community 50 - "PacketInterface"
Cohesion: 0.06
Nodes (14): PacketId, PacketInterface, {closure#1}(), {closure#2}(), ItemStackRequestPacket, ItemStackResponsePacket, ServerToClientHandshakePacket, {closure#1}() (+6 more)

### Community 52 - "ModuleRegistry"
Cohesion: 0.16
Nodes (4): ModuleRegistry, ReloadManagerTest, ServerKernelTest, shutdown()

### Community 53 - "ProtocolHandshake"
Cohesion: 0.06
Nodes (22): ConnectionState, AwaitingAuthentication, AwaitingLogin, AwaitingRequestNetworkSettings, AwaitingResourcePacks, Closed, Ready, HandshakeException (+14 more)

### Community 54 - "ConnectionPipelineTest"
Cohesion: 0.05
Nodes (23): ItemDescriptorFactory, ClientToServerHandshakePacket, InventoryTransactionPacket, MobEquipmentPacket, PlayerAuthInputPacket, SetLocalPlayerAsInitializedPacket, HandSlot, MainHand (+15 more)

### Community 55 - "ServerKernel"
Cohesion: 0.13
Nodes (4): LifecycleException, ServerKernel, ServerConfig, {closure#1}()

### Community 58 - "SkinData"
Cohesion: 0.06
Nodes (9): PngDecoder, PngImage, SkinException, SkinSerializer, SkinData, PngFixture, PngDecoderTest, SkinSerializerTest (+1 more)

### Community 59 - "BlockRegistry"
Cohesion: 0.10
Nodes (3): Fnv1a32, NbtWriter, BlockStateTest

### Community 61 - "ref-NetworkSession.php"
Cohesion: 0.02
Nodes (3): {closure#19}(), {closure#3}(), {closure#4}()

### Community 62 - "PlayerIdentityFactory"
Cohesion: 0.10
Nodes (7): AuthenticationEngine, AuthenticationMode, Offline, Online, AuthenticationResult, PlayerIdentityFactory, AuthenticationEngineTest

### Community 63 - "main.rs"
Cohesion: 0.11
Nodes (9): failed_handshake_does_not_consume_the_ipc_slot(), generate_token(), main(), read_frame(), run(), run_ipc_accept_loop(), token_is_64_hex_chars(), tokens_differ() (+1 more)

### Community 64 - "InteractAction"
Cohesion: 0.18
Nodes (6): InteractAction, InteractUpdate, Invalid, NpcOpen, OpenInventory, StopRiding

### Community 65 - "SubChunkRequestResult"
Cohesion: 0.22
Nodes (8): SubChunkPacketData, SubChunkRequestResult, IndexOutOfBounds, LevelChunkDoesntExist, PlayerDoesntExist, Success, SuccessAllAir, WrongDimension

### Community 68 - "FakeModule"
Cohesion: 0.14
Nodes (4): FakeModule, {closure#1}(), {closure#2}(), DependencyGraphTest

### Community 70 - "InventoryEntityPacketTest.php"
Cohesion: 0.05
Nodes (15): AddActorPacket, {closure#1}(), AddPlayerPacket, {closure#1}(), ContainerClosePacket, InventoryContentPacket, UpdateAbilitiesPacket, AbilityLayer (+7 more)

### Community 71 - "GameType"
Cohesion: 0.20
Nodes (7): GameType, Adventure, Creative, Default, Spectator, Survival, Undefined

### Community 72 - "ReloadRequest"
Cohesion: 0.13
Nodes (3): ReloadableInterface, ReloadPlan, SaveableInterface

### Community 73 - "NetworkSession"
Cohesion: 0.07
Nodes (4): {closure#10}(), {closure#11}(), {closure#6}(), NetworkSession

### Community 74 - "signaling.rs"
Cohesion: 0.62
Nodes (4): http_get(), signaling_status_endpoint_serves_server_data(), spawn_signaling_only(), unknown_path_is_not_server_status()

### Community 75 - "FakeTransport"
Cohesion: 0.06
Nodes (5): TransportInterface, TransportStats, FakeConnection, FakeTransport, TransportContractTest

### Community 76 - "item_stack.go"
Cohesion: 0.09
Nodes (20): BeaconPaymentStackRequestAction, ConsumeStackRequestAction, CraftCreativeStackRequestAction, CraftGrindstoneRecipeStackRequestAction, CraftLoomRecipeStackRequestAction, CraftNonImplementedStackRequestAction, CraftRecipeOptionalStackRequestAction, CraftRecipeStackRequestAction (+12 more)

### Community 79 - "CommandMap"
Cohesion: 0.13
Nodes (3): CommandInterface, CommandMap, ConsoleCommandDispatcher

### Community 90 - "Inventory"
Cohesion: 0.09
Nodes (3): Inventory, AtomicTransactionsTest, InventoryTest

### Community 91 - "ItemStack"
Cohesion: 0.12
Nodes (3): {closure#1}(), ItemStack, ItemStackTest

### Community 92 - "ItemRegistry"
Cohesion: 0.11
Nodes (3): Item, {closure#1}(), ItemRegistryTest

### Community 94 - "ItemActorPacketTest"
Cohesion: 0.18
Nodes (3): AddItemActorPacket, TakeItemActorPacket, ItemActorPacketTest

### Community 97 - "omniroute"
Cohesion: 0.09
Nodes (25): input, output, tools, capabilities, modelID, name, context, output (+17 more)

### Community 100 - "PlayerAttributes"
Cohesion: 0.07
Nodes (6): FoodConsumptionResult, FoodNutrition, FoodTable, PlayerAttributes, FoodTableTest, PlayerAttributesTest

### Community 101 - "IpcSocketInterface"
Cohesion: 0.13
Nodes (3): IpcSocketInterface, {closure#1}(), StreamIpcSocket

### Community 102 - "pocketmine\lang\Translatable"
Cohesion: 0.16
Nodes (5): {closure#12}(), {closure#14}(), {closure#17}(), {closure#2}(), {closure#9}()

### Community 107 - "Logger"
Cohesion: 0.08
Nodes (11): ItemRegistry, BlockActionManager, FoodConsumptionManager, MovementManager, PlayerAuthInput, PlayerJoinService, PlayerPositionSync, SessionAuthenticator (+3 more)

### Community 110 - "Session"
Cohesion: 0.05
Nodes (19): PlayerJoin, PlayerManager, Session, SessionException, {closure#1}(), SessionState, Authenticated, Authenticating (+11 more)

### Community 111 - "Vec3"
Cohesion: 0.05
Nodes (15): ItemPickupResult, PlayerSnapshot, ActorEventPacket, LevelSoundEventPacket, MoveActorAbsolutePacket, MoveActorDeltaPacket, PlayerListPacket, RemoveActorPacket (+7 more)

### Community 115 - "BlockPos"
Cohesion: 0.06
Nodes (17): ContainerOpenPacket, InteractPacket, NetworkChunkPublisherUpdatePacket, PlayerActionPacket, RespawnPacket, SetHealthPacket, SetSpawnPositionPacket, UpdateBlockPacket (+9 more)

### Community 118 - ".health"
Cohesion: 0.16
Nodes (7): PlayerState, Dead, Disconnected, Joining, Spawned, PlayerSurvival, PlayerSurvivalTest

### Community 121 - ".inventory"
Cohesion: 0.07
Nodes (12): ItemStackRequestFailure, ItemStackRequestManager, FullContainerName, ItemStackNetResult, ItemStackRequestAction, ItemStackRequestSlotInfo, ItemStackResponseContainerInfo, ItemStackResponseInfo (+4 more)

### Community 149 - "JwtParser"
Cohesion: 0.10
Nodes (10): {closure#1}(), CertificateValidator, TrustedKeyStore, JwtParser, JwtToken, JwtVerifier, {closure#1}(), SkinDataValidator (+2 more)

### Community 151 - "StartGame"
Cohesion: 0.08
Nodes (3): ItemRegistry, LevelSoundEvent, StartGame

### Community 152 - ".invalidKey"
Cohesion: 0.09
Nodes (10): EcdsaSignature, JwtAlgorithm, ES256, ES384, RS256, RS384, OpenSslJwtVerifier, SignatureVerifierInterface (+2 more)

### Community 163 - "SidecarTransportTest.php"
Cohesion: 0.10
Nodes (7): FakeIpcSocket, FakeSidecarProcess, {closure#1}(), __construct(), onConnectionClosed(), onConnectionOpen(), onPayload()

### Community 166 - "gopher_item_descriptor.go"
Cohesion: 0.16
Nodes (10): AutoCraftRecipeStackRequestAction, DefaultItemDescriptor, InvalidItemDescriptor, ItemDescriptor, ItemDescriptorCount, ItemTagItemDescriptor, MoLangItemDescriptor, itemDescriptorFromType() (+2 more)

### Community 167 - "ipc/mod.rs"
Cohesion: 0.14
Nodes (14): HEADER_SIZE, MAGIC, MAX_PAYLOAD, VERSION, ConnectionClosed, ConnectionOpen, Hello, HelloAck (+6 more)

### Community 170 - "gopher_item.go"
Cohesion: 0.18
Nodes (11): CraftResultsDeprecatedStackRequestAction, ItemEntry, ItemInstance, ItemStack, ItemType, itemUserData, MaterialReducer, MaterialReducerOutput (+3 more)

### Community 175 - ".bytes"
Cohesion: 0.04
Nodes (6): ChunkRadiusUpdatedPacket, RequestChunkRadiusPacket, ChunkPos, BinaryFuzzTest, BinaryRoundTripTest, WorldStreamPacketTest

### Community 187 - "ProtocolException"
Cohesion: 0.04
Nodes (5): VarInt, AttributeModifier, LegacySetSlot, ProtocolException, VarIntTest

### Community 188 - "SerializedSkin"
Cohesion: 0.04
Nodes (21): AnimatedImageData, AnimatedTextureType, Body128x128, Body32x32, Face, None, AnimationExpression, Blinking (+13 more)

### Community 189 - "StackResponseContainerInfo"
Cohesion: 0.25
Nodes (3): ItemStackResponse, StackResponseContainerInfo, StackResponseSlotInfo

### Community 192 - "StackRequestAction"
Cohesion: 0.33
Nodes (4): ItemStackRequest, StackRequestAction, lookupStackRequestAction(), lookupStackRequestActionType()

### Community 388 - "Uuid"
Cohesion: 0.15
Nodes (4): Uuid, PackIdVersion, PackInfoData, SerializedPersonaPieceHandle

## Knowledge Gaps
- **240 isolated node(s):** `Close`, `SendPayload`, `Frame`, `HandshakeTimeout`, `Io` (+235 more)
  These have ≤1 connection - possible missing edges. (Counts symbols only; 1300 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **301 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `NetworkSession` connect `NetworkSession` to `pocketmine\lang\Translatable`, `EncryptionContext`, `.flushGamePacketQueue`, `.__construct`, `.sendDataPacketInternal`, `.handleEncoded`, `.setHandler`, `ref-NetworkSession.php`?**
  _High betweenness centrality (0.073) - this node is a cross-community bridge._
- **What connects `Close`, `SendPayload`, `Frame` to the rest of the system?**
  _240 weakly-connected nodes found - possible missing edges._
- **Should `CompressionManager` be split into smaller, more focused modules?**
  _Cohesion score 0.07056962025316456 - nodes in this community are weakly interconnected._
- **Why does `Logger` connect `Logger` to `.consume`, `PHPUnit\Framework\TestCase`, `Entity`, `JwtParser`, `GameplayLoopTest`, `ConnectionPipeline`, `ReloadManager`, `PlayerJoinService.php`, `PlayerException`, `SessionManagerTest`, `Scheduler`, `ServerBootTest`, `SetHealthPacket`, `PacketInterface`, `ModuleRegistry`, `ProtocolHandshake`, `ConnectionPipelineTest`, `ServerKernel`, `ItemTable`, `PlayerIdentityFactory`, `GameplayTicker`, `Inventory`, `PlayerInventory`, `PickaxeMP\world\World`, `Session`, `Vec3`, `.__construct`, `Server`, `SessionManager`, `.inventory`?**
  _High betweenness centrality (0.069) - this node is a cross-community bridge._
- **Should `Config` be split into smaller, more focused modules?**
  _Cohesion score 0.06018018018018018 - nodes in this community are weakly interconnected._
- **Why does `Player` connect `Player` to `GameMode`, `.consume`, `PHPUnit\Framework\TestCase`, `.getChunk`, `BlockActionManagerTest`, `GameplayLoopTest`, `ConnectionPipeline`, `PlayerJoinService.php`, `PlayerException`, `SetHealthPacket`, `PacketInterface`, `PlayerMetadataTest`, `ConnectionPipelineTest`, `ServerKernel`, `SkinData`, `GameplayTicker`, `PlayerInventory`, `PickaxeMP\world\World`, `PlayerAttributes`, `pocketmine\lang\Translatable`, `Logger`, `Session`, `Vec3`, `.health`, `.inventory`?**
  _High betweenness centrality (0.049) - this node is a cross-community bridge._
- **Should `TrustedPublicKey` be split into smaller, more focused modules?**
  _Cohesion score 0.14035087719298245 - nodes in this community are weakly interconnected._