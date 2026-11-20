<?php

declare(strict_types=1);

namespace PickaxeMP\player;

use PickaxeMP\auth\PlayerIdentity;
use PickaxeMP\auth\SkinData;
use PickaxeMP\world\chunk\Chunk;
use PickaxeMP\world\World;

/**
 * Kimlik doğrulanmış bir oturuma bağlı oyuncu.
 *
 * Oyuncu nesnesi yalnızca doğrulanmış `PlayerIdentity` ile oluşturulur.
 * Bağlantı, oturum ve dünya bağları değişmezdir; yaşam döngüsü durumu
 * (`Joining`, `Spawned`, `Disconnected`) açıkça takip edilir.
 */
final class Player
{
    /** Maksimum sağlık (yarım kalp = 1 birim). */
    public const MAX_HEALTH = 20;

    /** Hotbar yuva sayısı. */
    public const HOTBAR_SIZE = 9;

    private PlayerState $state = PlayerState::Joining;

    private float $x;

    private float $y;

    private float $z;

    private float $pitch = 0.0;

    private float $yaw = 0.0;

    /**
     * Sağlık **oyuncu başınadır**.
     *
     * Phase 5'teki `HealthManager` tek bir alanı tüm oyuncular için
     * paylaşıyordu; bu, çok oyunculu sunucuda bir oyuncunun hasarının
     * diğerini öldürmesi demekti. Sağlık artık burada tutulur.
     */
    private int $health = self::MAX_HEALTH;

    /** Açlık, doygunluk, tükenme, yürüme hızı (Phase 9). */
    private PlayerAttributes $attributes;

    /** Oyun modu (Phase 10.4): yetenek katmanını belirler. */
    private GameMode $gameMode = GameMode::Survival;

    /** Yaratıcı/izleyici uçuşu açık mı? */
    private bool $flying = false;

    private int $selectedHotbarSlot = 0;

    /**
     * İstemci dünyayı yükleyip `SetLocalPlayerAsInitialized` gönderdi mi?
     *
     * Bu bayrak "oyuncu artık ekranda" demektir; ağır veri (chunk akışı,
     * zaman senkronu) bundan sonra gönderilir.
     */
    private bool $initialized = false;

    /** Sunucunun kabul ettiği görüş mesafesi (yarıçap); `null` = henüz istenmedi. */
    private ?int $viewDistance = null;

    public function __construct(
        public readonly PlayerIdentity $identity,
        public readonly string $sessionId,
        public readonly string $connectionId,
        public readonly int $entityId,
        public readonly int $runtimeId,
        public readonly World $world,
        float $x,
        float $y,
        float $z,
        private readonly PlayerInventory $inventory,
        public readonly ?SkinData $skin = null
    ) {
        $this->attributes = new PlayerAttributes();
        $this->setPosition($x, $y, $z);
    }

    /**
     * Oyuncunun envanteri (36 yuva).
     *
     * Envanter oyuncuyla birlikte doğar; ayrı bir "oluştur" adımı yoktur, böylece
     * envanteri olmayan bir oyuncu durumu temsil edilemez.
     */
    public function inventory(): PlayerInventory
    {
        return $this->inventory;
    }

    public function gameMode(): GameMode
    {
        return $this->gameMode;
    }

    /**
     * Oyun modunu değiştirir.
     *
     * Uçuşa izin vermeyen bir moda geçilirse uçuş **kapatılır**; aksi hâlde
     * hayatta kalmaya dönen oyuncu havada asılı kalırdı.
     */
    public function setGameMode(GameMode $mode): void
    {
        $this->gameMode = $mode;

        if (!$mode->allowsFlight()) {
            $this->flying = false;
        }
    }

    public function isFlying(): bool
    {
        return $this->flying;
    }

    /**
     * Uçuşu açar/kapatır.
     *
     * @return bool istek uygulandı mı (mod uçuşa izin vermiyorsa `false`)
     */
    public function setFlying(bool $flying): bool
    {
        if ($flying && !$this->gameMode->allowsFlight()) {
            return false;
        }

        $this->flying = $flying;

        return true;
    }

    public function state(): PlayerState
    {
        return $this->state;
    }

