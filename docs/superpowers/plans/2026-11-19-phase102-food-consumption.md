# Phase 10.2 — Yemek Tüketimi

**Tarih:** 2026-11-19
**Durum:** ✅ Tamamlandı (kod + test; gerçek istemci doğrulaması yok)

## Amaç

Phase 9 açlık zincirini, Phase 10.1 açlık sonuçlarını kurdu; ancak açlığı
**geri doldurmanın** hiçbir yolu yoktu. Phase 10.2 envanterdeki yenilebilir
eşyayı tüketip açlık/doygunluk ekler:

1. `ItemUseInventoryTransaction` (`Action Type = Use`) ve
   `PlayerAuthInputPacket::itemUseTransaction` yollarında yenilebilir eşya
   tespiti.
2. Eşya başına besin/doygunluk tablosu (ayrı veri sınıfları).
3. `Player::eat()` çağrısı, envanterden 1 düşüm ve `InventorySlot` bildirimi.
4. `UpdateAttributes` ile açlık çubuğunun hemen güncellenmesi.

## Kapsam

| # | İş | Durum |
|---|---|---|
| 1 | `FoodNutrition` — doğrulanmış besin değeri | ✅ |
| 2 | `FoodTable` — varsayılan yenilebilir eşya tablosu (37 eşya) | ✅ |
| 3 | `FoodConsumptionManager` — elde tutma, açlık, soğuma kuralları | ✅ |
| 4 | `PlayerAuthInput` — `Use` yolunu yemek tüketimine yönlendirme | ✅ |
| 5 | `InventorySlot` + `UpdateAttributes` bildirimi | ✅ |
| 6 | `ConnectionPipeline` — ayrılan oturumun soğuma kaydını silme | ✅ |
| 7 | Birim + entegrasyon testleri | ✅ |

Registry: **41** paket — yeni paket yok.

## Kurallar

| Kural | Değer | Gerekçe |
|---|---|---|
| `EAT_COOLDOWN_MS` | 1600 ms | Java/Bedrock yeme süresine yakın; paket spam'iyle tüm yığının anında yenmesini engeller |
| Yeme koşulu | `hunger < 20` | Tok oyuncu yemek yiyemez (vanilla `needsFood`) |
| Yalnızca ana el | `HandSlot::MainHand` | Off-hand envanteri yok |
| Yalnızca seçili hotbar | `selectedHotbarSlot()` | Sırt çantasından yemek yenemez |
| Ağ kimliği | `ItemRegistry` kaydı zorunlu | Kayıtsız eşyanın `InventorySlot` bildirimi gönderilemez; sunucu-istemci ayrışması olur |

- **Tüketim sunucu yetkilidir:** istemcinin `item` tanımlayıcısına güvenilmez;
  yığın envanterden okunur.
- **Önce düş, sonra uygula:** `removeFromSlot()` (fırlatabilir) **önce**,
  `eat()` (fırlatmaz) **sonra** çağrılır. Böylece düşüm başarısız olursa
  oyuncu besini bedavaya almaz. Dayanak: `PlayerAttributes::eat()` değerleri
  üst sınıra kırpar ve istisna fırlatmaz.
- **Tüketim başına 1 eşya:** yığın biterse yuva `null` olur ve istemciye boş
  tanımlayıcı (`NetworkItemStackDescriptor::empty()`) gider.
- **Ölü/`Joining` oyuncu yiyemez:** `Player::canInteract()` kapısı
  (`state->canInteract() && health > 0`).
- **Ağdan bağımsız çekirdek:** `FoodConsumptionManager` paket üretmez; yalnızca
  sonucu döndürür.

## Dürüst sınırlar

- Gerçek Bedrock istemcisi yemek yerken `ItemStackRequest` (147) de
  gönderebilir; bu paket hâlâ kayıtsızdır. `ItemUse` yolu gerçek istemcide
  sınanmadan "yemek çalışıyor" denemez.
- Üretimde (`start.php`) ağ katmanı hiç önyüklenmiyor; `ItemRegistry` canlı
  sunucuda dolmadığı için yemek kimlikleri yalnızca envanter içeriğiyle var
  olur. Kayıtsız eşya `consume()` tarafından reddedilir.
- Zehir/açlık efekti, altın elma soğuması, kek/pasta gibi özel tüketim
  kuralları yoktur; yalnızca açlık + doygunluk uygulanır.
- Bal şişesi cam şişe bırakmaz; kase yemekleri kase bırakmaz.
- Yaratıcı modda tüketim davranışı yoktur (yetenek modeli Phase 10.3).

## TDD günlüğü

1. **Kırmızı:** `tests/unit/player/FoodTableTest.php` (7 test) ve
   `tests/unit/player/FoodConsumptionManagerTest.php` (13 test) yazıldı;
   `FoodNutrition`, `FoodTable`, `FoodConsumptionManager`,
   `FoodConsumptionResult` yok → sınıf bulunamadı hataları.
2. **Yeşil:** Dört sınıf eklendi. Odaklı çalıştırma
   `OK (20 tests, 398 assertions)`. `consume(40.0)` gerçek tükenme
   matematiğiyle 5 doygunluk + 5 açlık yaktığı için beklentiler 20/19'a göre
   düzeltildi.
3. **Kırmızı (entegrasyon):** `PlayerAuthInput` yemek yönlendirmesi ve
   `ConnectionPipeline` `forget()` bağlanmadan `ConnectionPipelineTest`'te iki
   test yazıldı → `InventorySlot`/`UpdateAttributes` paketleri gelmedi.
4. **Yeşil:** `PlayerAuthInput::handleItemUse()` tek yönlendirici oldu
   (`Use` → yemek, diğerleri → `BlockActionManager::place`); `ConnectionPipeline`
   yapıcısına `?FoodConsumptionManager $food` eklendi ve
   `onConnectionClosed()` içinde `forget()` çağrıldı. Entegrasyon testleri
   yeşil.
5. **Ek kilit:** `testEatingLastItemSendsEmptySlotDescriptor` — son eşya
   tüketilince `InventorySlot` (id 0, count 0) **gönderildiği** doğrulanır
   (dört modelin "boş yuva bildirilmiyor" iddiası yanlış pozitifti).
6. **Dört model incelemesi** (`.team/phase102-review.md`): tek gerçek bulgu
   mutasyon sırasıydı; `removeFromSlot` → `eat` olarak düzeltildi, diğer 9
   bulgu yanlış pozitif / bilinen sınır olarak kayda geçti.

## Doğrulama

```
tests\unit\player\FoodTableTest.php + FoodConsumptionManagerTest.php
                                    → OK (20 tests, 398 assertions)
tests\integration\ConnectionPipelineTest.php
                                    → OK (38 tests, 441 assertions)
tam PHPUnit                         → OK (955 tests, 11955 assertions)
tools\composer.cmd lint             → 328 dosya, sözdizimi temiz
graphify update .                   → 4505 node, 12873 edge, 175 community
```

## Dosyalar

- `src/PickaxeMP/player/FoodNutrition.php`
- `src/PickaxeMP/player/FoodTable.php`
- `src/PickaxeMP/player/FoodConsumptionManager.php`
- `src/PickaxeMP/player/FoodConsumptionResult.php`
- `src/PickaxeMP/player/PlayerAuthInput.php`
- `src/PickaxeMP/connection/ConnectionPipeline.php`
- `tests/unit/player/FoodTableTest.php`
- `tests/unit/player/FoodConsumptionManagerTest.php`
- `tests/integration/ConnectionPipelineTest.php`
- `.team/phase102-prompt.md`, `.team/phase102-review.md`,
  `.team/phase102-<model>.json`
