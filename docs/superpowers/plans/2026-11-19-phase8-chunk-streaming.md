# Phase 8 — Chunk Akışı ve Dünya Saati

**Tarih:** 2026-11-19
**Durum:** ✅ Kod + test tamamlandı (gerçek istemci doğrulaması yok)

## Amaç

Phase 4'te istemciye yalnızca **spawn chunk'ı** gönderiliyordu. Oyuncu spawn
noktasından birkaç blok yürüyünce çevresi boş görünüyordu. Phase 8, görüş
mesafesindeki chunk'ları akıtır, dünya saatini bildirir ve istemcinin istediği
yarıçapı sunucu sınırlarına çeker.

## Kapsam

| # | İş | Durum |
|---|---|---|
| 1 | `SetTimePacket` (10) | ✅ |
| 2 | `RequestChunkRadiusPacket` (69) | ✅ |
| 3 | `ChunkRadiusUpdatedPacket` (70) | ✅ |
| 4 | `SetLocalPlayerAsInitializedPacket` (113) | ✅ |
| 5 | `ChunkSerializer` (giriş + akış tek serileştirici) | ✅ |
| 6 | `ChunkStreamer` (görüş mesafesi, tekrar gönderim engeli) | ✅ |
| 7 | `WorldProperties.time` + `World::getTime()` | ✅ |
| 8 | `Player::markInitialized()` / `grantViewDistance()` | ✅ |
| 9 | `ConnectionPipeline` dispatch dalları | ✅ |

Registry: **35 → 39** paket.

## Tel biçimi

| Paket | Alanlar |
|---|---|
| `SetTime` (10, S→C) | `Time` int32 → ZigZag varint32 |
| `RequestChunkRadius` (69, C→S) | `Chunk Radius` int32 → ZigZag varint32; `Max ChunkRadius` uint8 |
| `ChunkRadiusUpdated` (70, S→C) | `Chunk Radius` int32 → ZigZag varint32 |
| `SetLocalPlayerAsInitialized` (113, C→S) | `Player ID` → `ActorRuntimeID` varuint64 |

## Oynanış akışı

### Giriş (değişmedi + işaretleme)

1. `PlayerJoinService` spawn chunk'ını `ChunkSerializer` ile gönderir.
2. `ConnectionPipeline::spawn()` bu chunk'ı `ChunkStreamer::markSent()` ile
   işaretler. Aksi hâlde ilk `sync()` aynı chunk'ı ikinci kez yollardı.

### Hazır bildirimi

1. İstemci `SetLocalPlayerAsInitialized(runtimeId)` gönderir.
2. Sunucu `runtimeId`'yi oturumun gerçek kimliğiyle karşılaştırır; uyuşmazsa
   yok sayar.
3. `markInitialized()` ilk kez `true` dönerse: `SetTime(world.time)` +
   `streamChunks()`. Yinelenen paket hiçbir şey yollamaz.

### Görüş mesafesi isteği

1. İstemci `RequestChunkRadius(n)` gönderir.
2. `clampRadius(n)` → `[1, viewDistance]`.
3. `Player::grantViewDistance(granted)` + `ChunkRadiusUpdated(granted)`.

### Hareket

Her `PlayerAuthInput` sonrası `streamChunks()` çağrılır. `sync()`
gönderilmişleri atladığı için çağrı ucuzdur (25 sözlük araması) ve yalnızca
yeni sütunlar yayınlanır.

## Sözleşmeler (testle sabit)

- **Tekrar gönderim yok:** oturum başına `"x,z"` defteri.
- **Kare pencere:** `(2r+1)²`; dairesel maskeleme köşelerde delik bırakır.
- **Boş chunk da gider:** istemci "yüklenmedi" sanıp oyuncuyu düşürmesin.
- **Yarıçap kırpılır:** istemci otorite değil.
- **Hazır kapısı:** ağır veri onaydan önce gitmez.
- **Kimlik iddiası doğrulanır:** runtime kimlik oturumla eşleşmeli.

## Testler

| Dosya | Kapsam |
|---|---|
| `tests/unit/protocol/WorldStreamPacketTest.php` | Altın baytlar, round-trip, yön kaydı (4 paket) — 17 test |
| `tests/unit/world/ChunkStreamerTest.php` | Kırpma, pencere boyutu, tekrar engeli, oturum izolasyonu — 17 test |
| `tests/integration/ConnectionPipelineTest.php` | Hazır bildirimi, yanlış kimlik, yarıçap, hareket akışı — 5 yeni test |

## Kabul edilen sınırlar

- **Chunk boşaltma (unload) yok:** yarıçap dışına çıkan chunk'lar "gönderilmiş"
  sayılmaya devam eder.
- **Saat ilerlemiyor:** `SetTime` tek referans noktası; sunucu tick döngüsü
  zamanı artırmıyor.
- **Yarıçap büyütmede yeniden gönderim yok:** yeni pencere bir sonraki harekete
  kadar yüklenmez.
- **Hareket dışı tetikleyici yok:** ışınlanma sonrası akış bir sonraki
  `PlayerAuthInput`'a bağlı.
- **Gerçek istemci yok:** `SetTime` ve chunk penceresi kabulü sınanmadı.

## Doğrulama

```
tools\composer.cmd lint               → 309 dosya, sözdizimi temiz
tools\php.cmd ... phpunit -c phpunit.xml
                                      → OK (870 tests, 9144 assertions)
graphify update .                     → 4273 nodes, 11887 edges, 168 communities
```