    public function worldName(): string
    {
        return $this->world->getName();
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

    public function setPosition(float $x, float $y, float $z): void
    {
        if (!is_finite($x) || !is_finite($y) || !is_finite($z)) {
            throw PlayerException::invalidPosition($x, $y, $z);
        }

        if ($y < Chunk::MIN_Y || $y > Chunk::MAX_Y) {
            throw PlayerException::invalidPosition($x, $y, $z);
        }

        if ($this->world->isClosed()) {
            throw PlayerException::invalidState($this->state->value, 'open_world');
        }

        $this->x = $x;
        $this->y = $y;
        $this->z = $z;
    }

    public function setRotation(float $pitch, float $yaw): void
    {
        if (!is_finite($pitch) || !is_finite($yaw)) {
            throw PlayerException::invalidPosition($this->x, $this->y, $this->z);
        }

        $this->pitch = $pitch;
        $this->yaw = $yaw;
    }

    public function markSpawned(): void
    {
        if ($this->state !== PlayerState::Joining) {
            throw PlayerException::invalidState($this->state->value, PlayerState::Joining->value);
        }

        $this->state = PlayerState::Spawned;
    }

    // --- Sağlık / ölüm / yeniden doğma -----------------------------------

    public function health(): int
    {
        return $this->health;
    }

    /**
     * Açlık/doygunluk/tükenme/hız durumu.
     */
    public function attributes(): PlayerAttributes
    {
        return $this->attributes;
    }

    /**
     * Bir oyun tick'inde açlık zincirini işler.
     *
     * @return bool Doygunluk/açlık değişti mi? (yalnızca değişince
     *              `UpdateAttributes` yayınlanır)
     */
    public function tickAttributes(bool $moving): bool
    {
        return $this->attributes->tick($moving);
    }

    /**
     * Yemek yeme.
     */
    public function eat(int $hunger, float $saturation = 0.0): void
    {
        $this->attributes->eat($hunger, $saturation);
    }

    /**
     * `UpdateAttributes` için tam öznitelik listesi (sağlık dahil).
     *
     * @return list<\PickaxeMP\protocol\packet\types\Attribute>
     */
    public function attributeList(): array
    {
        return $this->attributes->attributeList($this->health, self::MAX_HEALTH);
    }

    /**
     * `UpdateAbilities` için yetenek verisi.
     */
    public function abilityData(): \PickaxeMP\protocol\packet\types\SerializedAbilitiesData
    {
        return PlayerAbilities::forPlayer($this);
    }

    public function isDead(): bool
    {
        return $this->health <= 0 || $this->state === PlayerState::Dead;
    }

    /**
     * Blok kırma/yerleştirme ve envanter işlemi yapılabilir mi?
     */
    public function canInteract(): bool
    {
        return $this->state->canInteract() && $this->health > 0;
    }

    /**
     * Hasar uygular.
     *
     * Zaten ölü ya da etkileşime kapalı oyuncuya hasar uygulanmaz.
     *
     * @return int Gerçekte düşen sağlık (0 = uygulanmadı)
     */
    public function damage(int $amount): int
    {
        if ($amount < 1) {
            throw PlayerException::invalidAmount('damage', $amount);
        }

        if (!$this->state->isActive() || $this->isDead()) {
            return 0;
        }

        $before = $this->health;
        $this->health = max(0, $this->health - $amount);

        if ($this->health === 0 && $this->state === PlayerState::Spawned) {
            $this->state = PlayerState::Dead;
        }

        return $before - $this->health;
    }

    /**
     * Sağlık verir (ölü oyuncuyu diriltmez).
     *
     * @return int Gerçekte eklenen sağlık
     */
    public function heal(int $amount): int
    {
        if ($amount < 1) {
            throw PlayerException::invalidAmount('heal', $amount);
        }

        if (!$this->state->isActive() || $this->isDead()) {
            return 0;
        }

        $before = $this->health;
        $this->health = min(self::MAX_HEALTH, $this->health + $amount);

        return $this->health - $before;
    }

    /**
     * Ölümden sonra sağlığı doldurur ve durumu `Spawned`'a çevirir.
     *
     * @throws PlayerException oyuncu `Dead` değilse
     */
    public function markRespawned(float $x, float $y, float $z): void
    {
        if ($this->state !== PlayerState::Dead) {
            throw PlayerException::invalidState($this->state->value, PlayerState::Dead->value);
        }

        $this->setPosition($x, $y, $z);
        $this->health = self::MAX_HEALTH;
        $this->attributes->reset();
        $this->state = PlayerState::Spawned;
    }

    // --- Hotbar -----------------------------------------------------------

    public function selectedHotbarSlot(): int
    {
        return $this->selectedHotbarSlot;
    }

    public function setSelectedHotbarSlot(int $slot): void
    {
        if ($slot < 0 || $slot >= self::HOTBAR_SIZE) {
            throw PlayerException::invalidSlot($slot, self::HOTBAR_SIZE);
        }

        $this->selectedHotbarSlot = $slot;
    }

    /**
     * Elinde tuttuğu yığın (seçili hotbar yuvası); boşsa `null`.
     */
    public function heldItem(): ?\PickaxeMP\item\ItemStack
    {
        return $this->inventory->getSlot($this->selectedHotbarSlot);
    }

    // --- İlk yükleme ve görüş mesafesi ------------------------------------

    /**
     * İstemci dünyayı yüklediğini bildirdi.
     *
     * @return bool Zaten işaretliyse `false` (yinelenen paket sessizce yutulmaz,
     *              çağıran loglar)
     */
    public function markInitialized(): bool
    {
        if ($this->initialized) {
            return false;
        }

        $this->initialized = true;

        return true;
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Sunucunun kabul ettiği görüş mesafesini kaydeder.
     *
     * İstemcinin **istediği** değer değil, kırpılmış değer saklanır.
     */
    public function grantViewDistance(int $radius): void
    {
        if ($radius < 1) {
            throw PlayerException::invalidAmount('view_distance', $radius);
        }

        $this->viewDistance = $radius;
    }

    public function grantedViewDistance(): ?int
    {
        return $this->viewDistance;
    }

    public function markDisconnected(): void
    {
        if ($this->state === PlayerState::Disconnected) {
            return;
        }

        $this->state = PlayerState::Disconnected;
    }

    public function describe(): string
    {
        return sprintf(
            'player(%s, uuid=%s, world=%s, state=%s)',
            $this->identity->displayName,
            $this->identity->uuid,
            $this->worldName(),
            $this->state->value
        );
    }
}
