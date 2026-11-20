<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit;

use PHPUnit\Framework\TestCase;
use PickaxeMP\auth\PlayerIdentity;
use PickaxeMP\auth\PlayerIdentityFactory;
use PickaxeMP\player\PlayerException;
use PickaxeMP\player\PlayerManager;
use PickaxeMP\session\Session;
use PickaxeMP\session\SessionManager;
use PickaxeMP\session\SessionState;
use PickaxeMP\utils\Logger;
use PickaxeMP\world\block\BlockRegistry;
use PickaxeMP\world\generator\FlatGenerator;
use PickaxeMP\world\storage\FileWorldStorage;
use PickaxeMP\world\World;
use PickaxeMP\world\WorldManager;

final class PlayerManagerTest extends TestCase
{
    private string $root;

    private Logger $logger;

    private World $world;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'pickaxe-player-manager-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0755, true);

        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);
        $this->logger = new Logger('player-manager-test', $stream, Logger::DEBUG);

        $registry = BlockRegistry::withPhase4Blocks();
        $airNetworkId = $registry->state('minecraft:air')->getNetworkId();
        $worlds = new WorldManager(
            $this->logger,
            $this->root,
            $airNetworkId,
            new FileWorldStorage($this->root, $airNetworkId),
            new FlatGenerator($registry)
        );
        $this->world = $worlds->loadOrCreate('world', 1, 'Test World');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testUnauthenticatedSessionCannotCreatePlayer(): void
    {
        $sessions = new SessionManager($this->logger);
        $session = $sessions->create('conn-unauthenticated', '127.0.0.1:51000');
        $session->transitionTo(SessionState::AwaitingNetworkSettings);
        $session->transitionTo(SessionState::AwaitingLogin);

        $players = new PlayerManager($this->logger);

        try {
            $players->create($session, $this->world);
            self::fail('Kimlik dogrulanmamis oturum oyuncu olusturmamaliydi.');
        } catch (PlayerException $exception) {
            self::assertStringContainsString('kimlik', strtolower($exception->getMessage()));
        }

        self::assertSame(0, $players->count());
        self::assertNull($players->bySession($session->id));
    }

    public function testAuthenticatedSessionCreatesAndIndexesPlayer(): void
    {
        $sessions = new SessionManager($this->logger);
        $session = $this->authenticatedSession($sessions, 'conn-authenticated', 'Steve');
        $players = new PlayerManager($this->logger);

        $player = $players->create($session, $this->world);

        self::assertSame($session->id, $player->sessionId);
        self::assertSame('world', $player->worldName());
        self::assertSame($player, $players->bySession($session->id));
        self::assertSame($player, $players->byConnection($session->connectionId));
        self::assertSame($player, $players->byUuid($player->identity->uuid));
        self::assertSame($player, $players->byXuid($player->identity->xuid));
        self::assertSame(1, $players->count());
    }

    public function testDuplicateIdentityIsRejected(): void
    {
        $identity = (new PlayerIdentityFactory())->offline('SamePlayer');
        $first = $this->authenticatedStandaloneSession('conn-first', $identity);
        $second = $this->authenticatedStandaloneSession('conn-second', $identity);
        $players = new PlayerManager($this->logger);
        $players->create($first, $this->world);

        $this->expectException(PlayerException::class);
        $players->create($second, $this->world);
    }

    public function testDuplicateConnectionIsRejected(): void
    {
        $identity = (new PlayerIdentityFactory())->offline('FirstPlayer');
        $first = $this->authenticatedStandaloneSession('conn-shared', $identity);
        $second = $this->authenticatedStandaloneSession('conn-shared', $identity);
        $players = new PlayerManager($this->logger);
        $players->create($first, $this->world);

        $this->expectException(PlayerException::class);
        $players->create($second, $this->world);
    }

    public function testLateRemovalOfOldPlayerDoesNotUnlinkReplacement(): void
    {
        $oldIdentity = (new PlayerIdentityFactory())->offline('OldPlayer');
        $newIdentity = (new PlayerIdentityFactory())->offline('NewPlayer');
        $oldSession = $this->authenticatedStandaloneSession('conn-shared', $oldIdentity);
        $players = new PlayerManager($this->logger);
        $oldPlayer = $players->create($oldSession, $this->world);

        // Eski oyuncu normal kapanışla kaldırılır.
        $players->remove($oldPlayer);

        // Aynı connection id yeniden kullanılabilir.
        $newSession = $this->authenticatedStandaloneSession('conn-shared', $newIdentity);
        $newPlayer = $players->create($newSession, $this->world);

        // Gecikmeli ikinci bir kaldırma çağrısı yeni oyuncuyu silmemeli.
        $players->remove($oldPlayer);

        self::assertSame($newPlayer, $players->byConnection('conn-shared'));
        self::assertSame($newPlayer, $players->bySession($newSession->id));
        self::assertSame($newPlayer, $players->byUuid($newIdentity->uuid));
        self::assertSame($newPlayer, $players->byXuid($newIdentity->xuid));
        self::assertSame(1, $players->count());
    }

    private function authenticatedSession(
        SessionManager $sessions,
        string $connectionId,
        string $name
    ): Session {
        $session = $sessions->create($connectionId, '127.0.0.1:51000');
        $session->transitionTo(SessionState::AwaitingNetworkSettings);
        $session->transitionTo(SessionState::AwaitingLogin);
        $session->transitionTo(SessionState::Authenticating);
        $sessions->bindIdentity($session, (new PlayerIdentityFactory())->offline($name));

        return $session;
    }

    private function authenticatedStandaloneSession(
        string $connectionId,
        PlayerIdentity $identity
    ): Session {
        $session = new Session('session-' . $connectionId, $connectionId, '127.0.0.1:51000');
        $session->transitionTo(SessionState::AwaitingNetworkSettings);
        $session->transitionTo(SessionState::AwaitingLogin);
        $session->transitionTo(SessionState::Authenticating);
        $session->markAuthenticated($identity);

        return $session;
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
