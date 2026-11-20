# Phase 6 — Envanter ve Varlık Motoru (uygulama planı)

Hedef protokol: **1.26.65 / 2225**. Şema kaynakları:
`docs/protocol/schemas/v1.26.65/` (Mojang `v1.26.65` etiketi, indirilmiş ve
`[cereal:packet]` kimlikleri doğrulanmış).

## 0. Test sözleşmesi onarımı

Sekiz RED testi makine üretimiydi ve birbiriyle çelişiyordu. Aşağıdaki tek
tutarlı sözleşme benimsendi; testler buna göre onarıldı.

| Çelişki | Karar |
|---|---|
| `ItemRegistry::register()` → `Item` mi `bool` mu? | **`Item` döner**, yinelenen kayıtta `PlayerException` fırlatır |
| `Inventory::isFull()` 1/27'de mi 36/36'da mı? | **Boş yuva yoksa** `true` (36/36) |
| `removeFromSlot(0,5)` sonrası `count()` | **Toplam eşya sayısı** (15 → 10) |
| `100.5f` PHP'de geçersiz | `100.5` |
| `uniqid()->substr(-10)` | `substr(uniqid(), -10)` |
| `Tools\PHP\Logger` | `PickaxeMP\utils\Logger` |
| `assertAlmostEquals` (PHPUnit 11'de yok) | `assertEqualsWithDelta` |
| `ItemStack(…, 65)` + `add(1)` hatası | kurucu 1..128 kabul eder; `add()` `maxStackSize` (64) sınırında fırlatır |

## 1. Sınıf haritası

| Sınıf | Ad alanı | Sorumluluk |
|---|---|---|
| `Item` | `PickaxeMP\item` | Değişmez eşya tanımı (kimlik, ağ kimliği, yığın sınırı) |
| `ItemRegistry` | `PickaxeMP\item` | Tek kayıt defteri; kimlik ve ağ kimliği benzersizliği; JSON kalıcılığı |
| `ItemStack` | `PickaxeMP\item` | Miktar/yığın doğrulaması, `add`/`remove`, boş yığın |
| `Inventory` | `PickaxeMP\inventory` | Yuva dizisi, atomik işlemler, `count`/`isFull` |
| `InventoryTransaction` | `PickaxeMP\inventory` | Doğrula-sonra-uygula (kısmi mutasyon yok) |
| `PlayerInventory` | `PickaxeMP\player` | 36 yuva (9 hotbar + 27 ana), UUID anahtarlı kalıcılık |
| `Entity` | `PickaxeMP` | Yaşam döngüsü, konum/dönüş doğrulaması |
| `EntityManager` | `PickaxeMP\entity` | Monoton kimlik + nesil (epoch), kayıt/temizlik |
| `EntityTracker` | `PickaxeMP\entity` | Görünürlük: izleyici başına takip kümesi |

## 2. Envanter paketleri (doğrulanmış kimlikler)

| Paket | ID | Yön | Kodlayıcı |
|---|---|---|---|
| `InventoryTransaction` | 30 | C→S | `InventoryTransactionPacket` (yalnız `Normal` kaynağı; diğerleri açık hata) |
| `MobEquipment` | 31 | S→C | `MobEquipmentPacket` |
| `Interact` | 33 | C→S | Phase 7 (kayıtsız) |
| `PlayerAction` | 36 | C→S | Phase 7 (kayıtsız) |
| `ContainerOpen` | 46 | S→C | `ContainerOpenPacket` |
| `ContainerClose` | 47 | S→C | `ContainerClosePacket` |
| `InventoryContent` | 49 | S→C | `InventoryContentPacket` |
| `InventorySlot` | 50 | S→C | `InventorySlotPacket` |
| `ItemStackRequest` | 147 | C→S | Phase 6.1 (açık `ProtocolException`) |
| `ItemStackResponse` | 148 | S→C | Phase 6.1 (açık `ProtocolException`) |
| `AddPlayer` | 12 | S→C | `AddPlayerPacket` |
| `AddActor` | 13 | S→C | `AddActorPacket` |
| `RemoveActor` | 14 | S→C | `RemoveActorPacket` |
| `MoveActorAbsolute` | 18 | S→C | `MoveActorAbsolutePacket` |

`NetworkItemStackDescriptor` alan sırası (doğrulanmış, ordinal 0,1,2,4,3,5):

```
Id               int16   (0 = geçersiz eşya)
Stack size       uint16
Aux value        uint32
Block Runtime Id uint32
Net Id Variant   ItemStackNetIdVariant (opsiyonel)
User Data Buffer string (varuint uzunluk + ham bayt)
```

**Dürüst sınır:** `User Data Buffer` içindeki NBT, Phase 6 kapsamında
üretilmez; çağıran ham bayt verir. Boş tampon "özel ad/biçim yok" demektir.

## 3. Kalıcılık

`PlayerInventory` her mutasyonda `dirty` işaretler; `save()`:

1. `players/<uuid>.inv` için geçici dosyaya yaz (`<uuid>.inv.tmp`)
2. Gövde = `magic("PXINV1") + payloadLength(uint32) + payload + crc32(uint32)`
3. `rename()` ile atomik yer değiştir (Windows'ta önce `@unlink`)

Yükleme: sihir + uzunluk + CRC32 doğrulanır; bozuksa yükleme **reddedilir**,
envanter boş başlar ve olay `warning` olarak loglanır (sessiz kabul yok).

Kanca noktaları: `PlayerManager::create()` (oluştur + yükle) ve
`ConnectionPipeline::onConnectionClosed()` (kaydet → sonra oyuncuyu kaldır).

## 4. Güvenlik kontrol listesi (Nemotron denetimi)

- [x] İstemci alanları mutasyondan **önce** doğrulanır
- [x] Atomik geri alma: `InventoryTransaction` kopya üzerinde doğrular
- [x] Sahiplik: `PlayerInventory` doğrulanmış UUID ile anahtarlanır
- [x] CRC32 + atomik `rename`
- [x] Monoton varlık kimliği + nesil (epoch); kimlik hemen yeniden kullanılmaz
- [x] Alıcı başına görünürlük denetimi (`EntityTracker`)
- [x] Sahte varlık paketi reddi: `AddActor`/`AddPlayer` yalnız sunucu tarafından üretilir

## 5. Doğrulama

```
tools/php.cmd -c tools/php.ini vendor\bin\phpunit --configuration phpunit.xml
tools\composer.cmd lint
graphify update .
```

Gerçek istemci doğrulaması: **BLOCKED** (istemci yok).
