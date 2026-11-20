# Phase 7.2 — Eşya Düşürme / Toplama Varlıkları (uygulama planı)

Durum: **tamamlandı** (2026-11-19). Protokol **2225 / Bedrock 1.26.65**.
`CLIENT_VERIFIED`: **BLOCKED** (gerçek istemci yok).

Her alt görev sonunda: `phpunit`, `composer lint`, `graphify update .`,
`docs/DEVELOPMENT_STATE.md` güncellemesi.

## 1. Kapsam

| ID | Paket | Yön | Kullanım |
|---|---|---|---|
| 15 | `AddItemActor` | S→C | Yere düşen eşya varlığını tanıtır |
| 17 | `TakeItemActor` | C→S | Eşya toplama isteği |

Registry **33 → 35** paket.

Yeni tipler: `SynchedActorData`, `SynchedActorDataEntry`.

Yeni sınıflar: `ItemActor`, `ItemActorManager`, `ItemPickupResult`.

## 2. Tel biçimi — doğrulanmış gerçekler

Kaynak: `gophertunnel` (`Writer.EntityMetadata`, `Writer.AddItemActor`,
`Writer.TakeItemActor`) ve yerel `docs/protocol/schemas/v1.26.65/` aynası.

`AddItemActor` gövdesi:

```
ActorUniqueID          varlong (zigzag)
ActorRuntimeID         varuint64
Item                   NetworkItemStackDescriptor
Position               Vec3 (3 × float32 LE)
Velocity               Vec3
Entity Data            SynchedActorData
Is From Fishing        bool (uint8)
```

`TakeItemActor` gövdesi: `Item Runtime ID` (varuint64) + `Actor Runtime ID`
(varuint64).

`SynchedActorData`:

```
Count            varuint32
per entry:
  ID             varuint32
  Selector Type  varuint32
  Legacy Type    uint8      ← selector ile aynı olmak zorunda
  Value          tipe gore
```

Desteklenen veri tipleri:

| Tip | Ad | Payload |
|---|---|---|
| 0 | byte | uint8 |
| 1 | short | int16 LE |
| 2 | int | zigzag varint |
| 3 | float | float32 LE |
| 4 | string | varuint32 uzunluk + UTF-8 |
| 5 | compound | **desteklenmiyor** (NBT çözücü gerekir) |
| 6 | block pos | 3 × zigzag varint |
| 7 | int64 | zigzag varlong |
| 8 | vec3 | 3 × float32 LE |

Doğrulanan ayrıntılar:

- **Girdiler artan `id` sırasında yazılır.** `encode()` sıralamayı kendisi
  uygular; çağırana güvenmez. Vanilla istemcisi bazı özellikleri yalnızca
  artan sırada işler, `gophertunnel` de yazmadan önce sıralar.
- **Selector/legacy uyuşmazlığı reddedilir** (`ProtocolException`). Sessizce
  "düzeltmek" bozuk paketi istemciye göndermek olurdu.
- **Tekrarlanan `id`** liste kurulurken reddedilir.
- Çözmede **4096 girdi sınırı** vardır (bellek tükenmesi savunması).
- **`CompoundTag` (5)** karşılaşılırsa **açık** `ProtocolException` fırlatılır —
  sessizce atlanmaz.

## 3. Oynanış akışı

```
Düşürme:  InventoryTransaction (Normal, source = World) →
          stackFromDescriptor → spawn (AddItemActor) →
          consume (envanterden düş) → InventorySlot + AddItemActor yayını

Toplama:  TakeItemActor → runtime kimlik sahipliği →
          menzil (≤ 1.5 blok) → insert (sığmıyorsa vazgeç) →
          InventorySlot* + RemoveActor yayını
```

Bırakma sırası bilinçli: varlık **önce** üretilir, envanter düşümü başarısız
olursa `despawn()` ile geri alınır. Ters sıra "eşya envanterden düştü ama
yerde yok" kaybına yol açardı.

Kimlik uzayı ayrımı: eşya varlıkları `EntityManager(1, 1000, 1000)` gibi
yüksek tabanlı bir defterden kimlik alır. `PlayerManager` 1'den başladığı için
oyuncu kimlikleriyle çakışma olmaz; çakışma istemcide iki varlığın aynı
`ActorUniqueID`'yi paylaşmasına yol açardı.

`RemoveActor` eşya varlıkları için **`ActorUniqueID`** (`entityId()`) taşır —
`AddItemActor::targetActorId` ile aynı alan. (`PlayerSnapshot::removeActor()`
mevcut örneği runtime kimlik kullanır; Phase 7.2 kapsamında değiştirilmedi.)

