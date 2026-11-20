<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\item;

use PHPUnit\Framework\TestCase;
use PickaxeMP\item\ItemRegistry;
use PickaxeMP\item\ItemTable;
use PickaxeMP\protocol\packet\types\ItemEntry;

/**
 * Eşya tablosu: gömülü `resources/item-registry.json` dosyasının
 * doğrulanması ve `ItemRegistry` önyüklemesi.
 *
 * ## Neden tablo?
 *
 * `ItemRegistry` yalnızca **kayıtlı** eşyayı bilir. Canlı sunucuda
 * kayıt yapılmadığı için ağ kimliği çözülemiyordu: yemek reddediliyor,
 * `InventorySlot` bildirimi gönderilmiyordu. Tablo, istemcinin
 * `ItemRegistry` (162) paketiyle aldığı kimliklerin **aynı** kaynağıdır;
 * ikisi ayrışırsa istemci yanlış eşya gösterir.
 */
final class ItemTableTest extends TestCase
{
    public function testBundledTableLoadsEveryEntry(): void
    {
        $table = ItemTable::bundled();

        self::assertGreaterThan(1900, $table->count());
    }

    public function testBundledTableResolvesKnownFoods(): void
    {
        $table = ItemTable::bundled();

        self::assertSame(285, $table->networkId('minecraft:apple'));
        self::assertSame(291, $table->networkId('minecraft:porkchop'));
        self::assertSame(319, $table->networkId('minecraft:rabbit_stew'));
        self::assertSame(634, $table->networkId('minecraft:honey_bottle'));
    }

    public function testUnknownIdentifierReturnsNull(): void
    {
        self::assertNull(ItemTable::bundled()->networkId('minecraft:not_a_real_item'));
    }

    public function testBootstrapRegistersPositiveNetworkIdsOnly(): void
    {
        $registry = ItemRegistry::withBundledItems();

        self::assertTrue($registry->has('minecraft:apple'));
        self::assertSame(285, $registry->get('minecraft:apple')->networkId());

        // Hava bir eşya değil, boş yuvanın temsilidir; `-158` negatif olduğu
        // için kayıt defterine girmez.
        self::assertFalse($registry->has('minecraft:air'));
        self::assertNull($registry->byNetworkId(-158));
    }

    public function testBootstrapCoversEveryFoodTableIdentifier(): void
    {
        $registry = ItemRegistry::withBundledItems();
        $missing = [];

        foreach (array_keys(\PickaxeMP\player\FoodTable::withDefaultFoods()->all()) as $identifier) {
            if (!$registry->has($identifier)) {
                $missing[] = $identifier;
            }
        }

        self::assertSame([], $missing, 'FoodTable kimlikleri kayit defterinde eksik');
    }

    public function testBootstrapIsIdempotentPerRegistryInstance(): void
    {
        $registry = ItemRegistry::withBundledItems();
        $before = $registry->count();

        // Aynı deftere ikinci kez önyükleme yinelenen kayıt hatası verir;
        // bu yüzden önyükleme yalnızca fabrikada yapılır.
        $this->expectException(\PickaxeMP\player\PlayerException::class);
        ItemTable::bundled()->bootstrap($registry);
    }

    public function testTableRejectsMalformedJson(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pxitem');
        self::assertNotFalse($path);
        file_put_contents((string) $path, '{"items": "bozuk"}');

        try {
            ItemTable::fromFile((string) $path);
            self::fail('Bozuk tablo kabul edilmemeliydi.');
        } catch (\PickaxeMP\player\PlayerException) {
            self::assertTrue(true);
        } finally {
            @unlink((string) $path);
        }
    }

    public function testTableRejectsMissingFile(): void
    {
        $this->expectException(\PickaxeMP\player\PlayerException::class);

        ItemTable::fromFile(sys_get_temp_dir() . '/pickaxemp-yok-' . bin2hex(random_bytes(4)) . '.json');
    }

    public function testEntriesAreSentWithVanillaDefinitions(): void
    {
        $table = ItemTable::bundled();
        $byName = [];

        foreach ($table->entries() as $entry) {
            $byName[$entry->name] = $entry;
        }

        self::assertArrayHasKey('minecraft:apple', $byName);
        self::assertSame(285, $byName['minecraft:apple']->runtimeId);

        // Tel uzerinde tum girisler vanilya tanimina duser: bilesen tabanli
        // bayrak kapali, surum `None` (2), veri bos compound.
        foreach ($table->entries() as $entry) {
            self::assertFalse($entry->componentBased, $entry->name . ' bilesen tabanli olmamali');
            self::assertSame(ItemTable::WIRE_VERSION, $entry->version, $entry->name);
            self::assertSame(ItemEntry::EMPTY_COMPOUND, $entry->data, $entry->name);
        }

        // Ham kaynak degerleri korunur (yeniden uretim/denetim icin).
        self::assertTrue($table->all()['minecraft:apple']['componentBased']);
        self::assertSame(1, $table->all()['minecraft:apple']['version']);
    }
}
