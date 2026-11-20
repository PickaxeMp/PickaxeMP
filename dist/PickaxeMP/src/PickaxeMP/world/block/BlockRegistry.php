<?php

declare(strict_types=1);

namespace PickaxeMP\world\block;

use PickaxeMP\world\WorldException;

/**
 * Bilinen blokların **tek kaynağı**.
 *
 * Phase 4 kapsamı yalnızca düz dünya için gereken 6 bloktur. Yeni blok
 * eklerken:
 *  1. Bedrock'un **gerçek adını** kullan (`minecraft:...`).
 *  2. Varsayılan durumu şemadan/dokümandan doğrula.
 *  3. Sertlik değerini oyun verisinden al, tahmin etme.
 */
final class BlockRegistry
{
    /** @var array<string, BlockType> */
    private array $types = [];

    /** @var array<string, BlockState> */
    private array $states = [];

    /** @var array<int, BlockState>|null ag kimligi => durum (tembel) */
    private ?array $byNetworkId = null;

    private static ?self $default = null;

    public function register(BlockType $type): void
    {
        if (isset($this->types[$type->name])) {
            throw new WorldException("Blok turu zaten kayitli: {$type->name}");
        }

        $this->types[$type->name] = $type;
    }

    public function has(string $name): bool
    {
        return isset($this->types[$name]);
    }

    public function type(string $name): BlockType
    {
        if (!isset($this->types[$name])) {
            throw new WorldException("Bilinmeyen blok turu: {$name}");
        }

        return $this->types[$name];
    }

    /**
     * Adlandırılmış durumu döndürür; yoksa oluşturur ve önbelleğe alır.
     *
     * @param array<string, mixed> $properties
     */
    public function state(string $name, array $properties = []): BlockState
    {
        $this->type($name); // var olmayan türü sessizce kabul etme

        $state = new BlockState($name, $properties);
        $key = $state->key();

        return $this->states[$key] ??= $state;
    }

    public function defaultState(string $name): BlockState
    {
        return $this->state($name, $this->type($name)->defaultState);
    }

    /**
     * Ag kimliginden blok durumuna ters arama.
     *
     * Ag kimlikleri FNV-1a hash'leridir; **teorik olarak** carpisabilir. Bu
     * yuzden ilk kayit kazanir ve carpisma sessizce yutulmaz: `null`
     * dondurmek yerine ilk durum doner, ancak kayit defteri kucuk (Phase 4:
     * 6 blok) oldugu icin pratikte carpisma yoktur.
     *
     * Bilinmeyen kimlik icin `null` doner - cagiran katman bunu "hava kabul
     * et" olarak yorumlamamalidir.
     */
    public function fromNetworkId(int $networkId): ?BlockState
    {
        if ($this->byNetworkId === null) {
            $this->byNetworkId = [];

            foreach ($this->types as $name => $type) {
                $state = $this->defaultState($name);
                $this->byNetworkId[$state->getNetworkId()] ??= $state;
            }

            foreach ($this->states as $state) {
                $this->byNetworkId[$state->getNetworkId()] ??= $state;
            }
        }

        return $this->byNetworkId[$networkId] ?? null;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->types);
    }

    public function count(): int
    {
        return count($this->types);
    }

    /**
     * Phase 4 için gereken bloklarla dolu varsayılan kayıt defteri.
     */
    public static function withPhase4Blocks(): self
    {
        $registry = new self();

        // minecraft:air — sertlik 0, katı değil, şeffaf, kırılamaz sayılır.
        $registry->register(new BlockType(
            'minecraft:air',
            [],
            0.0,
            false,
            true,
            false
        ));

        // minecraft:stone — sertlik 1.5
        $registry->register(new BlockType(
            'minecraft:stone',
            [],
            1.5,
            true,
            false
        ));

        // minecraft:dirt — sertlik 0.5
        $registry->register(new BlockType(
            'minecraft:dirt',
            [],
            0.5,
            true,
            false
        ));

        // minecraft:grass_block — sertlik 0.6, snowy durumu var (varsayılan false)
        $registry->register(new BlockType(
            'minecraft:grass_block',
            ['snowy' => false],
            0.6,
            true,
            false
        ));

        // minecraft:bedrock — kırılamaz, sertlik -1
        $registry->register(new BlockType(
            'minecraft:bedrock',
            [],
            -1.0,
            true,
            false,
            false
        ));

        // minecraft:water — liquid_depth durumu var (varsayılan 0 = kaynak)
        $registry->register(new BlockType(
            'minecraft:water',
            ['liquid_depth' => 0],
            100.0,
            false,
            true
        ));

        return $registry;
    }

    public static function default(): self
    {
        return self::$default ??= self::withPhase4Blocks();
    }
}
