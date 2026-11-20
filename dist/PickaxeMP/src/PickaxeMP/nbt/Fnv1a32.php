<?php

declare(strict_types=1);

namespace PickaxeMP\nbt;

/**
 * FNV-1a 32-bit hash — blok ağ kimliği için.
 *
 * Parametreler (FNV-1a spesifikasyonu):
 *  - offset basis : `0x811C9DC5`
 *  - prime        : `0x01000193`
 *
 * Bedrock, `block-network-ids-are-hashes=true` iken (BDS varsayılanı) blok
 * durumlarını **FNV-1a 32** ile hash'ler. Hash girdisi: `{name, states}`
 * compound'unun **little-endian NBT** baytları, anahtarlar sıralı.
 *
 * Kaynak: Mojang `server.properties` → `block-network-ids-are-hashes`
 * (varsayılan `true`); topluluk doğrulaması helix-bedrock/blockstate.
 *
 * DÜRÜST NOT: Hash **algoritması** doğrulanmıştır; üretilen **değerler**
 * gerçek istemciyle sınanmamıştır. Deterministik ve self-consistent
 * olduğu testlerle kanıtlanır.
 */
final class Fnv1a32
{
    private const OFFSET_BASIS = 0x811C9DC5;
    private const PRIME = 0x01000193;

    public static function hash(string $data): int
    {
        $hash = self::OFFSET_BASIS;
        $length = strlen($data);

        for ($i = 0; $i < $length; $i++) {
            $hash ^= ord($data[$i]);

            // 32-bit taşmasını PHP'nin 64-bit int'i üzerinden taklit et.
            $hash = ($hash * self::PRIME) & 0xFFFFFFFF;
        }

        return $hash;
    }

    /**
     * Testlerde bilinen vektörle karşılaştırma için.
     */
    public static function hashHex(string $data): string
    {
        return sprintf('%08x', self::hash($data));
    }
}
