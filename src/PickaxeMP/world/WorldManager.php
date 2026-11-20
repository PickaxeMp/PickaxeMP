<?php

declare(strict_types=1);

namespace PickaxeMP\world;

use PickaxeMP\utils\Logger;
use PickaxeMP\world\generator\FlatGenerator;
use PickaxeMP\world\storage\FileWorldStorage;
use PickaxeMP\world\storage\WorldStorageInterface;

/**
 * Dünyaların tek sahibi: oluşturma, yükleme, kapatma.
 *
 * Varsayılan olarak `worlds/` altında dosya tabanlı depolama kullanır.
 * İleride LevelDB uygulaması `WorldStorageInterface` ile eklenebilir; bu sınıf
 * değişmez.
 */
final class WorldManager
{
    /** @var array<string, World> */
    private array $worlds = [];

    private readonly WorldStorageInterface $storage;

    private readonly FlatGenerator $generator;

    public function __construct(
        private readonly Logger $logger,
        string $rootDirectory,
        private readonly int $defaultAirNetworkId = 0,
        ?WorldStorageInterface $storage = null,
        ?FlatGenerator $generator = null
    ) {
        $this->storage = $storage ?? new FileWorldStorage($rootDirectory, $defaultAirNetworkId);
        $this->generator = $generator ?? new FlatGenerator();
    }

    public function getStorage(): WorldStorageInterface
    {
        return $this->storage;
    }

    /**
     * Dünyayı yükler; yoksa oluşturur.
     */
    public function loadOrCreate(string $name, ?int $seed = null, string $displayName = ''): World
    {
        if (isset($this->worlds[$name])) {
            return $this->worlds[$name];
        }

        if ($this->storage->propertiesExist($name)) {
            return $this->load($name);
        }

        return $this->create($name, $seed, $displayName);
    }

    /**
     * Var olan dünyayı yükler.
     */
    public function load(string $name): World
    {
        if (isset($this->worlds[$name])) {
            return $this->worlds[$name];
        }

        $properties = $this->storage->loadProperties($name);
        $world = new World($properties, $this->storage, $this->generator);

        $this->worlds[$name] = $world;

        $this->logger->info('Dunya yuklendi.', ['world' => $name, 'seed' => $properties->seed]);

        return $world;
    }

    /**
     * Yeni dünya oluşturur.
     */
    public function create(string $name, ?int $seed = null, string $displayName = ''): World
    {
        if (isset($this->worlds[$name])) {
            throw new WorldException("Dunya zaten yuklu: {$name}");
        }

        $seed ??= random_int(-2147483648, 2147483647);
        $spawn = $this->generator->spawnPoint();
        $now = time();

        $properties = new WorldProperties(
            name: $name,
            displayName: $displayName === '' ? $name : $displayName,
            seed: $seed,
            generator: 'flat',
            dimension: 'overworld',
            spawnX: $spawn['x'],
            spawnY: $spawn['y'],
            spawnZ: $spawn['z'],
            createdAt: $now,
            updatedAt: $now
        );

        $this->storage->saveProperties($properties);

        $world = new World($properties, $this->storage, $this->generator);
        $this->worlds[$name] = $world;

        $this->logger->info('Dunya olusturuldu.', [
            'world' => $name,
            'seed' => $seed,
            'spawn' => sprintf('%.1f, %.1f, %.1f', $spawn['x'], $spawn['y'], $spawn['z']),
        ]);

        return $world;
    }

    public function get(string $name): World
    {
        if (!isset($this->worlds[$name])) {
            throw WorldException::unknownWorld($name);
        }

        return $this->worlds[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->worlds[$name]);
    }

    /**
     * @return list<World>
     */
    public function all(): array
    {
        return array_values($this->worlds);
    }

    public function count(): int
    {
        return count($this->worlds);
    }

    /**
     * Tüm dünyaları kaydeder ve kapatır.
     *
     * @return int Kaydedilen chunk sayısı
     */
    public function closeAll(): int
    {
        $saved = 0;

        foreach ($this->worlds as $world) {
            $saved += $world->close();
        }

        $this->worlds = [];

        return $saved;
    }

    public function unload(string $name): int
    {
        if (!isset($this->worlds[$name])) {
            return 0;
        }

        $saved = $this->worlds[$name]->close();
        unset($this->worlds[$name]);

        return $saved;
    }
}
