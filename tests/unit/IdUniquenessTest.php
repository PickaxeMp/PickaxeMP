<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit;

use PHPUnit\Framework\TestCase;
use PickaxeMP\auth\PlayerIdentityFactory;
use PickaxeMP\player\PlayerException;
use PickaxeMP\player\PlayerManager;
use PickaxeMP\session\Session;
use PickaxeMP\session\SessionState;
use PickaxeMP\utils\Logger;
use PickaxeMP\world\block\BlockRegistry;
use PickaxeMP\world\World;
use PickaxeMP\world\generator\FlatGenerator;
use PickaxeMP\world\storage\FileWorldStorage;
use PickaxeMP\world\WorldManager;

/**
 * Kimlik benzersizliği: entity/runtime kimlikleri, UUID ve XUID.
 */
final class IdUniquenessTest extends TestCase
{
    private string $root;
    private Logger $logger;
    private World $world;
    private PlayerManager $players;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'pickaxe-id-uniqueness-' . bin2hex(random_bytes(4));
        mkdir($this->root, 0755, true);

        $stream = fopen('php://memory', 'w+b');
        $this->logger = new Logger('id-uniqueness-test', $stream, Logger::DEBUG);

        $blockRegistry = BlockRegistry::withPhase4Blocks();
        $airNetworkId = $blockRegistry->state('minecraft:air')->getNetworkId();
        $worlds = new WorldManager(
            $this->logger,
            $this->root,
            $airNetworkId,
            new FileWorldStorage($this->root, $airNetworkId),
            new FlatGenerator($blockRegistry)
        );
        $this->world = $worlds->loadOrCreate('world', 1, 'Test World');

        $this->players = new PlayerManager($this->logger);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testPlayerEntityIdUniqueness(): void
    {
        $p1 = $this->players->create($this->authenticatedSession('conn-a', 'Player1'), $this->world);
        $p2 = $this->players->create($this->authenticatedSession('conn-b', 'Player2'), $this->world);

        self::assertNotEquals($p1->entityId, $p2->entityId);
        self::assertNotEquals($p1->runtimeId, $p2->runtimeId);
    }

    public function testPlayerUuidUniqueness(): void
    {
        $p1 = $this->players->create($this->authenticatedSession('conn-c', 'Player1'), $this->world);
        $p2 = $this->players->create($this->authenticatedSession('conn-d', 'Player2'), $this->world);

        self::assertNotEquals($p1->identity->uuid, $p2->identity->uuid);
    }

    public function testPlayerXuidUniqueness(): void
    {
        $p1 = $this->players->create($this->authenticatedSession('conn-e', 'Player1'), $this->world);
        $p2 = $this->players->create($this->authenticatedSession('conn-f', 'Player2'), $this->world);

        self::assertNotEquals($p1->identity->xuid, $p2->identity->xuid);
    }

    public function testManagerRejectsDuplicateIdentity(): void
    {
        $identity = (new PlayerIdentityFactory())->offline('DuplicateTest');

        $first = new Session('s-dup1', 'conn-dup1', '127.0.0.1:51000');
        $first->transitionTo(SessionState::AwaitingNetworkSettings);
        $first->transitionTo(SessionState::AwaitingLogin);
        $first->transitionTo(SessionState::Authenticating);
        $first->markAuthenticated($identity);
        $this->players->create($first, $this->world);

        $second = new Session('s-dup2', 'conn-dup2', '127.0.0.1:51000');
        $second->transitionTo(SessionState::AwaitingNetworkSettings);
        $second->transitionTo(SessionState::AwaitingLogin);
        $second->transitionTo(SessionState::Authenticating);
        $second->markAuthenticated($identity);

        $this->expectException(PlayerException::class);
        $this->players->create($second, $this->world);
    }

    public function testManagerRejectsDuplicateConnection(): void
    {
        $first = new Session('s-conn1', 'conn-shared', '127.0.0.1:51000');
        $first->transitionTo(SessionState::AwaitingNetworkSettings);
        $first->transitionTo(SessionState::AwaitingLogin);
        $first->transitionTo(SessionState::Authenticating);
        $first->markAuthenticated((new PlayerIdentityFactory())->offline('ConnPlayerOne'));
        $this->players->create($first, $this->world);

        $second = new Session('s-conn2', 'conn-shared', '127.0.0.1:51000');
        $second->transitionTo(SessionState::AwaitingNetworkSettings);
        $second->transitionTo(SessionState::AwaitingLogin);
        $second->transitionTo(SessionState::Authenticating);
        $second->markAuthenticated((new PlayerIdentityFactory())->offline('ConnPlayerTwo'));

        $this->expectException(PlayerException::class);
        $this->players->create($second, $this->world);
    }

    public function testRemovedPlayerIdIsNotImmediatelyReused(): void
    {
        $player = $this->players->create($this->authenticatedSession('conn-g', 'Player1'), $this->world);
        $firstId = $player->entityId;

        $this->players->remove($player);

        $next = $this->players->create($this->authenticatedSession('conn-h', 'Player2'), $this->world);

        self::assertGreaterThan($firstId, $next->entityId);
    }

    private function authenticatedSession(string $connectionId, string $name): Session
    {
        $session = new Session('s-' . $connectionId, $connectionId, '127.0.0.1:51000');
        $session->transitionTo(SessionState::AwaitingNetworkSettings);
        $session->transitionTo(SessionState::AwaitingLogin);
        $session->transitionTo(SessionState::Authenticating);
        $session->markAuthenticated((new PlayerIdentityFactory())->offline($name));

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
            is_dir($full) ? $this->removeDirectory($full) : @unlink($full);
        }

        @rmdir($path);
    }
}
