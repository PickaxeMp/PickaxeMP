# Phase 7 — OmniRoute dört model incelemesi (kayıt)

Tarih: 2026-11-19. Komut: `tools\omniroute-team.ps1` (yerel OmniRoute,
`http://127.0.0.1:20128/v1/chat/completions`). İstem dosyası:
`.team/phase7-prompt.md`. Ham yanıtlar `.team/phase7-<model>.json`.

| Model (gerçek kimlik) | Prompt | Completion | Toplam | Süre | Sonuç |
|---|---:|---:|---:|---:|---|
| `nvidia/nvidia/nemotron-3.5-lightning-30b-a3b` | 522 | 1099 | 1621 | 11.8 s | OK |
| `nvidia/minimaxai/minimax-m3.1-flash` | 734 | 1200 | 1934 | 81.1 s | OK (çıktı `max_tokens` sınırında kesildi) |
| `nvidia/meta/muse-glimmer-30b` | 507 | 939 | 1446 | 91.8 s | OK |
| `nvidia/nvidia/nemotron-3.5-super-vl-120b-a12b` | 522 | 668 | 1190 | 5.4 s | OK |
| **Toplam** | **2285** | **3906** | **6191** | — | 4/4 OK |

## Bulgular ve kararlar

| # | Bulgu (modeller) | Doğrulama | Karar |
|---|---|---|---|
| 1 | `announceSpawn()` kendi oyuncusuna `AddPlayer` gönderiyor / çift spawn | Kod: `if ($other === $player \|\| !$other->state()->isActive()) continue;` — kendine gönderim yok; iki yönlü akış kasıtlı ve `testSecondJoinMakesPlayersVisibleToEachOther` ile kanıtlı | **Reddedildi (yanlış pozitif)** |
| 2 | `canInteract()` ölü oyuncuyu durdurmuyor | `Player::canInteract()` = `state->canInteract() && health > 0`; `PlayerState::canInteract()` yalnız `Spawned`; `testLethalDamageMarksPlayerDeadAndBlocksInteraction` + `testDeadPlayerCannotBreakOrPlace` | **Reddedildi (yanlış pozitif)** |
| 3 | `setBlock`/`getBlock` `null`/`false` sonucu yok sayılıyor | `BlockActionManager::breakBlock()` `null`/`false`/hava durumlarını reddediyor; `place()` `blockPositionLoaded()` + hava kontrolü yapıyor; testlerde kanıtlı | **Reddedildi (yanlış pozitif)** |
| 4 | Hız sınırı uygulanmıyor | `admit()` her mutasyonda sayaç tutuyor; `testMutationRateLimitIsEnforced` | **Reddedildi (yanlış pozitif)** |
| 5 | `UnsupportedPacketFieldException` sessizce yutuluyor | `onPayload()` `logger->warning` ile `session` + `reason` yazıyor; bağlantı yaşıyor | **Reddedildi (log var)** |
| 6 | `BroadcastExcept` tek hata ile döngüyü düşürüyor | `catch (\Throwable)` paket başına; döngü devam ediyor | **Reddedildi (zaten korumalı)** |
| 7 | `CreativeDestroyBlock` (13) yaratıcı olmayan oyuncudan kabul ediliyor | Doğru: gamemode/yetenek modeli yok (Phase 8). Ancak kırma zaten `serverAuthBlockBreaking=false` ile tamamen istemci yetkili; 13'ü reddetmek ek güvenlik sağlamaz | **Kabul edildi, belgelendi** (Phase 8'de yetenek modeliyle sınırlanacak) |
| 8 | `PredictDestroyBlock` sonrası sunucu `setBlock` başarısızsa desync | `breakBlock()` `setBlock` `false` dönerse değişiklik üretmiyor; yayın yalnız `changes` üzerinden gidiyor | **Reddedildi (zaten korumalı)** |

Sonuç: dört modelin hiçbiri yeni bir gerçek hata göstermedi; yedi bulgu
mevcut kod ve testlerle çürütüldü, bir bulgu (creative kırma) bilinen ve
belgelenmiş bir sınır olarak kabul edildi. Kayıt, gelecekteki incelemelerde
tekrar üretilmemesi için burada tutulur.
