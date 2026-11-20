<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\player;

use PHPUnit\Framework\TestCase;
use PickaxeMP\auth\PlayerIdentity;
use PickaxeMP\auth\IdentityType;
use PickaxeMP\player\HealthManager;
use PickaxeMP\player\Player;
use PickaxeMP\player\PlayerInventory;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\packets\ActorEventPacket;
use PickaxeMP\protocol\packet\packets\LevelSoundEventPacket;
use PickaxeMP\protocol\packet\packets\SetHealthPacket;
use PickaxeMP\protocol\packet\types\ActorEventType;
use PickaxeMP\utils\Logger;
use PickaxeMP\world\block\BlockRegistry;
use PickaxeMP\world\generator\FlatGenerator;
use PickaxeMP\world\storage\FileWorldStorage;
use PickaxeMP\world\World;
use PickaxeMP\world\WorldProperties;

/**
 * Hasar/iyileşme geri bildirimi.
 *
 * ## Neden `ActorEvent` gerekli?
 *
 * `SetHealth` istemcinin can göstergesini günceller ama **vurulma** ve
 * **ölüm** animasyonunu/sesini tetiklemez. Vanilla sunucu bu olayları
 * `ActorEvent(Hurt/Death)` ve `LevelSoundEvent` ile bildirir; yoksa oyuncu
 * hasarı yalnızca sayı değişimi olarak görür.
 */
final class HealthManagerTest extends TestCase
{
    private World $world;

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'pickaxe-health-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0755, true);

        $registry = BlockRegistry::withPhase4Blocks();

        $this->world = new World(
            new WorldProperties(
                name: 'test',
                displayName: 'Test World',
                seed: 1,
                generator: 'flat',
                dimension: 'overworld',
                spawnX: 8.5,
                spawnY: -57.0,
                spawnZ: 8.5
            ),
            new FileWorldStorage($this->root, $registry->state('minecraft:air')->getNetworkId()),
            new FlatGenerator($registry)
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
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

                continue;
            }

            @unlink($full);
        }

        @rmdir($path);
    }

    public function testDamageSendsSetHealthAndHurtFeedback(): void
    {
        $manager = new HealthManager($this->logger());
        $player = $this->player();

        /** @var list<PacketInterface> $sent */
        $sent = [];
        $applied = $manager->damage($player, 3, static function (PacketInterface $packet) use (&$sent): void {
            $sent[] = $packet;
        });

        self::assertSame(3, $applied);

        $health = array_values(array_filter(
            $sent,
            static fn (PacketInterface $packet): bool => $packet instanceof SetHealthPacket
        ));
        self::assertCount(1, $health);
        self::assertSame(Player::MAX_HEALTH - 3, $health[0]->health);

        $events = $this->eventsOf($sent, ActorEventType::Hurt);
        self::assertCount(1, $events);
        self::assertSame($player->runtimeId, $events[0]->targetRuntimeId);

        self::assertCount(1, $this->soundsOf($sent, 'hurt'));
    }

    public function testLethalDamageSendsDeathFeedbackInsteadOfHurt(): void
    {
        $manager = new HealthManager($this->logger());
        $player = $this->player();

        /** @var list<PacketInterface> $sent */
        $sent = [];
        $manager->damage($player, Player::MAX_HEALTH, static function (PacketInterface $packet) use (&$sent): void {
            $sent[] = $packet;
        });

        self::assertTrue($player->isDead());

        // Olumde yalnizca Death gonderilir: Hurt + Death birlikte gonderilirse
        // istemci once vurulma sonra olum animasyonu oynatir (kisa titreme).
        self::assertCount(0, $this->eventsOf($sent, ActorEventType::Hurt));
        self::assertCount(1, $this->eventsOf($sent, ActorEventType::Death));
        self::assertCount(0, $this->soundsOf($sent, 'hurt'));

        $deathSounds = $this->soundsOf($sent, 'death');
        self::assertCount(1, $deathSounds);
        self::assertSame('minecraft:player', $deathSounds[0]->actorIdentifier);
    }

    public function testZeroDamageSendsNothing(): void
    {
        $manager = new HealthManager($this->logger());
        $player = $this->player();

        // Zaten olu oyuncuya hasar uygulanmaz; paket de uretilmemeli.
        $player->damage(Player::MAX_HEALTH);

        $sent = [];
        $applied = $manager->damage($player, 2, static function (PacketInterface $packet) use (&$sent): void {
            $sent[] = $packet;
        });

        self::assertSame(0, $applied);
        self::assertSame([], $sent);
    }

    public function testHealSendsOnlySetHealth(): void
    {
        $manager = new HealthManager($this->logger());
        $player = $this->player();
        $player->damage(4);

        $sent = [];
        self::assertSame(2, $manager->heal($player, 2, static function (PacketInterface $packet) use (&$sent): void {
            $sent[] = $packet;
        }));

        self::assertCount(1, $sent);
        self::assertInstanceOf(SetHealthPacket::class, $sent[0]);
        self::assertSame(Player::MAX_HEALTH - 2, $sent[0]->health);
    }

    /**
     * @param list<PacketInterface> $packets
     *
     * @return list<ActorEventPacket>
     */
    private function eventsOf(array $packets, ActorEventType $type): array
    {
        return array_values(array_filter(
            $packets,
            static fn (PacketInterface $packet): bool => $packet instanceof ActorEventPacket
                && $packet->event === $type
        ));
    }

    /**
     * @param list<PacketInterface> $packets
     *
     * @return list<LevelSoundEventPacket>
     */
    private function soundsOf(array $packets, string $sound): array
    {
        return array_values(array_filter(
            $packets,
            static fn (PacketInterface $packet): bool => $packet instanceof LevelSoundEventPacket
                && $packet->soundEvent === $sound
        ));
    }

    private function logger(): Logger
    {
        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);

        return new Logger('health-test', $stream, Logger::DEBUG);
    }

    private function player(): Player
    {
        $player = new Player(
            identity: new PlayerIdentity(
                xuid: 'xuid-health',
                uuid: '00000000-0000-0000-0000-000000000042',
                displayName: 'HealthTester',
                type: IdentityType::Offline,
                verifiedAt: time()
            ),
            sessionId: 'session-health',
            connectionId: 'connection-health',
            entityId: 42,
            runtimeId: 42,
            world: $this->world,
            x: 8.5,
            y: -57.0,
            z: 8.5,
            inventory: new PlayerInventory('', '00000000-0000-0000-0000-000000000042')
        );

        $player->markSpawned();

        return $player;
    }
}
