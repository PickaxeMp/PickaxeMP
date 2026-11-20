<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\player;

use PHPUnit\Framework\TestCase;
use PickaxeMP\auth\IdentityType;
use PickaxeMP\auth\PlayerIdentity;
use PickaxeMP\player\Player;
use PickaxeMP\player\PlayerAttributes;
use PickaxeMP\player\PlayerInventory;
use PickaxeMP\player\PlayerState;
use PickaxeMP\player\PlayerSurvival;
use PickaxeMP\world\block\BlockRegistry;
use PickaxeMP\world\generator\FlatGenerator;
use PickaxeMP\world\storage\FileWorldStorage;
use PickaxeMP\world\World;
use PickaxeMP\world\WorldProperties;

/**
 * Hayatta kalma kuralları: açlıktan hasar ve doğal can yenilenmesi.
 *
 * İkisi de 80 tick'te (4 saniye) bir 1 puan uygular. Zamanlayıcılar
 * oyuncu başınadır ve koşul bozulunca sıfırlanır.
 */
final class PlayerSurvivalTest extends TestCase
{
    private string $root;

    private World $world;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pickaxe_survival_' . bin2hex(random_bytes(6));
        mkdir($this->root, 0755, true);

        $registry = BlockRegistry::withPhase4Blocks();

