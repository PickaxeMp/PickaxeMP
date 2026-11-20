<?php

declare(strict_types=1);

namespace PickaxeMP\entity;

use PickaxeMP\Entity;
use PickaxeMP\item\ItemStack;
use PickaxeMP\protocol\packet\types\Vec3;

/**
 * Yerde duran tek bir esya varligi.
 *
 * ## Neden ayri kayit?
 *
 * {@see Entity} yalnizca yasam dongusunu (kimlik, konum, despawn) tasir;
 * esyanin **ne oldugu** oraya sigmaz. Bu kayit, varlik kimligini tasidigi
 * esya yiginiyla eslestirir. `RemoveActor`/`TakeItemActor` yalnizca runtime
 * kimligiyle calisir; esya bilgisi ve miktar daima **sunucu kaydindan**
 * okunur, istemciden gelen pakete guvenilmez (hayalet/esya cogaltma
 * savunmasi).
 *
 * `stack` bir **kopya** olarak saklanir: cagiranin elindeki yigin sonradan
 * degisse bile yerdeki esya etkilenmez.
 *
 * ## Hareket durumu
 *
 * `position`, `velocity` ve `onGround` **degiskendir**: dusen esya her
 * tick'te guncellenir ({@see ItemActorManager::tickMotion()}). Konum
 * `Vec3` olarak tutulur cunku `AddItemActor` ve `MoveActorDelta` ayni
 * bicimi bekler.
 */
final class ItemActor
{
    public Vec3 $position;

    public Vec3 $velocity;

    public bool $onGround = false;

    public function __construct(
        public readonly Entity $entity,
        public readonly ItemStack $stack,
        Vec3 $position,
        public readonly ?string $ownerSessionId,
        public readonly int $spawnedAt,
        ?Vec3 $velocity = null
    ) {
        $this->position = $position;
        $this->velocity = $velocity ?? Vec3::zero();
    }

    public function runtimeId(): int
    {
        return $this->entity->runtimeId();
    }

    public function entityId(): int
    {
        return $this->entity->entityId();
    }

    public function isDespawned(): bool
    {
        return $this->entity->isDespawned();
    }
}
