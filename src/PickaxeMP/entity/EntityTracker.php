<?php

declare(strict_types=1);

namespace PickaxeMP\entity;

use PickaxeMP\Entity;
use PickaxeMP\world\World;

/**
 * Alıcı başına görünürlük hesabı.
 *
 * ## Neden?
 *
 * Sunucu bir varlığı **herkese** göndermez: farklı dünyadaki veya görüş
 * mesafesi dışındaki istemciye `AddActor` göndermek, istemcide hayalet varlık
 * ve gereksiz bant genişliği üretir. Aynı şekilde "görünmeyen" bir varlığın
 * kaldırılması da alıcıya özel olmalıdır.
 *
 * Görünürlük kuralları (hepsi zorunlu):
 *
 * 1. Varlık `despawn` edilmemiş olmalı.
 * 2. Varlık ve izleyici aynı `World` örneğinde olmalı (dünya bilinmiyorsa
 *    kural uygulanmaz).
 * 3. Kare uzaklık `viewDistance²` sınırını aşmamalı.
 *
 * `viewDistance` **kare** olarak saklanır; her sorguda `sqrt()` çağırmamak
 * için sınır önceden çarpılır.
 */
final class EntityTracker
{
    private float $viewDistanceSquared;

    /** @var array<string, Entity> */
    private array $tracked = [];

    public function __construct(
        private readonly EntityManager $entities,
        float $viewDistance = 64.0
    ) {
        if (!is_finite($viewDistance) || $viewDistance <= 0.0) {
            $viewDistance = 64.0;
        }

        $this->viewDistanceSquared = $viewDistance * $viewDistance;
    }

    public function viewDistance(): float
    {
        return sqrt($this->viewDistanceSquared);
    }

    public function track(Entity $entity): void
    {
        $this->tracked[$entity->id()] = $entity;
    }

    public function untrack(string $id): void
    {
        unset($this->tracked[$id]);
    }

    public function isTracked(string $id): bool
    {
        return isset($this->tracked[$id]);
    }

    public function trackedCount(): int
    {
        return count($this->tracked);
    }

    /**
     * İzleyicinin görebildiği varlıklar.
     *
     * @param array{x: float, y: float, z: float} $position
     *
     * @return list<Entity>
     */
    public function visibleTo(string $viewerId, array $position, ?World $world = null): array
    {
        $visible = [];

        foreach ($this->tracked as $id => $entity) {
            if ($id === $viewerId) {
                continue;
            }

            if ($this->isVisibleTo($entity, $position, $world)) {
                $visible[] = $entity;
            }
        }

        return $visible;
    }

    /**
     * @param array{x: float, y: float, z: float} $position
     */
    public function isVisibleTo(Entity $entity, array $position, ?World $world = null): bool
    {
        if ($entity->isDespawned() || !$this->isTracked($entity->id())) {
            return false;
        }

        $entityWorld = $entity->world();

        if ($world !== null && $entityWorld !== null && $entityWorld !== $world) {
            return false;
        }

        return $entity->distanceSquaredTo($position['x'], $position['y'], $position['z'])
            <= $this->viewDistanceSquared;
    }

    public function countVisibleTo(string $viewerId, array $position, ?World $world = null): int
    {
        return count($this->visibleTo($viewerId, $position, $world));
    }

    /**
     * Emekliye ayrılmış (eski nesil) bir varlık kimliğine ait paket reddedilmeli.
     */
    public function isStale(string $entityId): bool
    {
        return $this->entities->isRetired($entityId);
    }
}
