# Phase 9 — Oyuncu Öznitelikleri, Yetenekler ve Tick Döngüsü

**Tarih:** 2026-11-19
**Durum:** ✅ Kod + test tamamlandı (gerçek istemci doğrulaması yok)

## Amaç

Phase 8'e kadar sunucu yalnızca olaylara tepki veriyordu: oyuncu hareket
etmedikçe dünya saati ilerlemiyor, açlık işlenmiyor ve yerdeki eşya hiç
düşmüyordu. Phase 9 sunucuya **kendi tick döngüsünü** verir, oyuncuya
`UpdateAttributes`/`UpdateAbilities` gönderir ve eşyalara basit yerçekimi
uygular.

## Kapsam

| # | İş | Durum |
|---|---|---|
| 1 | `UpdateAttributesPacket` (29) | ✅ |
| 2 | `UpdateAbilitiesPacket` (187) | ✅ |
| 3 | `Attribute` + `AttributeModifier` tel tipleri | ✅ |
| 4 | `SerializedAbilitiesData` + `AbilityLayer` (varuint32 düzeltmesi) | ✅ |
| 5 | `PlayerAttributes` (açlık/doygunluk/tükenme) | ✅ |
| 6 | `PlayerAbilities` (hayatta kalma katmanı) | ✅ |
| 7 | `Player::tickAttributes()` / `eat()` / `attributeList()` | ✅ |
| 8 | `WorldProperties::advanceTime()` + `World::advanceTime()` | ✅ |
| 9 | `GameplayTicker` + `GameplayTickResult` (ağdan bağımsız) | ✅ |
| 10 | `ConnectionPipeline::tick()` + `Scheduler` kaydı | ✅ |
| 11 | Eşya yerçekimi (`ItemActorManager::tickMotion()`) | ✅ |
| 12 | `ChunkStreamer::evictOutside()` defter sınırı | ✅ |
| 13 | `RequestChunkRadius` sonrası anında akış | ✅ |

Registry: **39 → 41** paket.

## Tel biçimi

| Paket | Alanlar |
|---|---|
| `UpdateAttributes` (29, S→C) | `Target Runtime ID` varuint64; `Attribute List` varuint32 sayı + `Attribute`; `Tick` varuint64 |
| `UpdateAbilities` (187, S→C) | `Data` → `SerializedAbilitiesData` |

`Attribute` sırası (gophertunnel `protocol.Attribute.Marshal` ile doğrulandı):
Min, Max, Current, DefaultMin, DefaultMax, Default → **float32**; Name →
string; modifier sayısı → **varuint32**; modifier'lar.

`AttributeModifier`: Id (string), Name (string), Amount (float32),
Operation (int32), Operand (int32), Serializable (bool).

`SerializedAbilitiesData`: entityUniqueId **int64 sabit LE**, player
permissions int8, command permissions uint8, **katman sayısı varuint32**,
katmanlar. `AbilityLayer`: uint16 type, uint32 abilitiesSet, uint32
abilityValues, float32 flySpeed, float32 verticalFlySpeed, float32 walkSpeed.

**Düzeltilen tuzak:** katman sayısı ilk yazımda tek bayttı. 256+ katman
gönderildiğinde paket sessizce bozulurdu; `writeVarUInt`/`readVarUInt`'e
çevrildi ve testle kilitlendi.

## Açlık zinciri

