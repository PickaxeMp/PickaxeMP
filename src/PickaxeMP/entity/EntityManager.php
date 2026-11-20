<?php

declare(strict_types=1);

namespace PickaxeMP\entity;

use PickaxeMP\Entity;
use PickaxeMP\player\PlayerException;
use PickaxeMP\world\World;

/**
 * Varlık kimliklerinin ve kayıtlarının tek sahibi.
 *
 * ## Kimlik politikası
 *
 * - `entityId` ve `runtimeId` **monoton** artar; hiçbir zaman yeniden
 *   kullanılmaz.
 * - Her kimlik bir **nesil** (`generation`) taşır. Nesil, sunucu yeniden
 *   başladığında ya da varlık yeniden doğduğunda artar. Gecikmiş bir
 *   `RemoveActor` paketi eski `(nesil, kimlik)` çiftine aittir ve yeni varlığı
 *   etkilemez.
 * - Kaldırılan kimlikler `retired` kümesinde tutulur; `isRetired()` ile eski
 *   paketler reddedilir (hayalet varlık savunması).
 */
final class EntityManager
{
    /** @var array<string, Entity> */
    private array $entities = [];

    /** @var array<string, int> kaldırılmış kimlik => nesil */
    private array $retired = [];

    private int $nextEntityId = 1;

    private int $nextRuntimeId = 1;

    public function __construct(
        private readonly int $generation = 1,
        int $firstEntityId = 1,
        int $firstRuntimeId = 1
    ) {
        if ($generation < 1) {
            throw PlayerException::invalidAmount('generation', $generation);
        }

        if ($firstEntityId < 1) {
            throw PlayerException::invalidAmount('firstEntityId', $firstEntityId);
        }

        if ($firstRuntimeId < 1) {
            throw PlayerException::invalidAmount('firstRuntimeId', $firstRuntimeId);
        }

        $this->nextEntityId = $firstEntityId;
        $this->nextRuntimeId = $firstRuntimeId;
    }

    public function generation(): int
    {
        return $this->generation;
    }

    /**
     * Yeni varlık üretir ve kimliğini bağlar.
     */
    public function create(
        string $type = Entity::TYPE_GENERIC,
        ?World $world = null,
        float $x = 0.0,
        float $y = 0.0,
        float $z = 0.0
    ): Entity {
        $entityId = $this->nextEntityId++;
        $runtimeId = $this->nextRuntimeId++;
        $id = sprintf('entity-%d-%d', $this->generation, $entityId);

        $entity = new Entity($id, '', $world, $type);
        $entity->bindIdentity($entityId, $runtimeId, $this->generation);
        $entity->setPosition($x, $y, $z);

        $this->entities[$id] = $entity;

        return $entity;
    }

    /**
     * Dışarıda üretilmiş (örn. oyuncuya bağlı) varlığı kaydeder.
     *
     * @throws PlayerException kimlik çakışırsa
     */
    public function register(Entity $entity): void
    {
        if (isset($this->entities[$entity->id()])) {
            throw PlayerException::invalidState($entity->id(), 'unique_entity');
        }

        if ($this->isRetired($entity->id())) {
            throw PlayerException::invalidState($entity->id(), 'not_retired');
        }

        $this->entities[$entity->id()] = $entity;
    }

    /**
     * Varlığı kaldırır ve kimliğini emekliye ayırır.
     */
    public function remove(string $id): bool
    {
        $entity = $this->entities[$id] ?? null;

        if ($entity === null) {
            return false;
        }

        $entity->despawn();
        unset($this->entities[$id]);

        $this->retired[$id] = $entity->generation();

        return true;
    }

    public function byId(string $id): ?Entity
    {
        return $this->entities[$id] ?? null;
    }

    public function isRetired(string $id): bool
    {
        return isset($this->retired[$id]);
    }

    /**
     * @return list<Entity>
     */
    public function all(): array
    {
        return array_values($this->entities);
    }

    /**
     * @return list<Entity>
     */
    public function active(): array
    {
        return array_values(array_filter(
            $this->entities,
            static fn (Entity $entity): bool => !$entity->isDespawned()
        ));
    }

    /**
     * @return list<Entity>
     */
    public function forWorld(World $world): array
    {
        return array_values(array_filter(
            $this->entities,
            static fn (Entity $entity): bool => $entity->world() === $world
        ));
    }

    public function count(): int
    {
        return count($this->entities);
    }

    public function retiredCount(): int
    {
        return count($this->retired);
    }
}
