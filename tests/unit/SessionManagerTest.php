<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit;

use PHPUnit\Framework\TestCase;
use PickaxeMP\auth\PlayerIdentity;
use PickaxeMP\auth\PlayerIdentityFactory;
use PickaxeMP\auth\SkinData;
use PickaxeMP\session\Session;
use PickaxeMP\session\SessionException;
use PickaxeMP\session\SessionManager;
use PickaxeMP\session\SessionState;
use PickaxeMP\utils\Logger;

final class SessionManagerTest extends TestCase
{
    private function logger(): Logger
    {
        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);

        return new Logger('session-test', $stream, Logger::DEBUG);
    }

    private function manager(int $timeout = 300): SessionManager
    {
        return new SessionManager($this->logger(), $timeout);
    }

    private function identity(string $name = 'Steve'): PlayerIdentity
    {
        return (new PlayerIdentityFactory())->offline($name);
    }

    public function testCreatesSession(): void
    {
        $manager = $this->manager();
        $session = $manager->create('conn-1', '127.0.0.1:1234');

        self::assertSame(SessionState::Connecting, $session->getState());
        self::assertSame(1, $manager->count());
        self::assertSame($session, $manager->byConnection('conn-1'));
    }

    public function testRejectsDuplicateConnection(): void
    {
        $manager = $this->manager();
        $manager->create('conn-1', '127.0.0.1:1234');

        $this->expectException(SessionException::class);
        $manager->create('conn-1', '127.0.0.1:9999');
    }

    public function testFullHappyPathTransitions(): void
    {
        $manager = $this->manager();
        $session = $manager->create('conn-1', '127.0.0.1:1234');

        $session->transitionTo(SessionState::AwaitingNetworkSettings);
        $session->transitionTo(SessionState::AwaitingLogin);
        $session->transitionTo(SessionState::Authenticating);
        $manager->bindIdentity($session, $this->identity());
        $session->transitionTo(SessionState::Initializing);
        $session->transitionTo(SessionState::Playing);

        self::assertTrue($session->isPlaying());
        self::assertTrue($session->isAuthenticated());
        self::assertSame(1, $manager->playerCount());
    }

    public function testCannotReachAuthenticatedWithoutIdentity(): void
    {
        $manager = $this->manager();
        $session = $manager->create('conn-1', '127.0.0.1:1234');

        $session->transitionTo(SessionState::AwaitingNetworkSettings);
        $session->transitionTo(SessionState::AwaitingLogin);
        $session->transitionTo(SessionState::Authenticating);

        $this->expectException(SessionException::class);
        $session->transitionTo(SessionState::Authenticated);
    }

    public function testCannotReachPlayingWithoutIdentity(): void
    {
        $manager = $this->manager();
        $session = $manager->create('conn-1', '127.0.0.1:1234');

        $session->transitionTo(SessionState::AwaitingNetworkSettings);
        $session->transitionTo(SessionState::AwaitingLogin);
        $session->transitionTo(SessionState::Authenticating);

        $this->expectException(SessionException::class);
        $session->transitionTo(SessionState::Playing);
    }

    public function testIllegalTransitionIsRejected(): void
    {
        $manager = $this->manager();
        $session = $manager->create('conn-1', '127.0.0.1:1234');

        $this->expectException(SessionException::class);
        $session->transitionTo(SessionState::Playing);
    }

    public function testTerminalStateAcceptsNoTransitions(): void
    {
        self::assertSame([], SessionState::Closed->allowedNext());
        self::assertSame([], SessionState::Failed->allowedNext());
        self::assertTrue(SessionState::Closed->isTerminal());
        self::assertTrue(SessionState::Failed->isTerminal());
    }

    public function testDuplicatePlayerIsRejected(): void
    {
        $manager = $this->manager();
        $first = $manager->create('conn-1', '127.0.0.1:1');
        $second = $manager->create('conn-2', '127.0.0.1:2');

        $first->transitionTo(SessionState::AwaitingNetworkSettings);
        $first->transitionTo(SessionState::AwaitingLogin);
        $first->transitionTo(SessionState::Authenticating);
        $one = $this->identity('SamePlayer');
        $manager->bindIdentity($first, $one);

        $second->transitionTo(SessionState::AwaitingNetworkSettings);
        $second->transitionTo(SessionState::AwaitingLogin);
        $second->transitionTo(SessionState::Authenticating);

        $this->expectException(SessionException::class);
        $manager->bindIdentity($second, $this->identity('SamePlayer'));
    }

    public function testSessionErrorDoesNotAffectOthers(): void
    {
        $manager = $this->manager();
        $a = $manager->create('conn-a', '127.0.0.1:1');
        $b = $manager->create('conn-b', '127.0.0.1:2');

        // a başarısız olsun.
        $a->markAuthenticationFailed('test hatasi');

        self::assertSame(SessionState::Failed, $a->getState());
        self::assertSame(SessionState::Connecting, $b->getState());
        self::assertSame(2, $manager->count());
    }

    public function testAuthenticationFailureRespectsStateMachine(): void
    {
        $manager = $this->manager();
        $session = $manager->create('conn-closed', '127.0.0.1:9');

        $manager->close($session->id, 'kapandi');

        // `Closed` terminal durumdan `Failed`'a geçilemez.
        $this->expectException(SessionException::class);
        $session->markAuthenticationFailed('gec kalsiz');
    }

    public function testAuthenticationFailureIsIdempotent(): void
    {
        $manager = $this->manager();
        $session = $manager->create('conn-twice', '127.0.0.1:10');

        $session->markAuthenticationFailed('ilk neden');
        $session->markAuthenticationFailed('ikinci neden');

        self::assertSame(SessionState::Failed, $session->getState());
        self::assertSame('ikinci neden', $session->closeReason());
    }

    public function testAuthenticatedIdentitySurvivesLaterFailure(): void
    {
        $manager = $this->manager();
        $identity = $this->identity('Keeper');
        $session = $manager->create('conn-keep', '127.0.0.1:11');

        $session->transitionTo(SessionState::AwaitingNetworkSettings);
        $session->transitionTo(SessionState::AwaitingLogin);
        $session->transitionTo(SessionState::Authenticating);
        $manager->bindIdentity($session, $identity);

        $session->markAuthenticationFailed('sonradan basarisiz oldu');

        // Kimlik `markAuthenticated()` ile atanmıştı; oturum kapanana kadar
        // SessionManager indeksiyle tutarlı kalmalı.
        self::assertSame($identity, $session->getIdentity());
        self::assertSame($session, $manager->byXuid($identity->xuid));
        self::assertSame(1, $manager->playerCount());
    }

    public function testCloseRemovesSession(): void
    {
        $manager = $this->manager();
        $session = $manager->create('conn-1', '127.0.0.1:1');
        $id = $session->id;

        $manager->close($id, 'test');

        self::assertSame(0, $manager->count());
        self::assertNull($manager->get($id));
        self::assertNull($manager->byConnection('conn-1'));
    }

    public function testCloseIsIdempotent(): void
    {
        $manager = $this->manager();
        $session = $manager->create('conn-1', '127.0.0.1:1');

        $manager->close($session->id, 'bir');
        $manager->close($session->id, 'iki');

        self::assertSame(0, $manager->count());
    }

    public function testClosingUnknownSessionIsNoOp(): void
    {
        $manager = $this->manager();
        $manager->close('olmayan-id', 'x');

        self::assertSame(0, $manager->count());
    }

    public function testConnectionLostClosesSession(): void
    {
        $manager = $this->manager();
        $session = $manager->create('conn-1', '127.0.0.1:1');

        $manager->onConnectionLost('conn-1');

        self::assertSame(0, $manager->count());
        self::assertTrue($session->isTerminal());
    }

    public function testConnectionLostForUnknownConnectionIsNoOp(): void
    {
        $manager = $this->manager();
        $manager->onConnectionLost('bilinmeyen');

        self::assertSame(0, $manager->count());
    }

    public function testReapExpiredClosesIdleSessions(): void
    {
        $now = 1_000_000;
        $manager = $this->manager(60);
        $session = $manager->create('conn-1', '127.0.0.1:1', $now);

        // Zaman aşımı içinde.
        self::assertSame(0, $manager->reapExpired($now + 30));
        self::assertSame(1, $manager->count());

        // Zaman aşımı dışında.
        self::assertSame(1, $manager->reapExpired($now + 500));
        self::assertSame(0, $manager->count());
        self::assertTrue($session->isTerminal());
    }

    public function testTimeoutZeroDisablesReaping(): void
    {
        $manager = $this->manager(0);
        $manager->create('conn-1', '127.0.0.1:1', 0);

        self::assertSame(0, $manager->reapExpired(PHP_INT_MAX >> 1));
        self::assertSame(1, $manager->count());
    }

    public function testCloseAllClosesEverything(): void
    {
        $manager = $this->manager();
        $manager->create('c1', 'a');
        $manager->create('c2', 'b');
        $manager->create('c3', 'c');

        self::assertSame(3, $manager->closeAll());
        self::assertSame(0, $manager->count());
    }

    public function testPlayingListOnlyIncludesPlaying(): void
    {
        $manager = $this->manager();
        $session = $manager->create('conn-1', '127.0.0.1:1');
        $session->transitionTo(SessionState::AwaitingNetworkSettings);
        $session->transitionTo(SessionState::AwaitingLogin);
        $session->transitionTo(SessionState::Authenticating);
        $manager->bindIdentity($session, $this->identity());
        $session->transitionTo(SessionState::Initializing);
        $session->transitionTo(SessionState::Playing);

        $other = $manager->create('conn-2', '127.0.0.1:2');

        self::assertCount(1, $manager->playing());
        self::assertSame($session, $manager->playing()[0]);
        self::assertSame(2, $manager->count());
        self::assertNotNull($other);
    }

    public function testUnknownSessionThrows(): void
    {
        $this->expectException(SessionException::class);
        $this->manager()->require('yok');
    }

    public function testByXuidFindsAuthenticatedSession(): void
    {
        $manager = $this->manager();
        $session = $manager->create('conn-1', '127.0.0.1:1');
        $identity = $this->identity('Finder');

        $session->transitionTo(SessionState::AwaitingNetworkSettings);
        $session->transitionTo(SessionState::AwaitingLogin);
        $session->transitionTo(SessionState::Authenticating);
        $manager->bindIdentity($session, $identity);

        self::assertSame($session, $manager->byXuid($identity->xuid));
    }

    public function testFailedAuthenticatedSessionDoesNotLockXuidForever(): void
    {
        $manager = $this->manager();
        $identity = $this->identity('Reconnect');

        $first = $manager->create('conn-1', '127.0.0.1:1');
        $first->transitionTo(SessionState::AwaitingNetworkSettings);
        $first->transitionTo(SessionState::AwaitingLogin);
        $first->transitionTo(SessionState::Authenticating);
        $manager->bindIdentity($first, $identity);
        $first->markAuthenticationFailed('kimlik sonradan basarisiz oldu');

        $second = $manager->create('conn-2', '127.0.0.1:2');
        $second->transitionTo(SessionState::AwaitingNetworkSettings);
        $second->transitionTo(SessionState::AwaitingLogin);
        $second->transitionTo(SessionState::Authenticating);

        $manager->bindIdentity($second, $identity);

        self::assertSame(1, $manager->playerCount());
        self::assertSame($second, $manager->byXuid($identity->xuid));
    }

    public function testBindIdentityRejectsSessionNotOwnedByManager(): void
    {
        $manager = $this->manager();
        $external = new Session('external', 'conn-external', '127.0.0.1:3');
        $external->transitionTo(SessionState::AwaitingNetworkSettings);
        $external->transitionTo(SessionState::AwaitingLogin);
        $external->transitionTo(SessionState::Authenticating);

        $this->expectException(SessionException::class);
        $manager->bindIdentity($external, $this->identity('External'));
    }

    public function testClosingFreesXuidForReconnect(): void
    {
        $manager = $this->manager();
        $session = $manager->create('conn-1', '127.0.0.1:1');
        $identity = $this->identity('Reconnect');

        $session->transitionTo(SessionState::AwaitingNetworkSettings);
        $session->transitionTo(SessionState::AwaitingLogin);
        $session->transitionTo(SessionState::Authenticating);
        $manager->bindIdentity($session, $identity);

        $manager->close($session->id, 'test');

        // Aynı oyuncu yeniden bağlanabilmeli.
        $second = $manager->create('conn-2', '127.0.0.1:2');
        $second->transitionTo(SessionState::AwaitingNetworkSettings);
        $second->transitionTo(SessionState::AwaitingLogin);
        $second->transitionTo(SessionState::Authenticating);

        $manager->bindIdentity($second, $identity);

        self::assertSame(1, $manager->playerCount());
        self::assertSame($second, $manager->byXuid($identity->xuid));
    }

    public function testBindIdentityCarriesValidatedSkin(): void
    {
        $manager = $this->manager();
        $session = $manager->create('conn-1', '127.0.0.1:1');

        $session->transitionTo(SessionState::AwaitingNetworkSettings);
        $session->transitionTo(SessionState::AwaitingLogin);
        $session->transitionTo(SessionState::Authenticating);

        $skin = new SkinData('custom.skin', 'ZmFrZQ==', '', '', '');
        $manager->bindIdentity($session, $this->identity('Skinny'), null, $skin);

        self::assertSame($skin, $session->getSkin());
    }
}
