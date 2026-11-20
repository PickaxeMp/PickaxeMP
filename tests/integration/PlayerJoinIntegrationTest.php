<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\integration;

use PHPUnit\Framework\TestCase;
use PickaxeMP\auth\AuthenticationEngine;
use PickaxeMP\auth\certificate\CertificateValidator;
use PickaxeMP\auth\certificate\TrustedKeyStore;
use PickaxeMP\auth\PlayerIdentityFactory;
use PickaxeMP\auth\jwt\JwtParser;
use PickaxeMP\auth\jwt\JwtVerifier;
use PickaxeMP\player\PlayerJoinService;
use PickaxeMP\player\PlayerManager;
use PickaxeMP\player\PlayerState;
use PickaxeMP\protocol\packet\PacketContext;
use PickaxeMP\protocol\packet\PacketDirection;
use PickaxeMP\protocol\packet\PacketRegistry;
use PickaxeMP\protocol\packet\packets\LevelChunkPacket;
use PickaxeMP\protocol\packet\packets\ItemRegistryPacket;
use PickaxeMP\protocol\packet\packets\LoginPacket;
use PickaxeMP\protocol\packet\packets\RequestNetworkSettingsPacket;
use PickaxeMP\protocol\packet\packets\SetSpawnPositionPacket;
use PickaxeMP\protocol\packet\packets\StartGamePacket;
use PickaxeMP\protocol\ProtocolEngine;
use PickaxeMP\protocol\ProtocolHandshake;
use PickaxeMP\protocol\ProtocolVersion;
use PickaxeMP\session\SessionAuthenticator;
use PickaxeMP\session\SessionManager;
use PickaxeMP\session\SessionState;
use PickaxeMP\Tests\support\CryptoFixture;
use PickaxeMP\utils\Logger;
use PickaxeMP\world\block\BlockRegistry;
use PickaxeMP\world\generator\FlatGenerator;
use PickaxeMP\world\storage\FileWorldStorage;
use PickaxeMP\world\WorldManager;

/**
 * Phase 5'in ilk dikey dilimi.
 *
 * Kimlik dogrulanmis oturumun gercek bir Player nesnesine baglanmasini,
 * secilen dunyadan spawn/chunk verisi uretilmesini ve bu paketlerin gercek
 * ProtocolEngine uzerinden kodlanmasini kanitlar.
 *
 * D URUST SINIR: Bu test gercek bir Bedrock istemcisi veya gercek NetherNet
 * baglantisi degildir. Katmanlar arasi entegrasyonu kanitlar; istemci uyumu
 * ayrica dogrulanmalidir.
 */
final class PlayerJoinIntegrationTest extends TestCase
{
    private string $root;

