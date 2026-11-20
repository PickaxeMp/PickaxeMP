<?php

declare(strict_types=1);

namespace PickaxeMP\world\block;

use PickaxeMP\nbt\Fnv1a32;
use PickaxeMP\nbt\NbtWriter;

/**
 * Bir bloğun **durumu** (tür + özellikler) ve ağ kimliği.
 *
 * ## Ağ kimliği neden hash?
 *
 * Bedrock iki mod destekler (`block-network-ids-are-hashes`):
 *  - `false` → sıralı runtime ID'ler (0, 1, 2, …). Devasa bir palet tablosu
 *    gerektirir ve sürümle değişir.
 *  - `true`  → **FNV-1a 32 hash** (BDS varsayılanı). Sunucu yalnızca
 *    kullandığı blokları tanımlar; istemci hash'i kendi kayıt defterinde
 *    çözer.
 *
 * PickaxeMP **hash modunu** kullanır: sürüm bağımsız, tablo gerektirmez.
 *
 * Hash girdisi: `{name, states}` compound'unun little-endian NBT'si,
 * anahtarlar **sıralı** (deterministik olması için).
 */
final class BlockState
{
    /** @var array<string, mixed> Sıralanmış özellikler */
    private array $properties;

    private ?int $networkId = null;

    /**
     * @param array<string, mixed> $properties
     */
    public function __construct(
        public readonly string $name,
        array $properties = []
    ) {
        // Deterministik hash için anahtarları sırala.
        ksort($properties);
        $this->properties = $properties;
    }

    public static function of(string $name, array $properties = []): self
    {
        return new self($name, $properties);
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * Ağ kimliği (FNV-1a 32 hash). İlk çağrıda hesaplanır, sonra önbellekte.
     */
    public function getNetworkId(): int
    {
        return $this->networkId ??= Fnv1a32::hash($this->toNbtBytes());
    }

    /**
     * Hash girdisi: `{name, states}` little-endian NBT.
     */
    public function toNbtBytes(): string
    {
        $writer = new NbtWriter();
        $writer->beginCompound();
        $writer->writeStringTag('name', $this->name);

        $writer->beginCompound('states');

        foreach ($this->properties as $key => $value) {
            // Özellik tipleri: bool → byte, int → int, string → string.
            if (is_bool($value)) {
                $writer->writeByteTag($key, $value ? 1 : 0);
            } elseif (is_int($value)) {
                $writer->writeIntTag($key, $value);
            } else {
                $writer->writeStringTag($key, (string) $value);
            }
        }

        $writer->endCompound();  // states
        $writer->endCompound();  // root

        return $writer->bytes();
    }

    public function equals(self $other): bool
    {
        return $this->name === $other->name && $this->properties === $other->properties;
    }

    /**
     * Palet/ayıklama için kısa anahtar.
     */
    public function key(): string
    {
        if ($this->properties === []) {
            return $this->name;
        }

        $parts = [];

        foreach ($this->properties as $key => $value) {
            $parts[] = $key . '=' . (is_bool($value) ? ($value ? 'true' : 'false') : (string) $value);
        }

        return $this->name . '[' . implode(',', $parts) . ']';
    }

    public function withProperties(array $properties): self
    {
        return new self($this->name, $properties);
    }

    public function __toString(): string
    {
        return $this->key();
    }
}
