# Graph Report - PickaxeMP  (2026-11-20)

## Corpus Check
- 630 files · ~269,502 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 12 file(s) not represented in the graph (top: (none) 5, .cmd 3, . PHP_EOL; } } foreach ($j[hyperedges] as $h) { foreach ($h[nodes] as $n) { if (!isset($set[$n])) { $bad++; echo dangling 1)

## Summary
- 5096 nodes · 14967 edges · 220 communities (93 shown, 127 thin omitted)
- Extraction: 78% EXTRACTED · 22% INFERRED · 0% AMBIGUOUS · INFERRED: 3243 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `29f8ffd3`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- CompressionManager
- Config
- PlayerAttributesPacketTest
- .consume
- SidecarMessageTest
- ItemEntry
- ChunkStreamer
- WorldPacketTest
- message.rs
- server.rs
- SubChunk
- PickaxeMP\item\ItemStack
- FileWorldStorage
- .getChunk
- EntityManager
- .parse
- PacketInterface
- nether/mod.rs
- RequestNetworkSettingsPacket
- NetworkSettingsPacket
- frame.rs
- AesCtrCipher
- PacketCodecTest
- GameplayLoopTest
- PlayerActionType
- TakeItemActorPacket
- .serialize
- SidecarProcess
- WorldStreamPacketTest
- ConnectionInterface
- ReloadManager
- PlayerAttributesPacketTest.php
- TrackerVisibilityTest
- ActorEventType
- rust
- FullContainerName
- ServerKernel
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
- PickaxeMP\protocol\binary\BinaryWriter
- BinaryReader
- ModuleRegistry
- ConnectionState
- ConnectionPipelineTest
- PlayStatus
- .decode
- Chunk
- .decode
- BlockRegistry
- SidecarTransport
- ref-NetworkSession.php
- PlayerIdentityFactory
- main.rs
- InteractPacket
- ProtocolException
- BlockPosition
- Entity
- PlayerMetadataTest
- ConnectionPipelineTest.php
- SynchedActorDataEntry
- GameType
- FoodNutrition
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
- PHPUnit\Framework\TestCase
- omniroute
- PickaxeMP\protocol\packet\types\Vec3
- PickaxeMP\world\World
- PlayerAttributes
- IpcSocketInterface
- pocketmine\lang\Translatable
- .decode
- SidecarTransportTest
- MovePlayerPacket
- EncryptionContext
- Logger
- .createProof
- .flushGamePacketQueue
- Session
- PlayerListPacketTest
- pocketmine\player\Player
- Player
- GameplayPacketTest
- PlayerJoinIntegrationTest.php
- .health
- SessionManager
- EntityTest
- .handle
- .sendDisconnectPacket
- TrustedPublicKey
- ReloadRequest
- StartGame
- .doVerify
- .fromArray
- InventoryEntityPacketTest
- SynchedActorData
- PlayerListPacketTest.php
- .testUntrustedChainFailsClosedAndClosesConnection
- PngFixture
- .withSupportedPackets
- DependencyGraphTest
- AuthException
- PlayerException
- FakeSidecarProcess
- gopher_item_descriptor.go
- SkinSerializerTest
- PickaxeException
- PlayerAuthInputPacket
- gopher_item.go
- SetHealthPacket
- ItemUseInventoryTransaction
- RespawnState
- ipc/mod.rs
- BinaryWriter
- BlockRegistry
- PickaxeMP\protocol\packet\types\BlockPos
- SetHealthPacket
- SkinImage
- GameMode
- BlockSound
- ProtocolVersion
- SubChunkRequestResult
- ItemStackNetResult
- TintMapColor
- StackResponseContainerInfo
- NetworkItemStackDescriptor
- PlayerSnapshotTest
- StackRequestAction
- BlockChange
- ItemTypeIds
- PlayerBlockAction
- ConnectionPipelineTest
- .tick

## God Nodes (most connected - your core abstractions)
1. `BinaryWriter` - 161 edges
2. `BinaryReader` - 160 edges
3. `ItemStack` - 146 edges
4. `Player` - 120 edges
5. `NetworkSession` - 106 edges
6. `ConnectionPipelineTest` - 83 edges
7. `ActorEventType` - 69 edges
8. `Logger` - 63 edges
9. `ProtocolException` - 61 edges
10. `PacketId` - 60 edges

## Surprising Connections (you probably didn't know these)
- `{closure#1}()` --calls--> `TransportException`  [INFERRED]
  tests/integration/ConnectionPipelineTest.php → src/PickaxeMP/exception/TransportException.php
- `ConnectionPipelineTest` --references--> `ConnectionPipeline`  [EXTRACTED]
  tests/integration/ConnectionPipelineTest.php → src/PickaxeMP/connection/ConnectionPipeline.php
- `ConnectionPipelineTest` --references--> `ItemActorManager`  [EXTRACTED]
  tests/integration/ConnectionPipelineTest.php → src/PickaxeMP/entity/ItemActorManager.php
- `ItemActorManagerTest` --references--> `ItemActorManager`  [EXTRACTED]
  tests/unit/ItemActorManagerTest.php → src/PickaxeMP/entity/ItemActorManager.php
- `SidecarTransportTest` --references--> `SidecarTransport`  [EXTRACTED]
  tests/unit/SidecarTransportTest.php → src/PickaxeMP/transport/sidecar/SidecarTransport.php

## Import Cycles
- None detected.

## Communities (220 total, 127 thin omitted)

### Community 0 - "CompressionManager"
Cohesion: 0.07
Nodes (10): CompressionAlgorithm, None, Snappy, Zlib, CompressionManager, CompressionSettings, ZlibCompressor, PacketBatch (+2 more)

### Community 1 - "Config"
Cohesion: 0.06
Nodes (4): ConfigException, Config, ConfigTest, ServerConfigTest

### Community 3 - "PlayerAttributesPacketTest"
Cohesion: 0.11
Nodes (7): UpdateAbilitiesPacket, UpdateAttributesPacket, AbilityLayer, Attribute, AttributeModifier, SerializedAbilitiesData, PlayerAttributesPacketTest

### Community 5 - "SidecarMessageTest"
Cohesion: 0.11
Nodes (3): IpcProtocolException, SidecarMessage, SidecarMessageTest

### Community 6 - "ItemEntry"
Cohesion: 0.07
Nodes (8): ItemTable, ItemRegistryPacket, ItemEntry, ItemTableTest, {closure#1}(), {closure#2}(), {closure#3}(), ItemRegistryPacketTest

### Community 7 - "ChunkStreamer"
Cohesion: 0.08
Nodes (3): ChunkStreamer, World, ChunkStreamerTest

### Community 9 - "message.rs"
Cohesion: 0.07
Nodes (33): Channel, Reliable, Unreliable, close_body_allows_empty_reason(), close_body_round_trip(), CLOSE_CONNECTION, CloseBody, CONNECTION_CLOSED (+25 more)

### Community 10 - "server.rs"
Cohesion: 0.08
Nodes (40): Pong, Command, Close, SendPayload, generate_nonce(), generated_nonce_is_canonical_32_bytes(), handle_frame(), handshake_accepts_correct_proof_and_reads_payload() (+32 more)

### Community 11 - "SubChunk"
Cohesion: 0.07
Nodes (4): SubChunk, SubChunkSerializer, WorldException, ChunkTest

### Community 12 - "PickaxeMP\item\ItemStack"
Cohesion: 0.13
Nodes (3): ItemActor, ItemPickupResult, FoodConsumptionResult

### Community 13 - "FileWorldStorage"
Cohesion: 0.07
Nodes (6): FlatGenerator, FileWorldStorage, WorldStorageInterface, WorldManager, WorldProperties, WorldStorageTest

### Community 14 - ".getChunk"
Cohesion: 0.12
Nodes (3): ChunkPosition, FlatGeneratorTest, WorldMutationTest

### Community 16 - ".parse"
Cohesion: 0.13
Nodes (4): OpenSslJwtVerifier, CryptoFixture, JwtParserTest, JwtVerifierTest

### Community 17 - "PacketInterface"
Cohesion: 0.12
Nodes (10): PacketContext, PacketDirection, Both, ClientToServer, ServerToClient, ProtocolEngine, {closure#1}(), __construct() (+2 more)

### Community 18 - "nether/mod.rs"
Cohesion: 0.08
Nodes (14): connection_ids_are_unique_and_bounded(), generate_connection_id(), generate_network_id(), NetherError, Identity, Inner, Jwks, Tls (+6 more)

### Community 19 - "RequestNetworkSettingsPacket"
Cohesion: 0.20
Nodes (3): LoginPacket, RequestNetworkSettingsPacket, HandshakeTest

### Community 20 - "NetworkSettingsPacket"
Cohesion: 0.10
Nodes (8): HandshakeException, NetworkSettingsPacket, ResourcePackClientResponsePacket, ResourcePackResponse, Cancel, Downloading, DownloadingFinished, ResourcePackStackFinished

### Community 21 - "frame.rs"
Cohesion: 0.18
Nodes (17): binary_payload_survives(), Frame, FrameError, BadMagic, EncodePayloadTooLarge, PayloadTooLarge, UnsupportedVersion, header_size_is_stable() (+9 more)

### Community 22 - "AesCtrCipher"
Cohesion: 0.05
Nodes (6): AesCtrCipher, EcdhKeyExchange, EcPublicKey, HandshakeToken, EncryptionTest, HandshakeEncryptionIntegrationTest

### Community 24 - "GameplayLoopTest"
Cohesion: 0.08
Nodes (4): BlockActionManager, BlockActionResult, BlockActionManagerTest, GameplayLoopTest

### Community 25 - "PlayerActionType"
Cohesion: 0.05
Nodes (36): PlayerActionType, AbortDestroyBlock, ChangeDimensionAck, ContinueDestroyBlock, Count, CrackBlock, CreativeDestroyBlock, DenyDestroyBlock (+28 more)

### Community 29 - "WorldStreamPacketTest"
Cohesion: 0.07
Nodes (5): ChunkRadiusUpdatedPacket, RequestChunkRadiusPacket, SetTimePacket, ChunkPos, WorldStreamPacketTest

### Community 30 - "ConnectionInterface"
Cohesion: 0.06
Nodes (10): ConnectionInterface, SidecarConnection, TransportListenerInterface, onConnectionClosed(), onConnectionOpen(), onConnectionClosed(), onConnectionOpen(), onPayload() (+2 more)

### Community 31 - "ReloadManager"
Cohesion: 0.12
Nodes (9): ReloadManager, {closure#1}(), ReloadReport, ReloadResult, ReloadStatus, Committed, Failed, RolledBack (+1 more)

### Community 34 - "ActorEventType"
Cohesion: 0.03
Nodes (62): ActorEventType, ActorGrowUp, AgentSwingArm, AirSupply, BabyAge, BalloonPop, CaravanUpdated, Death (+54 more)

### Community 35 - "rust"
Cohesion: 0.08
Nodes (25): run, jobs, rust, os, name, on, pull_request, push (+17 more)

### Community 36 - "FullContainerName"
Cohesion: 0.07
Nodes (10): InventoryContentPacket, ItemStackResponsePacket, FullContainerName, ItemStackRequest, ItemStackRequestAction, ItemStackRequestSlotInfo, ItemStackResponseContainerInfo, ItemStackResponseSlotInfo (+2 more)

### Community 37 - "ServerKernel"
Cohesion: 0.12
Nodes (4): LifecycleException, ServerKernel, ServerConfig, {closure#1}()

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

### Community 43 - ".fromConnectionRequest"
Cohesion: 0.15
Nodes (3): CertificateChain, CertificateValidatorTest, SkinDataValidatorTest

### Community 44 - "Scheduler"
Cohesion: 0.14
Nodes (5): SchedulerException, {closure#1}(), Scheduler, Task, SchedulerTest

### Community 47 - "DependencyException"
Cohesion: 0.13
Nodes (4): DependencyException, DependencyGraph, {closure#1}(), {closure#2}()

### Community 49 - ".from_args"
Cohesion: 0.18
Nodes (14): Config, ConfigError, IncompleteTls, InvalidValue, MissingValue, UnknownArgument, defaults_are_valid(), media_can_be_disabled() (+6 more)

### Community 50 - "PickaxeMP\protocol\binary\BinaryWriter"
Cohesion: 0.04
Nodes (18): PacketBroadcaster, PacketId, ContainerClosePacket, ContainerOpenPacket, {closure#1}(), {closure#2}(), {closure#1}(), MoveActorAbsolutePacket (+10 more)

### Community 51 - "BinaryReader"
Cohesion: 0.03
Nodes (15): BinaryReader, Uuid, PacketInterface, ResourcePacksInfoPacket, ResourcePackStackPacket, ServerToClientHandshakePacket, StartGamePacket, SubChunkPacket (+7 more)

### Community 52 - "ModuleRegistry"
Cohesion: 0.13
Nodes (5): ModuleRegistry, FakeModule, ReloadManagerTest, ServerKernelTest, shutdown()

### Community 53 - "ConnectionState"
Cohesion: 0.18
Nodes (9): ConnectionState, AwaitingAuthentication, AwaitingLogin, AwaitingRequestNetworkSettings, AwaitingResourcePacks, Closed, Ready, HandshakeResult (+1 more)

### Community 55 - "PlayStatus"
Cohesion: 0.11
Nodes (12): PlayStatusPacket, PlayStatus, LoginFailedClientOld, LoginFailedEditionMismatchEduToVanilla, LoginFailedEditionMismatchVanillaToEdu, LoginFailedEditorMismatchEditorToVanilla, LoginFailedEditorMismatchVanillaToEditor, LoginFailedInvalidTenant (+4 more)

### Community 59 - "BlockRegistry"
Cohesion: 0.08
Nodes (4): Fnv1a32, NbtWriter, BlockState, BlockStateTest

### Community 62 - "PlayerIdentityFactory"
Cohesion: 0.09
Nodes (7): AuthenticationResult, IdentityType, Offline, XboxLive, PlayerIdentity, PlayerIdentityFactory, AuthenticationEngineTest

### Community 63 - "main.rs"
Cohesion: 0.11
Nodes (9): failed_handshake_does_not_consume_the_ipc_slot(), generate_token(), main(), read_frame(), run(), run_ipc_accept_loop(), token_is_64_hex_chars(), tokens_differ() (+1 more)

### Community 64 - "InteractPacket"
Cohesion: 0.13
Nodes (7): InteractPacket, InteractAction, InteractUpdate, Invalid, NpcOpen, OpenInventory, StopRiding

### Community 65 - "ProtocolException"
Cohesion: 0.05
Nodes (6): VarInt, NetworkChunkPublisherUpdatePacket, EntityLink, LegacySetSlot, ProtocolException, VarIntTest

### Community 67 - "Entity"
Cohesion: 0.13
Nodes (3): Entity, {closure#1}(), {closure#2}()

### Community 69 - "ConnectionPipelineTest.php"
Cohesion: 0.06
Nodes (5): ItemDescriptorFactory, {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}()

### Community 71 - "GameType"
Cohesion: 0.20
Nodes (7): GameType, Adventure, Creative, Default, Spectator, Survival, Undefined

### Community 72 - "FoodNutrition"
Cohesion: 0.10
Nodes (3): FoodNutrition, FoodTable, FoodTableTest

### Community 73 - "NetworkSession"
Cohesion: 0.06
Nodes (4): {closure#10}(), {closure#11}(), {closure#6}(), NetworkSession

### Community 74 - "signaling.rs"
Cohesion: 0.62
Nodes (4): http_get(), signaling_status_endpoint_serves_server_data(), spawn_signaling_only(), unknown_path_is_not_server_status()

### Community 75 - "FakeTransport"
Cohesion: 0.10
Nodes (4): TransportException, {closure#1}(), FakeTransport, TransportContractTest

### Community 76 - "item_stack.go"
Cohesion: 0.09
Nodes (20): BeaconPaymentStackRequestAction, ConsumeStackRequestAction, CraftCreativeStackRequestAction, CraftGrindstoneRecipeStackRequestAction, CraftLoomRecipeStackRequestAction, CraftNonImplementedStackRequestAction, CraftRecipeOptionalStackRequestAction, CraftRecipeStackRequestAction (+12 more)

### Community 83 - "GameplayTicker"
Cohesion: 0.17
Nodes (3): GameplayTicker, GameplayTickResult, GameplayTickerTest

### Community 90 - "Inventory"
Cohesion: 0.08
Nodes (4): Inventory, InventoryTransaction, AtomicTransactionsTest, InventoryTest

### Community 91 - "ItemStack"
Cohesion: 0.11
Nodes (3): {closure#1}(), ItemStack, ItemStackTest

### Community 92 - "ItemRegistry"
Cohesion: 0.10
Nodes (4): Item, {closure#1}(), ItemRegistry, ItemRegistryTest

### Community 93 - "PngDecoder"
Cohesion: 0.17
Nodes (3): PngDecoder, PngImage, SkinException

### Community 94 - "ConnectionPipeline"
Cohesion: 0.12
Nodes (11): {closure#10}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}() (+3 more)

### Community 96 - "PHPUnit\Framework\TestCase"
Cohesion: 0.08
Nodes (9): ItemActorManager, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}() (+1 more)

### Community 97 - "omniroute"
Cohesion: 0.09
Nodes (25): input, output, tools, capabilities, modelID, name, context, output (+17 more)

### Community 98 - "PickaxeMP\protocol\packet\types\Vec3"
Cohesion: 0.08
Nodes (5): PacketRegistry, AddActorPacket, {closure#1}(), AddPlayerPacket, {closure#1}()

### Community 101 - "IpcSocketInterface"
Cohesion: 0.08
Nodes (9): IpcSocketInterface, {closure#1}(), StreamIpcSocket, FakeIpcSocket, {closure#1}(), __construct(), onConnectionClosed(), onConnectionOpen() (+1 more)

### Community 102 - "pocketmine\lang\Translatable"
Cohesion: 0.14
Nodes (4): {closure#14}(), {closure#15}(), {closure#17}(), {closure#2}()

### Community 110 - "Session"
Cohesion: 0.05
Nodes (16): PlayerJoin, Session, SessionException, {closure#1}(), SessionState, Authenticated, Authenticating, AwaitingLogin (+8 more)

### Community 111 - "PlayerListPacketTest"
Cohesion: 0.04
Nodes (16): ActorEventPacket, MoveActorDeltaPacket, PlayerListPacket, ArmSize, Slim, Wide, Color, MoveActorDeltaData (+8 more)

### Community 113 - "pocketmine\player\Player"
Cohesion: 0.14
Nodes (3): {closure#1}(), {closure#16}(), {closure#19}()

### Community 114 - "Player"
Cohesion: 0.11
Nodes (5): MovementManager, Player, PlayerAuthInput, PlayerPositionSync, InventorySlotPacket

### Community 115 - "GameplayPacketTest"
Cohesion: 0.14
Nodes (4): PlayerActionPacket, RespawnPacket, UpdateBlockPacket, GameplayPacketTest

### Community 116 - "PlayerJoinIntegrationTest.php"
Cohesion: 0.11
Nodes (8): AuthenticationEngine, {closure#1}(), AuthenticationMode, Offline, Online, {closure#1}(), SkinDataValidator, PlayerJoinIntegrationTest

### Community 118 - ".health"
Cohesion: 0.17
Nodes (7): PlayerState, Dead, Disconnected, Joining, Spawned, PlayerSurvival, PlayerSurvivalTest

### Community 119 - "SessionManager"
Cohesion: 0.12
Nodes (6): CertificateValidator, JwtParser, JwtVerifier, SessionAuthenticator, SessionManager, SessionAuthenticatorTest

### Community 150 - "ReloadRequest"
Cohesion: 0.12
Nodes (5): ReloadableInterface, ReloadPlan, ReloadRequest, SaveableInterface, prepareReload()

### Community 151 - "StartGame"
Cohesion: 0.08
Nodes (3): ItemRegistry, LevelSoundEvent, StartGame

### Community 152 - ".doVerify"
Cohesion: 0.06
Nodes (11): AuthException, Base64Url, EcdsaSignature, JwtAlgorithm, ES256, ES384, RS256, RS384 (+3 more)

### Community 154 - "InventoryEntityPacketTest"
Cohesion: 0.17
Nodes (3): RemoveActorPacket, PropertySyncData, InventoryEntityPacketTest

### Community 155 - "SynchedActorData"
Cohesion: 0.16
Nodes (3): AddItemActorPacket, {closure#1}(), SynchedActorData

### Community 161 - "AuthException"
Cohesion: 0.11
Nodes (3): JwtClaims, JwtHeader, JwtToken

### Community 166 - "gopher_item_descriptor.go"
Cohesion: 0.16
Nodes (10): AutoCraftRecipeStackRequestAction, DefaultItemDescriptor, InvalidItemDescriptor, ItemDescriptor, ItemDescriptorCount, ItemTagItemDescriptor, MoLangItemDescriptor, itemDescriptorFromType() (+2 more)

### Community 169 - "PlayerAuthInputPacket"
Cohesion: 0.18
Nodes (4): InventoryTransactionPacket, PlayerAuthInputPacket, PlayerAuthInputFlags, InventoryItemUseTest

### Community 170 - "gopher_item.go"
Cohesion: 0.18
Nodes (11): CraftResultsDeprecatedStackRequestAction, ItemEntry, ItemInstance, ItemStack, ItemType, itemUserData, MaterialReducer, MaterialReducerOutput (+3 more)

### Community 172 - "ItemUseInventoryTransaction"
Cohesion: 0.10
Nodes (12): BlockPos, HandSlot, MainHand, OffHand, ItemUseActionType, Destroy, Place, Use (+4 more)

### Community 173 - "RespawnState"
Cohesion: 0.25
Nodes (4): RespawnState, ClientReadyToSpawn, ReadyToSpawn, SearchingForSpawn

### Community 174 - "ipc/mod.rs"
Cohesion: 0.14
Nodes (14): HEADER_SIZE, MAGIC, MAX_PAYLOAD, VERSION, ConnectionClosed, ConnectionOpen, Hello, HelloAck (+6 more)

### Community 175 - "BinaryWriter"
Cohesion: 0.05
Nodes (3): BinaryWriter, SerializedPersonaPieceHandle, BinaryRoundTripTest

### Community 177 - "PickaxeMP\protocol\packet\types\BlockPos"
Cohesion: 0.15
Nodes (5): PlayerJoinService, SetSpawnPositionPacket, SpawnPositionType, PlayerRespawn, WorldSpawn

### Community 179 - "SkinImage"
Cohesion: 0.13
Nodes (9): AnimatedImageData, AnimatedTextureType, Body128x128, Body32x32, Face, None, AnimationExpression, Blinking (+1 more)

### Community 180 - "GameMode"
Cohesion: 0.17
Nodes (5): GameMode, Adventure, Creative, Spectator, Survival

### Community 184 - "SubChunkRequestResult"
Cohesion: 0.22
Nodes (8): SubChunkPacketData, SubChunkRequestResult, IndexOutOfBounds, LevelChunkDoesntExist, PlayerDoesntExist, Success, SuccessAllAir, WrongDimension

### Community 189 - "StackResponseContainerInfo"
Cohesion: 0.25
Nodes (3): ItemStackResponse, StackResponseContainerInfo, StackResponseSlotInfo

### Community 190 - "NetworkItemStackDescriptor"
Cohesion: 0.24
Nodes (4): MobEquipmentPacket, InventoryAction, InventorySource, NetworkItemStackDescriptor

### Community 192 - "StackRequestAction"
Cohesion: 0.33
Nodes (4): ItemStackRequest, StackRequestAction, lookupStackRequestAction(), lookupStackRequestActionType()

## Knowledge Gaps
- **240 isolated node(s):** `PlayerRespawn`, `WorldSpawn`, `None`, `Snappy`, `Zlib` (+235 more)
  These have ≤1 connection - possible missing edges. (Counts symbols only; 1101 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **127 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Player` connect `Player` to `.consume`, `ChunkStreamer`, `GameplayLoopTest`, `PlayerListPacketTest.php`, `.testUntrustedChainFailsClosedAndClosesConnection`, `PlayerException`, `ServerKernel`, `SetHealthPacket`, `PickaxeMP\protocol\packet\types\BlockPos`, `SetHealthPacket`, `BinaryReader`, `GameMode`, `ConnectionPipelineTest`, `PlayerSnapshotTest`, `PlayerMetadataTest`, `GameplayTicker`, `ConnectionPipeline`, `PlayerInventory`, `PHPUnit\Framework\TestCase`, `PickaxeMP\world\World`, `PlayerAttributes`, `pocketmine\lang\Translatable`, `Session`, `.health`, `.handle`?**
  _High betweenness centrality (0.061) - this node is a cross-community bridge._
- **Are the 52 inferred relationships involving `BinaryWriter` (e.g. with `.encode()` and `.bytes()`) actually correct?**
  _`BinaryWriter` has 52 INFERRED edges - model-reasoned connections that need verification._
- **What connects `PlayerRespawn`, `WorldSpawn`, `None` to the rest of the system?**
  _240 weakly-connected nodes found - possible missing edges._
- **Should `CompressionManager` be split into smaller, more focused modules?**
  _Cohesion score 0.06820987654320988 - nodes in this community are weakly interconnected._
- **Why does `Logger` connect `Logger` to `.consume`, `FileWorldStorage`, `ReloadRequest`, `GameplayLoopTest`, `ReloadManager`, `ServerKernel`, `SessionManagerTest`, `.fromConnectionRequest`, `Scheduler`, `Server`, `SetHealthPacket`, `ModuleRegistry`, `PlayerIdentityFactory`, `Entity`, `NetworkSession`, `GameplayTicker`, `ConnectionPipeline`, `Session`, `SessionManager`, `.handle`?**
  _High betweenness centrality (0.055) - this node is a cross-community bridge._
- **Are the 44 inferred relationships involving `BinaryReader` (e.g. with `.decode()` and `.testGameplayPacketsRejectTrailingBytes()`) actually correct?**
  _`BinaryReader` has 44 INFERRED edges - model-reasoned connections that need verification._
- **Should `Config` be split into smaller, more focused modules?**
  _Cohesion score 0.0609009009009009 - nodes in this community are weakly interconnected._