<?php

declare(strict_types=1);

namespace PickaxeMP\player;

use PickaxeMP\inventory\Inventory;
use PickaxeMP\item\ItemStack;
use PickaxeMP\utils\Logger;

/**
 * Oyuncunun 36 yuvası: 0-8 hotbar, 9-35 ana envanter.
 *
 * ## Kalıcılık
 *
 * Dosya adı **doğrulanmış UUID** ile üretilir (`players/<uuid>.inv`). Böylece
 * bir oyuncu başka bir oyuncunun envanter dosyasını "adı değiştirerek" ele
 * geçiremez; anahtar kimliktir, isim değil.
 *
 * Biçim (little-endian):
 *
 * ```
 * "PXINV1" | payloadLength uint32 | payload (JSON) | crc32(payload) uint32
 * ```
 *
 * Yazma **atomiktir**: önce `.tmp`, sonra `rename`. Bozuk/CRC uyuşmayan dosya
 * yüklenmez; envanter boş başlar ve durum `warning` olarak loglanır. Sessizce
 * yarım envanterle oyuna sokmak, eşya kaybını fark edilmez kılardı.
 */
final class PlayerInventory extends Inventory
{
    public const HOTBAR_SIZE = 9;
    public const MAIN_SIZE = 27;
    public const SIZE = self::HOTBAR_SIZE + self::MAIN_SIZE;

    private const FILE_MAGIC = 'PXINV1';

    private bool $dirty = false;

    private int $lastPersistedVersion = -1;

    public function __construct(
        string $storageRoot,
        private readonly string $ownerUuid,
        private readonly ?Logger $logger = null
    ) {
        parent::__construct($storageRoot, self::SIZE);

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $ownerUuid) !== 1) {
            throw PlayerException::invalidItemIdentifier('uuid:' . $ownerUuid);
        }
    }

    public function ownerUuid(): string
    {
        return $this->ownerUuid;
    }

    public function isDirty(): bool
    {
        return $this->dirty || $this->version() !== $this->lastPersistedVersion;
    }

    public function setSlot(int $slot, ?ItemStack $stack): void
    {
        parent::setSlot($slot, $stack);
        $this->dirty = true;
    }

    public function swapSlots(int $first, int $second): void
    {
        parent::swapSlots($first, $second);
        $this->dirty = true;
    }

    public function transfer(int $from, int $to): bool
    {
        $moved = parent::transfer($from, $to);
        $this->dirty = $this->dirty || $moved;

        return $moved;
    }

    public function removeFromSlot(int $slot, int $amount): ItemStack
    {
        $removed = parent::removeFromSlot($slot, $amount);
        $this->dirty = true;

        return $removed;
    }

    public function loadFromArray(array $data): void
    {
        parent::loadFromArray($data);
        $this->dirty = true;
    }

    public function isPersistent(): bool
    {
        return $this->storageRoot !== '';
    }

    public function path(): string
    {
        if (!$this->isPersistent()) {
            return '';
        }

        return $this->directory() . DIRECTORY_SEPARATOR . $this->ownerUuid . '.inv';
    }

    /**
     * Diskten yükler.
     *
     * @return bool dosya bulundu ve geçerliyse `true`
     */
    public function load(): bool
    {
        if (!$this->isPersistent()) {
            return false;
        }

        $path = $this->path();

        if (!is_file($path)) {
            return false;
        }

        $body = @file_get_contents($path);

        if ($body === false) {
            $this->logger?->warning('Envanter dosyasi okunamadi.', ['path' => $path]);

            return false;
        }

        $magicLength = strlen(self::FILE_MAGIC);

        if (strlen($body) < $magicLength + 8 || substr($body, 0, $magicLength) !== self::FILE_MAGIC) {
            $this->logger?->warning('Envanter dosyasi bicimi gecersiz.', ['path' => $path]);

            return false;
        }

        $length = unpack('N', substr($body, $magicLength, 4))[1] ?? -1;
        $payload = substr($body, $magicLength + 4, max(0, $length));
        $checksum = unpack('N', substr($body, $magicLength + 4 + max(0, $length), 4))[1] ?? -1;

        if ($length < 0 || strlen($payload) !== $length || crc32($payload) !== $checksum) {
            $this->logger?->warning('Envanter dosyasi bozuk (crc32).', ['path' => $path]);

            return false;
        }

        try {
            $decoded = json_decode($payload, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->logger?->warning('Envanter dosyasi cozulemedi.', ['path' => $path]);

            return false;
        }

        if (!is_array($decoded)) {
            $this->logger?->warning('Envanter govdesi dizi degil.', ['path' => $path]);

            return false;
        }

        try {
            parent::loadFromArray($decoded);
        } catch (PlayerException $exception) {
            $this->logger?->warning('Envanter icerigi gecersiz.', [
                'path' => $path,
                'reason' => $exception->getMessage(),
            ]);

            return false;
        }

        $this->lastPersistedVersion = $this->version();
        $this->dirty = false;

        return true;
    }

    /**
     * @throws PlayerException yazma başarısızsa
     */
    public function save(): void
    {
        if (!$this->isPersistent()) {
            throw PlayerException::inventoryPersistenceFailed('memory', 'depolama koku tanimsiz');
        }

        $path = $this->path();
        $directory = $this->directory();

        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw PlayerException::inventoryPersistenceFailed($path, 'dizin olusturulamadi');
        }

        $payload = json_encode($this->toArray(), JSON_THROW_ON_ERROR);
        $body = self::FILE_MAGIC . pack('N', strlen($payload)) . $payload . pack('N', crc32($payload));
        $temporary = $path . '.tmp';

        if (@file_put_contents($temporary, $body) === false) {
            throw PlayerException::inventoryPersistenceFailed($path, 'gecici dosya yazilamadi');
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            @unlink($path);
        }

        if (!@rename($temporary, $path)) {
            @unlink($temporary);
            throw PlayerException::inventoryPersistenceFailed($path, 'rename basarisiz');
        }

        $this->lastPersistedVersion = $this->version();
        $this->dirty = false;
    }

    /**
     * Yalnızca kirliyse yazar (bağlantı kapanışı için).
     */
    public function flush(): void
    {
        if (!$this->isPersistent()) {
            return;
        }

        if (!$this->isDirty() && is_file($this->path())) {
            return;
        }

        $this->save();
    }

    private function directory(): string
    {
        return rtrim($this->storageRoot, '/\\') . DIRECTORY_SEPARATOR . 'players';
    }
}