    private Logger $logger;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'pickaxe-player-join-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0755, true);

        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);
        $this->logger = new Logger('player-join-test', $stream, Logger::DEBUG);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testAuthenticatedSessionJoinsWorldAndProducesEncodedSpawnPackets(): void
    {
        $registry = BlockRegistry::withPhase4Blocks();
        $airNetworkId = $registry->state('minecraft:air')->getNetworkId();
        $storage = new FileWorldStorage($this->root, $airNetworkId);
        $worlds = new WorldManager(
            $this->logger,
            $this->root,
            $airNetworkId,
            $storage,
            new FlatGenerator($registry)
        );
        $world = $worlds->loadOrCreate('world', 424242, 'Test World');

        $fixture = CryptoFixture::createRealisticChain();
        $trusted = TrustedKeyStore::empty();
        $trusted->add($fixture['rootKey']['trusted'], 'test-root');

        $authentication = new AuthenticationEngine(
            new CertificateValidator(new JwtParser(), new JwtVerifier(), $trusted),
            new PlayerIdentityFactory(),
            $this->logger
        );

        $sessions = new SessionManager($this->logger);
        $authenticator = new SessionAuthenticator($authentication, $sessions, $this->logger);
        $handshake = new ProtocolHandshake();
        $handshake->handle(new RequestNetworkSettingsPacket(ProtocolVersion::PROTOCOL));

        $session = $sessions->create('conn-player-1', '127.0.0.1:51000');
        $session->transitionTo(SessionState::AwaitingNetworkSettings);
        $session->transitionTo(SessionState::AwaitingLogin);

        $loginResult = $authenticator->handleLogin(
            $handshake,
            $session,
            new LoginPacket(
                ProtocolVersion::PROTOCOL,
                CryptoFixture::connectionRequest($fixture['chain'])
            )
        );

        self::assertFalse($loginResult->isClosed());
        self::assertTrue($session->isAuthenticated());

        $players = new PlayerManager($this->logger);
        $joiner = new PlayerJoinService($players, $this->logger);

        $join = $joiner->prepare($session, $world);

        self::assertSame('TestPlayer', $join->player->identity->displayName);
        self::assertSame('world', $join->player->worldName());
        // Phase 10.4: StartGame -> ItemRegistry -> LevelChunk. Istemci esya
        // kimliklerini ItemRegistry'den cozer; LevelChunk'dan once gelmeli.
        // Phase 10.5: SetSpawnPosition en sonda (yeniden dogma noktasi).
        self::assertCount(4, $join->packets);
        self::assertInstanceOf(StartGamePacket::class, $join->packets[0]);
        self::assertInstanceOf(ItemRegistryPacket::class, $join->packets[1]);
        self::assertInstanceOf(LevelChunkPacket::class, $join->packets[2]);
        self::assertInstanceOf(SetSpawnPositionPacket::class, $join->packets[3]);
        self::assertSame(SessionState::Initializing, $session->getState());

        $engine = new ProtocolEngine(PacketRegistry::withSupportedPackets());
        $context = new PacketContext('conn-player-1', PacketDirection::ServerToClient);

        $encodedStartGame = $engine->encode($join->packets[0]);
        $encodedItemRegistry = $engine->encode($join->packets[1]);
        $encodedLevelChunk = $engine->encode($join->packets[2]);

        $decodedStartGame = $engine->decode($encodedStartGame, $context);
        $decodedItemRegistry = $engine->decode($encodedItemRegistry, $context);
        $decodedLevelChunk = $engine->decode($encodedLevelChunk, $context);

        self::assertInstanceOf(StartGamePacket::class, $decodedStartGame);
        self::assertInstanceOf(ItemRegistryPacket::class, $decodedItemRegistry);
        self::assertInstanceOf(LevelChunkPacket::class, $decodedLevelChunk);
        self::assertGreaterThan(1900, $decodedItemRegistry->count());

        $byName = [];

        foreach ($decodedItemRegistry->entries as $entry) {
            $byName[$entry->name] = $entry->runtimeId;
        }

        self::assertSame(285, $byName['minecraft:apple'] ?? null);
        self::assertSame(449, $byName['minecraft:cake'] ?? null);
        self::assertSame('world', $decodedStartGame->levelId);
        self::assertSame('Test World', $decodedStartGame->levelName);
        self::assertEqualsWithDelta(8.5, $decodedStartGame->x, 1.0e-6);
        self::assertEqualsWithDelta(-57.0, $decodedStartGame->y, 1.0e-6);
        self::assertSame(0, $decodedLevelChunk->chunkX);
        self::assertSame(0, $decodedLevelChunk->chunkZ);
        self::assertGreaterThan(0, $decodedLevelChunk->subChunkCount);
        self::assertNotSame('', $decodedLevelChunk->serializedChunkData);

        $joiner->complete($join);

        self::assertSame(SessionState::Playing, $session->getState());
        self::assertSame(PlayerState::Spawned, $join->player->state());
        self::assertSame(1, $players->count());
        self::assertSame($join->player, $players->bySession($session->id));

        $players->removeByConnection('conn-player-1');

        self::assertSame(0, $players->count());
        self::assertSame(PlayerState::Disconnected, $join->player->state());
    }

    public function testUnauthenticatedSessionCannotCreatePlayerOrWorldJoinPlan(): void
    {
        $registry = BlockRegistry::withPhase4Blocks();
        $airNetworkId = $registry->state('minecraft:air')->getNetworkId();
        $worlds = new WorldManager(
            $this->logger,
            $this->root,
            $airNetworkId,
            new FileWorldStorage($this->root, $airNetworkId),
            new FlatGenerator($registry)
        );
        $world = $worlds->loadOrCreate('world', 1, 'Test World');

        $sessions = new SessionManager($this->logger);
        $session = $sessions->create('conn-unauthenticated', '127.0.0.1:51001');
        $session->transitionTo(SessionState::AwaitingNetworkSettings);
        $session->transitionTo(SessionState::AwaitingLogin);

        $players = new PlayerManager($this->logger);
        $joiner = new PlayerJoinService($players, $this->logger);

        try {
            $joiner->prepare($session, $world);
            self::fail('Kimlik dogrulanmamis oturum dunyaya girmemeliydi.');
        } catch (\PickaxeMP\player\PlayerException $exception) {
            self::assertStringContainsString('kimlik', strtolower($exception->getMessage()));
        }

        self::assertSame(0, $players->count());
        self::assertSame(SessionState::AwaitingLogin, $session->getState());
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $path . DIRECTORY_SEPARATOR . $entry;

            if (is_dir($full)) {
                $this->removeDirectory($full);
            } else {
                @unlink($full);
            }
        }

        @rmdir($path);
    }
}
