<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit;

use PHPUnit\Framework\TestCase;
use PickaxeMP\Entity;
use PickaxeMP\entity\EntityManager;
use PickaxeMP\entity\EntityTracker;
use PickaxeMP\player\PlayerException;
use PickaxeMP\utils\Logger;
use PickaxeMP\world\World;
use PickaxeMP\world\WorldManager;
use PickaxeMP\world\block\BlockRegistry;
use PickaxeMP\world\generator\FlatGenerator;
use PickaxeMP\world\storage\FileWorldStorage;

/**
 * Varlık izleyicisi: yaşam döngüsü + alıcı başına görünürlük.
 *
 * Görünürlük kuralları: despawn edilmemiş + aynı dünya + görüş mesafesi.
 */
final class TrackerVisibilityTest extends TestCase
{
    private string $root;
    private Logger $logger;
    private World $world;
    private Entity $entity;
    private EntityTracker $tracker;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'pickaxe-tracker-' . bin2hex(random_bytes(4));
        mkdir($this->root, 0755, true);

        $stream = fopen('php://memory', 'w+b');
        $this->logger = new Logger('tracker-test', $stream, Logger::DEBUG);

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

        $this->entity = new Entity('tracker-test-entity', $this->root, $this->world);
        $this->tracker = new EntityTracker(new EntityManager());
        $this->entity->bindIdentity(1, 1, 1);
        $this->tracker->track($this->entity);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testEntityDespawnHidesFromTracker(): void
    {
        self::assertFalse($this->entity->isDespawned());
        self::assertCount(1, $this->tracker->visibleTo('viewer', ['x' => 0.0, 'y' => 0.0, 'z' => 0.0], $this->world));

        $this->entity->despawn();

        self::assertTrue($this->entity->isDespawned());
        self::assertCount(0, $this->tracker->visibleTo('viewer', ['x' => 0.0, 'y' => 0.0, 'z' => 0.0], $this->world));
    }

    public function testEntityDespawnIdempotent(): void
    {
        $this->entity->despawn();
        $this->entity->despawn(); // istisna fırlatmamalı, çift kaldırma yok
        self::assertTrue($this->entity->isDespawned());
        self::assertSame(1, $this->tracker->trackedCount());
    }

    public function testEntityRespawnReappearsInTracker(): void
    {
        $this->entity->despawn();
        self::assertTrue($this->entity->isDespawned());

        $this->entity->respawn();

        self::assertFalse($this->entity->isDespawned());
        self::assertCount(1, $this->tracker->visibleTo('viewer', ['x' => 0.0, 'y' => 0.0, 'z' => 0.0], $this->world));
    }

    public function testEntityPositionAfterRespawn(): void
    {
        $this->entity->despawn();
        $this->entity->setPosition(100.0, 64.0, 200.0);
        $this->entity->respawn();

        $pos = $this->entity->position();
        self::assertEqualsWithDelta(100.0, $pos['x'], 0.0001);
        self::assertEqualsWithDelta(64.0, $pos['y'], 0.0001);
        self::assertEqualsWithDelta(200.0, $pos['z'], 0.0001);
    }

    public function testEntitySetRotationAfterRespawn(): void
    {
        $this->entity->despawn();
        $this->entity->setRotation(0.5, 180.0);
        $this->entity->respawn();

        self::assertEqualsWithDelta(0.5, $this->entity->pitch(), 0.0001);
        self::assertEqualsWithDelta(180.0, $this->entity->yaw(), 0.0001);
    }

    public function testEntityInvalidPositionBelowMinYThrows(): void
    {
        $this->expectException(PlayerException::class);
        $this->entity->setPosition(0.0, -100.0, 0.0);
    }

    public function testEntityInvalidPositionAboveMaxYThrows(): void
    {
        $this->expectException(PlayerException::class);
        $this->entity->setPosition(0.0, 1000.0, 0.0);
    }

    public function testEntityOutsideViewDistanceIsHidden(): void
    {
        $this->entity->setPosition(1000.0, 0.0, 1000.0);

        self::assertCount(0, $this->tracker->visibleTo('viewer', ['x' => 0.0, 'y' => 0.0, 'z' => 0.0], $this->world));
    }

    public function testEntityInDifferentWorldIsHidden(): void
    {
        $worlds = new WorldManager(
            $this->logger,
            $this->root,
            0,
            new FileWorldStorage($this->root, 0)
        );
        $other = $worlds->loadOrCreate('other', 2, 'Other World');

        self::assertCount(0, $this->tracker->visibleTo('viewer', ['x' => 0.0, 'y' => 0.0, 'z' => 0.0], $other));
    }

    public function testViewerItselfIsNotVisible(): void
    {
        self::assertCount(0, $this->tracker->visibleTo($this->entity->id(), ['x' => 0.0, 'y' => 0.0, 'z' => 0.0], $this->world));
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
