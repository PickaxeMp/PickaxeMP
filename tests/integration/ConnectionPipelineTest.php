<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\integration;

use PHPUnit\Framework\TestCase;
use PickaxeMP\auth\AuthenticationEngine;
use PickaxeMP\auth\certificate\CertificateValidator;
use PickaxeMP\auth\certificate\TrustedKeyStore;
use PickaxeMP\auth\crypto\AesCtrCipher;
use PickaxeMP\auth\crypto\EcPublicKey;
use PickaxeMP\auth\crypto\EcdhKeyExchange;
use PickaxeMP\auth\crypto\HandshakeToken;
use PickaxeMP\auth\PlayerIdentityFactory;
use PickaxeMP\auth\jwt\JwtParser;
use PickaxeMP\auth\jwt\JwtVerifier;
use PickaxeMP\connection\ConnectionPipeline;
use PickaxeMP\entity\EntityManager;
use PickaxeMP\entity\ItemActorManager;
use PickaxeMP\exception\TransportException;
use PickaxeMP\item\ItemRegistry;
use PickaxeMP\item\ItemStack;
use PickaxeMP\player\Player;
use PickaxeMP\player\GameMode;
use PickaxeMP\player\PlayerAbilities;
use PickaxeMP\player\PlayerAuthInput;
use PickaxeMP\player\PlayerJoinService;
use PickaxeMP\player\PlayerManager;
use PickaxeMP\player\PlayerPositionSync;
use PickaxeMP\player\PlayerState;
use PickaxeMP\player\BlockActionManager;
use PickaxeMP\player\FoodConsumptionManager;
use PickaxeMP\player\FoodTable;
use PickaxeMP\player\MovementManager;
use PickaxeMP\player\PlayerAttributes;
use PickaxeMP\protocol\binary\Uuid;
use PickaxeMP\protocol\compression\CompressionAlgorithm;
use PickaxeMP\protocol\compression\CompressionManager;
use PickaxeMP\protocol\compression\CompressionSettings;
use PickaxeMP\protocol\PacketBatch;
use PickaxeMP\protocol\packet\PacketContext;
use PickaxeMP\protocol\packet\PacketDirection;
use PickaxeMP\protocol\packet\packets\AddItemActorPacket;
use PickaxeMP\protocol\packet\packets\AddPlayerPacket;
use PickaxeMP\protocol\packet\packets\ClientToServerHandshakePacket;
use PickaxeMP\protocol\packet\packets\ActorEventPacket;
use PickaxeMP\protocol\packet\packets\LevelSoundEventPacket;
use PickaxeMP\protocol\packet\types\ActorEventType;
use PickaxeMP\protocol\packet\packets\ChunkRadiusUpdatedPacket;
use PickaxeMP\protocol\packet\packets\InventorySlotPacket;
use PickaxeMP\protocol\packet\packets\InventoryTransactionPacket;
use PickaxeMP\protocol\packet\packets\ItemRegistryPacket;
use PickaxeMP\protocol\packet\packets\ItemStackRequestPacket;
use PickaxeMP\protocol\packet\packets\ItemStackResponsePacket;
use PickaxeMP\protocol\packet\packets\LevelChunkPacket;
use PickaxeMP\protocol\packet\packets\LoginPacket;
use PickaxeMP\protocol\packet\packets\MobEquipmentPacket;
use PickaxeMP\protocol\packet\packets\MoveActorDeltaPacket;
use PickaxeMP\protocol\packet\packets\MovePlayerPacket;
use PickaxeMP\protocol\packet\packets\PlayerActionPacket;
use PickaxeMP\protocol\packet\packets\PlayerAuthInputPacket;
use PickaxeMP\protocol\packet\packets\PlayerListPacket;
use PickaxeMP\protocol\packet\packets\RemoveActorPacket;
use PickaxeMP\protocol\packet\packets\RequestChunkRadiusPacket;
use PickaxeMP\protocol\packet\packets\RespawnPacket;
use PickaxeMP\protocol\packet\packets\ServerToClientHandshakePacket;
use PickaxeMP\protocol\packet\packets\SetHealthPacket;
use PickaxeMP\protocol\packet\packets\SetLocalPlayerAsInitializedPacket;
use PickaxeMP\protocol\packet\packets\SetSpawnPositionPacket;
use PickaxeMP\protocol\packet\packets\SetTimePacket;
use PickaxeMP\protocol\packet\packets\UpdateAbilitiesPacket;
use PickaxeMP\protocol\packet\packets\UpdateAttributesPacket;
use PickaxeMP\protocol\packet\packets\UpdateBlockPacket;
use PickaxeMP\protocol\packet\packets\NetworkChunkPublisherUpdatePacket;
use PickaxeMP\protocol\packet\packets\NetworkSettingsPacket;
use PickaxeMP\protocol\packet\packets\PlayStatusPacket;
use PickaxeMP\protocol\packet\packets\RequestNetworkSettingsPacket;
use PickaxeMP\protocol\packet\packets\ResourcePackClientResponsePacket;
use PickaxeMP\protocol\packet\packets\ResourcePackStackPacket;
use PickaxeMP\protocol\packet\packets\ResourcePacksInfoPacket;
use PickaxeMP\protocol\packet\packets\StartGamePacket;
use PickaxeMP\protocol\packet\packets\TakeItemActorPacket;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\PacketRegistry;
use PickaxeMP\protocol\packet\types\PlayStatus;
use PickaxeMP\protocol\packet\types\Attribute;
use PickaxeMP\protocol\packet\types\BlockPos;
use PickaxeMP\protocol\packet\types\HandSlot;
use PickaxeMP\protocol\packet\types\InventoryAction;
use PickaxeMP\protocol\packet\types\InventorySource;
use PickaxeMP\protocol\packet\types\FullContainerName;
use PickaxeMP\protocol\packet\types\ItemStackNetResult;
use PickaxeMP\protocol\packet\types\ItemStackRequest;
use PickaxeMP\protocol\packet\types\ItemStackRequestAction;
use PickaxeMP\protocol\packet\types\ItemStackRequestSlotInfo;
use PickaxeMP\protocol\packet\types\ItemUseActionType;
use PickaxeMP\protocol\packet\types\ItemUseInventoryTransaction;
use PickaxeMP\protocol\packet\types\NetworkItemStackDescriptor;
use PickaxeMP\protocol\packet\types\PlayerAuthInputFlags;
use PickaxeMP\protocol\packet\types\PlayerActionType;
use PickaxeMP\protocol\packet\types\PlayerBlockAction;
use PickaxeMP\protocol\packet\types\RespawnState;
use PickaxeMP\protocol\packet\types\SpawnPositionType;
use PickaxeMP\protocol\packet\types\Vec3;
use PickaxeMP\protocol\packet\types\ResourcePackResponse;
use PickaxeMP\protocol\ProtocolEngine;
use PickaxeMP\protocol\ProtocolException;
use PickaxeMP\protocol\ProtocolVersion;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\session\SessionAuthenticator;
use PickaxeMP\session\SessionManager;
use PickaxeMP\session\SessionState;
use PickaxeMP\Tests\support\CryptoFixture;
use PickaxeMP\Tests\support\FakeConnection;
use PickaxeMP\Tests\support\FakeTransport;
use PickaxeMP\utils\Logger;
use PickaxeMP\world\block\BlockRegistry;
use PickaxeMP\world\generator\FlatGenerator;
use PickaxeMP\world\storage\FileWorldStorage;
use PickaxeMP\world\World;
use PickaxeMP\world\WorldManager;

/**
 * Uçtan uca bağlantı pipeline testi.
 *
 * Taşıyıcı → batch zarfı → protokol → kimlik doğrulama → kaynak paketleri
 * → dünya girişi zincirinin tamamı `FakeTransport` üzerinde yürütülür.
 *
 * DÜRÜST SINIR: Bu test gerçek bir Bedrock istemcisinin bağlanabildiğini
 * KANITLAMAZ. Yalnızca katmanlar arası sözleşmeyi doğrular.
 */
final class ConnectionPipelineTest extends TestCase
{
    private const CONNECTION_ID = 'c-pipeline';

    private string $root;

    private Logger $logger;

    private World $world;

    private BlockRegistry $registry;

    private FakeTransport $transport;

    private FakeConnection $connection;

    private SessionManager $sessions;

    private PlayerManager $players;

    private ItemRegistry $items;

    private ItemActorManager $itemActors;

    private ConnectionPipeline $pipeline;

    private ProtocolEngine $engine;

    private PacketBatch $plainBatch;

    private PacketBatch $compressedBatch;

    /** @var array<string, mixed> */
    private array $fixture;

    /**
     * Bağlantı başına sunucu paketi okuma imleci (yalnızca yeni paketler).
     *
     * @var array<string, int>
     */
    private array $serverCursors = [];

    /**
     * Bağlantı başına istemci tarafı cipher.
     *
     * `ServerToClientHandshake` çözüldükten sonra kurulur; o andan itibaren
     * istemci giden yükleri şifreler, gelenleri çözer. Her bağlantının
     * anahtarı ayrıdır — bu, izolasyon testinin dayanağıdır.
     *
     * @var array<string, AesCtrCipher>
     */
    private array $clientCiphers = [];

    /**
     * Bağlantı başına istemci ECDH anahtarı (`cpk`).
     *
     * @var array<string, array<string, mixed>>
     */
    private array $clientKeys = [];

