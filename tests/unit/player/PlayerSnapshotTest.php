<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\player;

use PHPUnit\Framework\TestCase;
use PickaxeMP\auth\PlayerIdentityFactory;
use PickaxeMP\auth\SkinData;
use PickaxeMP\player\GameMode;
use PickaxeMP\player\Player;
use PickaxeMP\player\PlayerAbilities;
use PickaxeMP\player\PlayerInventory;
use PickaxeMP\player\PlayerSnapshot;
use PickaxeMP\Tests\support\PngFixture;
use PickaxeMP\world\block\BlockRegistry;
use PickaxeMP\world\generator\FlatGenerator;
use PickaxeMP\world\storage\FileWorldStorage;
use PickaxeMP\world\World;
use PickaxeMP\world\WorldProperties;

/**
 * `PlayerSnapshot` gercek deriyi `PlayerList` girisinde tasimali.
 *
 * Deri cozumu `SkinSerializer`'da test ediliyor; burada **baglanti**
 * sinaniyor: `Player::$skin` alani liste girişine gercekten geciyor mu?
 */
final class PlayerSnapshotTest extends TestCase
{
    private string $root;

    private World $world;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'pickaxe-snapshot-' . bin2hex(random_bytes(6));
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

    public function testPlayerListEntryCarriesDecodedSkin(): void
    {
        $rows = [[[12, 34, 56, 255], [78, 90, 12, 255]]];
        $skin = new SkinData(
            'custom.skin',
            base64_encode(PngFixture::encode($rows)),
            '',
            'geometry.humanoid.custom',
            base64_encode('{"format_version":"1.12.0"}')
        );

        $player = $this->player($skin);
        $entry = PlayerSnapshot::playerListEntry($player);

        self::assertSame('custom.skin', $entry->skin->id);
        self::assertSame(2, $entry->skin->image->width);
        self::assertSame(1, $entry->skin->image->height);
        self::assertSame(PngFixture::toRgba($rows), $entry->skin->image->data);
        self::assertSame('{"format_version":"1.12.0"}', $entry->skin->geometryData);
    }

    public function testPlayerWithoutSkinFallsBackToBlank(): void
    {
        $entry = PlayerSnapshot::playerListEntry($this->player(null));

        self::assertSame(0, $entry->skin->image->width);
        self::assertSame('', $entry->skin->image->data);
    }

    public function testGameModeTransitionControlsFlight(): void
    {
        $player = $this->player(null);

        self::assertFalse($player->setFlying(true), 'Hayatta kalmada ucus kapali olmali');
        self::assertFalse($player->isFlying());

        $player->setGameMode(GameMode::Creative);
        self::assertTrue($player->setFlying(true));
        self::assertTrue($player->isFlying());

        $abilities = $player->abilityData();
        self::assertNotSame(0, $abilities->layers[0]->abilitiesSet & PlayerAbilities::ABILITY_FLYING);

        $player->setGameMode(GameMode::Survival);
        self::assertFalse($player->isFlying(), 'Mod dusunce ucus kapanmali');
    }

    public function testAddPlayerCarriesCurrentGameMode(): void
    {
        $player = $this->player(null);
        $player->setGameMode(GameMode::Adventure);

        $packet = PlayerSnapshot::addPlayer($player);

        self::assertSame(GameMode::Adventure->value, $packet->playerGameType);
        self::assertSame(PlayerAbilities::ADVENTURE_ABILITIES, $packet->abilitiesData->layers[0]->abilitiesSet);
    }

    private function player(?SkinData $skin): Player
    {
        $identity = (new PlayerIdentityFactory())->offline('SnapshotPlayer');

        return new Player(
            identity: $identity,
            sessionId: 's-1',
            connectionId: 'c-1',
            entityId: 1,
            runtimeId: 1,
            world: $this->world,
            x: 8.5,
            y: -57.0,
            z: 8.5,
            inventory: new PlayerInventory($this->root, $identity->uuid),
            skin: $skin
        );
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