        $this->world = new World(
            new WorldProperties(
                name: 'test',
                displayName: 'Test World',
                seed: 424242,
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

    private function player(bool $spawned = true): Player
    {
        $player = new Player(
            identity: new PlayerIdentity(
                xuid: 'xuid-steve',
                uuid: '00000000-0000-0000-0000-000000000001',
                displayName: 'Steve',
                type: IdentityType::Offline,
                verifiedAt: time()
            ),
            sessionId: 'session-steve',
            connectionId: 'connection-steve',
            entityId: 1,
            runtimeId: 1,
            world: $this->world,
            x: 8.5,
            y: -57.0,
            z: 8.5,
            inventory: new PlayerInventory('', '00000000-0000-0000-0000-000000000001')
        );
        if ($spawned) {
            $player->markSpawned();
        }

        return $player;
    }

    /**
     * Doygunluk (5) + açlık farkı kadar tükenme harcar.
     */
    private function setHunger(Player $player, int $hunger): void
    {
        $points = PlayerAttributes::DEFAULT_HUNGER - $hunger + 5;
        $player->attributes()->consume($points * PlayerAttributes::EXHAUSTION_PER_POINT);

        self::assertSame($hunger, $player->attributes()->hunger());
    }

    public function testStarvationDamagesAfterEightyEligibleTicks(): void
    {
        $player = $this->player();
        $this->setHunger($player, 0);
        $survival = new PlayerSurvival();

        for ($i = 0; $i < PlayerSurvival::STARVATION_INTERVAL - 1; $i++) {
            self::assertSame(0, $survival->tick($player));
        }

        self::assertSame(Player::MAX_HEALTH, $player->health());
        self::assertSame(-1, $survival->tick($player));
        self::assertSame(Player::MAX_HEALTH - 1, $player->health());
    }

    public function testStarvationCanKill(): void
    {
        $player = $this->player();
        $this->setHunger($player, 0);
        $player->damage(Player::MAX_HEALTH - 1);

        $survival = new PlayerSurvival();

        for ($i = 0; $i < PlayerSurvival::STARVATION_INTERVAL - 1; $i++) {
            $survival->tick($player);
        }

        self::assertSame(-1, $survival->tick($player));
        self::assertSame(0, $player->health());
        self::assertSame(PlayerState::Dead, $player->state());

        // Ölü oyuncuya sonraki tick'lerde hasar uygulanmaz.
        self::assertSame(0, $survival->tick($player));
        self::assertSame(0, $player->health());
    }

    public function testStarvationTimerResetsAfterEating(): void
    {
        $player = $this->player();
        $this->setHunger($player, 0);
        $survival = new PlayerSurvival();

        for ($i = 0; $i < PlayerSurvival::STARVATION_INTERVAL - 1; $i++) {
            $survival->tick($player);
        }

        $player->eat(1);
        self::assertSame(0, $survival->tick($player), 'Açlık 1 iken hasar yok');

        $this->setHunger($player, 0);

        for ($i = 0; $i < PlayerSurvival::STARVATION_INTERVAL - 1; $i++) {
            self::assertSame(0, $survival->tick($player));
        }

        self::assertSame(-1, $survival->tick($player));
    }

    public function testRegenerationHealsAfterEightyTicks(): void
    {
        $player = $this->player();
        $player->damage(10);
        $survival = new PlayerSurvival();

        for ($i = 0; $i < PlayerSurvival::REGEN_INTERVAL - 1; $i++) {
            self::assertSame(0, $survival->tick($player));
        }

        self::assertSame(10, $player->health());
        self::assertSame(1, $survival->tick($player));
        self::assertSame(11, $player->health());
    }

    public function testRegenerationRequiresEighteenHunger(): void
    {
        $player = $this->player();
        $player->damage(10);
        $this->setHunger($player, PlayerSurvival::REGEN_MIN_HUNGER - 1);

        $survival = new PlayerSurvival();

        for ($i = 0; $i < PlayerSurvival::REGEN_INTERVAL * 2; $i++) {
            self::assertSame(0, $survival->tick($player));
        }

        self::assertSame(10, $player->health());
    }

    public function testRegenerationStopsAtFullHealth(): void
    {
        $player = $this->player();
        $player->damage(1);

        $survival = new PlayerSurvival();

        for ($i = 0; $i < PlayerSurvival::REGEN_INTERVAL - 1; $i++) {
            $survival->tick($player);
        }

        self::assertSame(1, $survival->tick($player));
        self::assertSame(Player::MAX_HEALTH, $player->health());

        for ($i = 0; $i < PlayerSurvival::REGEN_INTERVAL * 2; $i++) {
            self::assertSame(0, $survival->tick($player));
        }

        self::assertSame(Player::MAX_HEALTH, $player->health());
    }

    public function testDeadPlayerDoesNotRegenerate(): void
    {
        $player = $this->player();
        $player->damage(Player::MAX_HEALTH);

        $survival = new PlayerSurvival();

        for ($i = 0; $i < PlayerSurvival::REGEN_INTERVAL * 2; $i++) {
            self::assertSame(0, $survival->tick($player));
        }

        self::assertSame(0, $player->health());
        self::assertSame(PlayerState::Dead, $player->state());
    }

    /**
     * Henüz dünyaya girmemiş oyuncuya hayatta kalma kuralları uygulanmaz.
     */
    public function testJoiningPlayerIsIgnored(): void
    {
        $player = $this->player(spawned: false);
        $player->damage(10);

        self::assertSame(PlayerState::Joining, $player->state());

        $survival = new PlayerSurvival();

        for ($i = 0; $i < PlayerSurvival::REGEN_INTERVAL * 2; $i++) {
            self::assertSame(0, $survival->tick($player));
        }

        self::assertSame(10, $player->health());
    }

    public function testRegenerationTimerResetsWhenHungerFalls(): void
    {
        $player = $this->player();
        $player->damage(10);

        $survival = new PlayerSurvival();

        for ($i = 0; $i < PlayerSurvival::REGEN_INTERVAL - 1; $i++) {
            $survival->tick($player);
        }

        $this->setHunger($player, PlayerSurvival::REGEN_MIN_HUNGER - 1);
        self::assertSame(0, $survival->tick($player));

        $player->eat(1);

        for ($i = 0; $i < PlayerSurvival::REGEN_INTERVAL - 1; $i++) {
            self::assertSame(0, $survival->tick($player));
        }

        self::assertSame(1, $survival->tick($player));
    }

    public function testFullHealthyPlayerHasNoSurvivalWork(): void
    {
        $player = $this->player();
        $survival = new PlayerSurvival();

        for ($i = 0; $i < PlayerSurvival::REGEN_INTERVAL * 2; $i++) {
            self::assertSame(0, $survival->tick($player));
        }

        self::assertSame(Player::MAX_HEALTH, $player->health());
    }
}