## 4. Görev listesi

- [x] `PacketId::ADD_ITEM_ACTOR = 15`, `TAKE_ITEM_ACTOR = 17`
- [x] `PacketRegistry::withSupportedPackets()` kaydı + yönler (35 paket)
- [x] `SynchedActorDataEntry` — 8 tip, fabrika yardımcıları, uyuşmazlık reddi
- [x] `SynchedActorData` — artan sıralama, tekrar reddi, 4096 çözme sınırı
- [x] `AddItemActorPacket` / `TakeItemActorPacket` kodlayıcı/çözücü
- [x] `Entity::TYPE_ITEM` sabiti
- [x] `EntityManager` kimlik tabanı parametreleri (`firstEntityId`, `firstRuntimeId`)
- [x] `ItemActor`, `ItemPickupResult`, `ItemActorManager`
      (`spawn`/`despawn`/`pickup`/`consume`/`insert`)
- [x] `PlayerAuthInput` — düşürme (`handleDrop`) ve toplama
      (`handleTakeItemActor`) yolları
- [x] `ConnectionPipeline::dispatch()` — `TakeItemActor` dalı (oyuncu + şifreli
      oturum koşulu)
- [x] Registry sayısı 33 → 35 assertion güncellemeleri
      (`GameplayPacketTest`, `PacketCodecTest`, `PlayerListPacketTest`,
      `WorldPacketTest`)

## 5. Testler

`tests/unit/protocol/ItemActorPacketTest.php` — **13 test / 41 assertion**:

- Paket kimlikleri/isimleri, registry yönleri.
- `AddItemActor` altın baytlar (metadata'sız tam gövde).
- Gidiş-dönüş: tüm skaler alanlar + metadata.
- 8 veri tipinin gidiş-dönüşü.
- Artan `id` sırası altın baytlarla.
- Selector/legacy uyuşmazlığı reddi.
- `CompoundTag` açıkça desteklenmiyor.
- Tekrarlanan `id` reddi, 4097 girdide çözme sınırı.
- `TakeItemActor` altın baytlar + gidiş-dönüş.
- Sıkı modda artık bayt reddi.

`tests/unit/ItemActorManagerTest.php` — **13 test / 54 assertion**:

- `spawn` kimlik/alanları, yığın **kopyası** (dış mutasyon sızmaz),
  bilinmeyen eşya ve boş yığın reddi.
- `consume` yetersiz eşyada hiçbir yuvayı değiştirmez; çok yuvalı düşüm.
- `insert` önce birleştirir sonra boş yuva doldurur; dolu envanterde reddeder.
- `pickup` envantere taşır + `RemoveActor` alanı `ActorUniqueID`.
- Menzil dışı istek, dolu envanter, bilinmeyen varlık.
- `despawn` idempotent davranışı.

`tests/integration/ConnectionPipelineTest.php` — 3 yeni test, gerçek şifreli
çerçeve kullanır:

- `testDropConsumesItemAndSpawnsItemActor` — düşürme uçtan uca (2 oyuncu,
  yayın doğrulaması, eşya çoğaltma savunması).
- `testTakeItemActorPicksUpDroppedItem` — toplama + `InventorySlot` +
  `RemoveActor` yayını (toplayana ve diğerlerine).
- `testTakeItemActorRejectsOutOfRangePickup` — menzil savunması: eşya yerde
  kalır, envanter değişmez.

## 6. Kabul edilmiş sınırlar

1. **Eşya fiziği yok** — varlık bırakıldığı noktada durur; düşme/yuvarlanma
   ve yerçekimi Phase 8'in tick döngüsüne bırakıldı. `Vec3 velocity` `0` gider.
2. **Yaşam süresi (despawn timer) yok** — toplanmayan eşya sunucu belleğinde
   süresiz kalır.
3. **Toplama mesafesi sabit 1.5 blok** — bakış yönü/hitbox hesaba katılmaz.
4. **`Interact` hâlâ kayıtsız** — yalnız `TakeItemActor` akışı bağlandı.
5. **Metadata NBT yok** — eşya özel adı/etiketleri taşınmaz.
6. `SynchedActorData` legacy baytının selector ile **aynı** olması gerektiği
   `gophertunnel` kaynağına dayanır; istemci davranışı sınanmadı.
7. Gerçek Bedrock istemcisiyle test **yok**; `AddItemActor` kabulü sınanmadı.

## 7. Doğrulama

```
tools\composer.cmd lint            → 301 dosya, sözdizimi temiz
phpunit -c phpunit.xml             → OK (831 test, 9016 assertion)
graphify update .                  → 4171 node, 11543 edge, 169 community
```
