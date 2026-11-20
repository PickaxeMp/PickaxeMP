<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\player;

use PHPUnit\Framework\TestCase;
use PickaxeMP\auth\PlayerIdentityFactory;
use PickaxeMP\player\GameMode;
use PickaxeMP\player\Player;
use PickaxeMP\player\PlayerInventory;
use PickaxeMP\player\PlayerMetadata;
use PickaxeMP\protocol\packet\types\SynchedActorDataEntry;
use PickaxeMP\world\block\BlockRegistry;
use PickaxeMP\world\generator\FlatGenerator;
use PickaxeMP\world\storage\FileWorldStorage;
use PickaxeMP\world\World;
use PickaxeMP\world\WorldProperties;

/**
 * Isim etiketi meta verisi.
 *
 * ## Neden bu testler var?
 *
 * `Entity Data` bos gonderildiginde istemci diger oyunculatin **adini
 * gostermez**; oyuncu listesinde adi gorunur ama dunyada etiket yoktur.
 * Meta veri kimlikleri ve bayrak bitleri yanlis olursa istemci paketi
 * reddeder (baglanti duser) ya da etiket hic gorunmez.
 *
 * Kimlikler pmmp `ActorDataIds` ile sabitlendi: `FLAGS = 0`, `NAMETAG = 4`.
 * Bayraklar ayni `FLAGS` int64'u icinde bit 14/15'tir
 * (`CAN_SHOW_NAMETAG` / `ALWAYS_SHOW_NAMETAG`).
 */
final class PlayerMetadataTest extends TestCase
{
    private string $root;

    private World $world;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'pickaxe-meta-' . bin2hex(random_bytes(6));
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

    public function testNameTagCarriesDisplayName(): void
    {
        $data = PlayerMetadata::forPlayer($this->player('Steve'));

        $nameTag = $data->get(PlayerMetadata::DATA_NAMETAG);

        self::assertInstanceOf(SynchedActorDataEntry::class, $nameTag);
        self::assertSame(SynchedActorDataEntry::TYPE_STRING, $nameTag->type);
        self::assertSame('Steve', $nameTag->value);
    }

    public function testFlagsEnableNameTagVisibility(): void
    {
        $data = PlayerMetadata::forPlayer($this->player('Alex'));

        $flags = $data->get(PlayerMetadata::DATA_FLAGS);

        self::assertInstanceOf(SynchedActorDataEntry::class, $flags);
        self::assertSame(SynchedActorDataEntry::TYPE_INT64, $flags->type);
        self::assertIsInt($flags->value);

        self::assertNotSame(
            0,
            $flags->value & PlayerMetadata::FLAG_CAN_SHOW_NAMETAG,
            'CAN_SHOW_NAMETAG olmadan istemci etiketi cizmez'
        );
        self::assertNotSame(
            0,
            $flags->value & PlayerMetadata::FLAG_ALWAYS_SHOW_NAMETAG,
            'ALWAYS_SHOW_NAMETAG olmadan etiket yalnizca cok yakinda gorunur'
        );
    }

    public function testMetadataIdsMatchBedrockConstants(): void
    {
        self::assertSame(0, PlayerMetadata::DATA_FLAGS);
        self::assertSame(4, PlayerMetadata::DATA_NAMETAG);
        self::assertSame(1 << 14, PlayerMetadata::FLAG_CAN_SHOW_NAMETAG);
        self::assertSame(1 << 15, PlayerMetadata::FLAG_ALWAYS_SHOW_NAMETAG);
    }

    public function testMetadataKeepsOtherFlagBitsClear(): void
    {
        $data = PlayerMetadata::forPlayer($this->player('Herobrine'));
        $flags = $data->get(PlayerMetadata::DATA_FLAGS);

        self::assertInstanceOf(SynchedActorDataEntry::class, $flags);

        // Yalnizca iki bayrak acik olmali; yanlislikla 1. bit (sneaking),
        // 0. bit (onfire) veya 5. bit (invisible) acilirsa oyuncu yanlis
        // gorunur.
        self::assertSame(
            PlayerMetadata::FLAG_CAN_SHOW_NAMETAG | PlayerMetadata::FLAG_ALWAYS_SHOW_NAMETAG,
            $flags->value
        );
    }

    public function testMultibyteNameIsTruncatedToProtocolByteLimit(): void
    {
        // 64 karakter gecerli (PlayerIdentityFactory::MAX_NAME_LENGTH) ama
        // her karakter 3 bayt: 192 bayt > 128 bayt tel siniri.
        $long = str_repeat('世', 64);
        self::assertGreaterThan(PlayerMetadata::MAX_NAMETAG_LENGTH, strlen($long));

        $data = PlayerMetadata::forPlayer($this->player($long));

        $nameTag = $data->get(PlayerMetadata::DATA_NAMETAG);

        self::assertInstanceOf(SynchedActorDataEntry::class, $nameTag);
        self::assertLessThanOrEqual(PlayerMetadata::MAX_NAMETAG_LENGTH, strlen((string) $nameTag->value));
        self::assertNotSame('', (string) $nameTag->value);
        self::assertSame(
            (string) $nameTag->value,
            mb_convert_encoding((string) $nameTag->value, 'UTF-8', 'UTF-8'),
            'Kesim gecersiz UTF-8 birakmamali'
        );
    }

    public function testMetadataUsesOnlyTwoEntries(): void
    {
        $data = PlayerMetadata::forPlayer($this->player('Slim'));

        self::assertSame(2, $data->count());
    }

    private function player(string $name): Player
    {
        $identity = (new PlayerIdentityFactory())->offline($name);

        $player = new Player(
            identity: $identity,
            sessionId: 's-1',
            connectionId: 'c-1',
            entityId: 1,
            runtimeId: 1,
            world: $this->world,
            x: 8.5,
            y: -57.0,
            z: 8.5,
            inventory: new PlayerInventory($this->root, $identity->uuid)
        );
        $player->setGameMode(GameMode::Survival);

        return $player;
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
