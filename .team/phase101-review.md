# Phase 10.1 — OmniRoute dört model incelemesi (kayıt)

Tarih: 2026-11-19. Komut: `tools\omniroute-team.ps1` (yerel OmniRoute,
`http://127.0.0.1:20128/v1/chat/completions`). İstem dosyası:
`.team/phase101-prompt.md`. Ham yanıtlar `.team/phase101-<model>.json`.

## Kullanım

| Model (gerçek kimlik) | Prompt | Completion | Toplam | Süre | Sonuç |
|---|---:|---:|---:|---:|---|
| `nvidia/nvidia/nemotron-3.5-lightning-30b-a3b` | 1717 | 1908 | 3625 | 11.8 s | OK (stop) |
| `nvidia/minimaxai/minimax-m3.1-flash` | 1965 | 1760 | 3725 | 267.3 s | OK (stop) |
| `nvidia/meta/muse-glimmer-30b` | 1644 | 2023 | 3667 | 288.3 s | OK (stop) |
| `nvidia/nvidia/nemotron-3.5-super-vl-120b-a12b` | 1717 | 1036 | 2753 | 8.5 s | OK (stop) |
| **Toplam** | **7043** | **6727** | **13770** | — | **4/4 OK** |

## Bulgular ve kararlar

| # | Bulgu (modeller) | Doğrulama | Karar |
|---|---|---|---|
| 1 | Açlık sayacı hasardan önce sıfırlanıyor / hasar iptal oluyor (Lightning) | `PlayerSurvival::tick()` sayacı 80'e ulaşınca **önce** sıfırlar, **sonra** `damage(1)` uygular; sıra doğru. `-0 === 0` olduğu için uygulanmayan hasar paket üretmez (Super'in `-0` iddiası da yanlış). | **Reddedildi (yanlış pozitif)** |
| 2 | `starvationTicks = 0` yenilenme sayacını sıfırlıyor (Lightning) | İki ayrı alan var: `starvationTicks` ve `regenTicks`. Açlık dalında yalnızca `regenTicks`, normal dalda yalnızca `starvationTicks` sıfırlanır. | **Reddedildi (yanlış pozitif)** |
| 3 | Ölüm kontrolünün sırası yanlış (Lightning, Muse) | `state() !== Spawned \|\| isDead()` kısa devre; `Dead` zaten `Spawned` değil. Ölü oyuncuda sayaçlar sıfırlanır, hasar/yenilenme yok. | **Reddedildi** |
| 4 | İlk tick'te `previous === null` erken `continue` hayatta kalma sayacını atlıyor (MiniMax, Super) | Doğru gözlem, düşük etki: ilk tick yalnızca konum tabanını kurar; hayatta kalma **ikinci** tick'ten işler. Varsayılan açlık 20 olduğu için girişte açlık hasarı/yenilenme tetiklenmez. Sözleşme "80 uygun tick" olarak netleştirildi. | **Kabul edildi, belgelendi** (kod değişikliği yok) |
| 5 | `GameplayTicker` ile `PlayerSurvival` durum filtreleri çakışıyor (Muse, Lightning) | `canInteract()` yalnızca açlık zincirini korur; `PlayerSurvival` kendi `Spawned` kapısını savunma katmanı olarak taşır. İki katman aynı sonucu verir; biri kaldırılırsa çağrı sözleşmesi kırılganlaşır. | **Reddedildi (tasarım)** |
| 6 | Ölü/`Joining` oyuncu için `PlayerSurvival` nesnesi oluşturuluyor (Super) | Nesne yalnızca oturum başına bir kez oluşur; `tick()` sayaçları sıfırlar; ayrılan oturumda `unset` edilir. Bellek/CPU etkisi ihmal edilebilir. | **Reddedildi (önemsiz)** |
| 7 | Sağlık güncellemesi ölü oyuncuya da gidebilir (Muse) | `PlayerSurvival` ölüde 0 döndürür; yalnızca **ölümü üreten** tick'te `SetHealth(0)` gider. Bu istenen davranıştır; `!isDead()` filtresi ölüm bildirimini bastırırdı. İki oyunculu `testTickSendsSetHealthOnlyToStarvingPlayer` sağlığın yalnızca sahibine gittiğini doğrular. | **Reddedildi** |
| 8 | `SetHealth` yalnızca can taşıyor; eksik alan olabilir (Muse, Super) | Protokol 2225 şeması ve `SetHealthPacket` gövdesi yalnızca `Health` (int32, ZigZag varint32) içerir. `sendTo($sessionId, ...)` hedefi belirler; runtime kimlik gerekmez. | **Reddedildi (şema doğrulandı)** |
| 9 | `hunger() <= 0` negatif açlıkta hızlanma yapabilir (Muse) | `PlayerAttributes::consume()` açlığı 0'da durdurur; negatif değer üretilmez. `<= 0` savunma amaçlıdır. | **Reddedildi** |
| 10 | `heal(1)` dönüşü dolu canda 0 olabilir (Super) | `health() < MAX_HEALTH` kapısı zaten var; ayrıca `heal()` dolu canda 0 döner ve paket üretilmez. | **Reddedildi (zaten korumalı)** |

## Sonuç

Dört modelin hiçbiri yeni bir **gerçek hata** göstermedi. Tek kabul edilen
gözlem, ilk tick'in yalnızca konum tabanı kurması ve hayatta kalma sayacının
ikinci tick'ten başlamasıdır; bu davranış plan ve durum belgesinde açıkça
yazıldı. Ölü oyuncunun hareketle açlık harcaması hatası bu incelemeden değil,
Phase 10.1 sırasında eklenen 400 tick'lik regresyon testinden çıktı ve
`canInteract()` kapısıyla düzeltildi.

Kayıt, gelecekteki incelemelerde aynı yanlış pozitiflerin tekrar
tartışılmaması için burada tutulur.
