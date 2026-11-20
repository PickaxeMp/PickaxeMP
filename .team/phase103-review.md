# Phase 10.3 — OmniRoute dört model incelemesi (kayıt)

Tarih: 2026-11-19. Komut: `tools\omniroute-team.ps1` (yerel OmniRoute,
`http://127.0.0.1:20128/v1/chat/completions`). İstem dosyası:
`.team/phase103-prompt.md`. Ham yanıtlar `.team/phase103-<model>.json`.

## Kullanım

| Model (gerçek kimlik) | Prompt | Completion | Toplam | Süre | Sonuç |
|---|---:|---:|---:|---:|---|
| `nvidia/nvidia/nemotron-3.5-lightning-30b-a3b` | 3771 | 2500 | 6271 | 22.1 s | OK (length – `max_tokens`) |
| `nvidia/nvidia/nemotron-3.5-super-vl-120b-a12b` | 3771 | 2192 | 5963 | 20.0 s | OK (stop) |
| `nvidia/minimaxai/minimax-m3.1-flash` | 3988 | 1600 | 5588 | 164.0 s | OK (length – `max_tokens`) |
| `nvidia/meta/muse-glimmer-30b` | 3542 | 2500 | 6042 | 292.8 s | OK (length – `max_tokens`) |
| **Toplam** | **15072** | **8792** | **23864** | – | **4/4 yanıt** |

Not: Üç yanıt `max_tokens` sınırında kesildi (minimax ilk denemede 600 s
istemci zaman aşımına düşerdi; `MaxTokens 1600` ile tamamlandı). Bulgular
kesilmeden önce geldi; kesilen kısımlar öneri listelerinin sonudur.

## Bulgular ve kararlar

