<?php

declare(strict_types=1);

namespace PickaxeMP\item;

use PickaxeMP\player\PlayerException;
use PickaxeMP\utils\Logger;

/**
 * Eşya tanımlarının tek kayıt defteri.
 *
 * ## Değişmez kurallar
 *
 * 1. **Metin kimliği benzersizdir** — ikinci kayıt `PlayerException` fırlatır
 *    ve defter değişmez (atomik: önce doğrula, sonra yaz).
 * 2. **Ağ kimliği benzersizdir** — istemci eşyayı sayıyla tanır; iki farklı
 *    eşyanın aynı sayıyı paylaşması istemcide yanlış eşya üretir. Bu yüzden
 *    çakışan ağ kimliği de reddedilir.
 * 3. Defter **in-memory** çalışır; `save()` çağrılırsa JSON olarak diske
 *    yazılır. Kalıcılık açık bir eylemdir, her kayıtta disk G/Ç yapılmaz.
 *
 * ## Neden `register()` `Item` döner?
 *
 * Çağıranın kaydettiği nesneyi ayrıca araması gerekmesin diye. Yinelenen
 * kayıt istisna fırlattığı için "sessizce false dönen" bir imza, hatayı
 * yutmaya davetiye çıkarırdı.
 */
final class ItemRegistry
{
    private const FILE_MAGIC = 'PXITEM1';

    /** @var array<string, Item> */
    private array $byIdentifier = [];

    /** @var array<int, Item> ağ kimliği => eşya */
    private array $byNetworkId = [];

    public function __construct(
        private readonly string $storageRoot = '',
        private readonly ?Logger $logger = null
    ) {
        if ($storageRoot !== '') {
            $this->loadIfPresent();
        }
    }

    /**
     * Gömülü eşya tablosuyla dolu defter (canlı sunucu varsayılanı).
     *
     * Tablo `resources/item-registry.json` dosyasından okunur; istemciye
     * gönderilen `ItemRegistry` (162) paketi de aynı dosyadan beslenir, bu
     * yüzden iki taraf ayrışamaz.
     */
    public static function withBundledItems(string $storageRoot = '', ?Logger $logger = null): self
    {
        $registry = new self($storageRoot, $logger);
        ItemTable::bundled()->bootstrap($registry);

        return $registry;
    }

    /**
     * @throws PlayerException kimlik/ağ kimliği çakışırsa veya geçersizse
     */
    public function register(string $identifier, int $networkId, int $maxStackSize = Item::DEFAULT_MAX_STACK_SIZE): Item
    {
        $item = new Item($identifier, $networkId, $maxStackSize);

        if (isset($this->byIdentifier[$identifier])) {
            throw PlayerException::duplicateItem($identifier, 'kimlik');
        }

        if (isset($this->byNetworkId[$networkId])) {
            throw PlayerException::duplicateItem(
                $identifier,
                'ag kimligi ' . $networkId . ' => ' . $this->byNetworkId[$networkId]->identifier()
            );
        }

        $this->byIdentifier[$identifier] = $item;
        $this->byNetworkId[$networkId] = $item;

        return $item;
    }

    /**
     * @throws PlayerException eşya yoksa
     */
    public function get(string $identifier): Item
    {
        if (!isset($this->byIdentifier[$identifier])) {
            throw PlayerException::unknownItem($identifier);
        }

        return $this->byIdentifier[$identifier];
    }

    public function has(string $identifier): bool
    {
        return isset($this->byIdentifier[$identifier]);
    }

    public function byNetworkId(int $networkId): ?Item
    {
        return $this->byNetworkId[$networkId] ?? null;
    }

    /**
     * @return array<string, Item>
     */
    public function all(): array
    {
        return $this->byIdentifier;
    }

    public function count(): int
    {
        return count($this->byIdentifier);
    }

    public function storageRoot(): string
    {
        return $this->storageRoot;
    }

    // --- Kalıcılık --------------------------------------------------------

    /**
     * Defteri `item-registry.json` dosyasına atomik yazar.
     *
     * @throws PlayerException depolama kökü tanımsızsa veya yazma başarısızsa
     */
    public function save(): void
    {
        if ($this->storageRoot === '') {
            throw PlayerException::inventoryPersistenceFailed('item-registry.json', 'depolama koku tanimsiz');
        }

        $payload = json_encode(
            array_map(
                static fn (Item $item): array => [
                    'identifier' => $item->identifier(),
                    'networkId' => $item->networkId(),
                    'maxStackSize' => $item->maxStackSize(),
                ],
                array_values($this->byIdentifier)
            ),
            JSON_THROW_ON_ERROR
        );

        $path = $this->path();
        $temporary = $path . '.tmp';
        $body = self::FILE_MAGIC . pack('N', strlen($payload)) . $payload . pack('N', crc32($payload));

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
    }

    private function loadIfPresent(): void
    {
        $path = $this->path();

        if (!is_file($path)) {
            return;
        }

        $body = @file_get_contents($path);

        if ($body === false || strlen($body) < strlen(self::FILE_MAGIC) + 8) {
            $this->warnCorrupt($path, 'dosya cok kisa');

            return;
        }

        $magicLength = strlen(self::FILE_MAGIC);

        if (substr($body, 0, $magicLength) !== self::FILE_MAGIC) {
            $this->warnCorrupt($path, 'sihir uyusmuyor');

            return;
        }

        $length = unpack('N', substr($body, $magicLength, 4))[1] ?? -1;
        $payload = substr($body, $magicLength + 4, $length);
        $checksum = unpack('N', substr($body, $magicLength + 4 + $length, 4))[1] ?? -1;

        if ($length < 0 || strlen($payload) !== $length || crc32($payload) !== $checksum) {
            $this->warnCorrupt($path, 'crc32 uyusmuyor');

            return;
        }

        try {
            $entries = json_decode($payload, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->warnCorrupt($path, 'json cozulemedi');

            return;
        }

        if (!is_array($entries)) {
            $this->warnCorrupt($path, 'json dizi degil');

            return;
        }

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            try {
                $this->register(
                    (string) ($entry['identifier'] ?? ''),
                    (int) ($entry['networkId'] ?? -1),
                    (int) ($entry['maxStackSize'] ?? Item::DEFAULT_MAX_STACK_SIZE)
                );
            } catch (PlayerException) {
                // Tek bozuk kayıt tüm defteri düşürmesin; atla ve uyar.
                $this->logger?->warning('Esya kaydi yuklenemedi.', [
                    'identifier' => (string) ($entry['identifier'] ?? ''),
                ]);
            }
        }
    }

    private function warnCorrupt(string $path, string $reason): void
    {
        $this->logger?->warning('Esya defteri yuklenemedi.', [
            'path' => $path,
            'reason' => $reason,
        ]);
    }

    private function path(): string
    {
        return rtrim($this->storageRoot, '/\\') . DIRECTORY_SEPARATOR . 'item-registry.json';
    }
}
