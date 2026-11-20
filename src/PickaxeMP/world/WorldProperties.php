<?php

declare(strict_types=1);

namespace PickaxeMP\world;

/**
 * Dünyanın kalıcı özellikleri (meta veri).
 *
 * Bu nesne `world.json` olarak saklanır ve dünya yüklenirken doğrulanır.
 */
final class WorldProperties
{
    public const FORMAT_VERSION = 1;

    /** Varsayılan dünya saati: öğle (0 = gün doğumu, 18000 = gece yarısı). */
    public const DEFAULT_TIME = 6000;

    /** Bir günün tick sayısı. */
    public const DAY_LENGTH = 24000;

    public function __construct(
        public readonly string $name,
        public readonly string $displayName,
        public readonly int $seed,
        public readonly string $generator,
        public readonly string $dimension,
        public readonly float $spawnX,
        public readonly float $spawnY,
        public readonly float $spawnZ,
        public readonly int $formatVersion = self::FORMAT_VERSION,
        public readonly int $createdAt = 0,
        public readonly int $updatedAt = 0,
        /** Dünya saati (0..23999); `SetTime` bu değerle gönderilir. */
        public int $time = self::DEFAULT_TIME
    ) {
    }

    /**
     * Dünya saatini ilerletir ve gün sınırında sarar.
     *
     * @param int $ticks Eklenecek tick (negatif olamaz)
     *
     * @return int Yeni saat (`0..23999`)
     */
    public function advanceTime(int $ticks): int
    {
        if ($ticks < 0) {
            throw WorldException::invalidTimeStep($ticks);
        }

        $this->time = ($this->time + ($ticks % self::DAY_LENGTH)) % self::DAY_LENGTH;

        return $this->time;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'format_version' => $this->formatVersion,
            'name' => $this->name,
            'display_name' => $this->displayName,
            'seed' => $this->seed,
            'generator' => $this->generator,
            'dimension' => $this->dimension,
            'spawn' => [
                'x' => $this->spawnX,
                'y' => $this->spawnY,
                'z' => $this->spawnZ,
            ],
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'time' => $this->time,
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws WorldException zorunlu alan eksik/yanlış tipte ise
     */
    public static function fromArray(array $data): self
    {
        $formatVersion = $data['format_version'] ?? null;

        if (!is_int($formatVersion)) {
            throw WorldException::corruptWorldFile('world.json', 'format_version eksik veya sayi degil');
        }

        if ($formatVersion > self::FORMAT_VERSION) {
            throw WorldException::corruptWorldFile(
                'world.json',
                "desteklenmeyen format surumu: {$formatVersion}"
            );
        }

        $spawn = $data['spawn'] ?? null;

        if (!is_array($spawn)) {
            throw WorldException::corruptWorldFile('world.json', 'spawn eksik');
        }

        foreach (['x', 'y', 'z'] as $axis) {
            if (!isset($spawn[$axis]) || !is_int($spawn[$axis]) && !is_float($spawn[$axis])) {
                throw WorldException::corruptWorldFile('world.json', "spawn.{$axis} eksik");
            }
        }

        return new self(
            name: self::requireString($data, 'name'),
            displayName: self::requireString($data, 'display_name'),
            seed: self::requireInt($data, 'seed'),
            generator: self::requireString($data, 'generator'),
            dimension: self::requireString($data, 'dimension'),
            spawnX: (float) $spawn['x'],
            spawnY: (float) $spawn['y'],
            spawnZ: (float) $spawn['z'],
            formatVersion: $formatVersion,
            createdAt: self::optionalInt($data, 'created_at'),
            updatedAt: self::optionalInt($data, 'updated_at'),
            time: self::optionalInt($data, 'time', self::DEFAULT_TIME)
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function requireString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (!is_string($value) || $value === '') {
            throw WorldException::corruptWorldFile('world.json', "{$key} eksik veya bos");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function requireInt(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        if (!is_int($value)) {
            throw WorldException::corruptWorldFile('world.json', "{$key} eksik veya sayi degil");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function optionalInt(array $data, string $key, int $default = 0): int
    {
        $value = $data[$key] ?? $default;

        return is_int($value) ? $value : $default;
    }
}
