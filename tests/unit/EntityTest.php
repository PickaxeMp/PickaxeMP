<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit;

use PHPUnit\Framework\TestCase;
use PickaxeMP\Entity;
use PickaxeMP\player\PlayerException;

/**
 * Entity yaşam döngüsü ve doğrulama sözleşmesi.
 *
 * Konum sınırları `Player` ile aynıdır: Y ∈ [-64, 319]; sonlu olmayan değer
 * reddedilir.
 */
final class EntityTest extends TestCase
{
    private string $root;
    private string $id;
    private Entity $entity;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'pickaxe-entity-' . bin2hex(random_bytes(4));
        mkdir($this->root, 0755, true);
        $this->id = 'test-entity-' . bin2hex(random_bytes(8));
        $this->entity = new Entity($this->id, $this->root);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testEntityInitialization(): void
    {
        self::assertSame($this->id, $this->entity->id());
        self::assertFalse($this->entity->hasIdentity());
        self::assertFalse($this->entity->isDespawned());
        self::assertSame(Entity::TYPE_GENERIC, $this->entity->type());
    }

    public function testEntityDespawn(): void
    {
        $this->entity->despawn();
        self::assertTrue($this->entity->isDespawned());
    }

    public function testEntityRespawn(): void
    {
        $this->entity->despawn();
        $this->entity->respawn();
        self::assertFalse($this->entity->isDespawned());
    }

    public function testEntitySetPosition(): void
    {
        $this->entity->setPosition(100.5, 64.0, -200.3);
        $pos = $this->entity->position();
        self::assertEqualsWithDelta(100.5, $pos['x'], 0.0001);
        self::assertEqualsWithDelta(64.0, $pos['y'], 0.0001);
        self::assertEqualsWithDelta(-200.3, $pos['z'], 0.0001);
    }

    public function testEntitySetPositionInvalidThrows(): void
    {
        $this->expectException(PlayerException::class);
        $this->entity->setPosition(0.0, -100.0, 0.0); // min Y altı
    }

    public function testEntitySetPositionAboveMaxThrows(): void
    {
        $this->expectException(PlayerException::class);
        $this->entity->setPosition(0.0, 320.0, 0.0);
    }

    public function testEntityNonFinitePositionThrows(): void
    {
        $this->expectException(PlayerException::class);
        $this->entity->setPosition(NAN, 64.0, 0.0);
    }

    public function testEntitySetRotation(): void
    {
        $this->entity->setRotation(0.5, 180.0);
        self::assertEqualsWithDelta(0.5, $this->entity->pitch(), 0.0001);
        self::assertEqualsWithDelta(180.0, $this->entity->yaw(), 0.0001);
    }

    public function testEntityLifecycleSequence(): void
    {
        self::assertFalse($this->entity->isDespawned());
        $this->entity->despawn();
        self::assertTrue($this->entity->isDespawned());
        $this->entity->respawn();
        self::assertFalse($this->entity->isDespawned());
    }

    public function testEntityIdUniqueness(): void
    {
        $e1 = new Entity('unique-' . bin2hex(random_bytes(8)), $this->root);
        $e2 = new Entity('unique-' . bin2hex(random_bytes(8)), $this->root);
        self::assertNotEquals($e1->id(), $e2->id());
    }

    public function testEntityDespawnIdempotent(): void
    {
        $this->entity->despawn();
        $this->entity->despawn(); // istisna fırlatmamalı
        self::assertTrue($this->entity->isDespawned());
    }

    public function testRespawnWhenAliveIsNoOp(): void
    {
        $this->entity->respawn();
        self::assertFalse($this->entity->isDespawned());
    }

    public function testBindIdentityOnce(): void
    {
        $this->entity->bindIdentity(7, 70, 1);
        self::assertSame(7, $this->entity->entityId());
        self::assertSame(70, $this->entity->runtimeId());
        self::assertTrue($this->entity->hasIdentity());

        $this->expectException(PlayerException::class);
        $this->entity->bindIdentity(8, 80, 1);
    }

    public function testEmptyIdThrows(): void
    {
        $this->expectException(PlayerException::class);
        new Entity('', $this->root);
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
