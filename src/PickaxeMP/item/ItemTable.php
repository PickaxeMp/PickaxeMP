<?php

declare(strict_types=1);

namespace PickaxeMP\item;

use PickaxeMP\player\PlayerException;
use PickaxeMP\protocol\packet\types\ItemEntry;

/**
 * Eşya kimlik tablosu: metin kimliği -> ağ kimliği.
 *
 * ## Tek kaynak ilkesi
 *
 * İstemci eşya kimliklerini `ItemRegistry` (162) paketiyle **öğrenir**.
 * Sunucu envanteri ise aynı kimlikleri `ItemRegistry` (bellek) üzerinden
 * üretir. İki tablo ayrışırsa istemci yanlış eşya gösterir; bu yüzden
 * ikisi de aynı dosyadan (`resources/item-registry.json`) beslenir.
 *
 * ## Veri kaynağı
 *
 * Dosya, `pmmp/BedrockData` deposundaki `required_item_list.json`
 * (protocol 2225 / 1.26.65) tablosundan üretilir. Değerler **tahmin
 * değildir**; `runtime_id` alanı doğrudan ağ kimliğidir (`int16`).
 *
 * ## Neden hava kaydedilmiyor?
 *
 * `minecraft:air` tabloda `-158` kimliğiyle yer alır (istemci tablosunda
 * bulunması gerekir) ama **eşya değildir**: boş yuva `null` ile temsil
 * edilir. Bu yüzden önyükleme yalnızca negatif olmayan kimlikleri kaydeder.
 */
final class ItemTable
{
    /** Gömülü tablonun proje köküne göre yolu. */
    public const BUNDLED_PATH = 'resources/item-registry.json';

    /**
     * Tel üzerinde `Component Based` alanı **her zaman** `false`.
     *
     * Kaynak tabloda 73 eşya `component_based=true` (ör. `minecraft:apple`).
     * Bu bayrak "eşya tanımı `Data` alanındaki bileşenlerden gelir" demektir;
     * biz vanilya bileşen NBT'sini **üretemiyoruz**. `true` + boş compound
     * göndermek istemciye "tanımı var ama boş" demek olurdu; bu, ikonu/adi
     * olmayan eşyalara yol açar. `false` ise istemcinin **kendi gömülü
     * vanilya tanımına** düşmesini sağlar — sunucu vanilya içerik sunduğu
     * için doğru davranış budur.
     */
    public const WIRE_COMPONENT_BASED = false;

    /**
     * Tel üzerinde `Version` alanı **her zaman** `2` (`ItemEntryVersionNone`).
     *
     * gophertunnel sabitleri: `0 = Legacy`, `1 = DataDriven`, `2 = None`.
     * `None`, "bu eşyaya özel bir tanım eklenmedi" demektir; istemci yerleşik
     * tanımı kullanır. Kaynak tablodaki `1`/`2` değerleri veri odaklı tanım
     * sürümleridir ve elimizde o tanımlar yok.
     */
    public const WIRE_VERSION = 2;

    /** @var array<string, array{runtimeId: int, componentBased: bool, version: int}> */
    private readonly array $items;

    /**
     * @param array<string, array{runtimeId: int, componentBased: bool, version: int}> $items
     */
    private function __construct(array $items)
    {
        $this->items = $items;
    }

    /**
     * Proje kökündeki gömülü tabloyu yükler.
     *
     * @throws PlayerException dosya okunamazsa/bozuksa
     */
    public static function bundled(): self
    {
        return self::fromFile(self::bundledPath());
    }

    public static function bundledPath(): string
    {
        // Bu dosya: <kok>/src/PickaxeMP/item/ItemTable.php
        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, self::BUNDLED_PATH);
    }

    /**
     * @throws PlayerException dosya yoksa, JSON bozuksa veya giriş geçersizse
     */
    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw PlayerException::inventoryPersistenceFailed($path, 'esya tablosu bulunamadi');
        }

        $body = @file_get_contents($path);

        if ($body === false) {
            throw PlayerException::inventoryPersistenceFailed($path, 'esya tablosu okunamadi');
        }

        try {
            $decoded = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw PlayerException::inventoryPersistenceFailed($path, 'esya tablosu json: ' . $exception->getMessage());
        }

        $rows = is_array($decoded) ? ($decoded['items'] ?? null) : null;

        if (!is_array($rows)) {
            throw PlayerException::inventoryPersistenceFailed($path, 'items dizisi yok');
        }

        $items = [];

        foreach ($rows as $row) {
            if (!is_array($row) || count($row) < 2) {
                throw PlayerException::inventoryPersistenceFailed($path, 'gecersiz giris');
            }

            $identifier = (string) ($row[0] ?? '');
            $runtimeId = (int) ($row[1] ?? 0);
            $componentBased = (bool) ($row[2] ?? false);
            $version = (int) ($row[3] ?? 0);

            // Dogrulama `ItemEntry` uzerinden yapilir: tel bicimiyle ayni
            // kurallar (int16 araligi, kimlik deseni) burada da gecerlidir.
            new ItemEntry($identifier, $runtimeId, $componentBased, $version);

            $items[$identifier] = [
                'runtimeId' => $runtimeId,
                'componentBased' => $componentBased,
                'version' => $version,
            ];
        }

        return new self($items);
    }

    public function networkId(string $identifier): ?int
    {
        return $this->items[$identifier]['runtimeId'] ?? null;
    }

    public function has(string $identifier): bool
    {
        return isset($this->items[$identifier]);
    }

    /** @return array<string, array{runtimeId: int, componentBased: bool, version: int}> */
    public function all(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * İstemciye gönderilecek girişler — **tüm** tablo, ada göre artan.
     *
     * Girişler tel formatına normalize edilir: `componentBased=false`,
     * `version=2` ve boş `Data`. Gerekçe için bkz. `WIRE_COMPONENT_BASED` ve
     * `WIRE_VERSION`. Ham kaynak değerleri `all()` üzerinden erişilebilir.
     *
     * @return list<ItemEntry>
     */
    public function entries(): array
    {
        $identifiers = array_keys($this->items);
        sort($identifiers, SORT_STRING);

        $entries = [];

        foreach ($identifiers as $identifier) {
            $item = $this->items[$identifier];
            $entries[] = new ItemEntry(
                $identifier,
                $item['runtimeId'],
                self::WIRE_COMPONENT_BASED,
                self::WIRE_VERSION
            );
        }

        return $entries;
    }

    /**
     * Deftere kaydedilebilir eşyaları yazar (negatif kimlikliler atlanır).
     *
     * @return int Kaydedilen eşya sayısı
     */
    public function bootstrap(ItemRegistry $registry): int
    {
        $registered = 0;

        foreach ($this->entries() as $entry) {
            if ($entry->runtimeId < 0) {
                continue;
            }

            $registry->register($entry->name, $entry->runtimeId, Item::DEFAULT_MAX_STACK_SIZE);
            $registered++;
        }

        return $registered;
    }
}
