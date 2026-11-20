# Phase 10.1 — Hayatta Kalma Sonuçları

**Tarih:** 2026-11-19
**Durum:** ✅ Kod + test tamamlandı (gerçek istemci doğrulaması yok)

## Amaç

Phase 9 açlık zincirini kurdu ama açlık 0'a indiğinde hiçbir sonuç
üretmiyordu: oyuncu sonsuza kadar aç kalabiliyor, canı kendiliğinden
yenilenmiyordu. Phase 10.1 iki kuralı ekler ve ikisini de **ağdan bağımsız**
tutar:

1. **Açlıktan hasar:** açlık 0 iken her 80 tick'te 1 hasar; ölüm mümkün.
2. **Doğal yenilenme:** açlık ≥ 18 ve can dolu değilken her 80 tick'te 1 can.

## Kapsam

| # | İş | Durum |
|---|---|---|
| 1 | `PlayerSurvival` — oyuncu başına zamanlayıcılar | ✅ |
| 2 | Açlıktan hasar (80 tick, ölüm dahil) | ✅ |
| 3 | Doğal yenilenme (80 tick, açlık ≥ 18) | ✅ |
| 4 | `GameplayTickResult::$healthUpdates` | ✅ |
| 5 | `GameplayTicker` bağlantısı | ✅ |
| 6 | `ConnectionPipeline` → `SetHealth` (yalnızca sahibi) | ✅ |
| 7 | Yalnızca spawn edilmiş oyuncu kuralı | ✅ |
| 8 | Ölü oyuncunun hareketle açlık harcamaması | ✅ |

Registry: **41** paket — yeni paket yok, `SetHealth` (42) zaten kayıtlıydı.

## Zamanlama sözleşmesi

| Sabit | Değer | Anlam |
|---|---|---|
| `STARVATION_INTERVAL` | 80 tick | 20 TPS'te 4 saniye |
| `REGEN_INTERVAL` | 80 tick | 20 TPS'te 4 saniye |
| `REGEN_MIN_HUNGER` | 18 | Yenilenme için en düşük açlık |

- **Zamanlayıcılar oyuncu başınadır:** `GameplayTicker` oturum kimliği →
  `PlayerSurvival` haritası tutar.
- **İlk tick konum tabanıdır:** `GameplayTicker` oturumu ilk gördüğü tick'te
  yalnızca konumu kaydeder; hayatta kalma sayacı **sonraki** tick'ten işler.
  Bu nedenle 80 tick "uygun tick" olarak sayılır, giriş anından itibaren
  değil.
- **Koşul bozulunca sayaç sıfırlanır:** açlık 18'in altına düşerse yenilenme,
  açlık 1'e çıkarsa açlık hasarı sayacı sıfırlanır. "79 tick biriktir, bir
  lokma ye, hemen iyileş" mümkün değildir.
- **Yalnızca `Spawned` oyuncu işlenir:** `Joining`, `Dead` ve `Disconnected`
  durumunda sayaçlar sıfırlanır.
- **Açlıktan ölümde taban yok:** hasar 1'de durmaz; sağlık 0'a inince oyuncu
  `Dead` olur. Java'nın zorluk tabanlı davranışından bilinçli sapmadır —
  sunucuda zorluk kavramı yok.
- **Ölü oyuncu açlık harcamaz:** `GameplayTicker` açlık zincirini yalnızca
  `canInteract()` iken çalıştırır. Phase 9'daki tek tick'lik test bunu
  yakalamıyordu; 400 tick'lik hareket testi eklendi.

## Ağ bağlantısı

- `GameplayTicker` sağlık değişimini `healthUpdates` (oturum → yeni sağlık)
  olarak döndürür; ağ katmanını bilmez.
- `ConnectionPipeline::tick()` her değişim için **yalnızca ilgili oturuma**
  `SetHealth` (42) gönderir. `SetHealth` gövdesi ZigZag varint32'dir.
- Ölümde ek paket yok: istemciye `SetHealth(0)` gider; `ActorEvent(Death)`
  Phase 10.2'ye bırakıldı.
- `GameplayTickResult::hasWork()` artık `healthUpdates`'i de kapsar; boş
  tick'te gereksiz yayın yapılmaz.

## Testler

| Dosya | Kapsam |
|---|---|
| `tests/unit/player/PlayerSurvivalTest.php` | 80 tick eşiği, açlıktan ölüm, yemek sonrası sayaç sıfırlama, 18 açlık sınırı, dolu canda durma, ölü/giriş oyuncusuna işlem yok — 10 test |
| `tests/unit/gameplay/GameplayTickerTest.php` | Sağlık haritası, yenilenme, `forget`, ölü oyuncunun hareketle açlık harcamaması — 17 test |
| `tests/integration/ConnectionPipelineTest.php` | Gerçek pipeline'da açlıktan hasar → `SetHealth`; iki oyunculu akışta yalnızca sahibine yayın — 35 test |

TDD sırası: `PlayerSurvivalTest` önce **9 error** verdi (sınıf yok), iskelet
sonrası **6 failure**; ardından kurallar uygulandı. Ölü oyuncu hatası için
önce **1 failure** (`3.999...` tükenme), sonra `canInteract()` kapısı.
`Joining` oyuncusu için önce **1 failure** (80. tick'te iyileşme), sonra
`Spawned` kapısı.

## Kabul edilen sınırlar

- **Yemek tüketimi yok:** `Player::eat()` API'si var; envanterden yenilebilir
  eşya tüketimi ve besin/doygunluk tablosu bağlanmadı.
- **Ölüm geri bildirimi yok:** `SetHealth(0)` dışında `ActorEvent(Death)` ve
  ses yayınlanmıyor.
- **Yenilenme hızı sabit:** doygunluk seviyesine göre hızlanma yok.
- **Zorluk kavramı yok:** açlık hasarı her zaman 80 tick'te 1.
- **Gerçek istemci yok:** Bedrock 1.26.65 istemcisiyle can/açlık kabulü
  sınanmadı; `CLIENT_VERIFIED` **BLOCKED**.

## Doğrulama

```
tools\php.cmd -l <değişen dosyalar>   → No syntax errors
tools\composer.cmd lint               → Denetlenen dosya: 322 — Sözdizimi temiz
tools\php.cmd vendor\bin\phpunit      → OK (932 tests, 11520 assertions)
graphify update .                     → 4441 nodes, 12584 edges, 172 communities
```
