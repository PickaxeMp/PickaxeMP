<?php

declare(strict_types=1);

namespace PickaxeMP;

use PickaxeMP\player\PlayerException;
use PickaxeMP\world\chunk\Chunk;
use PickaxeMP\world\World;

/**
 * Dünyadaki her şeyin ortak yaşam döngüsü.
 *
 * ## Neden `PickaxeMP\Entity` (kök ad alanı)?
 *
 * Varlık, oyuncu ve dünya arasında **ortak** bir kavramdır; envanter ya da
 * protokol katmanına ait değildir. Testler de bu FQCN'i bekliyor.
 *
 * ## Değişmez kurallar
 *
 * 1. Konum doğrulaması `Player::setPosition()` ile **aynı** sınırları kullanır
 *    (`Chunk::MIN_Y`..`Chunk::MAX_Y`); iki farklı sınır, ışınlanma açıkları
 *    üretirdi.
 * 2. `despawn()` **idempotenttir** — ikinci çağrı istisna fırlatmaz, çünkü
 *    bağlantı kapanışı ile zamanlayıcı temizliği aynı varlığı iki kez
 *    kaldırabilir.
 * 3. Sayısal kimlikler (`entityId`, `runtimeId`) yalnızca `bindIdentity()` ile
 *    **bir kez** atanır. Aynı varlığa ikinci kez kimlik vermek, istemcide
 *    hayalet varlık üretir.
 * 4. `respawn()` yeni kimlik **üretmez**; aynı kimlik geri gelir, nesil
 *    (`generation`) artar. Böylece gecikmiş bir `RemoveActor` paketi yeni
 *    varlığı yanlışlıkla silmez.
 */
final class Entity
{
    /** Oyuncu dışı varsayılan tür. */
    public const TYPE_GENERIC = 'minecraft:entity';

    /** Yerde duran eşya varlığı. */
    public const TYPE_ITEM = 'minecraft:item';

    private float $x = 0.0;

    private float $y = 0.0;

    private float $z = 0.0;

    private float $pitch = 0.0;

    private float $yaw = 0.0;

    private bool $despawned = false;

    private int $entityId = 0;

    private int $runtimeId = 0;

    private int $generation = 0;

    public function __construct(
        private readonly string $id,
        private readonly string $storageRoot = '',
        private readonly ?World $world = null,
        private readonly string $type = self::TYPE_GENERIC
    ) {
        if ($id === '' || strlen($id) > 128) {
            throw PlayerException::invalidState(substr($id, 0, 32), 'entity_id');
        }
    }

    public function id(): string
    {
        return $this->id;
    }

    public function type(): string
    {
        return $this->type;
    }

    /**
     * Kalıcılık kökü — varlık durumu henüz diske yazılmıyor; alan ileride
     * aynı kökü kullanacak çağıranlar için taşınıyor.
     */
    public function storageRoot(): string
    {
        return $this->storageRoot;
    }

    public function world(): ?World
    {
        return $this->world;
    }

    public function isDespawned(): bool
    {
        return $this->despawned;
    }

    /**
     * @throws PlayerException zaten bağlıysa
     */
    public function bindIdentity(int $entityId, int $runtimeId, int $generation): void
    {
        if ($this->entityId !== 0) {
            throw PlayerException::invalidState('bound', 'unbound');
        }

        if ($entityId < 1 || $runtimeId < 1 || $generation < 1) {
            throw PlayerException::invalidAmount('entity_identity', $entityId);
        }

        $this->entityId = $entityId;
        $this->runtimeId = $runtimeId;
        $this->generation = $generation;
    }

    public function hasIdentity(): bool
    {
        return $this->entityId !== 0;
    }

    public function entityId(): int
    {
        return $this->entityId;
    }

    public function runtimeId(): int
    {
        return $this->runtimeId;
    }

    public function generation(): int
    {
        return $this->generation;
    }

    public function despawn(): void
    {
        if ($this->despawned) {
            return;
        }

        $this->despawned = true;
    }

    public function respawn(): void
    {
        if (!$this->despawned) {
            return;
        }

        $this->despawned = false;
        $this->generation++;
    }

    public function x(): float
    {
        return $this->x;
    }

    public function y(): float
    {
        return $this->y;
    }

    public function z(): float
    {
        return $this->z;
    }

    public function pitch(): float
    {
        return $this->pitch;
    }

    public function yaw(): float
    {
        return $this->yaw;
    }

    /**
     * @return array{x: float, y: float, z: float}
     */
    public function position(): array
    {
        return [
            'x' => $this->x,
            'y' => $this->y,
            'z' => $this->z,
        ];
    }

    /**
     * @throws PlayerException sonlu olmayan değer veya Y sınırı dışı
     */
    public function setPosition(float $x, float $y, float $z): void
    {
        if (!is_finite($x) || !is_finite($y) || !is_finite($z)) {
            throw PlayerException::invalidPosition($x, $y, $z);
        }

        if ($y < Chunk::MIN_Y || $y > Chunk::MAX_Y) {
            throw PlayerException::invalidPosition($x, $y, $z);
        }

        if ($this->world !== null && $this->world->isClosed()) {
            throw PlayerException::invalidState('closed_world', 'open_world');
        }

        $this->x = $x;
        $this->y = $y;
        $this->z = $z;
    }

    /**
     * @throws PlayerException sonlu olmayan değer
     */
    public function setRotation(float $pitch, float $yaw): void
    {
        if (!is_finite($pitch) || !is_finite($yaw)) {
            throw PlayerException::invalidPosition($this->x, $this->y, $this->z);
        }

        $this->pitch = $pitch;
        $this->yaw = $yaw;
    }

    public function distanceSquaredTo(float $x, float $y, float $z): float
    {
        $dx = $this->x - $x;
        $dy = $this->y - $y;
        $dz = $this->z - $z;

        return $dx * $dx + $dy * $dy + $dz * $dz;
    }

    public function describe(): string
    {
        return sprintf(
            'entity(%s, type=%s, eid=%d, rid=%d, gen=%d, despawned=%s)',
            $this->id,
            $this->type,
            $this->entityId,
            $this->runtimeId,
            $this->generation,
            $this->despawned ? 'yes' : 'no'
        );
    }
}
