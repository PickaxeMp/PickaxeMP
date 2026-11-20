<?php

declare(strict_types=1);

namespace PickaxeMP\world;

use PickaxeMP\world\chunk\Chunk;
use PickaxeMP\world\chunk\ChunkPosition;
use PickaxeMP\world\generator\FlatGenerator;
use PickaxeMP\world\storage\WorldStorageInterface;

/**
 * Tek bir dünya: chunk'lar, meta veri, spawn.
 *
 * Chunk'lar **tembel** yüklenir: `getChunk()` önce belleğe, sonra depoya,
 * en son üreticiye bakar. Bu sıra önemlidir — depodaki veri üretilenden
 * daha değerlidir.
 */
final class World
{
    /** @var array<string, Chunk> konum anahtarı => chunk */
    private array $chunks = [];

    /** @var array<string, true> Yüklenmiş ama boşaltılmamış chunk'lar */
    private array $loaded = [];

    private bool $closed = false;

    public function __construct(
        private readonly WorldProperties $properties,
        private readonly WorldStorageInterface $storage,
        private readonly FlatGenerator $generator
    ) {
    }

    public function getProperties(): WorldProperties
    {
        return $this->properties;
    }

    public function getName(): string
    {
        return $this->properties->name;
    }

    public function getDisplayName(): string
    {
        return $this->properties->displayName;
    }

    public function getSeed(): int
    {
        return $this->properties->seed;
    }

    /**
     * Dünya saati (`0..23999`). `SetTime` paketi bu değeri taşır.
     */
    public function getTime(): int
    {
        return $this->properties->time;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    /**
     * Dünya saatini ilerletir (gün sınırında sarar).
     *
     * @return int Yeni saat (`0..23999`)
     */
    public function advanceTime(int $ticks): int
    {
        if ($this->closed) {
            throw new WorldException('Dunya kapatilmis: ' . $this->properties->name);
        }

        return $this->properties->advanceTime($ticks);
    }

    /**
     * Chunk'ı getirir: bellek → depo → üretici.
     */
    public function getChunk(ChunkPosition $position): Chunk
    {
        if ($this->closed) {
            throw new WorldException('Dunya kapatilmis: ' . $this->properties->name);
        }

        $key = $position->key();

        if (isset($this->chunks[$key])) {
            return $this->chunks[$key];
        }

        $stored = $this->storage->loadChunk($this->properties->name, $position->x, $position->z);

        if ($stored !== null) {
            $this->chunks[$key] = $stored;
            $this->loaded[$key] = true;

            return $stored;
        }

        $generated = $this->generator->generate($position);
        $this->chunks[$key] = $generated;
        $this->loaded[$key] = true;

        return $generated;
    }

    public function hasLoadedChunk(ChunkPosition $position): bool
    {
        return isset($this->chunks[$position->key()]);
    }

    /**
     * Verilen blok konumu **bellekte yuklu** bir chunk'a mi dusuyor?
     *
     * `false` donmesi "hava" demek degildir; "bu konum hakkinda bilgimiz yok"
     * demektir.
     */
    public function blockPositionLoaded(int $x, int $y, int $z): bool
    {
        if ($this->closed || !Chunk::isYInRange($y)) {
            return false;
        }

        return $this->hasLoadedChunk(ChunkPosition::fromBlock($x, $z));
    }

    /**
     * Blok ag kimligini okur.
     *
     * `null` = chunk yuklu degil, dunya kapali veya Y araligi disinda. Hava
     * icin `0` degil, chunk'in kendi hava kimligi doner.
     */
    public function getBlock(int $x, int $y, int $z): ?int
    {
        if (!$this->blockPositionLoaded($x, $y, $z)) {
            return null;
        }

        $chunk = $this->chunks[ChunkPosition::fromBlock($x, $z)->key()];

        return $chunk->getBlock($y, $x & 0xF, $z & 0xF);
    }

    /**
     * Blok ag kimligini yazar.
     *
     * **Chunk yuklemez**: istemci tetikli yukleme, bellek buyutme vektoru
     * olurdu. Yuklu olmayan chunk'ta `false` doner.
     *
     * @return bool Gercekten degisti mi? (ayni deger yazildiysa `false`)
     *
     * @throws WorldException Y dunya sinirlari disindaysa
     */
    public function setBlock(int $x, int $y, int $z, int $networkId): bool
    {
        if (!Chunk::isYInRange($y)) {
            throw WorldException::outOfBounds($x, $y, $z);
        }

        if ($this->closed) {
            return false;
        }

        $position = ChunkPosition::fromBlock($x, $z);
        $chunk = $this->chunks[$position->key()] ?? null;

        if ($chunk === null) {
            return false;
        }

        $localX = $x & 0xF;
        $localZ = $z & 0xF;

        if ($chunk->getBlock($y, $localX, $localZ) === $networkId) {
            return false;
        }

        $chunk->setBlock($y, $localX, $localZ, $networkId);

        return true;
    }

    /**
     * Bellekten boşaltır (kirliyse önce kaydeder).
     */
    public function unloadChunk(ChunkPosition $position, bool $save = true): bool
    {
        $key = $position->key();

        if (!isset($this->chunks[$key])) {
            return false;
        }

        $chunk = $this->chunks[$key];

        if ($save && $chunk->isDirty()) {
            $this->storage->saveChunk($this->properties->name, $chunk);
        }

        unset($this->chunks[$key], $this->loaded[$key]);

        return true;
    }

    /**
     * Kirli chunk'ları kaydeder.
     *
     * @return int Kaydedilen chunk sayısı
     */
    public function saveDirtyChunks(): int
    {
        $saved = 0;

        foreach ($this->chunks as $chunk) {
            if (!$chunk->isDirty()) {
                continue;
            }

            $this->storage->saveChunk($this->properties->name, $chunk);
            $saved++;
        }

        return $saved;
    }

    /**
     * Yüklü chunk'ları (bellekteki) listeler.
     *
     * @return list<Chunk>
     */
    public function loadedChunks(): array
    {
        return array_values($this->chunks);
    }

    public function loadedChunkCount(): int
    {
        return count($this->chunks);
    }

    /**
     * Dünya spawn noktası.
     *
     * @return array{x: float, y: float, z: float}
     */
    public function getSpawn(): array
    {
        return [
            'x' => $this->properties->spawnX,
            'y' => $this->properties->spawnY,
            'z' => $this->properties->spawnZ,
        ];
    }

    /**
     * Dünyayı kapatır: kirli chunk'ları kaydeder, belleği boşaltır.
     */
    public function close(): int
    {
        if ($this->closed) {
            return 0;
        }

        $saved = $this->saveDirtyChunks();

        $this->chunks = [];
        $this->loaded = [];
        $this->closed = true;

        return $saved;
    }

    /**
     * Teşhis için özet.
     */
    public function describe(): string
    {
        return sprintf(
            'world(%s, seed=%d, generator=%s, chunks=%d)',
            $this->properties->name,
            $this->properties->seed,
            $this->properties->generator,
            count($this->chunks)
        );
    }
}
