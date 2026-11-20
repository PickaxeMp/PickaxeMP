# PickaxeMP — Geliştirme Durumu

Son güncelleme: **2026-11-20 (Phase 10.5 yiyecek sesi + blok menzili ve isim etiketi sonrası)**

## 0. Bu oturumda yapılanlar (özet)

- **Phase 10.5 — yiyecek/içki sesi ve dokümantasyon güncellemesi eklendi.**
  - `PlayerAuthInput::handleItemUse` artık başarılı gıda tüketimi sonrası
    `LevelSoundEvent('eat'|'drink')` gönderir; `FoodTable::isDrink()` ile honey_bottle
    içme sesi ayırt edilir. Test `testEatingEmitsEatSound` eklendi.
- **Phase 10.4 — canlı `ItemRegistry` (162), oynanış geri bildirimi ve
  gerçek deri gönderimi tamamlandı.**
  - **`ItemRegistryPacket` (162)**: 1934 gerçek eşya, pmmp/BedrockData
    `required_item_list.json`'dan üretildi (`resources/item-registry.json`).
    Tel üzerinde `componentBased = false` + `version = 2` gönderilir; böylece
    istemci **vanilla tanımlarına** düşer (bizde bileşen NBT'si yok). Ham
    kaynak değerleri `ItemTable::all()` ile erişilebilir. Registry
    **43 → 44** paket.
  - **Oynanış geri bildirimi**: `ActorEvent` + `LevelSoundEvent` artık hasar
    (`Hurt`), ölüm (`Death`), blok kırma/yerleştirme (`break`/`place`),
    eşya toplama (`pickup`) ve yemek yeme (`burp`) için gerçekten gidiyor.
    Ölümde `Hurt` **gönderilmez** (istemcide titreme yapar).
  - **Gerçek deri**: bağımlılıksız saf PHP `PngDecoder` (8 bit, renk tipi
    0/2/4/6, filtre 0-4, CRC/chunk doğrulaması) → ham RGBA →
    `SerializedSkin`. Çözüm başarısız olursa **oyuncu atılmaz**,
    `blank()` döner.
  - **Oyun modu / yetenek geçişi**: `GameMode` (Survival 0 / Creative 1 /
    Adventure 2 / Spectator 6), `Player::setGameMode()` (uçuşa izin
    vermeyen moda geçişte uçuşu kapatır), `PlayerAbilities::forGameMode()`
    ve `ConnectionPipeline::applyGameMode()` / `setFlying()` /
    `refreshAbilities()`. `AddPlayer` artık gerçek oyun modunu ve yetenek
    katmanını taşıyor.
- **Phase 10.3 — `ItemStackRequest` (envanter eşya taşıma) tamamlandı.**
  `ItemStackRequest` (147) ve `ItemStackResponse` (148) paketleri, tel tipleri
  (`ItemStackRequest`, `ItemStackRequestAction`, slot/yanıt tipleri) ve
  `ItemStackRequestManager` eklendi. Yalnızca `Take` / `Place` / `Swap`
  uygulanır; konteynerler `CombinedHotbarAndInventory` (12, yuva 0-35) ve
  `Cursor` (59, yuva 0) ile sınırlı. Doğrulama **kopya** üzerinde yürür,
  hata hâlinde envanter ve imleç hiç değişmez. Registry 41 → **43** paket.
  `PlayerAuthInput` bit 34 artık alanı gerçekten çözer (eski
  `UnsupportedPacketFieldException` kaldırıldı).
- **Phase 10.2 — Yemek Tüketimi tamamlandı.** `ItemUse` yolu (`PlayerAuthInput`
  bit 32 ve `InventoryTransaction`) yenilebilir eşyayı tüketiyor: besin tablosu
  (`FoodTable`, 37 eşya), `Player::eat()` çağrısı, envanterden 1 düşüm,
  `InventorySlot` + `UpdateAttributes` bildirimi ve oturum başına 1600 ms
  soğuma. Tüketim **sunucu yetkilidir**; yığın envanterden okunur. Yeni paket
  yok; registry **41** kalır.
- **Phase 10.1 — Hayatta Kalma Sonuçları tamamlandı.** Açlık 0 iken her 80
  tick'te 1 hasar; açlık ≥ 18 ve can dolu değilken her 80 tick'te 1 can
  yenilenmesi. Zamanlayıcılar **oyuncu başınadır**, yalnızca spawn edilmiş
  oyuncuda işler ve koşul bozulunca sıfırlanır. Sağlık değişimi yalnızca
  ilgili oyuncuya `SetHealth` ile gider. Yeni paket yok; registry **41** kalır.
- **Phase 9 — Oyuncu Öznitelikleri, Açlık, Yetenekler ve Tick Döngüsü
  tamamlandı.** Oyuncuya `UpdateAttributes` (29) ve `UpdateAbilities` (187)
  gönderiliyor; sunucu tick'i dünya saatini ilerletiyor, açlık zincirini
  işliyor ve düşen eşyaları simüle ediyor. Registry 39 → **41** paket.
- **İki yeni paket:** `UpdateAttributes` (29, S→C), `UpdateAbilities`
  (187, S→C). Kimlikler Mojang v1.26.65 şemasından (`[cereal:packet]`)
  doğrulandı.
- **`Attribute` / `AttributeModifier` tel tipleri.** Sıra gophertunnel
  `protocol.Attribute.Marshal` ile doğrulandı: Min, Max, Current, DefaultMin,
  DefaultMax, Default, Name, modifier sayısı, modifier'lar. Modifier:
  Id, Name, Amount, Operation, Operand, Serializable.
- **`SerializedAbilitiesData` düzeltildi:** katman sayısı **varuint32**'dir;
  tek bayt yazmak 256+ katmanda paketi bozardı.
- **`PlayerAttributes`:** açlık (20), doygunluk, tükenme ve hareket hızı.
  Tükenme 4.0'da bir açlık puanına dönüşür; doygunluk önce harcanır; açlık
  negatife düşmez. **Açlıktan ölüm hasarı Phase 10.1'de eklendi.**
- **`PlayerAbilities`:** hayatta kalma katmanı — build, mine, doors,
  containers, attackPlayers, attackMobs (bit toplamı **63**); uçuş/yürüme
  hızı değerleri **548864**. Bit sabitleri Mojang `ActorAbility` /
  gophertunnel `protocol/ability.go` ile birebir.
- **`GameplayTicker` (ağdan bağımsız):** dünya saatini her tick ilerletir,
  her 200 tick'te (10 sn) `SetTime` yayınlar, süresi dolan eşyaları siler,
  düşen eşyaların konumunu ilerletir ve **hareket mesafesine** dayalı açlık
  uygular. İstemcinin "yürüyorum" bayrağına güvenilmez; eşik 0.001 blok.
- **`ConnectionPipeline::tick()`:** sonucu oturumlara dağıtır; `Scheduler`
  verilirse kendini periyodik görev olarak kaydeder (`gameplay.pipeline-tick`,
  gecikme 1, periyot 1). Birim testleri `tick()`'i elle çağırır.
- **Eşya fiziği:** `ItemActorManager::tickMotion()` yerçekimi `0.04`,
  sönüm `0.98` ile düşer; ilk katı bloğun üstüne oturur ve hareket eden
  eşya için `MoveActorDelta` üretir. Blok içine doğan eşya yukarı itilir.
  Dürüst sınır: yalnız dikey çarpışma; yatay duvar/sıvı modeli yok.
- **Chunk defteri sınırlandı:** `ChunkStreamer::evictOutside()` pencere
  dışındaki oturum kayıtlarını unutur (`radius + 2` pay). Bu **istemci
  tarafında chunk boşaltmaz**; `NetworkChunkPublisherUpdate` henüz yok.
- **Yarıçap büyütme:** `RequestChunkRadius` artık yeni pencereyi **hemen**
  doldurur; önceden bir sonraki harekete kadar boş kalıyordu.
- **Phase 8 — Chunk Akışı ve Dünya Saati tamamlandı.** Oyuncu yürürken
  çevresindeki chunk'lar artık gönderiliyor; dünya saati istemciye bildiriliyor;
  istemcinin istediği görüş mesafesi **sunucu tarafında kırpılıyor**. Registry
  35 → **39** paket.
- **Dört yeni paket:** `SetTime` (10, S→C), `RequestChunkRadius` (69, C→S),
  `ChunkRadiusUpdated` (70, S→C), `SetLocalPlayerAsInitialized` (113, C→S).
- **`ChunkSerializer`:** `PlayerJoinService` (tek spawn chunk'ı) ve
  `ChunkStreamer` (görüş mesafesi) artık **aynı** serileştiriciyi kullanıyor.
  Önceki kopya kod zamanla ayrışıp istemcide bozuk chunk üretebilirdi.
- **`ChunkStreamer`** — oturum başına gönderilen chunk defteri:
  - `sync()` yalnızca **henüz gönderilmemiş** chunk'ları döner; her `PlayerAuthInput`
    paketinde çağrılabilir (25 sözlük araması).
  - Yarıçap istemci isteğine göre değil, `[1, viewDistance]` aralığına
    kırpılarak uygulanır (`clampRadius`).
  - Kare pencere `(2r+1)²` — dairesel maskeleme köşelerde delik bırakırdı.
  - Girişte gönderilen spawn chunk'ı `markSent()` ile işaretlenir; aksi hâlde
    ilk `sync()` aynı chunk'ı ikinci kez yollardı.
- **Yeni oturum kapısı:** `SetLocalPlayerAsInitialized` gelmeden ağır veri
  (saat + chunk akışı) gönderilmez. Paketteki runtime kimlik oturumun gerçek
  kimliğiyle karşılaştırılır; uyuşmazsa paket yok sayılır. Yinelenen paket
  chunk'ları yeniden yollamaz.
- **Dünya saati kalıcı:** `WorldProperties` `time` alanı (`world.json`) +
  `World::getTime()`. Giriş ve hazır bildirimi `SetTime` gönderir.
- **`Player` durumu:** `markInitialized()` / `isInitialized()` ve
  `grantViewDistance()` / `grantedViewDistance()`.
- **Phase 7.2 — Eşya Düşürme / Toplama Varlıkları tamamlandı.** `AddItemActor`
  (15, S→C) ve `TakeItemActor` (17, C→S) eklendi; registry 33 → **35** paket.
- **`SynchedActorData` / `SynchedActorDataEntry` yazıldı.** 8 veri tipi
  (byte, short, int, float, string, BlockPos, int64, Vec3) destekli; tel
  biçiminde selector tipi ile legacy bayt **aynı olmak zorunda**, uyuşmazlık
  `ProtocolException` ile reddedilir. Girdiler `id`'ye göre **artan** sırada
  yazılır (vanilla istemcisi bunu bekler). `CompoundTag` (5) Phase 7.2
  kapsamında **açıkça** desteklenmez.
- **`ItemActorManager`:** varlık üretimi, envanterden düşüm (`consume`) ve
  toplama (`pickup`) tek yerde. Kritik kurallar testle sabitlendi:
  - İstemcinin bildirdiği miktar **doğrulanır**; envanterde yoksa istek yok
    sayılır (eşya çoğaltma savunması).
  - Toplama **1.5 blok** menzille sınırlı; menzil dışı istek sessizce düşer.
  - Envanter doluysa eşya **yerde kalır** (kısmi toplama yok).
  - Bırakma **atomiktir**: önce varlık doğrulanmış konumda üretilir, envanter
    düşümü başarısız olursa varlık geri alınır (`despawn`).
- **`RemoveActor` doğru alanla gönderilir:** eşya varlığı için
  `ActorUniqueID` (`entityId()`), runtime kimlik değil.
- **Kimlik uzayı ayrımı:** eşya varlıkları `EntityManager(1, 1000, 1000)` gibi
  yüksek tabanlı bir defterden kimlik alır; oyuncu kimlikleriyle çakışma
  istemcide iki varlığın aynı `ActorUniqueID`'yi paylaşmasına yol açardı.
- **Phase 7.1 — Oyuncu Listesi ve Olay Paketleri tamamlandı.** Sekme
  listesi, varlık olayları, seviye sesleri ve delta hareket için dört paket
  eklendi; registry 29 → **33** paket.
- **Dört yeni paket:** `ActorEvent` (27), `PlayerList` (63),
  `MoveActorDelta` (111), `LevelSoundEvent` (123).
- **Tel biçimleri `gophertunnel` kaynağına karşı doğrulandı.** Önceki
  taslaklarda üç gerçek hata bulundu ve düzeltildi:
  - `Color` = **`BEARGB`**: alfa **düşük** bayttadır; `black()` `-16777216`
    değil **255**'tir.
  - `TrustedSkinFlag` enum değil **string**'dir (`"Unset"` / `"True"` /
    `"False"`).
  - `PersonaPieceTintColour` iç sayım yazmaz: string anahtar + 4 × `BEARGB`.
- **`PlayerList` girişi iki alan taşır:** `Varuint32 variant`
  (Add=1/Remove=0) *ve* eski `Uint8` action (Add=0/Remove=1). Kodlayıcı
  ikisini de yazar; çözücü uyuşmazlığı `ProtocolException` ile reddeder.
- **Sekme listesi kablolaması:** `announceSpawn()` artık yeni oyuncuya
  **tek** `PlayerList` (kendi girişi + diğerleri), diğerlerine tek giriş
  gönderir; `PlayerList` her zaman `AddPlayer`'dan **önce** gider.
  `onConnectionClosed()` liste silmesini `RemoveActor`'dan **önce** yayınlar.
- **Bilinçli sınır:** `SerializedSkin::blank()` gönderilir. Gerçek deri
  JWT'de base64 PNG'dir; tel biçimi ham RGBA bekler. PNG çözmek GD/Imagick
  gerektirdiği için ertelendi (istemcinin varsayılan deriyi çizmesi
  beklenir — **doğrulanmadı**).
- **Phase 7 — Oynanış Döngüsü tamamlandı.** Blok kırma/yerleştirme gerçek
  dünya mutasyonuna ve `UpdateBlock` yayınına bağlandı; sağlık, ölüm ve
  yeniden doğma oyuncu başına çalışır hâle geldi; iki oyuncu birbirini
  görüyor ve dünya değişikliklerini paylaşıyor.
- **Beş yeni paket:** `UpdateBlock` (21), `Interact` (33), `PlayerAction` (36),
  `SetHealth` (42), `Respawn` (45). Registry 24 → **29** paket.
- **Yayın altyapısı:** `PacketBroadcaster` arayüzü ve `ConnectionPipeline`
  uygulaması (`sendTo`, `broadcastExcept`). Yalnızca şifrelemesi kanıtlanmış,
  spawn olmuş ve `Playing` oturumlar hedef alınır; tek bağlantının gönderim
  hatası turu düşürmez.
- **Spawn/despawn:** Katılım tamamlanınca yeni oyuncu diğerlerine, diğerleri
  yeni oyuncuya `AddPlayer` olarak duyurulur; kapanışta `RemoveActor` yayınlanır.
- **`UnsupportedPacketFieldException`:** `PlayerAuthInput` içindeki
  `Item Stack Request` (bit 34) yalnızca o paketi düşürür; bağlantı yaşar.
  `Item Use` (bit 32) çözülür ve blok yerleştirme girdisi olarak kullanılır.
- **Phase 6 — Envanter ve Varlık Motoru tamamlandı.** Eşya kayıt defteri,
  envanter, oyuncu envanteri kalıcılığı, varlık yaşam döngüsü, varlık
  yöneticisi/izleyicisi ve on envanter/varlık paketi yazıldı.
- **Test sözleşmesi onarıldı.** Phase 6 için makine üretimi sekiz test dosyası
  birbiriyle çelişiyordu; tek tutarlı sözleşme benimsendi
  (`docs/superpowers/plans/2026-11-19-phase6-inventory-entity.md` §0) ve
  testler bu sözleşmeye göre düzeltildi. Hiçbir beklenti düşürülmedi.
- **On paket eklendi:** `AddPlayer` (12), `AddActor` (13), `RemoveActor` (14),
  `MoveActorAbsolute` (18), `InventoryTransaction` (30), `MobEquipment` (31),
  `ContainerOpen` (46), `ContainerClose` (47), `InventoryContent` (49),
  `InventorySlot` (50). Paket kimlikleri Mojang v1.26.65 şemasından
  doğrulandı; registry 14 → **24** pakete çıktı.
- **`NetworkItemStackDescriptor` tel biçimi** resmî şemadan ve pmmp
  `BedrockProtocol` ile çapraz doğrulandı: ordinal 0,1,2,3,4,5 —
  `Id` int16, `Stack size` uint16, `Aux value` varuint32,
  `Net Id Variant` opsiyonel varint32, `Block Runtime Id` varuint32,
  `User Data Buffer` string.
- **Phase 6 sınıfları:** `PickaxeMP\item\{Item, ItemStack, ItemRegistry}`,
  `PickaxeMP\inventory\{Inventory, InventoryTransaction}`,
  `PickaxeMP\player\PlayerInventory`, `PickaxeMP\Entity`,
  `PickaxeMP\entity\{EntityManager, EntityTracker}`.
- **Kalıcılık:** `PlayerInventory` her mutasyonda `dirty` işaretler; `save()`
  `PXINV1 + uzunluk + yük + CRC32` gövdesini geçici dosyaya yazıp atomik
  `rename` ile yer değiştirir. `ItemRegistry` aynı deseni `PXITEM1` ile
  `item-registry.json` üzerinde uygular.
- **Kancalar:** `PlayerManager::create()` envanteri oluşturup yükler;
  `ConnectionPipeline::onConnectionClosed()` oyuncuyu kaldırmadan önce
  envanteri kaydeder.
- **Phase 5 şifreleme/hareket düzeltmeleri korunuyor:** `cipherAvailable`
  ve `encryptionEstablished` ayrımı, tüm gövdeyi çözen/tüm çerçeveyi
  şifreleyen akış, tek `MovePlayerPacket` kaynağı.

---

## 1. Özet

| Alan | Durum |
|---|---|
| Phase 0 — Core Foundation | ✅ Tamamlandı |
| Phase 1 — NetherNet Transport | 🚧 PHP ✅ / Rust ✅ / **gerçek istemci testi YOK** |
| Phase 2 — Bedrock Protocol | ✅ Kod + test + denetim |
| Phase 3 — Authentication & Session | ✅ Kod + test / 🚧 gerçek istemciyle değil |
| Phase 4 — World Engine | ✅ Kod + test / ✅ `StartGame` + `LevelChunk` gerçek şifreli oturuma bağlı |
| Phase 5 — Player & Gameplay | ✅ Hareket · sağlık · ölüm/yeniden doğma |
| **Phase 6 — Inventory & Entity Engine** | ✅ **Kod + test + kalite kapısı** |
| **Phase 7 — Gameplay Loop** | ✅ **Kod + test (istemci doğrulaması yok)** |
| **Phase 7.1 — Player List & Event Packets** | ✅ **Kod + test (istemci doğrulaması yok)** |
| **Phase 7.2 — Item Drop / Pickup Actors** | ✅ **Kod + test (istemci doğrulaması yok)** |
| **Phase 8 — Chunk Streaming & World Time** | ✅ **Kod + test (istemci doğrulaması yok)** |
| **Phase 9 — Player Attributes & Tick Loop** | ✅ **Kod + test (istemci doğrulaması yok)** |
| **Phase 10.1 — Survival Consequences** | ✅ **Kod + test (istemci doğrulaması yok)** |
| **Phase 10.2 — Food Consumption** | ✅ **Kod + test (istemci doğrulaması yok)** |
| **Phase 10.3 — Item Stack Request (147/148)** | ✅ **Kod + test (istemci doğrulaması yok)** |
| **Phase 10.4 — Item Registry · Feedback · Skin** | ✅ **Kod + test (istemci doğrulaması yok)** |
| PHP testleri (depo geneli) | ✅ **1091 test, 18489 assertion — 0 failure, 0 error** |
| Rust testleri | **44 test** |
| PHP | 8.2.35 · OpenSSL 3.0.22 |
| Rust | 1.100.0 (windows-msvc) |
| Hedef protokol | **2225 / 1.26.65** |
| Kayıtlı paket sayısı | **46** |
| Graphify | **5096 node · 14967 edge · 220 community** |
| Gerçek Bedrock istemcisi | ❌ **Hiç test edilmedi** |
| `CLIENT_VERIFIED` | **BLOCKED — gerçek Bedrock istemcisi yok** |

---

## 1.5 PICKAXEMP PHP ÇALIŞTIRMA KURALI

Windows üzerinde hiçbir PHP testini veya sunucu başlatma işlemini doğrudan
`php` komutuyla çalıştırma. Her zaman proje wrapper'ını kullan:

| İşlem | Yanlış | Doğru |
|---|---|---|
| Sunucu kontrolü | `php start.php --check` | `.\tools\php.cmd start.php --check` |
| Sunucu başlatma | `php start.php` | `.\tools\php.cmd start.php` |
| PHPUnit | `php vendor\bin\phpunit` | `.\tools\php.cmd vendor\bin\phpunit` |
| Composer lint | `composer lint` | `.\tools\composer.cmd lint` |
| Composer test | `composer test` | `.\tools\composer.cmd test` |

PHP eklentileri (`mbstring`, `openssl` vb.) ve OpenSSL yapılandırması
proje içindeki `tools/php.ini` ve `tools/openssl.cnf` üzerinden yüklenir.
Sistem genelindeki `php.ini` **değiştirilmez**.

---

## 2. Şifreli oturum akışı (gerçek durum)

```
NetworkSettings → Login
  → zincir doğrulama → PlayerIdentity
  → PlayerIdentity → Session.bindIdentity (tek oyuncu, tek oturum)
  → ECDH anahtar çifti → salt
  → ServerToClientHandshake (şifresiz gönderilir)
  → cipherAvailable = true            ← bundan sonra HER yük şifreli
  → ClientToServerHandshake (şifreli gelir, çözülür)
  → encryptionEstablished = true      ← sonuçtur, ön koşul değil
  → ResourcePackClientResponse (şifreli)
  → PlayerJoinService::prepare() → Initializing
  → StartGame + LevelChunk gönderimi (şifreli)
  → PlayerJoinService::complete() → Playing + oyuncu Spawned
```

Kurallar:

1. `encryptionEstablished` **hiçbir zaman** bir paketin çözülmesi için ön
   koşul değildir; yalnızca `ClientToServerHandshake`'in başarılı
   çözülmesinin **sonucudur**.
2. Aynı oturum için ikinci `ServerToClientHandshake` gönderilmez.
3. Başarısız el sıkışma oyuncuyu doğrulanmış/şifreli **işaretlemez**.
4. Bozuk ciphertext, hatalı çerçeve uzunluğu veya eksik şifreleme **yalnızca
   o bağlantıyı** kapatır; diğer bağlantılar etkilenmez.
5. Bağlantı kapanınca `PipelineConnection` ve anahtarları düşer; cipher
   offset'leri çerçeve başına sıfırlanmaz.

---

## 3. Phase 6 — envanter ve varlık motoru

### 3.1 Sınıflar

| Sınıf | Ad alanı | Sorumluluk |
|---|---|---|
| `Item` | `PickaxeMP\item` | Değişmez eşya tanımı; `MAX_NETWORK_ID = 32767` |
| `ItemRegistry` | `PickaxeMP\item` | Tek kayıt defteri; kimlik/ağ kimliği benzersizliği; `PXITEM1` + CRC32 JSON kalıcılığı |
| `ItemStack` | `PickaxeMP\item` | Biçim sınırı 1..128; `add`/`remove` oyun `maxStackSize` (64) sınırında hata |
| `Inventory` | `PickaxeMP\inventory` | Yuva dizisi, `count()` toplam eşya, `isFull()` boş yuva yoksa `true` |
| `InventoryTransaction` | `PickaxeMP\inventory` | `toArray()` anlık görüntüsü üzerinde doğrula; hata varsa geri al |
| `PlayerInventory` | `PickaxeMP\player` | 36 yuva (9 hotbar + 27 ana), UUID anahtarlı `PXINV1` kalıcılığı |
| `Entity` | `PickaxeMP` | Yaşam döngüsü, `Chunk::MIN_Y`/`MAX_Y` konum sınırları, sonlu sayı denetimi |
| `EntityManager` | `PickaxeMP\entity` | Monoton kimlik + nesil (epoch), `retired` haritasıyla bayat paket reddi |
| `EntityTracker` | `PickaxeMP\entity` | Alıcı başına görünürlük kümesi |

### 3.2 Envanter/varlık paketleri (doğrulanmış kimlikler)

| Paket | ID | Yön | Kodlayıcı |
|---|---|---|---|
| `AddPlayer` | 12 | S→C | `AddPlayerPacket` |
| `AddActor` | 13 | S→C | `AddActorPacket` |
| `RemoveActor` | 14 | S→C | `RemoveActorPacket` |
| `MoveActorAbsolute` | 18 | S→C | `MoveActorAbsolutePacket` |
| `InventoryTransaction` | 30 | C→S | `InventoryTransactionPacket` (yalnız `Normal`) |
| `MobEquipment` | 31 | S→C | `MobEquipmentPacket` |
| `ContainerOpen` | 46 | S→C | `ContainerOpenPacket` |
| `ContainerClose` | 47 | S→C | `ContainerClosePacket` |
| `InventoryContent` | 49 | S→C | `InventoryContentPacket` |
| `InventorySlot` | 50 | S→C | `InventorySlotPacket` |
| `Interact` | 33 | C→S | Phase 7 (bilinçli olarak kayıtsız) |
| `PlayerAction` | 36 | C→S | Phase 7 (bilinçli olarak kayıtsız) |
| `ItemStackRequest` | 147 | C→S | `ItemStackRequestPacket` (Phase 10.3) |
| `ItemStackResponse` | 148 | S→C | `ItemStackResponsePacket` (Phase 10.3) |

Yeni yardımcı tipler `PickaxeMP\protocol\packet\types\` altındadır:
`BlockPos`, `Vec2`, `Vec3`, `NetworkItemStackDescriptor`, `InventorySource`,
`InventoryAction`, `LegacySetSlot`, `FullContainerName`, `EntityLink`,
`SyncedAttribute`, `PropertySyncData`, `AbilityLayer`,
`SerializedAbilitiesData`, `PassengerOfBlockArguments`.

**Dürüst sınır:** `Entity Data` / `Actor Data` (metadata) Phase 6'da
üretilmez. `AddPlayer`/`AddActor` yalnızca **boş** metadata listesini kabul
eder; dolu liste açık `ProtocolException` üretir. `User Data Buffer` içindeki
NBT de yorumlanmaz, çağıran ham bayt verir.

---

## 4. Phase 7 — oynanış döngüsü

Tasarım: `docs/superpowers/specs/2026-11-19-phase7-gameplay-loop-design.md`.
Plan: `docs/superpowers/plans/2026-11-19-phase7-gameplay-loop.md`.

### 4.1 Yaklaşım

**A — istemci tahminli blok kırma** benimsendi: `StartGame` içinde
`serverAuthBlockBreaking = false` gönderilir. İstemci kırma animasyonunu
yerelde oynatır ve sunucuya sonucu bildirir; sunucu otoriter dünya durumunu
günceller ve **diğer** istemcilere yayınlar. Kıran istemciye `UpdateBlock`
dönmez (yerel tahmini zaten uygulanmıştır).

Kabul edilen kırma eylemleri: `CrackBlock` (18), `PredictDestroyBlock` (26),
`CreativeDestroyBlock` (13). `PlayerBlockAction` sabitleri resmî şemaya göre
düzeltildi (eski `PREDICT_DESTROY_BLOCK = 18` yanlıştı).

### 4.2 Yeni paketler

| Paket | ID | Yön | Kodlayıcı |
|---|---|---|---|
| `UpdateBlock` | 21 | S→C | `UpdateBlockPacket` |
| `Interact` | 33 | C→S | `InteractPacket` |
| `PlayerAction` | 36 | C→S | `PlayerActionPacket` |
| `SetHealth` | 42 | S→C | `SetHealthPacket` |
| `Respawn` | 45 | S→C | `RespawnPacket` |

Yeni yardımcı tipler: `PlayerActionType`, `InteractAction`, `RespawnState`,
`ItemUseActionType`, `HandSlot`, `ItemUseInventoryTransaction`,
`BlockChange`, `BlockActionResult`.

### 4.3 Dünya mutasyonu ve yayın

| Sınıf | Sorumluluk |
|---|---|
| `World::getBlock/setBlock/blockPositionLoaded` | Chunk **yüklemez**; yüklü değilse `null`/`false`; −64..319 dışı `WorldException` |
| `BlockRegistry::fromNetworkId()` | Ağ kimliğinden blok tipine ters eşleme |
| `BlockActionManager` | Kırma, yerleştirme, oyuncu başına saniyede 30 mutasyon sınırı |
| `PacketBroadcaster` | `sendTo` / `broadcastExcept` sözleşmesi |
| `PlayerSnapshot` | `AddPlayer` / `RemoveActor` paket üreticileri |

Yerleştirme kuralları: eylem `Place` olmalı; hedef blok yüklü ve hava olmalı;
eldeki eşya blok kayıtlı olmalı; başarıda envanterden 1 düşülür ve
`InventorySlot` gönderilir. Dolu hedefe yazılmaz.

### 4.4 Sağlık, ölüm ve yeniden doğma

- Sağlık **oyuncu başına** tutulur (`Player::$health`, 0..20). Phase 5'teki
  paylaşılan `HealthManager` alanı kaldırıldı.
- `damage()` sağlığı 0'a indirince `PlayerState::Dead`; `canInteract()` false
  olur ve kırma/yerleştirme reddedilir.
- İstemci `PlayerAction(Respawn = 7)` gönderince sunucu `Respawn(state = 1)`,
  `SetHealth(20)` ve `MovePlayerPacket(MODE_TELEPORT)` ile spawn noktasına
  taşır; oyuncu yeniden `Spawned` olur.
- Canlı oyuncunun yeniden doğma isteği yok sayılır.

### 4.5 Dürüst sınırlar

- Kırma **istemci bildirimine** dayanır; menzil/ısınma süresi doğrulaması,
  sunucu yetkili kırma ve ceza (anti-cheat) yoktur. Tek koruma: yüklü chunk,
  Y aralığı, oyuncu başına hız sınırı ve `Spawned`/canlı olma koşulu.
- `Entity Data` / `Actor Data` hâlâ boş gönderilir; oyuncular birbirini
  görünür ama metadata (isim etiketi, deri) taşınmaz.
- Phase 7.1'e ertelenenler: `PlayerList` (63), `ActorEvent` (27),
  `LevelSoundEvent` (123), `MoveActorDelta` (111). Phase 7.2: eşya düşürme/
  toplama varlıkları ✅ tamamlandı (§4B). Phase 8: açlık, yetenekler, chunk
  akışı.
- Gerçek Bedrock istemcisiyle test **yok**; `CLIENT_VERIFIED` `BLOCKED` kalır.

---

## 4A. Phase 7.1 — oyuncu listesi ve olay paketleri

### 4A.1 Yaklaşım

Sekme listesi `AddPlayer`'dan bağımsızdır: istemci bir oyuncunun adını ve
derisini **yalnızca** `PlayerList` girişinden okur. Bu yüzden sıra bağlayıcıdır
ve testlerle zorlanır:

1. Yeni oyuncuya **tek** `PlayerList` — kendi girişi *ve* diğerlerinin
   girişleri (istemci listeyi bundan kurar).
2. Diğerlerine yeni oyuncunun tek girişi.
3. `AddPlayer` çift yönlü.
4. Kapanışta `PlayerList` **silmesi**, ardından `RemoveActor`.

### 4A.2 Yeni paketler (kimlikler resmî şemadan)

| ID | Paket | Amaç |
|---|---|---|
| 27 | `ActorEvent` | Varlık olayları (ölüm, hasar, animasyon) |
| 63 | `PlayerList` | Sekme listesi ekle/sil |
| 111 | `MoveActorDelta` | Bileşen bazlı delta hareket |
| 123 | `LevelSoundEvent` | Seviye ses olayları |

Registry **29 → 33** paket. `PlayerList` girişi `Varuint32 variant` *ve* eski
`Uint8 action` alanlarını birlikte taşır; çözücü ikisinin uyuşmasını zorunlu
tutar (`ProtocolException`).

### 4A.3 Tel biçimi doğrulaması (gophertunnel'a karşı)

Bu oturumda üç taslak hatası bulundu ve düzeltildi — hiçbiri teste
yansımadan geçmedi:

| Alan | Yanlış taslak | Doğru biçim |
|---|---|---|
| `Color` bayt sırası | `ARGB` (alfa yüksek bayt) | **`BEARGB`**, alfa **düşük** bayt; `black()` = `255` |
| `TrustedSkinFlag` | sayısal enum | **string** (`"Unset"`/`"True"`/`"False"`) |
| `PersonaPieceTintColour` | iç sayım + enum | string anahtar + 4 × `BEARGB`, **sayım yok** |

`SerializedSkin` alanları v2225 şemasının **ordinal** sırasına göre yazılır
(gophertunnel'ın `Skin.Marshal`'ı eski bir protokoldür ve bu sürüm için
referans alınmadı).

### 4A.4 Dürüst sınırlar

- **Deri gönderilmez:** `SerializedSkin::blank()`. JWT'deki deri base64
  PNG'dir; tel biçimi ham RGBA bekler. PNG çözmek GD/Imagick gerektirir ve
  Phase 7.1 kapsamı dışıdır. İstemcinin varsayılan deriyi çizmesi
  **beklenir ama doğrulanmamıştır**.
- `ActorEvent::FireAtPosition` varlık kuralı (yalnızca `FireworksExplode`)
  gophertunnel/pmmp türevi bir varsayımdır; istemciyle doğrulanmadı.
- `MoveActorDelta` ve `LevelSoundEvent` kodlandı ancak oynanış akışına
  **bağlanmadı** (yayın tarafı Phase 8 chunk/entity akışıyla gelecek).

---

## 4B. Phase 7.2 — eşya varlıkları (düşürme / toplama)

### 4B.1 Yaklaşım

Düşürme ve toplama **sunucu yetkilidir**. İstemci yalnızca "ne bıraktım" ve
"hangi varlığı istiyorum" der; miktar, kimlik ve mesafe daima sunucuda
doğrulanır.

```
Düşürme:  InventoryTransaction (Normal, source = World) →
          stackFromDescriptor → spawn (AddItemActor üretimi) →
          consume (envanterden düş) → InventorySlot + AddItemActor (yayın)

Toplama:  TakeItemActor → runtime kimlik sahipliği →
          menzil (≤ 1.5 blok) → insert (sığmıyorsa vazgeç) →
          InventorySlot* + RemoveActor (yayın)
```

Bırakma sırası bilinçli: varlık **önce** üretilir, envanter düşümü başarısız
olursa `despawn()` ile geri alınır. Ters sıra, "eşya envanterden düştü ama
yerde yok" kaybına yol açardı.

### 4B.2 Yeni paketler (kimlikler resmî şemadan)

| ID | Paket | Amaç |
|---|---|---|
| 15 | `AddItemActor` | Yere düşen eşya varlığını tanıtır (S→C) |
| 17 | `TakeItemActor` | Eşya toplama isteği (C→S) |

Registry **33 → 35** paket.

`AddItemActor` gövdesi: `ActorUniqueID` (varlong) + `ActorRuntimeID`
(varuint64) + `NetworkItemStackDescriptor` + `Vec3` konum + `Vec3` hız +
`SynchedActorData` + `bool isFromFishing`. Varlık meta verisi boş liste olarak
gönderilir.

`TakeItemActor` gövdesi iki `varuint64`: `Item Runtime ID` + `Actor Runtime ID`.

### 4B.3 `SynchedActorData` tel biçimi

```
Count            varuint32
per entry:
  ID             varuint32
  Selector Type  varuint32
  Legacy Type    uint8      ← selector ile aynı olmak zorunda
  Value          tipe gore
```

Doğrulanan ayrıntılar:

- **Girdiler artan `id` sırasında yazılır** (`encode()` kendisi sıralar).
  Vanilla istemcisi bazı özellikleri yalnızca artan sırada işler.
- **Selector/legacy uyuşmazlığı reddedilir** (`ProtocolException`); sessizce
  "düzeltmek" bozuk paketi istemciye göndermek olurdu.
- **Tekrarlanan `id`** liste kurulurken reddedilir.
- Çözmede **4096 girdi sınırı** vardır (bellek tükenmesi savunması).
- **`CompoundTag` (5)** NBT çözücüsü gerektirdiği için Phase 7.2 dışıdır;
  karşılaşılırsa açık `ProtocolException` fırlatılır — sessizce atlanmaz.

### 4B.4 Dürüst sınırlar

- **Eşya fiziği yok:** varlık bırakıldığı noktada durur; düşme/yuvarlanma ve
  yerçekimi simülasyonu Phase 8'e bırakıldı. `Vec3 velocity` alanı `0` gider.
- **Yaşam süresi (despawn timer) yok:** toplanmayan eşya sunucu belleğinde
  süresiz kalır. Phase 8'de tick tabanlı temizlik gerekir.
- **Toplama mesafesi sabit 1.5 blok**; oyuncunun bakış yönü/hitbox'ı hesaba
  katılmaz.
- **`Interact` hâlâ kayıtsız:** yalnız `TakeItemActor` akışı bağlandı; mob/
  oyuncu etkileşimi Phase 8.
- **Metadata NBT yok:** eşya özel adı/etiketleri taşınmaz.
- Gerçek Bedrock istemcisiyle test **yok**; `AddItemActor` kabulü sınanmadı.

---

## 4C. Phase 8 — chunk akışı ve dünya saati

### 4C.1 Yaklaşım

Phase 4'te istemciye yalnızca **spawn chunk'ı** gönderiliyordu. Oyuncu spawn
noktasından birkaç blok yürüyünce etrafı boş görünüyordu: komşu chunk'lar hiç
gönderilmemişti. Phase 8 üç parçayı bağlar:

1. **Tek serileştirici** (`ChunkSerializer`) — giriş ve akış aynı tel biçimini
   üretir.
2. **`ChunkStreamer`** — oturum başına "gönderildi" defteri tutar, hareket
   ettikçe yeni chunk'ları döner.
3. **Hazır kapısı** — istemci `SetLocalPlayerAsInitialized` göndermeden ağır
   veri yollanmaz.

### 4C.2 Yeni paketler (kimlikler resmî şemadan)

| Paket | Kimlik | Yön | Tel biçimi |
|---|---|---|---|
| `SetTime` | 10 | S→C | `Time` int32 → **ZigZag varint32** |
| `RequestChunkRadius` | 69 | C→S | `Chunk Radius` varint32, `Max ChunkRadius` uint8 |
| `ChunkRadiusUpdated` | 70 | S→C | `Chunk Radius` int32 → **ZigZag varint32** |
| `SetLocalPlayerAsInitialized` | 113 | C→S | `Player ID` → `ActorRuntimeID` = **varuint64** |

### 4C.3 Akış sözleşmesi

- **Tekrar gönderim yok:** her chunk anahtarı (`"x,z"`) oturum defterine yazılır.
- **Yarıçap kırpılır:** `clampRadius()` istenen değeri `[1, viewDistance]`
  aralığına çeker. Varsayılan görüş mesafesi 2 (5×5 = 25 chunk).
- **Kare pencere:** dairesel maskeleme köşelerde delik bırakır; istemci bunları
  yeniden ister ve gereksiz gidiş-dönüş olur.
- **Boş chunk da gider:** aksi hâlde istemci orayı "yüklenmedi" sayar.
- **Giriş işareti:** `PlayerJoinService` spawn chunk'ını yolladıktan sonra
  `markSent()` çağrılır; ilk `sync()` onu yinelemez.
- **Sağlık kontrolü:** `SetLocalPlayerAsInitialized` içindeki runtime kimlik
  oturumun gerçek kimliğiyle karşılaştırılır; uyuşmazsa yok sayılır.
- **Yineleme:** ikinci `SetLocalPlayerAsInitialized` saat/chunk yollamaz.

### 4C.4 Dürüst sınırlar

- **İstemci tarafında chunk boşaltma yok:** `NetworkChunkPublisherUpdate`
  uygulanmadı. Sunucu defteri `evictOutside()` ile sınırlanır, ancak istemci
  uzak chunk'ları bellekte tutmaya devam eder.
- **Saat ilerlemesi Phase 9'da tamamlandı:** `GameplayTicker` her tick
  `World::advanceTime(1)` çağırır ve 200 tick'te bir `SetTime` yayınlar.
- **Yarıçap büyütme Phase 9'da düzeltildi:** `RequestChunkRadius` yeni
  pencereyi hemen doldurur.
- **Movement dışı tetikleyici yok:** ışınlanma (`respawn`) sonrası chunk akışı
  bir sonraki `PlayerAuthInput`'a bağlı.
- Gerçek Bedrock istemcisiyle test **yok**; `SetTime` / chunk penceresi kabulü
  sınanmadı.

---

## 4D. Phase 9 — oyuncu öznitelikleri, yetenekler ve tick döngüsü

### 4D.1 Yaklaşım

Phase 9 üç katmanı ayırır:

1. **Protokol** — `UpdateAttributes` (29), `UpdateAbilities` (187),
   `Attribute`, `AttributeModifier`. Ağ biçimi golden-byte testleriyle
   kilitlendi.
2. **Alan (domain)** — `PlayerAttributes` açlık/doygunluk/tükenme zincirini,
   `PlayerAbilities` yetenek katmanını taşır. İkisi de ağ bilmez.
3. **Tick** — `GameplayTicker` ağdan bağımsız çalışır; `ConnectionPipeline`
   sonucu oturumlara dağıtır. Birim testleri gerçek soket açmaz.

### 4D.2 Yeni paketler (kimlikler resmî şemadan)

| Paket | Kimlik | Yön | Tel biçimi |
|---|---|---|---|
| `UpdateAttributes` | 29 | S→C | `Runtime ID` varuint64, `Attributes` varuint32 sayı, `Tick` varuint64 |
| `UpdateAbilities` | 187 | S→C | `SerializedAbilitiesData`: int64 uniqueId, int8 permission, uint8 commandPermission, **varuint32** katman sayısı, katmanlar |

`Attribute` alan sırası: Min, Max, Current, DefaultMin, DefaultMax, Default
(float32), Name (string), modifier sayısı (varuint32), modifier'lar.
`AttributeModifier`: Id, Name, Amount (float32), Operation (int32),
Operand (int32), Serializable (bool).

`AbilityLayer`: uint16 type, uint32 abilitiesSet, uint32 abilityValues,
float32 flySpeed, float32 verticalFlySpeed, float32 walkSpeed.

### 4D.3 Tick sözleşmesi

- **Dünya saati:** her tick +1; `TIME_SYNC_INTERVAL = 200` tick'te bir
  `SetTime`. Tick 0 yayın yapmaz (girişte zaten gönderildi).
- **Açlık:** yalnızca **hareket eden** oyuncuda tükenme artar. Hareket,
  tick'ler arası yatay mesafeyle ölçülür (`MOVE_EPSILON = 0.001`); istemcinin
  bayrağına güvenilmez. 4.0 tükenme = 1 açlık; doygunluk önce harcanır.
- **Eşya ömrü:** `LIFETIME_SECONDS = 300`; süre dolunca `RemoveActor`
  yayınlanır.
- **Eşya fiziği:** yerçekimi `0.04`, sönüm `0.98`; ilk katı bloğun üstüne
  oturur. Yalnızca dikey çarpışma modellenir.
- **Öznitelik yayını:** açlık değiştiğinde yalnızca ilgili oyuncuya
  `UpdateAttributes` gider.
- **Zamanlayıcı:** `Scheduler` enjekte edilirse pipeline kendini
  `gameplay.pipeline-tick` olarak kaydeder (gecikme 1, periyot 1).

### 4D.4 Dürüst sınırlar

- **Açlıktan ölüm Phase 10.1'de eklendi:** artık 80 tick'te 1 hasar üretir
  ve oyuncuyu öldürebilir.
- **Doygunluk kazanımı yok:** yemek yeme (`eat()`) API'si var; envanterden
  yemek tüketimi ve besin değerleri bağlanmadı.
- **Yetenek değişimi yok:** yalnızca hayatta kalma katmanı gönderilir;
  yaratıcı/uçuş geçişi, izin değişimi yok.
- **Eşya fiziği basit:** yatay duvar, sıvı, eğim ve yuvarlanma yok.
- **`Interact` hâlâ yalnızca günlüğe yazılır:** binme/NPC/envanter açma
  işlenmiyor.
- **`ActorEvent` / `LevelSoundEvent` oynanışa bağlanmadı:** ses/görsel geri
  bildirim yok.
- Gerçek Bedrock istemcisiyle test **yok**; öznitelik/yetenek kabulü sınanmadı.

---

## 4E. Phase 10.1 — hayatta kalma sonuçları

### 4E.1 Yaklaşım

Phase 9 açlık zincirini kurdu ama açlık 0'da hiçbir sonuç üretmiyordu. Bu
adım iki kuralı ekler ve ikisini de **ağdan bağımsız** tutar:

1. **Açlıktan hasar:** açlık 0 iken her `STARVATION_INTERVAL` (80) tick'te 1.
2. **Doğal yenilenme:** açlık ≥ `REGEN_MIN_HUNGER` (18) ve can dolu değilken
   her `REGEN_INTERVAL` (80) tick'te 1 can.

`PlayerSurvival` oyuncu başına zamanlayıcı tutar. `GameplayTicker` bu sonucu
`healthUpdates` haritasına yazar; `ConnectionPipeline` yalnızca ilgili
oyuncuya `SetHealth` gönderir.

Plan: `docs/superpowers/plans/2026-11-19-phase101-survival-consequences.md`.

### 4E.2 Sözleşmeler (testle sabit)

- **Zamanlayıcı koşula bağlıdır:** açlık 18'in altına düşerse yenilenme
  sayacı, açlık 1'e çıkarsa açlık hasarı sayacı **sıfırlanır**. "79 tick
  biriktir, bir lokma ye, hemen iyileş" mümkün değildir.
- **İlk tick yalnızca konum tabanıdır:** oturum ilk görüldüğünde hareket
  karşılaştırması yapılmaz; hayatta kalma sayacı sonraki tick'ten işler.
  Sözleşme "girişten itibaren 80 tick" değil, **80 uygun tick**'tir.
- **Yalnızca spawn edilmiş oyuncu işlenir:** ölü, giriş aşamasındaki
  (`Joining`) veya ayrılmış oyuncuda sayaçlar sıfırlanır; hayatta kalma
  kuralı uygulanmaz. Ölü oyuncu hareket etse bile açlık zinciri ilerlemez.
- **Açlıktan ölüm mümkündür:** hasar 1'de durmaz; 0'a inince oyuncu `Dead`
  olur. Bu, Java'nın zorluk tabanlı tabanından bilinçli sapmadır (sunucuda
  zorluk kavramı yok).
- **Sağlık yalnızca sahibine gider:** `SetHealth` başka oyunculara
  yayınlanmaz.
- **Yeni paket yok:** `SetHealth` (42) zaten kayıtlı.

### 4E.3 Dürüst sınırlar

- **Yemek tüketimi yok:** `Player::eat()` API'si var ama envanterden yemek
  yeme bağlanmadı; oyuncu açlığını yalnızca test/API ile yenileyebilir.
- **Açlık hasarında ölüm olayı yayını yok:** istemciye `SetHealth(0)` gider;
  `ActorEvent(Death)` Phase 10.2'ye bırakıldı.
- **Yenilenme hızı sabit:** doygunluk seviyesine göre hızlanma yok.
- **Gerçek istemci kabulü sınanmadı.**

---

## 4F. Phase 10.2 — yemek tüketimi

### 4F.1 Yaklaşım

Phase 10.1 açlığın **sonuçlarını** bağladı ama açlığı geri doldurmanın yolu
yoktu. Bu adım envanterdeki yenilebilir eşyayı tüketir:

1. `ItemUseInventoryTransaction` (`Action Type = Use`) iki giriş yolundan da
   (`PlayerAuthInput` bit 32 ve `InventoryTransaction` paketi) **tek**
   yönlendiriciye (`PlayerAuthInput::handleItemUse`) düşer; `Place` eylemi
   eskisi gibi `BlockActionManager::place` yoluna gider.
2. `FoodTable` eşya kimliğini besin/doygunluk değerine eşler; bilinmeyen eşya
   `null` döner ("sıfır besin" uydurulmaz).
3. `FoodConsumptionManager` kuralları uygular, `Player::eat()` çağırır ve
   envanterden 1 düşer; sonucu `FoodConsumptionResult` olarak döner.
4. `PlayerAuthInput` sonucu `InventorySlotPacket` (konteyner 0) ve
   `UpdateAttributesPacket` (açlık çubuğu bir sonraki tick'i beklemesin)
   olarak istemciye yollar.
5. `ConnectionPipeline::onConnectionClosed` → `FoodConsumptionManager::forget`
   ile soğuma kaydı silinir.

Plan: `docs/superpowers/plans/2026-11-19-phase102-food-consumption.md`.

### 4F.2 Sözleşmeler (testle sabit)

- **Sunucu yetkilidir:** istemcinin `item` tanımlayıcısına güvenilmez; yığın
  `inventory()->getSlot($slot)` ile okunur.
- **Yalnızca seçili hotbar + ana el:** `transaction->slot` ile
  `selectedHotbarSlot()` uyuşmazsa istek reddedilir; `HandSlot::OffHand`
  reddedilir.
- **Ağ kimliği zorunlu:** `ItemRegistry` kaydı yoksa tüketim reddedilir ve
  `warning` loglanır — aksi hâlde `InventorySlot` bildirimi gönderilemez ve
  sunucu/istemci envanteri ayrışır.
- **Tok oyuncu yiyemez:** `hunger >= MAX_HUNGER` (20) iken istek reddedilir.
- **Ölü / `Joining` oyuncu yiyemez:** `Player::canInteract()` kapısı.
- **Soğuma:** `EAT_COOLDOWN_MS = 1600`; oturum başına izlenir, oyuncular
  birbirini etkilemez.
- **Tüketim başına 1 eşya:** yığın biterse yuva `null` olur ve istemciye boş
  tanımlayıcı (`NetworkItemStackDescriptor::empty()`) gider.
- **Tek çağrıda mutasyon:** önce `removeFromSlot()` (fırlatabilir), sonra
  `eat()` (fırlatmaz). Sıra bu yöndedir ki düşüm başarısız olursa oyuncu
  besini bedavaya almaz. Hata hâlinde paket üretilmez.
- **Yeni paket yok:** `InventorySlot` (50) ve `UpdateAttributes` (29) zaten
  kayıtlı.

### 4F.3 Dürüst sınırlar

- **`ItemStackRequest` (147) Phase 10.3'te bağlandı** (§4G). Gerçek istemci
  yemek yerken envanter değişimini bu yolla bildirir; paket artık yanıtlandığı
  için bağlantı düşmez, ancak **`ItemUse` yolu gerçek istemcide hâlâ sınanmış
  sayılmaz**.
- **Sunucu önyüklemesinde canlı `ItemRegistry` yok.** Yemek kimlikleri şu an
  yalnızca envanter içeriği ve testler üzerinden var olur; canlı sunucuda
  `FoodTable` kayıtları registry'ye bağlanmadan tüketim reddedilir. Bu,
  Phase 10.3 sonrası bilinen boşluktur.
- **Zehir/açlık efekti yok:** çürük et, örümcek gözü, balon balığı yalnızca
  açlık + doygunluk uygular; durum etkisi üretilmez.
- **Özel tüketim kuralları yok:** bal şişesi cam şişe, kase yemekleri kase
  bırakmaz; kek/pasta dilim mantığı yoktur.
- **Yaratıcı modda tüketim davranışı yok** (yetenek modeli henüz yok).
- **Gerçek istemci kabulü sınanmadı.**

### 4F.4 Dört model incelemesi

`.team/phase102-review.md` — 4/4 model yanıt verdi (14096 token toplam).
Bulguların **biri gerçekti**: `Player::eat()` + `removeFromSlot()` sırası
`eat → remove` idi ve `removeFromSlot` fırlatırsa "açlık geldi, eşya kaldı"
penceresi bırakıyordu. Sıra `remove → eat` yapıldı. Diğer dokuz bulgu
doğrulandı ve **yanlış pozitif / bilinen sınır** olarak kayda geçti:

| İddia | Neden yanlış pozitif / sınır |
|---|---|
| `hunger >= 20` yerine `> 20` olmalı | Vanilla'da tam 20 iken yemek yenmez; `>=` doğru |
| Son eşyada `InventorySlot` gönderilmiyor | `descriptorFor(null)` boş tanımlayıcı döner ve paket gönderilir; `testEatingLastItemSendsEmptySlotDescriptor` kilitler |
| `consume()` `null` dönünce `place()` çalışmıyor | `place()` zaten `actionType !== Place` için reddediyor |
| İkinci `getSlot()` deterministik değil | Okuma sunucu durumunun kendisidir; kasıtlı tercih |
| Soğuma paket gönderiminden önce yazılıyor | Sunucu tarafı durum; geri alma istismar kapısı açar |
| `UpdateAttributes` yalnızca değişenleri yollamalı | Tam liste katılımda da kullanılıyor, protokol uyumlu |
| Ölü oyuncu için `isAlive()` yok | `canInteract()` = state + `health > 0` |
| Soğuma yeniden bağlanınca sıfırlanıyor | Yeniden bağlanma el sıkışması 1.6 s'den uzun |
| `ItemRegistry` `null` iken hiç yemek yenmez | Doğru ama bilinen sınır (§4F.3) |

---

## 4G. Phase 10.3 — `ItemStackRequest` (147/148)

### 4G.1 Yaklaşım

Gerçek istemci envanterde eşya taşırken sunucu yetkili `ItemStackRequest`
yolunu kullanır. Bu yol **kayıtsız olduğu için** istemci tek eşya taşıdığında
bağlantı düşüyordu (`ProtocolException::unknownPacket` → `failConnection`);
`PlayerAuthInput` bit 34 ise alanı çözmeyip yalnızca o paketi düşürüyordu.
Phase 10.3 bu yolu açar:

1. Tel tipleri: `ItemStackRequest`, `ItemStackRequestAction`,
   `ItemStackRequestSlotInfo` (+ `FullContainerName`), `ItemStackNetResult`,
   `ItemStackResponseInfo` / `ItemStackResponseContainerInfo` /
   `ItemStackResponseSlotInfo`.
2. Paketler: `ItemStackRequestPacket` (147, C→S, 64 KB) ve
   `ItemStackResponsePacket` (148, S→C).
3. `PlayerAuthInput` alanı 12 (`Item Stack Request`, bit 34) artık
   `ItemStackRequest` olarak çözülür/kodlanır.
4. `ItemStackRequestManager` doğrular ve **kopya** üzerinde uygular; sonuç tek
   `ItemStackResponsePacket` olarak döner.
5. `ConnectionPipeline::onConnectionClosed` → `release()` imleci envantere geri
   yazar, sonra `forget()` kaydı siler.

Plan: `docs/superpowers/plans/2026-11-19-phase103-item-stack-request.md`.

### 4G.2 Tel biçimi (gophertunnel / pmmp ile doğrulandı)

Düzeltilen üç ayrıntı:

- `ItemStackRequestSlotInfo` ağ kimliği **sabit int32 LE** (`readInt32`),
  varint değil.
- `MineBlock.stackNetworkId` **varint32** (zigzag), sabit int32 değil.
- `CraftRepairAndDisenchant` = `varuint32` recipeNetworkId + `varint32`
  repairCost + `uint8` repetitions.

Aksiyon varyantı: `type >= 9` iken `varyant = type - 2`, aksi hâlde
`varyant = type`. Uyuşmazlık `ProtocolException` (paket düşer, bağlantı yaşar).
`PlaceInContainer` (7), `TakeOutContainer` (8), `CraftRecipeAuto` (13) ve
`CraftResults` (20) gövdeleri eşya tanımlayıcı listeleri taşıdığı için
çözülmez → `UnsupportedPacketFieldException` (yalnızca o paket düşer).

### 4G.3 Sözleşmeler (testle sabit)

- **Yalnızca `Take` / `Place` / `Swap`.** Diğer türler **tüm isteği**
  `ActionRequestNotAllowed` ile düşürür; yarım uygulama yok.
- **Konteynerler:** `CombinedHotbarAndInventory` (12) yuva 0-35 ve `Cursor`
  (59) yuva 0. Diğerleri / dinamik kimlik → `InvalidSourceContainer`.
- **Ağ kimliği doğrulanmaz:** sunucu net id dağıtmıyor, tüm slot
  tanımlayıcıları `netId = null`. İstemcinin gönderdiği değer yok sayılır.
- **Atomik:** doğrulama ve uygulama kopya üzerinde; herhangi bir hata
  envanteri **ve** imleci hiç değiştirmez, hata yanıtı `containers = []`.
- **Miktar sunucu durumuna göre:** `amount < 1` veya `amount > kaynak miktarı`
  → `InvalidRemovedAmount`; boş hedefte `amount > maxStackSize` →
  `CannotPlaceItem`; dolu hedefte farklı tür → `CannotPlaceItem`, yer yok →
  `InvalidAdjustedAmount`.
- **Aynı kaynak ve hedef** → `DstContainerAndSlotEqualToSrcContainerAndSlot`.
- **İstek kimliği** negatif **tek** sayı olmalı; değilse `Error` (1).
- **Başarı yanıtı yalnızca değişen yuvaları taşır** (envanter, sonra imleç);
  boşaltılan yuva `amount = 0` bildirir.
- **İmleç kalıcı değil:** bağlantı kapanırken `release()` eşyayı envantere geri
  koyar (önce birleştir, sonra boş yuva); sığmazsa `warning` loglanır.
- Registry 41 → **43** paket.

### 4G.4 Dürüst sınırlar

- **Yaratıcı mod, zırh/offhand ve sandık konteynerleri kapsam dışı.** Yalnızca
  envanter ↔ imleç taşıması uygulanır.
- **`Drop` / `Destroy` / `Consume` / `Craft*` uygulanmaz:** istemci bunları
  gönderirse istek reddedilir (eşya düşürme Phase 7.2'nin `ItemActor` yolundan
  yapılır, buradan değil).
- **`netId` üretimi yok:** sunucu yığın kimliği dağıtmadığı için istemcinin
  kimlik tabanlı doğrulaması devre dışı; yalnızca yuva/miktar doğrulanır.
- **`release()` envanter tamamen doluysa eşyayı düşürür** (Vanilla ayakların
  dibine bırakır); item actor'a bırakma bu fazda yok.
- **Canlı `ItemRegistry` önyüklemesi hâlâ yok** (§4F.3 ile aynı boşluk).
- **Gerçek istemci kabulü sınanmadı** — `CLIENT_VERIFIED` **BLOCKED**.

### 4G.5 Dört model incelemesi

`.team/phase103-review.md` — 4/4 model yanıt verdi (23864 token toplam).
14 bulgu değerlendirildi, **biri gerçekti**: imleçteki eşya kopmada kayboluyordu
(`forget()` yalnızca kaydı siliyordu). `release()` eklendi ve
`onConnectionClosed` içinde `saveInventory`'den önce çağrılıyor. Kalan 13 bulgu
**yanlış pozitif** (paylaşılan referans, `Swap` miktarı, `MAX_AMOUNT`, NBT
alanları, mutex, konteyner sırası, `MAX_REQUESTS` denetimi) olarak kayda geçti.

---

## 5. Doğrulama (bu oturumun gerçek çıktıları)

```
tools\php.cmd -l <değişen dosyalar>   → No syntax errors
tools\composer.cmd lint               → Denetlenen dosya: 341 (atlanan: 0) — Sözdizimi temiz
tools\php.cmd ... phpunit -c phpunit.xml
                                      → OK (988 tests, 12115 assertions)
graphify update .                     → 4667 nodes, 13641 edges, 176 communities
```

`phpunit.phase5.xml` (Phase 6 testini dışlayan geçici yapılandırma) Phase 6
sınıfları tamamlandığı için **silindi**. Ana kapı (`phpunit.xml`) hiçbir
dosyayı dışlamıyor. `itest-phase6.txt` hata ayıklama çıktısı da silindi.

Odaklı çalıştırmalar:

```
tests\unit\protocol\InventoryEntityPacketTest.php → OK (22 tests, 109 assertions)
tests\unit\protocol\GameplayPacketTest.php        → OK (20 tests, 74 assertions)
tests\unit\protocol\InventoryItemUseTest.php      → OK (8 tests, 26 assertions)
tests\unit\protocol\PlayerListPacketTest.php      → OK (29 tests, 106 assertions)
tests\unit\world\WorldMutationTest.php            → OK (11 tests, 27 assertions)
tests\unit\player\GameplayLoopTest.php            → OK (18 tests, 99 assertions)
tests\integration\ConnectionPipelineTest.php      → OK (38 tests, 441 assertions)
tests\unit\auth\SkinDataValidatorTest.php         → OK (7 tests, 22 assertions)
tests\unit\protocol\MovePlayerPacketTest.php      → OK (7 tests)
tests\unit\protocol\ItemActorPacketTest.php       → OK (13 tests, 41 assertions)
tests\unit\ItemActorManagerTest.php               → OK (19 tests, 86 assertions)
tests\unit\protocol\WorldStreamPacketTest.php     → OK (17 tests, 34 assertions)
tests\unit\world\ChunkStreamerTest.php            → OK (21 tests, 49 assertions)
tests\unit\gameplay\GameplayTickerTest.php        → OK (17 tests, 41 assertions)
tests\unit\protocol\PlayerAttributesPacketTest.php → OK (8 tests, 33 assertions)
tests\unit\player\PlayerAttributesTest.php        → OK (11 tests, 1046 assertions)
tests\unit\player\PlayerSurvivalTest.php          → OK (10 tests, 1146 assertions)
```

Phase 8 regresyonları:

- `WorldStreamPacketTest` — altın baytlar, round-trip, yön kaydı (4 paket)
- `ChunkStreamerTest` — yarıçap kırpma, kare pencere, tekrar gönderim engeli,
  oturum izolasyonu, giriş chunk'ı işareti, `reset`
- `testSetLocalPlayerAsInitializedSendsTimeAndStreamsChunks` — saat + 24 yeni
  chunk, yineleme yok
- `testSetLocalPlayerAsInitializedRejectsWrongRuntimeId` — kimlik iddiası
- `testRequestChunkRadiusIsClampedAndAnswered`, `testRequestChunkRadiusAcceptsSmallValue`
- `testMovementStreamsNewChunks` — hareketle yalnızca yeni sütunlar

Phase 9 regresyonları:

- `PlayerAttributesPacketTest` — `UpdateAttributes`/`UpdateAbilities` altın
  baytları, round-trip, yetenek bit sabitleri, katman sayısı varuint32
- `PlayerAttributesTest` — açlık zinciri, doygunluk önceliği, negatife düşmeme,
  `reset`, yetenek katmanı ve hız değerleri
- `GameplayTickerTest` — saat ilerlemesi, 200 tick'te `SetTime`, hareket
  tabanlı açlık, ölü oyuncu, `forget`, eşya hareketinin sonuca eklenmesi
- `ItemActorManagerTest` — yerçekimi, zeminde dinlenme, blok içinden itilme,
  hareket paketinin konumla uyumu
- `ChunkStreamerTest` — `evictOutside` uzak kayıtları siler, pay içindekileri
  korur, geri dönünce chunk yeniden gönderilir
- `testTickAdvancesWorldTimeAndBroadcastsSetTime` — gerçek saat ilerlemesi
- `testTickRemovesExpiredItemActors` — ömür sınırı
- `testTickBroadcastsItemMotion` — düşen eşya `MoveActorDelta` yayını
- `testSetLocalPlayerAsInitializedSendsAbilitiesBeforeAttributes` — sıra
- `testRequestChunkRadiusStreamsNewWindowImmediately` — yarıçap büyütme

Phase 10.1 regresyonları:

- `PlayerSurvivalTest` — 80 tick eşiği, açlıktan ölüm, yemek sonrası sayaç
  sıfırlama, 18 açlık sınırı, dolu canda durma, ölü/giriş oyuncusuna işlem yok
- `testStarvationIsReportedAsHealthUpdate` — tick sonucunda sağlık haritası
- `testRegenerationIsReportedAsHealthUpdate` — yenilenme aynı kanaldan
- `testForgetResetsSurvivalTimers` — oturum unutulunca sayaç sıfırlanır
- `testDeadPlayerDoesNotConsumeHungerWhileMoving` — ölü oyuncu 400 tick
  hareket etse bile tükenme/doygunluk değişmez
- `testJoiningPlayerIsIgnored` — spawn olmamış oyuncu yenilenmez
- `testTickSendsSetHealthOnStarvation` — gerçek pipeline `SetHealth` gönderir
- `testTickSendsSetHealthOnlyToStarvingPlayer` — iki oyunculu akışta sağlık
  yalnızca açlık çeken oyuncuya gider

Phase 10.2 regresyonları:

- `FoodTableTest` — varsayılan tablo (37 eşya), bilinmeyen eşya `null`, her
  değer 0..20 aralığında, geçersiz besin/doygunluk reddedilir
- `FoodConsumptionManagerTest` — tüketim + besin uygulama, son eşyada yuva
  boşalması, tok oyuncu, yemek olmayan eşya, ağ kimliği kayıtsız eşya,
  seçili hotbar dışı yuva, off-hand, ölü/giriş oyuncusu, boş yuva, 1600 ms
  soğuma, `forget`, oyuncu başına bağımsız soğuma
- `testEatingFoodRestoresHungerAndReportsInventorySlot` — gerçek pipeline
  `InventorySlot` (count 1) + `UpdateAttributes` (açlık 20) gönderir
- `testEatingWhenFullIsRejectedWithoutPackets` — tok oyuncu için hiçbir paket
  üretilmez, yığın korunur

Phase 7.2 regresyonları:

- `testAddItemActorGoldenBytesWithoutMetadata` — 15 paketinin tam gövdesi
- `testAddItemActorRoundTripCarriesAllScalarFields` — alan eşlemesi
- `testMetadataRoundTripCoversEverySupportedType` — 8 veri tipi
- `testMetadataIsEncodedInAscendingIdOrder` — sıralama zorunluluğu
- `testSelectorAndLegacyTypeMismatchIsRejected` — sessiz düzeltme yok
- `testCompoundTagDecodeIsExplicitlyUnsupported` — açık sınır
- `testDuplicateMetadataIdIsRejected`, `testMetadataCountIsCapped`
- `testTakeItemActorGoldenBytes`, `testTakeItemActorRoundTrip`
- `testDropConsumesItemAndSpawnsItemActor` — düşürme uçtan uca (2 oyuncu)
- `testTakeItemActorPicksUpDroppedItem` — toplama + `RemoveActor` yayını
- `testTakeItemActorRejectsOutOfRangePickup` — menzil savunması
- `ItemActorManagerTest` — `consume`/`insert` atomikliği, dolu envanter,
  kopya yığın izolasyonu, bilinmeyen eşya, `despawn`

Yeni regresyon testleri (hepsi gerçek şifreli çerçeve kullanır):

- `testFullJoinReachesPlaying` — uçtan uca şifreli katılım
- `testUntrustedChainFailsClosedAndClosesConnection`
- `testSpawnDeliveryFailureRollsBackPlayerAndSession`
- `testUnexpectedPacketBeforeHandshakeClosesConnection`
- `testGarbagePayloadClosesOnlyThatConnection`
- `testConnectionCloseRemovesPlayerAndSession`
- `testMoveAfterSpawnSendsExactlyOneMovePlayerPacket`
- `testDuplicateClientToServerHandshakeClosesConnection`
- `testInvalidCiphertextClosesOnlyThatConnection`
- `testInvalidEncryptedFrameLengthClosesConnection`
- `testMultipleEncryptedPacketsInOneFrameAreProcessed`
- `testResourcePackResponseBeforeEncryptionIsRejected`
- `testValidExtraDataVerifiesAgainstPlayerKey`, `testExtraDataSignedByAnotherKeyIsRejected`,
  `testExtraDataPresentButUnsignedIsRejected`, `testMissingExtraDataYieldsNullSkin`,
  `testExtraDataCannotOverrideIdentityFields`, `testEngineFailsClosedWhenExtraDataSignatureIsInvalid`

Phase 6 regresyonları:

- `testInventoryEntityPacketIdsMatchOfficialSchema` — on paket kimliği
- `testRegistryContainsInventoryEntityPacketsWithDirections` — yönler
- `testEmptyDescriptorWireFormat` — 8 baytlık boş tanımlayıcı
- `testDescriptorFieldOrderMatchesWire` — alan sırası ve `EMPTY_USER_DATA` uzunluğu
- `testInventoryTransactionRoundTripNormal` — Normal varyantı
- `testInventoryTransactionRejectsNonNormalTransactionType` — Phase 6 dışı varyant reddi
- `testInventoryTransactionRejectsMoreThanOneHundredActions` — 100 aksiyon sınırı
- `testAddActorRejectsNonEmptyMetadata` — metadata reddi
- `testNewPacketsRejectTrailingBytesInStrictMode` — sıkı çözüm

Phase 7 regresyonları:

- `testPacketIdsMatchOfficialSchema` — beş yeni paket kimliği
- `testUpdateBlockGoldenBytes`, `testInteractWithoutPositionIsTwoBytes`,
  `testSetHealthGoldenBytesZigZag`, `testRespawnGoldenBytes` — golden baytlar
- `testPlayerActionTypeWireValuesMatchSchema` — 26/27/13 düzeltmesi
- `testUnloadedChunkIsNotReadable`, `testSetBlockOutsideWorldHeightThrows`,
  `testSetBlockMutatesAndMarksChunkDirty` — dünya mutasyonu sınırları
- `testItemUseVariantRoundTripsInsideInventoryTransaction`,
  `testItemStackRequestFlagRaisesRecoverableException`,
  `testPlayerAuthInputCarriesItemUseTransaction` — ItemUse/bit 32-34
- `testHealthIsPerPlayer`, `testLethalDamageMarksPlayerDeadAndBlocksInteraction`,
  `testRespawnRestoresStateHealthAndPosition`, `testMutationRateLimitIsEnforced`
- `testSecondJoinMakesPlayersVisibleToEachOther` — iki yönlü `AddPlayer`
- `testBlockBreakIsBroadcastToOtherPlayers` — kıran hariç `UpdateBlock`
- `testPlaceConsumesItemAndReportsInventorySlot` — `UpdateBlock` + `InventorySlot`
- `testRespawnRestoresHealthAndTeleportsPlayer` — `Respawn` + `SetHealth` + `MovePlayer`
- `testAlivePlayerRespawnRequestIsIgnored`
- `testDisconnectBroadcastsRemoveActorToRemainingPlayers`
- `testUnsupportedPacketFieldDropsOnlyThatPacket` — bağlantı yaşar

---

## 6. Bu oturumda bulunan gerçek hatalar

| Hata | Düzeltme |
|---|---|
| Tek `encryptionEstablished` bayrağı "cipher var" ile "karşı taraf kanıtladı"yı karıştırıyordu | `cipherAvailable` + `encryptionEstablished` ayrımı |
| Şifreleme paket bazında sanılıyordu | Çözme tüm gövdeye, şifreleme tüm çerçeveye taşındı |
| `PlayerAuthInput` içinde `World` import'u yoktu | `use PickaxeMP\world\World;` eklendi |
| `PlayerPositionSync` var olmayan bir `MovePlayerPacket` API'si kullanıyordu | Paket şemaya göre yazıldı, çağrı gerçek imzaya göre düzeltildi |
| Pipeline hareket paketini iki kez gönderiyordu | Doğrudan gönderim silindi; tek kaynak `PlayerPositionSync` |
| `Item`/`ItemStack` kimlik doğrulaması `/` sınırlayıcılı regex ile yazılmıştı | `~` sınırlayıcıya çevrildi |
| `extraData` hiç doğrulanmıyordu | `SkinDataValidator` + `SkinData` eklendi, motor fail-closed bağlandı |
| `NetworkItemStackDescriptor` taslağı iki ayrı net-id alanı yazıyordu | Şemaya göre tek opsiyonel `Net Id Variant` varint32'ye indirildi |
| `FullContainerName.Dynamic ID` varint sanılıyordu | Şemaya göre sabit uint32 yapıldı |
| `ActorUniqueID` sabit int64 sanılıyordu | Şemadaki `Compression` nedeniyle varlong yapıldı |
| `PlayerBlockAction` sabitleri şemayla uyuşmuyordu (`PREDICT_DESTROY_BLOCK = 18`) | 26, `CONTINUE` 27, `CREATIVE` 13 olarak düzeltildi; `isBreak()` gerçek değerleri kapsıyor |
| `HealthManager` tek paylaşılan sağlık alanı kullanıyordu | Sağlık `Player` nesnesine taşındı; her oyuncu bağımsız |
| `Color` taslağı `ARGB` sanıyordu (alfa yüksek bayt) | `BEARGB` olarak düzeltildi; alfa düşük bayt, `black()` = `255` |
| `TrustedSkinFlag` sayısal enum sanılıyordu | Şemaya göre **string** yapıldı (`"Unset"`/`"True"`/`"False"`) |
| `PersonaPieceTintColour` iç sayım + enum yazıyordu | String anahtar + 4 × `BEARGB`, sayım kaldırıldı |
| `SerializedPersonaPieceHandle.PieceType` varuint sanılıyordu | Sabit uint32 yapıldı |
| `Color::name()` yerine var olmayan `value` API'si kullanılıyordu | `->value` / `->toRgba()` doğru çağrıldı |
| `PlayerListEntry::decode()` variant/action uyuşmazlığını kabul ediyordu | `ProtocolException` ile reddediliyor |
| `ActorEventPacket::encode()`, `decode()` ile asimetrikti | `Fire At Position` yalnız `carriesFirePosition()` iken yazılıyor |
| Testte zigzag varint elle yanlış hesaplanmıştı (1234 → `d209`) | `a413` olarak düzeltildi (zigzag 2468) |
| `RemoveActor` alanına runtime kimlik yazılıyordu (eşya varlıklarında) | `ActorUniqueID` (`entityId()`) gönderiliyor; istemci aksi hâlde varlığı sahnede bırakır |
| Düşürmede envanter önce boşaltılıyordu | Sıra tersine çevrildi: varlık önce üretilir, düşüm başarısızsa `despawn()` ile geri alınır |
| Eşya varlık kimlikleri oyuncu kimlikleriyle çakışabiliyordu | `EntityManager` yüksek tabandan başlatılır (`1000`), dokümante edildi |
| `RequestChunkRadius.maxChunkRadius` için `writeUint8` çağrıldı (metot yok) | `writeByte()`/`readByte()` kullanıldı |
| `SetTime` altın baytları elle yanlış hesaplanmıştı (`C0 DD 00`) | Gerçek ZigZag varint `E0 5D` olarak düzeltildi |
| `ChunkStreamer` testi `BlockRegistry`'yi yanlış namespace'ten import ediyordu | `PickaxeMP\world\block\BlockRegistry` |
| Giriş sonrası spawn chunk'ı akış tarafından ikinci kez gönderilecekti | `markSent()` ile işaretlendi |
| `SerializedAbilitiesData` katman sayısı tek bayt yazılıyordu | **varuint32**'ye çevrildi; 256+ katman artık bozulmuyor |
| Eşya yalnızca `Entity` içinde konum tutuyordu; fizik için ikinci kaynak gerekir | `ItemActor` mutable `position`/`velocity`/`onGround` taşır; `commit()` ikisini senkronlar |
| Yarıçap büyütme kabul ediliyor ama yeni pencere hemen dolmuyordu | `RequestChunkRadius` sonrası `streamChunks()` çağrılır |
| Oturum başına gönderilen chunk defteri sınırsız büyüyordu | `evictOutside(radius + 2)` ile uzak kayıtlar unutulur |
| Yemekte mutasyon sırası `eat → remove` idi; düşüm fırlatırsa bedava besin penceresi kalıyordu | Sıra `removeFromSlot → eat` yapıldı (`eat` fırlatmaz, üst sınıra kırpar) |

---

## 7. AÇIK EKSİKLER (dürüst liste)

| Eksik | Etki |
|---|---|
| Gerçek Bedrock istemcisiyle test | **YOK** → `CLIENT_VERIFIED: BLOCKED` |
| Sunucu yetkili blok kırma / menzil doğrulaması | Kırma istemci bildirimine dayanır; hile mümkün |
| `PlayerList` (63) | ✅ Eklendi ve bağlandı (spawn/ayrılma). Deri gerçek (aşağıya bak). |
| `ActorEvent` (27) / `LevelSoundEvent` (123) | ✅ Phase 10.4: hasar `Hurt`, ölüm `Death`, blok `break`/`place`, eşya `pickup`, yemek `burp` |
| `MoveActorDelta` (111) | ✅ Phase 9'da düşen eşya hareketine bağlandı; oyuncu dışı başka varlık yok |
| Gerçek deri (skin) gönderimi | ✅ Phase 10.4: saf PHP `PngDecoder` → ham RGBA → `SerializedSkin`; bozuk PNG'de `blank()` (oyuncu atılmaz) |
| `Entity Data` / `Actor Data` (metadata) | Boş liste; isim etiketi/deri taşınmıyor |
| `ItemStackRequest`/`ItemStackResponse` (147/148) | ✅ Phase 10.3: envanter ↔ imleç `Take`/`Place`/`Swap`; diğer konteyner/aksiyonlar reddedilir |
| Eşya düşürme/toplama varlıkları | ✅ Phase 7.2'de eklendi ve bağlandı |
| Eşya varlık fiziği (düşme/yuvarlanma) | ✅ Phase 9: dikey yerçekimi + zemine oturma; yatay çarpışma ve yuvarlanma yok |
| Toplanmayan eşyanın ömrü (despawn timer) | ✅ Phase 9: 300 sn sonra `RemoveActor` |
| Eşya metadata'sı (özel ad/NBT) | `SynchedActorData` tip 5 desteklenmiyor (açık hata) |
| Açlık, yetenekler, öznitelikler | ✅ Phase 9'da eklendi ve bağlandı |
| Açlıktan ölüm hasarı | ✅ Phase 10.1: açlık 0 iken 80 tick'te 1 hasar; ölüm mümkün |
| Doğal can yenilenmesi | ✅ Phase 10.1: açlık ≥ 18 iken 80 tick'te 1 can |
| Açlık ölümünde `ActorEvent(Death)` | ✅ Phase 10.4: `ActorEvent(Death)` + `death` sesi yayınlanır |
| Yemek tüketimi / besin değerleri | ✅ Phase 10.2: `ItemUse` (`Use`) + `FoodTable` + `Player::eat()` + `InventorySlot`/`UpdateAttributes`. Canlı sunucuda `ItemRegistry` (162) Phase 10.4'te bağlandı |
| Zehir/açlık durum etkisi | Yok; yalnızca açlık + doygunluk uygulanır |
| Kase/şişe bırakan yemekler | Yok; tüketim her zaman 1 eşya düşer |
| Yetenek geçişi (creative/fly) | ✅ Phase 10.4: `GameMode` + `applyGameMode()`/`setFlying()` → `UpdateAbilities` yeniden yayını |
| Chunk akışı (görüş mesafesi) | ✅ Phase 8'de eklendi ve bağlandı |
| İstemci tarafında chunk boşaltma | Yok; `NetworkChunkPublisherUpdate` uygulanmadı. Sunucu defteri `evictOutside()` ile sınırlı |
| Dünya saati ilerlemesi (tick) | ✅ Phase 9: her tick +1, 200 tick'te bir `SetTime` |
| Yarıçap büyütmede yeniden gönderim | ✅ Phase 9: `RequestChunkRadius` yeni pencereyi hemen doldurur |
| `SubChunkPacket` istek modu | `LevelChunk` tam veri gönderiyor |
| Heightmap, biome verisi | Gönderilmiyor |
| `ChunkCache` / `SaveJournal` | Yok |
| `RestartManager`/`Supervisor` | Yok |
| Git geçmişi | Tek commit (`29f8ffd`); `src/PickaxeMP/connection/`, `tests/`, `tools/`, `docs/protocol/`, `graphify-out/` untracked — **güvenlik ağı yok** |

Şema varsayımları (gerçek istemci olmadan doğrulanamaz):

1. `StartGame` içinde `Player Property Data` boş NBT,
   `Server Configuration Join Info` = 3 × `0x00`, `SpawnSettings` = 4 × int32.
2. Blok ağ hash değerleri.
3. `Interact` paketinde konumun yalnız `InteractUpdate` eyleminde yazılması.
4. `MovePlayerPacket`, Phase 6, Phase 7, Phase 7.1, Phase 7.2, Phase 8 ve
   Phase 9 paketlerinin alan sıraları resmî şemadan alındı; istemci kabulü
   sınanmadı.
5. `ActorEvent::FireAtPosition` yalnız `FireworksExplode` olayında yazılır
   (gophertunnel/pmmp türevi varsayım).
6. `SynchedActorData` legacy baytının selector ile **aynı** olması gerektiği
   gophertunnel `Writer.EntityMetadata`'ya dayanır; istemci davranışı
   sınanmadı.

---

## 8. Sıradaki kesin görev

Phase 10.4 ile canlı `ItemRegistry`, oynanış geri bildirimi, gerçek deri ve
oyun modu/yetenek geçişleri kapandı. Phase 10.5 ile isim etiketleri, blok
menzil doğrulaması ve istemci tarafı chunk penceresi (`NetworkChunkPublisherUpdate`)
kapandı. Kalan sıra:

1. **Ses olayı genişletmesi:** yemek dışı `item.use.on`, `hit`, `attack.*`
   sesleri; ok/patlama gibi olaylar yok.
2. **Yeniden doğma akışı:** `Death` sonrası `Respawn` paketi ve
   `SetSpawnPosition`; şu an yalnız `Respawn` + `SetHealth` + `MovePlayer`.
3. **Gerçek Bedrock 1.26.65 istemcisiyle katılım testi.**

**Bitirme koşulu:** Gerçek Bedrock 1.26.65 istemcisi düz dünyaya girip hareket
edebilmeli, blok kırıp koyabilmeli ve öznitelik/yetenek paketlerini kabul
etmeli. Bu doğrulanmadan `CLIENT_VERIFIED` değeri `BLOCKED` kalır.

---

## 9. Gerçek istemci testi ön koşulları

`docs/runbooks/nethernet-sidecar.md` §6 — özetle: Bedrock 1.26.60+ istemci,
açık TCP signaling + UDP media portu, LAN IP (loopback reddedilebilir),
token doğrulaması açık, mümkünse HTTPS.
