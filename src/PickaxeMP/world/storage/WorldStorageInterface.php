<?php

declare(strict_types=1);

namespace PickaxeMP\world\storage;

use PickaxeMP\world\chunk\Chunk;
use PickaxeMP\world\WorldException;
use PickaxeMP\world\WorldProperties;

/**
 * Dünya kalıcılığı sözleşmesi.
 *
 * Uygulamalar (dosya tabanlı, ileride LevelDB) bu arayüzü uygular. Dünya
 * motoru hangi depolamanın kullanıldığını bilmez.
 */
interface WorldStorageInterface
{
    /**
     * Dünya meta verisini yazar.
     */
    public function saveProperties(WorldProperties $properties): void;

    /**
     * Dünya meta verisini okur.
     *
     * @throws WorldException dosya yoksa veya bozuksa
     */
    public function loadProperties(string $worldName): WorldProperties;

    public function propertiesExist(string $worldName): bool;

    /**
     * Tek bir chunk'ı yazar (atomik).
     */
    public function saveChunk(string $worldName, Chunk $chunk): void;

    /**
     * Tek bir chunk'ı okur; yoksa null.
     */
    public function loadChunk(string $worldName, int $chunkX, int $chunkZ): ?Chunk;

    /**
     * Chunk var mı?
     */
    public function hasChunk(string $worldName, int $chunkX, int $chunkZ): bool;

    /**
     * Tüm chunk anahtarlarını listeler ("x,z" biçiminde).
     *
     * @return list<string>
     */
    public function listChunks(string $worldName): array;
}