1. Hareket algılanırsa tükenme **+0.01**/tick.
2. Tükenme **4.0**'a ulaşınca 1 açlık puanı düşer, tükenme 4.0 azalır.
3. Doygunluk **önce** harcanır; açlık ancak doygunluk bitince azalır.
4. Açlık **0'ın altına inmez** ve hasar üretmez (açlıktan ölüm Phase 10).
5. Hareket, tick'ler arası **yatay mesafe** ile ölçülür (`MOVE_EPSILON =
   0.001`); istemcinin "yürüyorum" bayrağına güvenilmez. Kayıt ilk
   görüldüğünde oyuncu **duruyor** sayılır.

## Yetenekler

Hayatta kalma katmanı (`LAYER_BASE = 1`, izin `PLAYER_PERMISSION_MEMBER = 1`):

- İzin bitleri: build(1), mine(2), doors(4), openContainers(8),
  attackPlayers(16), attackMobs(32) → **63**.
- Değer bitleri: flySpeed, walkSpeed, verticalFlySpeed → **548864**.
- Hızlar: uçuş `0.05`, dikey uçuş `1.0`, yürüme `0.1`.
- Bit sabitleri Mojang `ActorAbility` / gophertunnel `protocol/ability.go`
  ile birebir; tahmin edilmedi.

`UpdateAbilities` **`UpdateAttributes`'tan önce** gönderilir; sıra testle
kilitlidir.

## Tick sözleşmesi

- `GameplayTicker::tick(tickNumber, players)` **ağdan bağımsızdır**; birim
  testleri soket açmaz.
- Dünya saati her tick +1; `TIME_SYNC_INTERVAL = 200` tick'te bir `SetTime`.
  Tick **0** yayın yapmaz (girişte zaten gönderilir).
- Eşya ömrü `LIFETIME_SECONDS = 300`; süre dolunca `RemoveActor` (hedef
  `ActorUniqueID`).
- Eşya fiziği: yerçekimi `0.04`, sönüm `0.98`, dinme eşiği `0.0001`.
  Yalnızca **dikey** çarpışma; eşya ilk katı bloğun üstüne oturur, blok
  içine doğarsa yukarı itilir. Konum değişmezse paket üretilmez.
- `ConnectionPipeline::tick()` sonucu oturumlara dağıtır. `Scheduler`
  enjekte edilirse kendini `gameplay.pipeline-tick` (gecikme 1, periyot 1)
  olarak kaydeder; testler `tick()`'i elle çağırır.

## Sözleşmeler (testle sabit)

- **Katman sayısı varuint32.**
- **Öznitelik alan sırası** ve float32 yuvarlaması (`assertEqualsWithDelta`).
- **Hazır kapısı korunur:** `SetLocalPlayerAsInitialized` olmadan ağır veri
  yok; runtime kimlik oturumla eşleşmeli.
- **Açlık negatife düşmez**, doygunluk önce harcanır.
- **Ölü oyuncu** açlık biriktirmez.
- **Dinlenen eşya** her tick paket üretmez.
- **Defter temizliği:** `evictOutside()` yalnızca `radius + 2` payın dışını
  unutur; geri dönünce chunk yeniden gönderilir.

## Testler

| Dosya | Kapsam |
|---|---|
| `tests/unit/protocol/PlayerAttributesPacketTest.php` | Altın baytlar, round-trip, bit sabitleri, varuint32 katman sayısı — 8 test |
| `tests/unit/player/PlayerAttributesTest.php` | Açlık zinciri, doygunluk önceliği, negatife düşmeme, `reset`, yetenek katmanı — 11 test |
| `tests/unit/gameplay/GameplayTickerTest.php` | Saat, `SetTime` aralığı, hareket tabanlı açlık, ölü oyuncu, `forget`, eşya hareketi — 13 test |
| `tests/unit/ItemActorManagerTest.php` | Yerçekimi, dinlenme, blok içinden itilme, konum/paket uyumu — 19 test |
| `tests/unit/world/ChunkStreamerTest.php` | Defter, pencere, kırpma, `evictOutside`, geri dönüş — 21 test |
| `tests/integration/ConnectionPipelineTest.php` | Yetenek/öznitelik sırası, yarıçap, saat, ömür, eşya hareketi yayını — 33 test |

## Kabul edilen sınırlar

- **Açlıktan ölüm yok:** açlık 0'a iner, hasar üretmez (Phase 10).
- **Doygunluk kazanımı bağlı değil:** `eat()` API'si var; envanterden yemek
  tüketimi ve besin değerleri yok.
- **Yetenek geçişi yok:** yalnızca hayatta kalma katmanı; yaratıcı/uçuş ve
  izin değişimi gönderilmez.
- **Eşya fiziği basit:** yatay duvar, sıvı, eğim, yuvarlanma yok.
- **`Interact` hâlâ yalnızca günlüğe yazılır.**
- **`ActorEvent` / `LevelSoundEvent` oynanışa bağlanmadı.**
- **İstemci chunk boşaltma yok:** `NetworkChunkPublisherUpdate` uygulanmadı;
  `evictOutside()` yalnızca sunucu defterini sınırlar.
- **Gerçek istemci yok:** öznitelik/yetenek kabulü ve eşya hareketi
  sınanmadı; `CLIENT_VERIFIED` **BLOCKED**.

## Doğrulama

```
tools\php.cmd -l <değişen dosyalar>   → No syntax errors
tools\composer.cmd lint               → 320 dosya, sözdizimi temiz
tools\php.cmd vendor\bin\phpunit      → OK (916 tests, 10334 assertions)
graphify update .                     → 4407 nodes, 12425 edges, 184 communities
```
