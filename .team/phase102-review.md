# Phase 10.2 — OmniRoute dört model incelemesi (kayıt)

Tarih: 2026-11-19. Komut: `tools\omniroute-team.ps1` (yerel OmniRoute,
`http://127.0.0.1:20128/v1/chat/completions`). İstem dosyası:
`.team/phase102-prompt.md`. Ham yanıtlar `.team/phase102-<model>.json`.

## Kullanım

| Model (gerçek kimlik) | Prompt | Completion | Toplam | Süre | Sonuç |
|---|---:|---:|---:|---:|---|
| `nvidia/nvidia/nemotron-3.5-lightning-30b-a3b` | 1367 | 2042 | 3409 | 15.0 s | OK (stop) |
| `nvidia/nvidia/nemotron-3.5-super-vl-120b-a12b` | 1367 | 3000 | 4367 | 24.8 s | OK (length — `max_tokens`) |
| `nvidia/minimaxai/minimax-m3.1-flash` | 1586 | 1600 | 3186 | 273.4 s | OK (length — `max_tokens`) |
| `nvidia/meta/muse-glimmer-30b` | 1308 | 1826 | 3134 | 225.0 s | OK (stop) |
| **Toplam** | **5628** | **8468** | **14096** | — | **4/4 yanıt** |

Notlar:

- `minimax` ilk denemede istemci zaman aşımına (600 s) düştü; ikinci deneme
  `MaxTokens 1600` ile 273 s'de tamamlandı ve yanıt `length` ile kesildi.
- `super` ve `minimax` yanıtları `max_tokens` sınırında kesildi; bu yüzden
  sonlarındaki öneri listeleri eksik olabilir. Bulguların tamamı kesilmeden
  önce geldi.

## Bulgular ve kararlar

| # | Bulgu (modeller) | Doğrulama | Karar |
|---|---|---|---|
| 1 | `hunger >= MAX_HUNGER` reddi yanlış; `>` olmalı (Lightning) | Vanilla'da açlık tam 20 iken yemek yenmez; `>=` doğru. Model "açlık 20'de açlıktan ölüm başlar" varsayımı yanlış — hasar açlık **0** iken başlar | **Yanlış pozitif** — değişiklik yok |
| 2 | Son eşya tüketilince `InventorySlotPacket` gönderilmiyor → istemci/sunucu ayrışması (Muse, Super, Lightning) | `descriptorFor(null)` `NetworkItemStackDescriptor::empty()` döner; paket **gönderilir**. Yeni entegrasyon testi `testEatingLastItemSendsEmptySlotDescriptor` bunu kilitler (count 0, id 0) | **Yanlış pozitif** — testle kilitlendi |
| 3 | `consume()` `null` dönünce `handleItemUse` erken çıkıyor, `place()` çalışmıyor (Muse, Super) | `BlockActionManager::place()` zaten `actionType !== Place` için reddediyor; iki davranış eşdeğer | **Yanlış pozitif** — değişiklik yok |
| 4 | `eat()` + `removeFromSlot()` atomik değil; istisna hâlinde bedava besin veya eşya kaybı (Muse, Super, minimax) | `eat()` istisna fırlatmaz (`FoodNutrition` değerleri kurucuda doğrulanır, `PlayerAttributes::eat` üst sınıra kırpar); `removeFromSlot()` fırlatır. Sıra `eat → remove` olduğu için teorik "besin geldi, eşya kaldı" penceresi vardı | **Düzeltildi** — sıra `remove → eat` yapıldı; kodda gerekçe yorumu var |
| 5 | `removeFromSlot()` sonrası ikinci `getSlot()` deterministik değil; kalan miktar önceden hesaplanmalı (Super, Lightning) | `removeFromSlot` boşalan yuvayı `null` yapar ve aynı nesneyi yerinde günceller; ikinci okuma sunucu durumunun **kendisidir** (daha güvenli, istemci sayısına güvenmez) | **Reddedildi** — okuma kasıtlı, yorum eklendi |
| 6 | `lastEatAt` paketler gönderilmeden yazılıyor (Super) | Soğuma sunucu tarafı durumdur; paket gönderimi başarısız olsa bile oyuncu eşyayı kaybetmiştir, soğumanın geri alınması istismar kapısı açar | **Reddedildi** |
| 7 | `UpdateAttributes` tüm listeyi yolluyor, yalnızca değişenler yollanmalı (Super) | Katılımda (`completeJoin`) da tüm liste yollanıyor; protokol tam listeyi kabul eder, sıra korunur | **Reddedildi** (bant genişliği notu) |
| 8 | Ölü oyuncu için ayrıca `isAlive()` kontrolü yok (Lightning, Super) | `Player::canInteract()` = `state->canInteract() && health > 0`; ölü oyuncuda `false` | **Yanlış pozitif** — mevcut test `testRejectsDeadPlayer` |
| 9 | Soğuma oturum kimliğine bağlı; yeniden bağlanınca sıfırlanır (Lightning, Super) | Yeniden bağlanma el sıkışması + kimlik doğrulaması 1.6 s'den uzun sürer; `forget()` bellek sızıntısını önler | **Kabul edilen sınır** — dokümanda yazılı |
| 10 | `ItemRegistry` `null` ise tüm yemekler reddedilir; canlı sunucuda açlık giderilemez (Super) | Doğru: üretimde `ConnectionPipeline`/`ItemRegistry` önyüklemesi yok (ağ katmanı henüz çalışma zamanında bağlanmadı). Phase 10.2 bunu değiştirmez | **Bilinen sınır** — `DEVELOPMENT_STATE.md` §4F.3 ve §7'de yazılı |

## Düzeltme (tek gerçek bulgu)

`FoodConsumptionManager::consume()` içinde mutasyon sırası tersine çevrildi:

```php
// önce envanter düşer: removeFromSlot fırlatırsa besin verilmemiş olur
$player->inventory()->removeFromSlot($slot, 1);
$player->eat($nutrition->hunger, $nutrition->saturation);
```

Önceki sıra (`eat` → `remove`) teorik olarak "açlık eklendi ama eşya
envanterde kaldı" penceresi bırakıyordu. `removeFromSlot` ön koşulları
( yuva dolu, `amount = 1 <= count` ) yukarıda doğrulandığı için bu pencere
pratikte erişilemezdi; yine de sıra değişimi bedelsiz ve daha güvenli.

## Doğrulama (düzeltme sonrası)

```
tools\php.cmd -l src\PickaxeMP\player\FoodConsumptionManager.php → No syntax errors
tests\unit\player\FoodTableTest.php + FoodConsumptionManagerTest.php → OK (20 tests, 398 assertions)
tests\integration\ConnectionPipelineTest.php → OK (38 tests, 441 assertions)
tam PHPUnit → OK (955 tests, 11955 assertions)
```