| # | Bulgu (modeller) | Doğrulama | Karar |
|---|---|---|---|
| 1 | **İmleçteki eşya kopmada kaybolur**: `forget()` yalnızca kaydı siler, eşya envantere geri yazılmaz (Muse, Super, minimax; Lightning dolaylı) | Doğru: `cursor` map'i bellekte tutulur, `onConnectionClosed` yalnızca `forget()` çağırıyordu. Oyuncu imleçte eşya varken çıkarsa eşya yok oluyordu | **Düzeltildi** — `ItemStackRequestManager::release()` imleci envantere geri yazar (önce birleştir, sonra boş yuva); `onConnectionClosed` içinde `saveInventory`'den **önce** çağrılır. Testler: `testReleaseMergesCursorStackBackIntoInventory`, `testReleaseUsesEmptySlotWhenNoStackCanMerge`, `testReleaseWithoutCursorLeavesInventoryUntouched`, `testDisconnectReturnsCursorStackToInventory` |
| 2 | `amount` üst sınırı (64) sunucuda doğrulanmıyor; tel `uint8` 0-255 taşıyabilir (Super, minimax, Muse, Lightning) | `amount`, kaynak yığının **gerçek** miktarıyla sınırlanıyor (`amount > $sourceStack->count()` reddi); boş hedefte `amount > maxStackSize()` → `CannotPlaceItem`; dolu hedefte `remainingSpace() < amount` → `InvalidAdjustedAmount`. Yığın miktarı hiçbir zaman `maxStackSize`'ı (varsayılan 64) aşamaz, dolayısıyla `amount > 64` pratikte kabul edilemez. Kodlayıcı zaten `MAX_AMOUNT` zorluyor | **Yanlış pozitif** — değişiklik yok |
| 3 | `Swap` gövdesinde `amount` yok; `move()` çağrılırsa `amount < 1` hatası (Lightning) | Kod `Swap` için `swap()` çağırıyor, `move()` değil; çözücü `Swap` için `amount = 0` bırakır ve hiç kullanılmaz | **Yanlış pozitif** |
| 4 | `swap()` miktar doğrulaması yapmıyor → dupe (Lightning) | `Swap` tam takastır: miktar kavramı yoktur. Yalnızca `source`/`destination` yığınları yer değiştirir; hiçbir eşya yaratılmaz/çoğalmaz. Eşya kaybı iddiasındaki senaryo (`Place` sonrası `Swap`) zaten aynı kopya üzerinde sıralı çalışır | **Yanlış pozitif** |
| 5 | `ItemStack` nesneleri paylaşılan referanstır, kopya yüzeysel → geri alma bozuk (Lightning, minimax) | `$slots` her yuva için `$stack?->copy()`, imleç de `->copy()` ile **ayrı nesne** olur. `withCount()` yeni nesne döner; `add()`/`remove()` yalnızca kopyaları değiştirir. Hata hâlinde kopya atılır, envanter/imleç hiç yazılmaz (test: `testUnsupportedActionIsRejectedAtomically`, `testInsufficientSourceRejectsWithoutMutation`) | **Yanlış pozitif** |
| 6 | `sameStack()` NBT/meta/durability karşılaştırmıyor (Super) | `ItemStack` alanları yalnızca `type`, `count`, `maxStackSize`; NBT/durability bu sürümde yok. Karşılaştırma tüm alanları kapsıyor | **Yanlış pozitif** (alanlar yok) |
| 7 | `requestId` negatif-tek kontrolü doğrulanmalı (Super) | `ItemStackRequest::hasValidRequestId()` = `requestId < 0 && requestId % 2 !== 0`; test `testInvalidRequestIdIsRejected` | **Zaten doğru** |
| 8 | `type >= 9` varyant kuralı uygulanmıyor (Super) | Çözücüde `expectedVariant = type >= 9 ? type - 2 : type`; uyuşmazlıkta `ProtocolException`. Test: `ItemStackRequestTest` (varyant kuralı) | **Zaten doğru** |
| 9 | Konteyner sırası (envanter → imleç) protokolde sabit değil (Super, Muse) | Protokol konteynerleri `FullContainerName` ile eşler, sıra anlamlı değil; istemci isimle eşler. Sıra deterministik tutuluyor | **Yanlış pozitif** |
| 10 | `MAX_REQUESTS` / `MAX_RESPONSES` denetlenmiyor (Muse) | `ItemStackRequestPacket` çözücüsü 100 istek, `ItemStackResponsePacket` kodlayıcısı 100 yanıt sınırını zorlar (`lengthTooLarge`) | **Yanlış pozitif** |
| 11 | Eşzamanlı isteklerde yarış durumu / mutex gerekli (Lightning) | Sunucu tek iş parçacıklı tick döngüsünde çalışır; `dispatch()` istekleri sırayla işler, `$slots` yerel değişkendir. Kilit gereksiz | **Yanlış pozitif** |
| 12 | `canInteract()` false iken imleç sızıntısı (Super) | Erken dönüşte imleç **okunmaz** bile; yalnızca `forget()`/`release()` siler | **Yanlış pozitif** |
| 13 | `Swap` isteğinde `amount` sıfır olmalı, doğrulanmalı (Muse) | Tel gövdesinde `amount` alanı yok; çözücü daima 0 verir, `swap()` kullanmaz. Ulaşılamaz durum | **Yanlış pozitif** (telden ulaşılamaz) |
| 14 | `onConnectionClosed` sırasında `forget()` çağrılmazsa sızıntı (Super) | Çağrılıyor (`$this->stackRequests->forget(...)`) | **Yanlış pozitif** |

## Düzeltme (tek gerçek bulgu)

`ItemStackRequestManager::release(string $sessionId, PlayerInventory $inventory)`
eklendi. Sıra:

1. İmleç yoksa hiçbir şey yapılmaz.
2. Aynı türde ve yeri olan yığınlara `min(remainingSpace, kalan)` kadar eklenir.
3. Kalan miktar boş yuvalara `maxStackSize` sınırıyla yeni yığın olarak konur.
4. Hâlâ yer yoksa `warning` loglanır (`dropped` miktarı ile) — sessiz kayıp yok.

`ConnectionPipeline::onConnectionClosed`, `saveInventory($player)` çağrısından
**önce** `release()` çağırır; `setSlot()` `dirty` işaretini güncellediği için
geri yazılan eşya kalıcı dosyaya da düşer.

## Dürüst sınır

- `release()` envanter tamamen doluysa ve birleştirilecek yer yoksa eşyayı
  düşürür; Vanilla'da bu durumda eşya oyuncunun ayaklarında yere düşer.
  Yere düşürme (item actor) bu fazın kapsamı dışında; log ile bildirilir.
- Dört modelin hiçbiri gerçek istemci davranışını sınamadı; `CLIENT_VERIFIED`
  hâlâ **BLOCKED**.