    /**
     * Açılan bağlantı nesneleri. Taşıyıcı, bağlantı kapandığında kaydı
     * kaldırdığı için gönderilen çerçeveleri okumak üzere burada tutulur.
     *
     * @var array<string, FakeConnection>
     */
    private array $connections = [];

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'pickaxe-pipeline-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0755, true);

        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);
        $this->logger = new Logger('pipeline-test', $stream, Logger::DEBUG);

        $registry = BlockRegistry::withPhase4Blocks();
        $this->registry = $registry;
        $airNetworkId = $registry->state('minecraft:air')->getNetworkId();
        $worlds = new WorldManager(
            $this->logger,
            $this->root,
            $airNetworkId,
            new FileWorldStorage($this->root, $airNetworkId),
            new FlatGenerator($registry)
        );
        $this->world = $worlds->loadOrCreate('world', 1, 'Test World');

        $this->fixture = CryptoFixture::createRealisticChain(withClientPublicKey: true);

        self::assertIsArray($this->fixture['clientKey']);
        $this->clientKeys[self::CONNECTION_ID] = $this->fixture['clientKey'];

        $store = TrustedKeyStore::empty();
        $store->add($this->fixture['rootKey']['trusted'], 'test-root');

        $authEngine = new AuthenticationEngine(
            new CertificateValidator(new JwtParser(), new JwtVerifier(), $store),
            new PlayerIdentityFactory(),
            $this->logger
        );

        $this->sessions = new SessionManager($this->logger);
        $this->players = new PlayerManager($this->logger);

        // Blok yerlestirmede `InventorySlot` bildirimi icin esya defteri.
        $this->items = new ItemRegistry();
        $this->items->register('minecraft:stone', 1);
        $this->items->register('minecraft:bread', 2);

        // Yerdeki esya varliklari: oyuncu kimlikleriyle cakismamasi icin
        // kimlik uzayi yuksekten baslar (Phase 7.2).
        $this->itemActors = new ItemActorManager(
            new EntityManager(1, 1000, 1000),
            $this->items,
            $this->logger
        );

        $settings = new CompressionSettings(
            CompressionAlgorithm::Zlib,
            CompressionSettings::DEFAULT_THRESHOLD
        );

        $this->engine = new ProtocolEngine(
            PacketRegistry::withSupportedPackets(),
            new CompressionManager($settings)
        );

        $this->plainBatch = new PacketBatch(
            new CompressionManager($settings),
            PacketBatch::TRANSPORT_NETHERNET,
            false
        );
        $this->compressedBatch = new PacketBatch(
            new CompressionManager($settings),
            PacketBatch::TRANSPORT_NETHERNET,
            true
        );

        $this->transport = new FakeTransport();

        $food = new FoodConsumptionManager($this->logger, FoodTable::withDefaultFoods(), $this->items);

        $this->pipeline = new ConnectionPipeline(
            $this->transport,
            $this->sessions,
            new SessionAuthenticator($authEngine, $this->sessions, $this->logger),
            new PlayerJoinService($this->players, $this->logger),
            $this->players,
            new PlayerAuthInput(
                new MovementManager($this->logger, new PlayerPositionSync($this->logger)),
                new BlockActionManager($this->logger, BlockRegistry::withPhase4Blocks()),
                $this->logger,
                $this->items,
                $this->itemActors,
                $food
            ),
            $this->world,
            $this->logger,
            $settings,
            itemActors: $this->itemActors,
            food: $food
        );

        $this->transport->setListener($this->pipeline);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testFullJoinReachesPlaying(): void
    {
        $this->openConnection();

        $session = $this->sessions->byConnection(self::CONNECTION_ID);
        self::assertNotNull($session);
        self::assertSame(SessionState::AwaitingNetworkSettings, $session->getState());

        // 1) RequestNetworkSettings (sıkıştırmasız).
        $this->clientSends(new RequestNetworkSettingsPacket(ProtocolVersion::PROTOCOL), false);

        $packets = $this->serverPackets();
        self::assertCount(1, $packets);
        self::assertInstanceOf(NetworkSettingsPacket::class, $packets[0]);
        self::assertSame(SessionState::AwaitingLogin, $session->getState());

        // NetworkSettings sıkıştırma zarfı taşımamalı.
        self::assertStringStartsNotWith(
            chr(PacketBatch::COMPRESSION_ZLIB),
            $this->connection->sentReliable[0],
            'NetworkSettings sikistirma zarfı tasimamali'
        );

        // 2) Login (güvenilir zincir).
        $this->clientSends(
            new LoginPacket(
                ProtocolVersion::PROTOCOL,
                CryptoFixture::connectionRequest($this->fixture['chain'])
            ),
            true
        );

        $packets = $this->serverPackets();
        self::assertCount(4, $packets);
        self::assertInstanceOf(ServerToClientHandshakePacket::class, $packets[0]);
        self::assertInstanceOf(PlayStatusPacket::class, $packets[1]);
        self::assertSame(PlayStatus::LoginSuccess, $packets[1]->statusEnum());
        self::assertInstanceOf(ResourcePacksInfoPacket::class, $packets[2]);
        self::assertInstanceOf(ResourcePackStackPacket::class, $packets[3]);
        self::assertSame(SessionState::Authenticated, $session->getState());
        self::assertSame('TestPlayer', $session->getIdentity()?->displayName);

        // Şifreleme anahtarı el sıkışma JWT'sinden türetilebildi: istemci
        // tarafı cipher kuruldu. Bu, gerçek ECDH türetmesinin kanıtıdır.
        self::assertArrayHasKey(self::CONNECTION_ID, $this->clientCiphers);
        $state = $this->pipeline->get(self::CONNECTION_ID);
        self::assertNotNull($state);
        self::assertTrue($state->cipherAvailable);
        self::assertFalse($state->encryptionEstablished);

        // 3) Şifreli ClientToServerHandshake: anahtar kanıtı.
        $this->clientSends(new ClientToServerHandshakePacket(), true);

        self::assertCount(0, $this->serverPackets());
        self::assertTrue($state->encryptionEstablished);

        // 4) Kaynak paketleri tamamlandı → dünya girişi.
        $this->clientSends(
            ResourcePackClientResponsePacket::of(ResourcePackResponse::ResourcePackStackFinished),
            true
        );

        $packets = $this->serverPackets();
        self::assertCount(5, $packets);
        self::assertInstanceOf(StartGamePacket::class, $packets[0]);
        self::assertInstanceOf(ItemRegistryPacket::class, $packets[1]);
        self::assertInstanceOf(LevelChunkPacket::class, $packets[2]);
        // Phase 10.5: dunya spawn noktasi girişte bildirilir.
        self::assertInstanceOf(SetSpawnPositionPacket::class, $packets[3]);
        // Phase 7.1: spawn sonrasi oyuncunun kendi liste girdisi.
        self::assertInstanceOf(PlayerListPacket::class, $packets[4]);

        // 5) Oturum Playing; oyuncu kayıtlı ve spawn oldu.
        self::assertSame(SessionState::Playing, $session->getState());
        self::assertSame(1, $this->players->count());
        self::assertNotNull($this->players->bySession($session->id));
        self::assertSame(PlayerState::Spawned, $this->players->bySession($session->id)?->state());
        self::assertSame(1, $this->pipeline->count());
    }

    public function testUntrustedChainFailsClosedAndClosesConnection(): void
    {
        // Güvenilmeyen kök: kimlik doğrulama reddedilmeli.
        $engine = new AuthenticationEngine(
            new CertificateValidator(new JwtParser(), new JwtVerifier(), TrustedKeyStore::empty()),
            new PlayerIdentityFactory(),
            $this->logger
        );

        $this->pipeline = new ConnectionPipeline(
            $this->transport,
            $this->sessions,
            new SessionAuthenticator($engine, $this->sessions, $this->logger),
            new PlayerJoinService($this->players, $this->logger),
            $this->players,
            new PlayerAuthInput(
                new MovementManager($this->logger, new PlayerPositionSync($this->logger)),
                new BlockActionManager($this->logger, BlockRegistry::withPhase4Blocks()),
                $this->logger,
                $this->items,
                $this->itemActors
            ),
            $this->world,
            $this->logger
        );
        $this->transport->setListener($this->pipeline);

        $this->openConnection();
        $this->clientSends(new RequestNetworkSettingsPacket(ProtocolVersion::PROTOCOL), false);

        // NetworkSettings tüketilsin (imleci ilerlet).
        self::assertCount(1, $this->serverPackets());

        $session = $this->sessions->byConnection(self::CONNECTION_ID);
        self::assertNotNull($session);

        $this->clientSends(
            new LoginPacket(
                ProtocolVersion::PROTOCOL,
                CryptoFixture::connectionRequest($this->fixture['chain'])
            ),
            true
        );

        // Sunucu hata PlayStatus'u gönderip bağlantıyı kapatmalı.
        $packets = $this->serverPackets();
        self::assertGreaterThanOrEqual(1, count($packets));
        self::assertInstanceOf(PlayStatusPacket::class, $packets[0]);
        self::assertNotSame(PlayStatus::LoginSuccess, $packets[0]->statusEnum());

        self::assertSame(SessionState::Failed, $session->getState());
        self::assertNull($session->getIdentity());
        self::assertSame(0, $this->players->count());
        self::assertSame(0, $this->pipeline->count());
        self::assertArrayNotHasKey(self::CONNECTION_ID, $this->transport->getConnections());
    }

    public function testSpawnDeliveryFailureRollsBackPlayerAndSession(): void
    {
        $this->openConnection();
        $this->clientSends(new RequestNetworkSettingsPacket(ProtocolVersion::PROTOCOL), false);

        $this->clientSends(
            new LoginPacket(
                ProtocolVersion::PROTOCOL,
                CryptoFixture::connectionRequest($this->fixture['chain'])
            ),
            true
        );

        // El sıkışma JWT'sini tüket ve şifreli kanalı kur.
        $this->serverPackets();
        $this->clientSends(new ClientToServerHandshakePacket(), true);

        $session = $this->sessions->byConnection(self::CONNECTION_ID);
        self::assertNotNull($session);

        // Spawn paketlerinden biri gönderilemeyecek: StartGame gönderilir,
        // LevelChunk sırasında hata fırlatılır.
        //
        // Kanca yalnızca bundan sonra kurulur; bu nedenle sayaç spawn
        // paketlerinden başlar: 1 = StartGame, 2 = LevelChunk.
        $sent = 0;
        $this->transport->beforeSendReliable = function () use (&$sent): void {
            $sent++;

            if ($sent === 2) {
                throw new TransportException('gonderim basarisiz');
            }
        };

        $this->clientSends(
            ResourcePackClientResponsePacket::of(ResourcePackResponse::ResourcePackStackFinished),
            true
        );

        // Oyuncu "dünyada" görünmemeli, oturum Playing olmamalı.
        self::assertSame(0, $this->players->count());
        self::assertNull($this->players->bySession($session->id));
        self::assertNotSame(SessionState::Playing, $session->getState());
        self::assertSame(0, $this->pipeline->count());
        self::assertArrayNotHasKey(self::CONNECTION_ID, $this->transport->getConnections());
    }

    public function testUnexpectedPacketBeforeHandshakeClosesConnection(): void
    {
        $this->openConnection();

        // El sıkışma başlamadan Login gönderilemez.
        $this->clientSends(
            new LoginPacket(ProtocolVersion::PROTOCOL, '{"chain":["a.b.c"]}'),
            false
        );

        self::assertSame(0, $this->pipeline->count());
        self::assertArrayNotHasKey(self::CONNECTION_ID, $this->transport->getConnections());
    }

    public function testGarbagePayloadClosesOnlyThatConnection(): void
    {
        $this->openConnection();

        $this->transport->injectPayload(self::CONNECTION_ID, "\xFF\xFF\xFF\xFF");

        self::assertSame(0, $this->pipeline->count());
        self::assertArrayNotHasKey(self::CONNECTION_ID, $this->transport->getConnections());
    }

    public function testConnectionCloseRemovesPlayerAndSession(): void
    {
        $this->openConnection();

        $this->clientSends(new RequestNetworkSettingsPacket(ProtocolVersion::PROTOCOL), false);
        $this->clientSends(
            new LoginPacket(
                ProtocolVersion::PROTOCOL,
                CryptoFixture::connectionRequest($this->fixture['chain'])
            ),
            true
        );

        // El sıkışma JWT'sini tüket ve şifreli kanalı kur.
        $this->serverPackets();
        $this->clientSends(new ClientToServerHandshakePacket(), true);

        $this->clientSends(
            ResourcePackClientResponsePacket::of(ResourcePackResponse::ResourcePackStackFinished),
            true
        );

        $session = $this->sessions->byConnection(self::CONNECTION_ID);
        self::assertNotNull($session);
        self::assertSame(1, $this->players->count());

        $this->transport->close(self::CONNECTION_ID, 'istemci kapatti');

        self::assertSame(0, $this->players->count());
        self::assertNull($this->sessions->byConnection(self::CONNECTION_ID));
        self::assertSame(0, $this->pipeline->count());
    }

    public function testMoveAfterSpawnSendsExactlyOneMovePlayerPacket(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);

        $player = $this->players->bySession(
            (string) $this->sessions->byConnection(self::CONNECTION_ID)?->id
        );
        self::assertNotNull($player);

        $beforeX = $player->x();

        $this->clientSends(
            new PlayerAuthInputPacket(
                pitch: 0.0,
                yaw: 90.0,
                x: $beforeX,
                y: $player->y(),
                z: $player->z(),
                moveX: 0.0,
                moveZ: 0.0,
                headYaw: 90.0,
                inputData: 0,
                inputMode: 0,
                playMode: 0,
                interactionModel: 0,
                interactPitch: 0.0,
                interactYaw: 0.0,
                clientTick: 1,
                posDeltaX: 1.0,
                posDeltaY: 0.0,
                posDeltaZ: 0.0
            ),
            true
        );

        $packets = $this->serverPackets();
        self::assertCount(1, $packets, 'Hareket tam olarak bir MovePlayer uretmeli');
        self::assertInstanceOf(MovePlayerPacket::class, $packets[0]);
        self::assertSame(MovePlayerPacket::MODE_TELEPORT, $packets[0]->mode);
    }

    public function testDuplicateClientToServerHandshakeClosesConnection(): void
    {
        $this->openConnection();
        $this->loginAndEstablishEncryption(self::CONNECTION_ID, $this->fixture['chain']);

        $this->clientSends(new ClientToServerHandshakePacket(), true);

        self::assertSame(0, $this->pipeline->count());
        self::assertArrayNotHasKey(self::CONNECTION_ID, $this->transport->getConnections());
    }

    public function testInvalidCiphertextClosesOnlyThatConnection(): void
    {
        $this->openConnection();
        $this->loginAndEstablishEncryption(self::CONNECTION_ID, $this->fixture['chain']);

        // İkinci bağlantı aynı güvenilen köke bağlı ama ayrı kimlik/anahtar.
        $secondId = 'c-pipeline-b';
        $second = CryptoFixture::createChainWithRoot(
            $this->fixture['rootKey'],
            '2535400000000002',
            '22222222-3333-4444-5555-666666666666',
            'IkinciOyuncu',
            true
        );
        $this->openConnection($secondId);
        $this->clientKeys[$secondId] = $second['clientKey'];
        $this->loginAndEstablishEncryption($secondId, $second['chain']);

        self::assertSame(2, $this->pipeline->count());

        // A'nın çerçevesini boz: yalnızca A kapanmalı.
        $this->transport->injectPayload(self::CONNECTION_ID, "\x00bozuk-ciphertext");

        self::assertSame(1, $this->pipeline->count());
        self::assertArrayNotHasKey(self::CONNECTION_ID, $this->transport->getConnections());

        // B hâlâ çalışıyor: kendi şifreli akışıyla dünya girişini tamamlar.
        $this->clientSendsTo(
            $secondId,
            ResourcePackClientResponsePacket::of(ResourcePackResponse::ResourcePackStackFinished),
            true
        );

        $packets = $this->serverPacketsFrom($secondId);
        self::assertCount(5, $packets);
        self::assertInstanceOf(StartGamePacket::class, $packets[0]);
        self::assertInstanceOf(ItemRegistryPacket::class, $packets[1]);
        self::assertInstanceOf(LevelChunkPacket::class, $packets[2]);
        self::assertInstanceOf(SetSpawnPositionPacket::class, $packets[3]);
        self::assertInstanceOf(PlayerListPacket::class, $packets[4]);
        self::assertSame(1, $this->players->count());
    }

    public function testInvalidEncryptedFrameLengthClosesConnection(): void
    {
        $this->openConnection();
        $this->loginAndEstablishEncryption(self::CONNECTION_ID, $this->fixture['chain']);

        // Checksum geçerli, ama batch uzunluk öneki gövdeden uzun.
        $badFrame = chr(PacketBatch::COMPRESSION_NONE) . "\x7F";
        $cipher = $this->clientCiphers[self::CONNECTION_ID];
        $this->transport->injectPayload(self::CONNECTION_ID, $cipher->encrypt($badFrame));

        self::assertSame(0, $this->pipeline->count());
        self::assertArrayNotHasKey(self::CONNECTION_ID, $this->transport->getConnections());
    }

    public function testMultipleEncryptedPacketsInOneFrameAreProcessed(): void
    {
        $this->openConnection();
        $this->clientSends(new RequestNetworkSettingsPacket(ProtocolVersion::PROTOCOL), false);
        $this->serverPackets();

        $this->clientSends(
            new LoginPacket(
                ProtocolVersion::PROTOCOL,
                CryptoFixture::connectionRequest($this->fixture['chain'])
            ),
            true
        );
        $this->serverPackets();

        // Tek şifreli çerçevede iki paket: önce el sıkışma kanıtı, sonra
        // kaynak paketi yanıtı. İkisi de sırayla işlenmeli.
        $this->clientSendsMany(self::CONNECTION_ID, [
            new ClientToServerHandshakePacket(),
            ResourcePackClientResponsePacket::of(ResourcePackResponse::ResourcePackStackFinished),
        ]);

        $packets = $this->serverPackets();
        self::assertCount(5, $packets);
        self::assertInstanceOf(StartGamePacket::class, $packets[0]);
        self::assertInstanceOf(ItemRegistryPacket::class, $packets[1]);
        self::assertInstanceOf(LevelChunkPacket::class, $packets[2]);
        // Phase 10.5: dunya spawn noktasi.
        self::assertInstanceOf(SetSpawnPositionPacket::class, $packets[3]);
        // Phase 7.1: spawn sonrasi oyuncunun kendi liste girdisi.
        self::assertInstanceOf(PlayerListPacket::class, $packets[4]);

        $session = $this->sessions->byConnection(self::CONNECTION_ID);
        self::assertNotNull($session);
        self::assertSame(SessionState::Playing, $session->getState());
    }

    public function testResourcePackResponseBeforeEncryptionIsRejected(): void
    {
        $this->openConnection();
        $this->clientSends(new RequestNetworkSettingsPacket(ProtocolVersion::PROTOCOL), false);

        $this->clientSends(
            new LoginPacket(
                ProtocolVersion::PROTOCOL,
                CryptoFixture::connectionRequest($this->fixture['chain'])
            ),
            true
        );

        // Handshake JWT'sini tüket (istemci cipher'ı kurulur) ama
        // ClientToServerHandshake gönderme: şifreleme kanıtlanmadı.
        $this->serverPackets();

        $this->clientSends(
            ResourcePackClientResponsePacket::of(ResourcePackResponse::ResourcePackStackFinished),
            true
        );

        self::assertSame(0, $this->pipeline->count());
        self::assertSame(0, $this->players->count());
        self::assertArrayNotHasKey(self::CONNECTION_ID, $this->transport->getConnections());
    }

    // --- Phase 7: oynanis dongusu -----------------------------------------

    public function testSecondJoinMakesPlayersVisibleToEachOther(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);
        $this->serverPackets();

        $second = $this->joinSecondPlayer();

        $firstPackets = $this->serverPackets();
        $secondPackets = $this->serverPacketsFrom($second);

        self::assertSame(2, $this->players->count());

        $toSecond = $this->only(AddPlayerPacket::class, $secondPackets);
        self::assertCount(1, $toSecond, 'Ikinci oyuncu ilk oyuncuyu gormeli.');
        self::assertSame('TestPlayer', $toSecond[0]->playerName);

        $toFirst = $this->only(AddPlayerPacket::class, $firstPackets);
        self::assertCount(1, $toFirst, 'Ilk oyuncu ikinciyi gormeli.');
        self::assertSame('SecondPlayer', $toFirst[0]->playerName);

        $first = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($first);
        self::assertSame($first->runtimeId, $toSecond[0]->targetRuntimeId);
    }

    public function testSecondJoinSendsPlayerListBeforeAddPlayer(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);
        $this->serverPackets();

        $second = $this->joinSecondPlayer();

        $firstPackets = $this->serverPackets();
        $secondPackets = $this->serverPacketsFrom($second);

        // Yeni oyuncuya tek liste: kendi girisi + ilk oyuncu.
        $listsToSecond = $this->only(PlayerListPacket::class, $secondPackets);
        self::assertCount(1, $listsToSecond, 'Ikinci oyuncuya tek `PlayerList` gitmeli.');
        self::assertCount(2, $listsToSecond[0]->entries);
        self::assertSame('SecondPlayer', $listsToSecond[0]->entries[0]->playerName);
        self::assertSame('TestPlayer', $listsToSecond[0]->entries[1]->playerName);

        // Ilk oyuncuya: yalnizca yeni oyuncunun liste girdisi.
        $listsToFirst = $this->only(PlayerListPacket::class, $firstPackets);
        self::assertCount(1, $listsToFirst);
        self::assertCount(1, $listsToFirst[0]->entries);
        self::assertSame('SecondPlayer', $listsToFirst[0]->entries[0]->playerName);

        // Liste, varlik tanitimindan ONCE gelmeli.
        self::assertLessThan(
            $this->indexOf(AddPlayerPacket::class, $secondPackets),
            $this->indexOf(PlayerListPacket::class, $secondPackets),
            '`PlayerList` `AddPlayer` oncesinde olmali.'
        );
        self::assertLessThan(
            $this->indexOf(AddPlayerPacket::class, $firstPackets),
            $this->indexOf(PlayerListPacket::class, $firstPackets)
        );

        // `Actor Unique ID` kalici kimliktir: `entityId`, `runtimeId` degil.
        $first = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($first);
        self::assertSame($first->entityId, $listsToSecond[0]->entries[1]->actorUniqueId);

        $joining = $this->players->byConnection($second);
        self::assertNotNull($joining);
        self::assertSame($joining->entityId, $listsToFirst[0]->entries[0]->actorUniqueId);
        self::assertSame($joining->identity->xuid, $listsToFirst[0]->entries[0]->xuid);
    }

    public function testBlockBreakIsBroadcastToOtherPlayers(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);
        $second = $this->joinSecondPlayer();
        $this->serverPackets();
        $this->serverPacketsFrom($second);

        $spawn = $this->world->getSpawn();

        $this->clientSends(new PlayerAuthInputPacket(
            pitch: 0.0,
            yaw: 0.0,
            x: $spawn['x'],
            y: $spawn['y'],
            z: $spawn['z'],
            moveX: 0.0,
            moveZ: 0.0,
            headYaw: 0.0,
            // Blok aksiyonlari yalnizca bu bayrak acikken tel uzerinde tasinir.
            inputData: 1 << PlayerAuthInputFlags::PERFORM_BLOCK_ACTIONS,
            inputMode: 0,
            playMode: 0,
            interactionModel: 0,
            interactPitch: 0.0,
            interactYaw: 0.0,
            clientTick: 1,
            blockActions: [
                new PlayerBlockAction(PlayerBlockAction::CRACK_BREAK, 5, -58, 5, 1),
            ]
        ), true);

        $updates = $this->only(UpdateBlockPacket::class, $this->serverPacketsFrom($second));

        self::assertCount(1, $updates, 'Dunya degisikligi diger oyuncuya gitmeli.');
        self::assertSame(5, $updates[0]->position->x);
        self::assertSame(-58, $updates[0]->position->y);
        self::assertSame(5, $updates[0]->position->z);
        self::assertSame($this->airNetworkId(), $updates[0]->blockRuntimeId);

        // Kiran istemciye `UpdateBlock` donmez: yerel tahmin zaten uygulandi.
        self::assertCount(0, $this->only(UpdateBlockPacket::class, $this->serverPackets()));

        self::assertSame($this->airNetworkId(), $this->world->getBlock(5, -58, 5));
    }

    public function testPlaceConsumesItemAndReportsInventorySlot(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);
        $second = $this->joinSecondPlayer();
        $this->serverPackets();
        $this->serverPacketsFrom($second);

        $first = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($first);
        $first->inventory()->setSlot(0, new ItemStack('minecraft:stone', 3));

        $this->clientSends(new InventoryTransactionPacket(
            itemUse: new ItemUseInventoryTransaction(
                actions: [],
                actionType: ItemUseActionType::Place,
                triggerType: 0,
                position: new BlockPos(5, -58, 5),
                face: 1,
                slot: 0,
                hand: HandSlot::MainHand,
                item: NetworkItemStackDescriptor::empty(),
                fromPosition: new Vec3(8.5, -57.0, 8.5),
                clickPosition: new Vec3(0.5, 1.0, 0.5),
                targetBlockId: 0,
                clientInteractPrediction: 0,
                clientCooldownState: 0
            )
        ), true);

        $slotUpdates = $this->only(InventorySlotPacket::class, $this->serverPackets());
        self::assertCount(1, $slotUpdates, 'Yerlestiren istemci yuva guncellemesi almali.');
        self::assertSame(0, $slotUpdates[0]->slot);
        self::assertSame(2, $slotUpdates[0]->item->count);

        $updates = $this->only(UpdateBlockPacket::class, $this->serverPacketsFrom($second));
        self::assertCount(1, $updates);
        self::assertSame(
            $this->registry->defaultState('minecraft:stone')->getNetworkId(),
            $updates[0]->blockRuntimeId
        );

        self::assertSame(2, $first->inventory()->getSlot(0)?->count());
        self::assertSame(
            $this->registry->defaultState('minecraft:stone')->getNetworkId(),
            $this->world->getBlock(5, -57, 5)
        );
    }

    /**
     * Phase 7.2: yere bırakma — envanterden düşer, varlık doğar, yayınlanır.
     */
    public function testDropConsumesItemAndSpawnsItemActor(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);
        $second = $this->joinSecondPlayer();
        $this->serverPackets();
        $this->serverPacketsFrom($second);

        $first = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($first);
        $first->inventory()->setSlot(0, new ItemStack('minecraft:stone', 3));

        $this->clientSends(new InventoryTransactionPacket(
            actions: [
                new InventoryAction(
                    source: InventorySource::world(InventorySource::FLAG_WORLD_INTERACTION_RANDOM),
                    slot: 0,
                    fromItem: new NetworkItemStackDescriptor(id: 1, count: 3),
                    toItem: NetworkItemStackDescriptor::empty()
                ),
            ]
        ), true);

        $fromFirst = $this->serverPackets();

        $spawns = $this->only(AddItemActorPacket::class, $fromFirst);
        self::assertCount(1, $spawns, 'Birakan istemci varlik kimligini almali.');
        self::assertSame(1, $spawns[0]->item->id);
        self::assertSame(3, $spawns[0]->item->count);
        self::assertSame((float) $first->x(), $spawns[0]->position->x);
        self::assertSame((float) $first->y() + 1.0, $spawns[0]->position->y);

        // Eşya çoğaltma savunması: yuva gerçekten boşaldı.
        self::assertNull($first->inventory()->getSlot(0));

        $slotUpdates = $this->only(InventorySlotPacket::class, $fromFirst);
        self::assertCount(1, $slotUpdates);
        self::assertSame(0, $slotUpdates[0]->slot);
        self::assertSame(0, $slotUpdates[0]->item->id, 'Bos yuva "air" olarak bildirilmeli.');

        // Diğer oyuncular da varlığı görmeli.
        self::assertCount(1, $this->only(AddItemActorPacket::class, $this->serverPacketsFrom($second)));
        self::assertSame(1, $this->itemActors->count());
    }

    /**
     * Phase 7.2: yerdeki eşyayı toplama — varlık kalkar, envanter dolar.
     */
    public function testTakeItemActorPicksUpDroppedItem(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);
        $second = $this->joinSecondPlayer();
        $this->serverPackets();
        $this->serverPacketsFrom($second);

        $first = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($first);
        $first->inventory()->setSlot(0, new ItemStack('minecraft:stone', 3));

        $this->clientSends(new InventoryTransactionPacket(
            actions: [
                new InventoryAction(
                    source: InventorySource::world(),
                    slot: 0,
                    fromItem: new NetworkItemStackDescriptor(id: 1, count: 3),
                    toItem: NetworkItemStackDescriptor::empty()
                ),
            ]
        ), true);

        $spawns = $this->only(AddItemActorPacket::class, $this->serverPackets());
        self::assertCount(1, $spawns);
        $spawn = $spawns[0];
        $this->serverPackets();
        $this->serverPacketsFrom($second);

        $this->clientSends(new TakeItemActorPacket(
            itemRuntimeId: $spawn->targetRuntimeId,
            actorRuntimeId: $first->runtimeId
        ), true);

        $fromFirst = $this->serverPackets();
        $fromSecond = $this->serverPacketsFrom($second);

        $removals = $this->only(RemoveActorPacket::class, $fromFirst);
        self::assertCount(1, $removals, 'Toplayan istemci varligin kalktigini ogrenmeli.');
        self::assertSame($spawn->targetActorId, $removals[0]->targetActorId);
        self::assertCount(1, $this->only(RemoveActorPacket::class, $fromSecond));

        $slotUpdates = $this->only(InventorySlotPacket::class, $fromFirst);
        self::assertCount(1, $slotUpdates);
        self::assertSame(0, $slotUpdates[0]->slot);
        self::assertSame(3, $slotUpdates[0]->item->count);

        self::assertSame(3, $first->inventory()->getSlot(0)?->count());
        self::assertSame(0, $this->itemActors->count());

        // Phase 10.4: toplama sesi hem toplayana hem yakina gider; konum
        // esyanin toplandigi noktadir.
        foreach (['first' => $fromFirst, 'second' => $fromSecond] as $who => $packets) {
            $pickups = array_values(array_filter(
                $this->only(LevelSoundEventPacket::class, $packets),
                static fn (LevelSoundEventPacket $packet): bool => $packet->soundEvent === 'pickup'
            ));

            self::assertCount(1, $pickups, $who . ' icin toplama sesi yok');
            self::assertSame('minecraft:item', $pickups[0]->actorIdentifier);
            self::assertFalse($pickups[0]->isGlobal);
        }
    }

    /**
     * Phase 7.2: menzil dışındaki eşya toplanamaz (istemci iddiasına güven yok).
     */
    public function testTakeItemActorRejectsOutOfRangePickup(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);
        $this->serverPackets();

        $first = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($first);
        $first->inventory()->setSlot(0, new ItemStack('minecraft:stone', 2));

        $this->clientSends(new InventoryTransactionPacket(
            actions: [
                new InventoryAction(
                    source: InventorySource::world(),
                    slot: 0,
                    fromItem: new NetworkItemStackDescriptor(id: 1, count: 2),
                    toItem: NetworkItemStackDescriptor::empty()
                ),
            ]
        ), true);

        $spawns = $this->only(AddItemActorPacket::class, $this->serverPackets());
        self::assertCount(1, $spawns);
        $this->serverPackets();

        // Oyuncu eşyadan 10 blok uzağa ışınlanır.
        $first->setPosition($first->x() + 10.0, $first->y(), $first->z());

        $this->clientSends(new TakeItemActorPacket(
            itemRuntimeId: $spawns[0]->targetRuntimeId,
            actorRuntimeId: $first->runtimeId
        ), true);

        $fromFirst = $this->serverPackets();
        self::assertCount(0, $this->only(RemoveActorPacket::class, $fromFirst));
        self::assertCount(0, $this->only(InventorySlotPacket::class, $fromFirst));
        self::assertSame(1, $this->itemActors->count(), 'Esya yerde kalmali.');
        self::assertNull($first->inventory()->getSlot(0));
    }

    /**
     * Phase 8: istemci dünyayı yüklediğini bildirince saat + çevre chunk'lar
     * gönderilir.
     *
     * Giriş paketleri yalnızca spawn chunk'ını yollamıştı; oyuncu birkaç blok
     * yürüyünce boşluğa düşüyordu. Bu test akışın kapandığını doğrular.
     */
    public function testSetLocalPlayerAsInitializedSendsTimeAndStreamsChunks(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);

        $player = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($player);
        self::assertFalse($player->isInitialized(), 'Giris sonrasi henuz hazir degil.');

        $this->clientSends(
            new SetLocalPlayerAsInitializedPacket($player->runtimeId),
            true
        );

        $packets = $this->serverPackets();

        $times = $this->only(SetTimePacket::class, $packets);
        self::assertCount(1, $times, 'Dunya saati tam olarak bir kez gonderilmeli.');
        self::assertSame($this->world->getTime(), $times[0]->time);

        $chunks = $this->only(LevelChunkPacket::class, $packets);
        // Varsayilan yaricap 2 -> 5x5 = 25 chunk; spawn chunk'i girişte
        // gonderildigi icin 24 yeni paket beklenir.
        self::assertCount(24, $chunks, 'Spawn chunk tekrar gonderilmemeli.');
        self::assertCount(1, $this->only(SetTimePacket::class, $packets));

        self::assertTrue($player->isInitialized());

        // Ayni chunk'lar ikinci kez gelmemeli.
        $this->clientSends(
            new SetLocalPlayerAsInitializedPacket($player->runtimeId),
            true
        );

        self::assertCount(0, $this->only(LevelChunkPacket::class, $this->serverPackets()));
    }

    /**
     * Phase 10.5: istemcinin chunk penceresi `NetworkChunkPublisherUpdate` ile
     * **chunk'lardan önce** yayınlanır.
     *
     * `LevelChunk` yükletir ama boşaltmaz; pencere olmadan istemci belleği
     * sınırsız büyür ve bazı istemci sürümleri chunk'ları hiç göstermez
     * (gophertunnel: "If the packet is not sent at all, no chunks will be
     * shown"). Yarıçap **blok** cinsindendir: `granted * 16`.
     */
    public function testInitializedPublishesChunkWindowBeforeChunks(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);

        $player = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($player);

        $this->clientSends(new SetLocalPlayerAsInitializedPacket($player->runtimeId), true);

        $packets = $this->serverPackets();

        $published = $this->only(NetworkChunkPublisherUpdatePacket::class, $packets);
        self::assertCount(1, $published, 'Pencere tam olarak bir kez yayinlanmali.');

        $expectedRadius = (\PickaxeMP\world\ChunkStreamer::DEFAULT_RADIUS) * 16;
        self::assertSame($expectedRadius, $published[0]->radius);
        self::assertSame((int) floor($player->x()), $published[0]->position->x);
        self::assertSame((int) floor($player->z()), $published[0]->position->z);
        self::assertSame([], $published[0]->savedChunks, 'SavedChunks sunucu tarafinda hesaplanmaz.');

        // Pencere, ilk `LevelChunk`'tan once gelmeli: aksi halde istemci
        // yuklenen chunk'i gostermez.
        $publisherIndex = null;
        $firstChunkIndex = null;

        foreach ($packets as $index => $packet) {
            if ($packet instanceof NetworkChunkPublisherUpdatePacket && $publisherIndex === null) {
                $publisherIndex = $index;
            }

            if ($packet instanceof LevelChunkPacket && $firstChunkIndex === null) {
                $firstChunkIndex = $index;
            }
        }

        self::assertNotNull($publisherIndex);
        self::assertNotNull($firstChunkIndex);
        self::assertLessThan($firstChunkIndex, $publisherIndex);
    }

    /**
     * Phase 10.5: pencere her karede değil, yalnızca **değiştiğinde** gider.
     */
    public function testChunkWindowIsNotRepublishedWhenUnchanged(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);

        $player = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($player);

        $this->clientSends(new SetLocalPlayerAsInitializedPacket($player->runtimeId), true);
        self::assertCount(1, $this->only(NetworkChunkPublisherUpdatePacket::class, $this->serverPackets()));

        // Ayni yaricapi yeniden iste: pencere anahtari degismez.
        $this->clientSends(new RequestChunkRadiusPacket(\PickaxeMP\world\ChunkStreamer::DEFAULT_RADIUS), true);

        self::assertCount(
            0,
            $this->only(NetworkChunkPublisherUpdatePacket::class, $this->serverPackets()),
            'Ayni pencere ikinci kez yayinlanmamali.'
        );
    }

    /**
     * Phase 10.5: görüş yarıçapı değişince pencere yeniden yayınlanır.
     */
    public function testChunkWindowIsRepublishedWhenRadiusChanges(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);

        $player = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($player);

        $this->clientSends(new SetLocalPlayerAsInitializedPacket($player->runtimeId), true);
        $this->serverPackets();

        $this->clientSends(new RequestChunkRadiusPacket(1), true);

        $published = $this->only(NetworkChunkPublisherUpdatePacket::class, $this->serverPackets());

        self::assertCount(1, $published);
        self::assertSame(16, $published[0]->radius);
    }

    /**
     * Phase 10.5: istemcinin seçtiği hotbar yuvası sunucuya işlenir.
     *
     * Bu paket işlenmezse `Player::$selectedHotbarSlot` daima 0 kalır: yemek
     * yeme ve blok yerleştirme yanlış yuvadan okur.
     */
    public function testMobEquipmentUpdatesSelectedHotbarSlot(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);
        $this->serverPackets();

        $player = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($player);
        self::assertSame(0, $player->selectedHotbarSlot());

        $this->clientSends(new MobEquipmentPacket(
            targetRuntimeId: $player->runtimeId,
            item: new NetworkItemStackDescriptor(0, 0),
            slot: 3,
            selectedSlot: 3,
            containerId: 0
        ), true);

        self::assertSame(3, $player->selectedHotbarSlot());
    }

    /**
     * Phase 10.5: yanlış runtime kimliğiyle gelen hotbar değişimi yok sayılır.
     */
    public function testMobEquipmentRejectsWrongRuntimeId(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);
        $this->serverPackets();

        $player = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($player);

        $this->clientSends(new MobEquipmentPacket(
            targetRuntimeId: $player->runtimeId + 1,
            item: new NetworkItemStackDescriptor(0, 0),
            slot: 3,
            selectedSlot: 3,
            containerId: 0
        ), true);

        self::assertSame(0, $player->selectedHotbarSlot());
    }

    /**
     * Phase 10.5: aralık dışı yuva yok sayılır (9 yuvalı hotbar).
     */
    public function testMobEquipmentRejectsOutOfRangeSlot(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);
        $this->serverPackets();

        $player = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($player);

        $this->clientSends(new MobEquipmentPacket(
            targetRuntimeId: $player->runtimeId,
            item: new NetworkItemStackDescriptor(0, 0),
            slot: 200,
            selectedSlot: 200,
            containerId: 0
        ), true);

        self::assertSame(0, $player->selectedHotbarSlot());
    }

    /**
     * Phase 10.5: hotbar değişimi diğer oyunculara yayınlanır; yayınlanmazsa
     * istemciler karşı tarafın elindeki eşyayı güncellemez.
     */
    public function testMobEquipmentBroadcastsAuthoritativeHeldItem(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);
        $second = $this->joinSecondPlayer();
        $this->serverPackets();
        $this->serverPacketsFrom($second);

        $player = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($player);

        $player->inventory()->setSlot(2, new ItemStack('minecraft:stone', 5));

        $this->clientSends(new MobEquipmentPacket(
            targetRuntimeId: $player->runtimeId,
            item: new NetworkItemStackDescriptor(0, 0),
            slot: 2,
            selectedSlot: 2,
            containerId: 0
        ), true);

        $toSecond = $this->only(MobEquipmentPacket::class, $this->serverPacketsFrom($second));

        self::assertCount(1, $toSecond);
        self::assertSame($player->runtimeId, $toSecond[0]->targetRuntimeId);
        self::assertSame(2, $toSecond[0]->selectedSlot);
        self::assertSame(5, $toSecond[0]->item->count);
        self::assertSame(
            \PickaxeMP\item\ItemRegistry::withBundledItems()->get('minecraft:stone')->networkId(),
            $toSecond[0]->item->id
        );
    }

    /**
     * Phase 8: yanlış runtime kimliği ile gelen hazır bildirimi yok sayılır.
     *
     * İstemci kimliği iddia eder; sunucu oturumla karşılaştırmadan kabul
     * ederse başka oyuncunun adına chunk akışı tetiklenebilir.
     */
    public function testSetLocalPlayerAsInitializedRejectsWrongRuntimeId(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);

        $player = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($player);

        $this->clientSends(
            new SetLocalPlayerAsInitializedPacket($player->runtimeId + 999),
            true
        );

        $packets = $this->serverPackets();

        self::assertCount(0, $this->only(SetTimePacket::class, $packets));
        self::assertCount(0, $this->only(LevelChunkPacket::class, $packets));
        self::assertFalse($player->isInitialized());
    }

    /**
     * Phase 8: görüş mesafesi isteği kırpılır ve kabul edilen değer döner.
     */
    public function testRequestChunkRadiusIsClampedAndAnswered(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);

        $player = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($player);

        // Sunucu tavani pipeline'in `ChunkStreamer` görünüm mesafesidir
        // (varsayilan 2); istemci 64 ister.
        $this->clientSends(new RequestChunkRadiusPacket(64, 64), true);

        $updates = $this->only(ChunkRadiusUpdatedPacket::class, $this->serverPackets());

        self::assertCount(1, $updates);
        self::assertSame(\PickaxeMP\world\ChunkStreamer::DEFAULT_RADIUS, $updates[0]->chunkRadius);
        self::assertSame(\PickaxeMP\world\ChunkStreamer::DEFAULT_RADIUS, $player->grantedViewDistance());
        self::assertFalse($player->isInitialized(), 'Yaricap istegi hazir bayragini degistirmemeli.');
    }

    /**
     * Phase 8: küçük yarıçap isteği olduğu gibi kabul edilir.
     */
    public function testRequestChunkRadiusAcceptsSmallValue(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);

        $player = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($player);

        $this->clientSends(new RequestChunkRadiusPacket(1), true);

        $updates = $this->only(ChunkRadiusUpdatedPacket::class, $this->serverPackets());

        self::assertCount(1, $updates);
        self::assertSame(1, $updates[0]->chunkRadius);
        self::assertSame(1, $player->grantedViewDistance());
    }

    /**
     * Phase 8: hareket chunk sınırını geçince yeni chunk'lar akar.
     *
     * Bu, "oyuncu yürüyünce boşluğa düşme" hatasının asıl kapanışıdır;
     * `SetLocalPlayerAsInitialized` yalnızca ilk pencereyi doldurur.
     */
    public function testMovementStreamsNewChunks(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);

        $player = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($player);

        $this->clientSends(new SetLocalPlayerAsInitializedPacket($player->runtimeId), true);
        $this->serverPackets();

        // 32 blok = 2 chunk doguya: 2 yeni sutun x 5 satir = 10 chunk.
        $targetX = $player->x() + 32.0;
        $player->setPosition($targetX, $player->y(), $player->z());

        $this->clientSends(
            new PlayerAuthInputPacket(
                pitch: 0.0,
                yaw: 90.0,
                x: $targetX,
                y: $player->y(),
                z: $player->z(),
                moveX: 0.0,
                moveZ: 0.0,
                headYaw: 90.0,
                inputData: 0,
                inputMode: 0,
                playMode: 0,
                interactionModel: 0,
                interactPitch: 0.0,
                interactYaw: 0.0,
                clientTick: 2,
                posDeltaX: 32.0,
                posDeltaY: 0.0,
                posDeltaZ: 0.0
            ),
            true
        );

        $chunks = $this->only(LevelChunkPacket::class, $this->serverPackets());

        self::assertCount(10, $chunks, 'Yalnizca yeni sutunlar gonderilmeli.');
    }

    /**
     * Phase 9: dünya yüklendikten sonra yetenek ve öznitelik paketleri gider.
     */
    public function testInitializedSendsAbilitiesAndAttributes(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);

        $player = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($player);

        $this->clientSends(new SetLocalPlayerAsInitializedPacket($player->runtimeId), true);

        $packets = $this->serverPackets();

        $abilities = $this->only(UpdateAbilitiesPacket::class, $packets);
        self::assertCount(1, $abilities);
        self::assertSame($player->entityId, $abilities[0]->data->targetPlayerRawId);
        self::assertCount(1, $abilities[0]->data->layers);
        self::assertSame(1, $abilities[0]->data->layers[0]->layer);

        $attributes = $this->only(UpdateAttributesPacket::class, $packets);
        self::assertCount(1, $attributes);
        self::assertSame($player->runtimeId, $attributes[0]->targetRuntimeId);
        self::assertCount(5, $attributes[0]->attributes);

        // Yetenekler özniteliklerden önce gitmeli: istemci önce izinleri,
        // sonra hareket/arayüz değerlerini uygular.
        self::assertLessThan(
            $this->indexOf(UpdateAttributesPacket::class, $packets),
            $this->indexOf(UpdateAbilitiesPacket::class, $packets)
        );
    }

    /**
     * Phase 10.4: oyun modu/uçuş değişimi `UpdateAbilities` yeniden yayınlar.
     */
    public function testGameModeTransitionRebroadcastsAbilities(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);

        $player = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($player);

        $this->serverPackets();

        self::assertTrue($this->pipeline->applyGameMode($player, GameMode::Creative));

        $packets = $this->serverPackets();
        $abilities = $this->only(UpdateAbilitiesPacket::class, $packets);
        self::assertCount(1, $abilities);
        self::assertNotSame(0, $abilities[0]->data->layers[0]->abilitiesSet & PlayerAbilities::ABILITY_MAY_FLY);
        self::assertSame(0, $abilities[0]->data->layers[0]->abilitiesSet & PlayerAbilities::ABILITY_FLYING);

        self::assertTrue($this->pipeline->setFlying($player, true));

        $packets = $this->serverPackets();
        $abilities = $this->only(UpdateAbilitiesPacket::class, $packets);
        self::assertCount(1, $abilities);
        self::assertNotSame(0, $abilities[0]->data->layers[0]->abilitiesSet & PlayerAbilities::ABILITY_FLYING);

        self::assertTrue($this->pipeline->applyGameMode($player, GameMode::Survival));

        $packets = $this->serverPackets();
        $abilities = $this->only(UpdateAbilitiesPacket::class, $packets);
        self::assertCount(1, $abilities);
        self::assertSame(0, $abilities[0]->data->layers[0]->abilitiesSet & PlayerAbilities::ABILITY_MAY_FLY);
        self::assertSame(0, $abilities[0]->data->layers[0]->abilitiesSet & PlayerAbilities::ABILITY_FLYING);
        self::assertFalse($player->isFlying());
    }

    /**
     * Phase 9: tick döngüsü dünya saatini ilerletir ve periyodik olarak
     * `SetTime` yayınlar.
     */
    public function testTickAdvancesWorldTimeAndBroadcastsSetTime(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);

        $player = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($player);

        $this->clientSends(new SetLocalPlayerAsInitializedPacket($player->runtimeId), true);
        $this->serverPackets();

        $before = $this->world->getTime();

        $result = $this->pipeline->tick(1);

        self::assertSame($before + 1, $result->time);
        self::assertSame($before + 1, $this->world->getTime());

        // İlk tick'te periyodik yayın yok.
        self::assertNull($result->timeToBroadcast);
        self::assertCount(0, $this->only(SetTimePacket::class, $this->serverPackets()));
    }

    /**
     * Phase 9: tick, eşya ömrü dolduğunda `RemoveActor` yayınlar.
     */
    public function testTickRemovesExpiredItemActors(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);

        $player = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($player);

        $this->clientSends(new SetLocalPlayerAsInitializedPacket($player->runtimeId), true);
        $this->serverPackets();

        $spawn = $this->itemActors->spawn(
            $this->world,
            new ItemStack('minecraft:stone', 1),
            $player->x(),
            $player->y(),
            $player->z()
        );
        self::assertNotNull($spawn);

        // Ömür dolmadan bir tick: eşya durur.
        $result = $this->pipeline->tick(1);
        self::assertSame([], $result->removedItems);
        self::assertSame(1, $this->itemActors->count());
    }

    /**
     * Phase 9: düşen eşyanın konumu sunucuda ilerletilir ve istemcilere
     * `MoveActorDelta` ile bildirilir.
     */
    public function testTickBroadcastsItemMotion(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);

        $player = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($player);

        $this->clientSends(new SetLocalPlayerAsInitializedPacket($player->runtimeId), true);
        $this->serverPackets();

        $spawn = $this->itemActors->spawn(
            $this->world,
            new ItemStack('minecraft:stone', 1),
            $player->x(),
            $player->y() + 2.0,
            $player->z()
        );
        self::assertNotNull($spawn);

        $result = $this->pipeline->tick(1);

        self::assertNotEmpty($result->movedItems);

        $moves = $this->only(MoveActorDeltaPacket::class, $this->serverPackets());

        self::assertNotEmpty($moves);
        self::assertSame($spawn->targetRuntimeId, $moves[0]->data->actorRuntimeId);
        self::assertNotNull($moves[0]->data->positionY);
        self::assertLessThan($player->y() + 2.0, $moves[0]->data->positionY);
    }

    /**
     * Phase 10.4: açlıktan ölüm istemciye **geri bildirim** olarak gider.
     *
     * `SetHealth(0)` tek başına istemcide ölüm animasyonunu/ölüm ekranını
     * tetiklemez; `ActorEvent(Death)` ve `LevelSoundEvent("death")` gerekir.
     * Olay yalnızca bir kez yayınlanmalı: her tick yeniden gönderilirse
     * animasyon döngüye girer.
     */
    public function testStarvationDeathBroadcastsActorEventAndSoundOnce(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);
        $this->serverPackets();

        $player = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($player);

        $player->attributes()->consume(100.0);
        $player->damage(Player::MAX_HEALTH - 1);

        $captured = [];

        for ($tick = 1; $tick <= 200; $tick++) {
            $result = $this->pipeline->tick($tick);

            if ($result->deaths !== []) {
                $captured[] = $result->deaths;
            }
        }

        self::assertSame([[$player->sessionId => $player->entityId]], $captured);

        self::assertSame(0, $player->health());
        self::assertTrue($player->isDead());

        $packets = $this->serverPackets();
        $events = $this->only(ActorEventPacket::class, $packets);
        $deaths = array_values(array_filter(
            $events,
            static fn (ActorEventPacket $packet): bool => $packet->event === ActorEventType::Death
        ));

        self::assertCount(1, $deaths, 'ActorEvent sayisi: ' . count($events));
        self::assertSame($player->runtimeId, $deaths[0]->targetRuntimeId);

        $sounds = array_values(array_filter(
            $this->only(LevelSoundEventPacket::class, $packets),
            static fn (LevelSoundEventPacket $packet): bool => $packet->soundEvent === 'death'
        ));

        self::assertCount(1, $sounds);
        self::assertTrue($sounds[0]->isGlobal);
        self::assertSame('minecraft:player', $sounds[0]->actorIdentifier);
    }

    /**
     * Phase 10: açlık 0 iken tick döngüsü hasar üretir ve istemciye
     * `SetHealth` gönderilir.
     */
    public function testTickSendsSetHealthOnStarvation(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);

        $player = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($player);

        $player->attributes()->consume(100.0);
        self::assertSame(0, $player->attributes()->hunger());

        for ($tick = 1; $tick <= 81; $tick++) {
            $this->pipeline->tick($tick);
        }

        $healthPackets = $this->only(SetHealthPacket::class, $this->serverPackets());

        self::assertNotEmpty($healthPackets);
        self::assertSame(Player::MAX_HEALTH - 1, $healthPackets[count($healthPackets) - 1]->health);
        self::assertSame(Player::MAX_HEALTH - 1, $player->health());
    }

    /**
     * Sağlık güncellemesi yalnızca açlık çeken oyuncuya gider; ikinci
     * oyuncunun canı ve paket akışı etkilenmez.
     */
    public function testTickSendsSetHealthOnlyToStarvingPlayer(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);
        $this->serverPackets();

        $second = $this->joinSecondPlayer();
        $this->serverPackets();
        $this->serverPacketsFrom($second);

        $starving = $this->players->byConnection(self::CONNECTION_ID);
        $healthy = $this->players->byConnection($second);
        self::assertNotNull($starving);
        self::assertNotNull($healthy);

        $starving->attributes()->consume(100.0);

        for ($tick = 1; $tick <= 81; $tick++) {
            $this->pipeline->tick($tick);
        }

        $toStarving = $this->only(SetHealthPacket::class, $this->serverPackets());
        $toHealthy = $this->only(SetHealthPacket::class, $this->serverPacketsFrom($second));

        self::assertNotEmpty($toStarving);
        self::assertSame(
            Player::MAX_HEALTH - 1,
            $toStarving[count($toStarving) - 1]->health
        );
        self::assertSame(Player::MAX_HEALTH - 1, $starving->health());

        self::assertSame([], $toHealthy);
        self::assertSame(Player::MAX_HEALTH, $healthy->health());
    }

    /**
     * Phase 10.2: `PlayerAuthInput` bit 32 ile gelen `Use` eylemi yemeği
     * tüketir; açlık hemen, envanter yuvası da ayrıca bildirilir.
     */
    public function testEatingFoodRestoresHungerAndReportsInventorySlot(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);
        $this->serverPackets();

        $player = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($player);

        // 40 tukenme = 10 puan: 5 doygunluk + 5 aclik -> aclik 15.
        $player->attributes()->consume(40.0);
        self::assertSame(15, $player->attributes()->hunger());

        $player->inventory()->setSlot(0, new ItemStack('minecraft:bread', 2));

        $spawn = $this->world->getSpawn();

        $this->clientSends(new PlayerAuthInputPacket(
            pitch: 0.0,
            yaw: 0.0,
            x: $spawn['x'],
            y: $spawn['y'],
            z: $spawn['z'],
            moveX: 0.0,
            moveZ: 0.0,
            headYaw: 0.0,
            // `Item Use` yalnizca bu bayrak acikken tel uzerinde tasinir.
            inputData: 1 << PlayerAuthInputFlags::PERFORM_ITEM_INTERACTION,
            inputMode: 0,
            playMode: 0,
            interactionModel: 0,
            interactPitch: 0.0,
            interactYaw: 0.0,
            clientTick: 1,
            itemUseTransaction: new ItemUseInventoryTransaction(
                actions: [],
                actionType: ItemUseActionType::Use,
                triggerType: 0,
                position: new BlockPos(0, -58, 0),
                face: 1,
                slot: 0,
                hand: HandSlot::MainHand,
                item: NetworkItemStackDescriptor::empty(),
                fromPosition: new Vec3($spawn['x'], $spawn['y'], $spawn['z']),
                clickPosition: new Vec3(0.5, 1.0, 0.5),
                targetBlockId: 0,
                clientInteractPrediction: 0,
                clientCooldownState: 0
            )
        ), true);

        $packets = $this->serverPackets();

        $slots = $this->only(InventorySlotPacket::class, $packets);
        self::assertCount(1, $slots, 'Tuketilen esya icin yuva bildirimi gitmeli.');
        self::assertSame(0, $slots[0]->slot);
        self::assertSame(1, $slots[0]->item->count);

        $updates = $this->only(UpdateAttributesPacket::class, $packets);
        self::assertCount(1, $updates, 'Aclik cubugu ayni pakette guncellenmeli.');
        self::assertSame($player->runtimeId, $updates[0]->targetRuntimeId);

        $hunger = null;

        foreach ($updates[0]->attributes as $attribute) {
            if ($attribute->name === Attribute::HUNGER) {
                $hunger = $attribute;
            }
        }

        self::assertNotNull($hunger, 'Aclik ozniteligi pakette olmali.');
        self::assertEqualsWithDelta(20.0, $hunger->current, 1e-6);

        self::assertSame(20, $player->attributes()->hunger());
        self::assertSame(1, $player->inventory()->getSlot(0)?->count());
    }

    /**
     * Phase 10.2: son eşya tüketildiğinde istemciye **boş** tanımlayıcı gider.
     *
     * Boş yuva için paket göndermemek istemcide hayalet eşya bırakırdı;
     * `NetworkItemStackDescriptor::empty()` (id 0, count 0) zorunludur.
     */
    public function testEatingLastItemSendsEmptySlotDescriptor(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);
        $this->serverPackets();

        $player = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($player);

        $player->attributes()->consume(40.0);
        $player->inventory()->setSlot(0, new ItemStack('minecraft:bread', 1));

        $spawn = $this->world->getSpawn();

        $this->clientSends(new InventoryTransactionPacket(
            itemUse: new ItemUseInventoryTransaction(
                actions: [],
                actionType: ItemUseActionType::Use,
                triggerType: 0,
                position: new BlockPos(0, -58, 0),
                face: 1,
                slot: 0,
                hand: HandSlot::MainHand,
                item: NetworkItemStackDescriptor::empty(),
                fromPosition: new Vec3($spawn['x'], $spawn['y'], $spawn['z']),
                clickPosition: new Vec3(0.5, 1.0, 0.5),
                targetBlockId: 0,
                clientInteractPrediction: 0,
                clientCooldownState: 0
            )
        ), true);

        $slots = $this->only(InventorySlotPacket::class, $this->serverPackets());

        self::assertCount(1, $slots, 'Bosalan yuva da bildirilmeli.');
        self::assertSame(0, $slots[0]->slot);
        self::assertSame(0, $slots[0]->item->count);
        self::assertSame(0, $slots[0]->item->id);
        self::assertNull($player->inventory()->getSlot(0));
    }

    /**
     * Phase 10.5: yemek tüketimi `eat` ses olayı gönderir; içki `drink`.
     */
    public function testEatingEmitsEatSound(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);
        $this->serverPackets();

        $player = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($player);

        $player->attributes()->consume(40.0);
        $player->inventory()->setSlot(0, new ItemStack('minecraft:bread', 1));

        $spawn = $this->world->getSpawn();

        $this->clientSends(new InventoryTransactionPacket(
            itemUse: new ItemUseInventoryTransaction(
                actions: [],
                actionType: ItemUseActionType::Use,
                triggerType: 0,
                position: new BlockPos(0, -58, 0),
                face: 1,
                slot: 0,
                hand: HandSlot::MainHand,
                item: NetworkItemStackDescriptor::empty(),
                fromPosition: new Vec3($spawn['x'], $spawn['y'], $spawn['z']),
                clickPosition: new Vec3(0.5, 1.0, 0.5),
                targetBlockId: 0,
                clientInteractPrediction: 0,
                clientCooldownState: 0
            )
        ), true);

        $sounds = $this->only(LevelSoundEventPacket::class, $this->serverPackets());
        self::assertCount(1, $sounds, 'Yeme sesi gönderilmeli.');
        self::assertSame('eat', $sounds[0]->soundEvent);
        self::assertFalse($sounds[0]->isGlobal);
        self::assertSame($player->entityId, $sounds[0]->actorUniqueId);
    }

    /**
     * Phase 10.2: tok oyuncu yemek yiyemez. `InventoryTransaction` yolu da
     * aynı kuralı paylaşır; hiçbir paket üretilmez.
     */
    public function testEatingWhenFullIsRejectedWithoutPackets(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);
        $this->serverPackets();

        $player = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($player);
        self::assertSame(PlayerAttributes::MAX_HUNGER, $player->attributes()->hunger());

        $player->inventory()->setSlot(0, new ItemStack('minecraft:bread', 2));

        $spawn = $this->world->getSpawn();

        $this->clientSends(new InventoryTransactionPacket(
            itemUse: new ItemUseInventoryTransaction(
                actions: [],
                actionType: ItemUseActionType::Use,
                triggerType: 0,
                position: new BlockPos(0, -58, 0),
                face: 1,
                slot: 0,
                hand: HandSlot::MainHand,
                item: NetworkItemStackDescriptor::empty(),
                fromPosition: new Vec3($spawn['x'], $spawn['y'], $spawn['z']),
                clickPosition: new Vec3(0.5, 1.0, 0.5),
                targetBlockId: 0,
                clientInteractPrediction: 0,
                clientCooldownState: 0
            )
        ), true);

        $packets = $this->serverPackets();

        self::assertCount(0, $this->only(InventorySlotPacket::class, $packets));
        self::assertCount(0, $this->only(UpdateAttributesPacket::class, $packets));
        self::assertSame(2, $player->inventory()->getSlot(0)?->count());
    }

    public function testRespawnRestoresHealthAndTeleportsPlayer(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);
        $this->serverPackets();

        $player = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($player);
        $player->damage(Player::MAX_HEALTH);
        self::assertSame(PlayerState::Dead, $player->state());

        $this->clientSends(new PlayerActionPacket(
            playerRuntimeId: $player->runtimeId,
            action: PlayerActionType::Respawn,
            blockPosition: new BlockPos(0, 0, 0),
            resultPosition: new BlockPos(0, 0, 0),
            face: 0
        ), true);

        $packets = $this->serverPackets();

        $respawns = $this->only(RespawnPacket::class, $packets);
        self::assertCount(1, $respawns);
        self::assertSame($player->runtimeId, $respawns[0]->playerRuntimeId);
        self::assertSame(RespawnState::ReadyToSpawn, $respawns[0]->state);

        // Phase 10.5: yeniden dogma noktasi `Respawn`'dan once bildirilir.
        $spawnPositions = $this->only(SetSpawnPositionPacket::class, $packets);
        self::assertCount(1, $spawnPositions);
        self::assertSame(SpawnPositionType::WorldSpawn, $spawnPositions[0]->spawnType);
        self::assertSame(
            (int) floor($this->world->getSpawn()['y']),
            $spawnPositions[0]->blockPosition->y
        );

        $spawnIndex = null;
        $respawnIndex = null;

        foreach ($packets as $index => $packet) {
            if ($packet instanceof SetSpawnPositionPacket && $spawnIndex === null) {
                $spawnIndex = $index;
            }

            if ($packet instanceof RespawnPacket && $respawnIndex === null) {
                $respawnIndex = $index;
            }
        }

        self::assertNotNull($spawnIndex);
        self::assertNotNull($respawnIndex);
        self::assertLessThan($respawnIndex, $spawnIndex, 'Spawn noktasi Respawn\'dan once gelmeli.');

        $health = $this->only(SetHealthPacket::class, $packets);
        self::assertCount(1, $health);
        self::assertSame(Player::MAX_HEALTH, $health[0]->health);

        $moves = $this->only(MovePlayerPacket::class, $packets);
        self::assertCount(1, $moves);
        self::assertSame(MovePlayerPacket::MODE_TELEPORT, $moves[0]->mode);

        self::assertSame(PlayerState::Spawned, $player->state());
        self::assertSame(Player::MAX_HEALTH, $player->health());
        self::assertSame($this->world->getSpawn()['x'], $player->x());
    }

    public function testAlivePlayerRespawnRequestIsIgnored(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);
        $this->serverPackets();

        $player = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($player);

        $this->clientSends(new PlayerActionPacket(
            playerRuntimeId: $player->runtimeId,
            action: PlayerActionType::Respawn,
            blockPosition: new BlockPos(0, 0, 0),
            resultPosition: new BlockPos(0, 0, 0),
            face: 0
        ), true);

        self::assertCount(0, $this->only(RespawnPacket::class, $this->serverPackets()));
    }

    /**
     * Phase 10.3 incelemesi (gerçek bulgu): imleçte eşya varken ayrılan
     * oyuncunun eşyası envantere geri yazılır, kaybolmaz.
     */
    public function testDisconnectReturnsCursorStackToInventory(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);
        $this->serverPackets();

        $player = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($player);
        $player->inventory()->setSlot(1, new ItemStack('minecraft:bread', 4));

        $this->clientSends(new ItemStackRequestPacket([
            new ItemStackRequest(
                requestId: -11,
                actions: [
                    ItemStackRequestAction::take(
                        4,
                        new ItemStackRequestSlotInfo(new FullContainerName(12), 1),
                        new ItemStackRequestSlotInfo(new FullContainerName(59), 0)
                    ),
                ]
            ),
        ]), true);

        self::assertNull($player->inventory()->getSlot(1));

        $this->transport->close(self::CONNECTION_ID);

        self::assertSame(4, $player->inventory()->getSlot(0)?->count());
        self::assertSame('minecraft:bread', $player->inventory()->getSlot(0)?->type());
    }

    public function testDisconnectBroadcastsRemoveActorToRemainingPlayers(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);
        $second = $this->joinSecondPlayer();
        $this->serverPackets();
        $this->serverPacketsFrom($second);

        $leaving = $this->players->byConnection($second);
        self::assertNotNull($leaving);

        $this->transport->close($second);

        $removals = $this->only(RemoveActorPacket::class, $this->serverPackets());
        self::assertCount(1, $removals);
        self::assertSame($leaving->runtimeId, $removals[0]->targetActorId);

        self::assertSame(1, $this->players->count());
        self::assertNull($this->players->byConnection($second));
    }

    public function testDisconnectBroadcastsPlayerListRemoveBeforeRemoveActor(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);
        $second = $this->joinSecondPlayer();
        $this->serverPackets();
        $this->serverPacketsFrom($second);

        $leaving = $this->players->byConnection($second);
        self::assertNotNull($leaving);

        $this->transport->close($second);

        $packets = $this->serverPackets();

        $lists = $this->only(PlayerListPacket::class, $packets);
        self::assertCount(1, $lists, 'Ayrilan oyuncu icin tek liste silme gitmeli.');
        self::assertCount(1, $lists[0]->entries);
        self::assertFalse($lists[0]->entries[0]->isAdd(), 'Giris bir silme kaydi olmali.');
        self::assertSame(
            Uuid::fromString($leaving->identity->uuid)->toString(),
            $lists[0]->entries[0]->uuid->toString()
        );

        // Liste girisi, varlik silme paketinden ONCE gelmeli.
        self::assertLessThan(
            $this->indexOf(RemoveActorPacket::class, $packets),
            $this->indexOf(PlayerListPacket::class, $packets),
            '`PlayerList` silmesi `RemoveActor` oncesinde olmali.'
        );

        // Kapanan oturuma kendi silme paketi geri gonderilmez.
        self::assertSame([], $this->only(PlayerListPacket::class, $this->serverPacketsFrom($second)));
    }

    /**
     * Phase 10.3: `ItemStackRequestPacket` (147) envanterden imlece taşır ve
     * tek `ItemStackResponsePacket` (148) ile yanıtlanır.
     */
    public function testItemStackRequestMovesItemAndRespondsWithChangedSlots(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);
        $this->serverPackets();

        $player = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($player);
        $player->inventory()->setSlot(4, new ItemStack('minecraft:bread', 5));

        $this->clientSends(new ItemStackRequestPacket([
            new ItemStackRequest(
                requestId: -3,
                actions: [
                    ItemStackRequestAction::take(
                        2,
                        new ItemStackRequestSlotInfo(new FullContainerName(12), 4),
                        new ItemStackRequestSlotInfo(new FullContainerName(59), 0)
                    ),
                ]
            ),
        ]), true);

        $packets = $this->serverPackets();

        $responses = $this->only(ItemStackResponsePacket::class, $packets);
        self::assertCount(1, $responses, 'Her istek icin tek yanit gitmeli.');
        self::assertCount(1, $responses[0]->responses);

        $response = $responses[0]->responses[0];
        self::assertTrue($response->isSuccess());
        self::assertSame(-3, $response->requestId);
        self::assertSame(3, $player->inventory()->getSlot(4)?->count());

        $inventory = $response->containers[0];
        self::assertSame(12, $inventory->container->containerName);
        self::assertSame(4, $inventory->slots[0]->slot);
        self::assertSame(3, $inventory->slots[0]->amount);

        $cursor = $response->containers[1];
        self::assertSame(59, $cursor->container->containerName);
        self::assertSame(2, $cursor->slots[0]->amount);
    }

    /**
     * Phase 10.3: aynı istek `PlayerAuthInput` bit 34 üzerinden de gelebilir.
     */
    public function testItemStackRequestInsideAuthInputIsAnswered(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);
        $this->serverPackets();

        $player = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($player);
        $player->inventory()->setSlot(0, new ItemStack('minecraft:bread', 4));

        $spawn = $this->world->getSpawn();

        $this->clientSends(new PlayerAuthInputPacket(
            pitch: 0.0,
            yaw: 0.0,
            x: $spawn['x'],
            y: $spawn['y'],
            z: $spawn['z'],
            moveX: 0.0,
            moveZ: 0.0,
            headYaw: 0.0,
            inputData: 1 << PlayerAuthInputFlags::PERFORM_ITEM_STACK_REQUEST,
            inputMode: 0,
            playMode: 0,
            interactionModel: 0,
            interactPitch: 0.0,
            interactYaw: 0.0,
            clientTick: 1,
            itemStackRequest: new ItemStackRequest(
                requestId: -5,
                actions: [
                    ItemStackRequestAction::take(
                        1,
                        new ItemStackRequestSlotInfo(new FullContainerName(12), 0),
                        new ItemStackRequestSlotInfo(new FullContainerName(59), 0)
                    ),
                ]
            )
        ), true);

        $packets = $this->serverPackets();
        $responses = $this->only(ItemStackResponsePacket::class, $packets);

        self::assertCount(1, $responses);
        self::assertTrue($responses[0]->responses[0]->isSuccess());
        self::assertSame(-5, $responses[0]->responses[0]->requestId);
        self::assertSame(3, $player->inventory()->getSlot(0)?->count());
    }

    /**
     * Phase 10.3: desteklenmeyen aksiyon tüm isteği düşürür; envanter ve
     * imleç değişmez, yanıt hata kodu taşır.
     */
    public function testUnsupportedItemStackActionIsRejectedWithoutMutation(): void
    {
        $this->openConnection();
        $this->completeJoin(self::CONNECTION_ID);
        $this->serverPackets();

        $player = $this->players->byConnection(self::CONNECTION_ID);
        self::assertNotNull($player);
        $player->inventory()->setSlot(0, new ItemStack('minecraft:bread', 4));

        $this->clientSends(new ItemStackRequestPacket([
            new ItemStackRequest(
                requestId: -7,
                actions: [
                    ItemStackRequestAction::take(
                        2,
                        new ItemStackRequestSlotInfo(new FullContainerName(12), 0),
                        new ItemStackRequestSlotInfo(new FullContainerName(59), 0)
                    ),
                    new ItemStackRequestAction(
                        type: ItemStackRequestAction::DROP,
                        amount: 1,
                        source: new ItemStackRequestSlotInfo(new FullContainerName(12), 0)
                    ),
                ]
            ),
        ]), true);

        $packets = $this->serverPackets();
        $responses = $this->only(ItemStackResponsePacket::class, $packets);

        self::assertCount(1, $responses);
        self::assertSame(ItemStackNetResult::ACTION_REQUEST_NOT_ALLOWED, $responses[0]->responses[0]->result);
        self::assertSame(4, $player->inventory()->getSlot(0)?->count());
        self::assertSame(1, $this->pipeline->count());
    }


    private function airNetworkId(): int
    {
        return $this->registry->state('minecraft:air')->getNetworkId();
    }

    /**
     * @template T of PacketInterface
     *
     * @param class-string<T> $class
     * @param list<PacketInterface> $packets
     *
     * @return list<T>
     */
    private function only(string $class, array $packets): array
    {
        return array_values(array_filter(
            $packets,
            static fn (PacketInterface $packet): bool => $packet instanceof $class
        ));
    }

    /**
     * Verilen sinifin paket listesindeki **ilk** konumu.
     *
     * Siralama iddialari icin: `PlayerList` `AddPlayer`'dan once gitmeli.
     *
     * @param list<PacketInterface> $packets
     */
    private function indexOf(string $class, array $packets): int
    {
        foreach ($packets as $index => $packet) {
            if ($packet instanceof $class) {
                return $index;
            }
        }

        self::fail("{$class} paketi gonderilmedi");
    }

    /**
     * Ayni guvenilen kokle imzalanmis ikinci oyuncuyu dunyaya sokar.
     */
    private function joinSecondPlayer(): string
    {
        $connectionId = 'c-pipeline-2';

        $chain = CryptoFixture::createChainWithRoot(
            $this->fixture['rootKey'],
            xuid: '2535400000000002',
            uuid: 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            name: 'SecondPlayer',
            withClientPublicKey: true
        );

        self::assertIsArray($chain['clientKey']);
        $this->clientKeys[$connectionId] = $chain['clientKey'];

        $this->openConnection($connectionId);
        $this->loginAndEstablishEncryption($connectionId, $chain['chain']);

        $this->clientSendsTo(
            $connectionId,
            ResourcePackClientResponsePacket::of(ResourcePackResponse::ResourcePackStackFinished),
            true
        );

        return $connectionId;
    }

    /**
     * Phase 10.4: canlı bağlantı varsayılanları.
     *
     * Pipeline eşya defteri ve yemek zinciri verilmeden kurulduğunda gömülü
     * tabloyu kullanmalı; aksi hâlde canlı sunucuda yemek reddedilir ve
     * envanter bildirimleri kimliksiz kalır.
     */
    public function testPipelineDefaultsToBundledItemRegistryAndFood(): void
    {
        $pipeline = new ConnectionPipeline(
            new FakeTransport(),
            new SessionManager($this->logger),
            new SessionAuthenticator(
                new AuthenticationEngine(
                    new CertificateValidator(new JwtParser(), new JwtVerifier(), TrustedKeyStore::empty()),
                    new PlayerIdentityFactory(),
                    $this->logger
                ),
                new SessionManager($this->logger),
                $this->logger
            ),
            new PlayerJoinService($this->players, $this->logger),
            $this->players,
            new PlayerAuthInput(
                new MovementManager($this->logger, new PlayerPositionSync($this->logger)),
                new BlockActionManager($this->logger, BlockRegistry::withPhase4Blocks()),
                $this->logger
            ),
            $this->world,
            $this->logger
        );

        self::assertTrue($pipeline->items()->has('minecraft:apple'));
        self::assertSame(285, $pipeline->items()->get('minecraft:apple')->networkId());
        self::assertNotNull($pipeline->food());
    }

    private function completeJoin(string $connectionId): void
    {
        $this->loginAndEstablishEncryption($connectionId, $this->fixture['chain']);

        $this->clientSendsTo(
            $connectionId,
            ResourcePackClientResponsePacket::of(ResourcePackResponse::ResourcePackStackFinished),
            true
        );

        $packets = $this->serverPacketsFrom($connectionId);

        // Tek basina giren oyuncu: 4 spawn paketi (StartGame, ItemRegistry,
        // LevelChunk, SetSpawnPosition) + kendi `PlayerList` girdisi
        // (Phase 7.1). Liste, istemcinin sekme listesini kurmasini saglar;
        // `AddPlayer` tek basina yeterli degildir.
        self::assertCount(5, $packets);
        self::assertCount(1, $this->only(PlayerListPacket::class, $packets));

        // Phase 10.4: esya kimlik tablosu StartGame'den hemen sonra gelmeli.
        $itemRegistry = $this->only(ItemRegistryPacket::class, $packets);
        self::assertCount(1, $itemRegistry);
        self::assertGreaterThan(1900, $itemRegistry[0]->count());

        // Phase 10.5: yeniden dogma noktasi girisde bir kez bildirilir.
        $spawnPosition = $this->only(SetSpawnPositionPacket::class, $packets);
        self::assertCount(1, $spawnPosition);
        self::assertSame(SpawnPositionType::WorldSpawn, $spawnPosition[0]->spawnType);
    }

    /**
     * RequestNetworkSettings → Login → handshake JWT tüketimi →
     * şifreli ClientToServerHandshake.
     *
     * @param list<string> $chain
     */
    private function loginAndEstablishEncryption(string $connectionId, array $chain): void
    {
        $this->clientSendsTo(
            $connectionId,
            new RequestNetworkSettingsPacket(ProtocolVersion::PROTOCOL),
            false
        );
        $this->serverPacketsFrom($connectionId);

        $this->clientSendsTo(
            $connectionId,
            new LoginPacket(ProtocolVersion::PROTOCOL, CryptoFixture::connectionRequest($chain)),
            true
        );
        $this->serverPacketsFrom($connectionId);

        $this->clientSendsTo($connectionId, new ClientToServerHandshakePacket(), true);
    }

    private function openConnection(?string $connectionId = null): void
    {
        if (!$this->transport->isRunning()) {
            $this->transport->start();
        }

        $id = $connectionId ?? self::CONNECTION_ID;
        $this->connection = $this->transport->connect($id);
        $this->connections[$id] = $this->connection;
    }

    /**
     * Birden çok paketi tek şifreli batch çerçevesinde gönderir.
     *
     * @param list<PacketInterface> $packets
     */
    private function clientSendsMany(string $connectionId, array $packets): void
    {
        $encoded = [];

        foreach ($packets as $packet) {
            $encoded[] = $this->engine->encode($packet);
        }

        $frame = $this->compressedBatch->encode($encoded);
        $cipher = $this->clientCiphers[$connectionId] ?? null;

        if ($cipher !== null) {
            $frame = $cipher->encrypt($frame);
        }

        $this->transport->injectPayload($connectionId, $frame);
    }

    private function clientSends(PacketInterface $packet, bool $compressed): void
    {
        $this->clientSendsTo(self::CONNECTION_ID, $packet, $compressed);
    }

    private function clientSendsTo(string $connectionId, PacketInterface $packet, bool $compressed): void
    {
        $bytes = $this->engine->encode($packet);

        $batch = $compressed ? $this->compressedBatch : $this->plainBatch;

        $frame = $batch->encode([$bytes]);

        // Tel sırası: paket → batch (sıkıştırma) → ciphertext.
        $cipher = $this->clientCiphers[$connectionId] ?? null;

        if ($cipher !== null) {
            $frame = $cipher->encrypt($frame);
        }

        $this->transport->injectPayload($connectionId, $frame);
    }

    /**
     * Sunucunun `ServerToClientHandshake` JWT'sinden istemci cipher'ını kurar.
     *
     * @param array<string, mixed> $clientKey
     */
    private function buildClientCipher(ServerToClientHandshakePacket $packet, array $clientKey): AesCtrCipher
    {
        $token = $packet->handshakeWebToken;
        $salt = HandshakeToken::extractSalt($token);
        $serverPublic = EcPublicKey::fromDer(HandshakeToken::extractServerPublicKey($token));

        $shared = EcdhKeyExchange::deriveForTest($clientKey['resource'], $serverPublic);
        $key = EcdhKeyExchange::deriveEncryptionKey($salt, $shared);

        return new AesCtrCipher($key);
    }

    /**
     * Sunucunun istemciye gönderdiği **yeni** paketler.
     *
     * Çerçeve sıkıştırma zarfı taşıyıp taşımadığına göre çözülür: görüşme
     * öncesi yanıtlar zafsızdır, sonrası zaflıdır.
     *
     * @return list<PacketInterface>
     */
    private function serverPackets(): array
    {
        return $this->serverPacketsFrom(self::CONNECTION_ID);
    }

    /**
     * @return list<PacketInterface>
     */
    private function serverPacketsFrom(string $connectionId): array
    {
        $connection = $this->connections[$connectionId]
            ?? $this->transport->getConnections()[$connectionId]
            ?? null;

        if ($connection === null) {
            return [];
        }

        $packets = [];
        $frames = $connection->sentReliable;
        $cursor = $this->serverCursors[$connectionId] ?? 0;

        for ($index = $cursor; $index < count($frames); $index++) {
            $frame = $frames[$index];

            // Şifreleme, batch zarfının tamamını sarar: önce çöz, sonra
            // sıkıştırma/batch ayrıştırması.
            $cipher = $this->clientCiphers[$connectionId] ?? null;

            if ($cipher !== null) {
                $frame = $cipher->decrypt($frame);
            }

            foreach ($this->decodeServerFrame($frame) as $packetBytes) {
                $packet = $this->engine->decode(
                    $packetBytes,
                    new PacketContext($connectionId, PacketDirection::ServerToClient)
                );

                if ($packet instanceof ServerToClientHandshakePacket && $cipher === null) {
                    $clientKey = $this->clientKeys[$connectionId] ?? null;
                    self::assertIsArray($clientKey, 'Handshake icin istemci anahtari yok');
                    $this->clientCiphers[$connectionId] = $this->buildClientCipher($packet, $clientKey);
                }

                $packets[] = $packet;
            }
        }

        $this->serverCursors[$connectionId] = count($frames);

        return $packets;
    }

    /**
     * @return list<string>
     */
    private function decodeServerFrame(string $frame): array
    {
        try {
            return $this->plainBatch->decode($frame);
        } catch (ProtocolException) {
            return $this->compressedBatch->decode($frame);
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory) ?: [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
