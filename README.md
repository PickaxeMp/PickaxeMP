# PickaxeMP

> Minecraft: Bedrock Edition için PHP ile yazılan bağımsız sunucu yazılımı.

**Durum:** 🚧 Erken geliştirme — **gerçek Bedrock istemcisiyle doğrulanmadı.**

Phase 0–8 tamamlandı: ağ, şifreli oturum, dünya, envanter/varlık motoru,
oynanış döngüsü (hareket, blok kırma/yerleştirme, sağlık/ölüm/yeniden doğma),
yere eşya düşürme/toplama ve görüş mesafesinde chunk akışı + dünya saati kod ve
test düzeyinde çalışıyor. Gerçek istemci kabulü **henüz sınanmadı**;
`CLIENT_VERIFIED` değeri `BLOCKED`. Ayrıntılı durum için
[docs/DEVELOPMENT_STATE.md](docs/DEVELOPMENT_STATE.md).

## Ne çalışıyor?

| Bileşen | Durum |
|---|---|
| Yapılandırma okuyucu (belgelenmiş YAML alt kümesi) | ✅ Çalışıyor, test edildi |
| Tipli ayar doğrulama (`ServerConfig`) | ✅ Çalışıyor, test edildi |
| Seviyeli logger + hassas veri maskeleme | ✅ Çalışıyor, test edildi |
| Tick tabanlı zamanlayıcı | ✅ Çalışıyor, test edildi |
| Modül yaşam döngüsü + bağımlılık sıralaması | ✅ Çalışıyor, test edildi |
| Reload orkestrasyonu (config/veri, **kod değil**) | ✅ Çalışıyor, test edildi |
| Taşıyıcı sözleşmeleri + IPC çerçevesi | ✅ Çalışıyor, test edildi |
| **Rust NetherNet sidecar** | ✅ Derleniyor, `/v1/join` doğrulandı |
| **PHP ↔ Rust IPC** | ✅ Gerçek sidecar ile test edildi |
| **Bedrock protokol motoru (paketler 1–193)** | ✅ Çalışıyor, test edildi |
| **Sıkıştırma (zlib, eşik, bomb koruması)** | ✅ Çalışıyor, test edildi |
| **El sıkışma durum makinesi** | ✅ Çalışıyor, auth kapısı test edildi |
| **JWT ayrıştırma + ES384 imza doğrulama** | ✅ Çalışıyor, gerçek OpenSSL ile test edildi |
| **Sertifika zinciri doğrulama (Mojang kökü)** | ✅ Çalışıyor, test edildi |
| **Oyuncu kimliği (XUID/UUID/isim)** | ✅ Çalışıyor, test edildi |
| **Oturum motoru (10 durum, geçiş kontrolü)** | ✅ Çalışıyor, test edildi |
| **Dünya motoru (blok, chunk, düz üretici)** | ✅ Çalışıyor, test edildi |
| **Dünya kaydı (atomik dosya + CRC32)** | ✅ Çalışıyor, test edildi |
| **StartGame / LevelChunk / SubChunk paketleri** | ✅ Kodlanıyor, sıra testli |
| **ECDH + sunucu şifreleme (`ServerToClientHandshake`)** | ✅ Çalışıyor, entegrasyon testli |
| **Şifreli oturum + `StartGame` bağlantısı** | ✅ Uçtan uca testli (sahte taşıyıcı) |
| **Hareket (`PlayerAuthInput` → `MovePlayer`)** | ✅ Çalışıyor, test edildi |
| **Envanter + eşya kalıcılığı** | ✅ Çalışıyor, test edildi |
| **Blok kırma/yerleştirme + `UpdateBlock` yayını** | ✅ Çalışıyor, test edildi |
| **Sağlık, ölüm, yeniden doğma** | ✅ Çalışıyor, test edildi |
| **Oyuncuların birbirini görmesi (`AddPlayer`/`RemoveActor`)** | ✅ Çalışıyor, test edildi |
| `php start.php --check` | ✅ Çalışıyor |
| **Oyuncu listesi (`PlayerList`) — deri `blank()`** | ✅ Bağlandı, test edildi |
| **Eşya düşürme/toplama (`AddItemActor`/`TakeItemActor`)** | ✅ Bağlandı, test edildi |
| **Chunk akışı (`ChunkStreamer`) + `SetTime`** | ✅ Bağlandı, test edildi |
| `ActorEvent` / `LevelSoundEvent` | ❌ Kod var; oynanışa bağlanmadı |
| Gerçek Bedrock istemcisi bağlantısı | ❌ **Test edilmedi** |
| Gerçek deri (skin) gönderimi | ❌ Yok — `blank()` deri |
| **Açlık / yetenekler / öznitelikler** | ✅ Bağlandı, test edildi |
| **Açlıktan ölüm + doğal yenilenme** | ✅ Bağlandı, test edildi |
| **Yemek tüketimi (`ItemUse` + `FoodTable`)** | ✅ Bağlandı, test edildi (gerçek istemci doğrulaması yok) |
| **Sunucu tick döngüsü + eşya fiziği** | ✅ Bağlandı, test edildi |
| Eklenti API | ❌ Yok |
| Restart / Supervisor | ❌ Yok |

## Gereksinimler

- **PHP 8.2+** (`openssl`, `mbstring`, `fileinfo`, `curl`, `zip`, `zlib`)
- Linux veya Windows

## Kurulum

Sistem geneli `php.ini` değiştirilmez; proje kendi araç zincirini kullanır.

```bash
# Composer'ı proje içine kur (yalnızca bir kez)
php -r "copy('https://getcomposer.org/installer','tools/composer-setup.php');"
php tools/composer-setup.php --install-dir=tools --filename=composer.phar
rm tools/composer-setup.php

# Bağımlılıkları kur
tools/composer.cmd install     # Windows
tools/composer.sh install      # Linux/macOS
```

## Çalıştırma

```bash
tools/php.cmd start.php --check    # başlat + hemen kapat (doğrulama)
tools/php.cmd start.php --ticks=20 # 20 tick çalıştır ve dur
tools/php.cmd start.php            # sürekli çalış (CTRL+C ile durur)
```

> `php start.php` yerine `tools/php.cmd` kullanın; aksi halde `openssl` gibi
> eklentiler yüklenmez. Ayrıntı: [docs/adr/0002-php-version.md](docs/adr/0002-php-version.md)

## Test

```bash
tools/composer.cmd lint
tools/composer.cmd test
tools/composer.cmd check   # ikisi birlikte
```

Sidecar entegrasyon testleri gerçek ikiliyi çalıştırır. İkili yoksa bu testler
**atlanır** (sessizce "geçti" sayılmaz):

```bash
cd transport/pickaxe-transport
cargo build --release
cargo test
```

## Taşıyıcı (NetherNet)
Oyun motoru PHP'de, WebRTC/HTTP signaling Rust sidecar'da çalışır.

```
Minecraft istemcisi
   │  HTTPS/TCP  GET /v1/join, POST /v1/join/{id}
   │  WebRTC/UDP DTLS + SCTP
   ▼
transport/pickaxe-transport (Rust)   ← NetherNet
   │  yerel IPC (127.0.0.1, token ile)
   ▼
PHP çekirdeği                        ← oyun mantığı
```

Sidecar bağımlılığı **sabit git revizyonuna** pinlidir ve `Cargo.lock` ile
kilitlidir. Kurulum ve güvenlik notları:
[docs/runbooks/nethernet-sidecar.md](docs/runbooks/nethernet-sidecar.md)

## Protokol (1.26.65 / 2225)

Hedef sürüm **tek merkezi sabitten** yönetilir:
`PickaxeMP\protocol\ProtocolVersion`.

| Belge | İçerik |
|---|---|
| [docs/protocol/1.26.65.md](docs/protocol/1.26.65.md) | Sürüm eşlemesi, paketler, doğrulanmamış noktalar |
| [docs/protocol/binary-format.md](docs/protocol/binary-format.md) | Endian, VarInt, string, UUID kuralları |
| [docs/protocol/packet-registry.md](docs/protocol/packet-registry.md) | Kayıtlı paketler, yeni paket ekleme |

Şemalar Mojang'ın `v1.26.65` etiketinden alınmıştır; paket kimlikleri
tahmin edilmemiştir. Şu anda **43 paket** kayıtlıdır. Öne çıkanlar:
Login (1), PlayStatus (2), ResourcePacksInfo (6), ResourcePackStack (7),
ResourcePackClientResponse (8), SetTime (10), AddPlayer (12), AddActor (13),
RemoveActor (14), AddItemActor (15), TakeItemActor (17),
MoveActorAbsolute (18), UpdateBlock (21), ActorEvent (27), UpdateAttributes (29),
InventoryTransaction (30),
MobEquipment (31), Interact (33), PlayerAction (36), SetHealth (42),
Respawn (45), ContainerOpen (46), ContainerClose (47), InventoryContent (49),
InventorySlot (50), PlayerList (63), RequestChunkRadius (69),
ChunkRadiusUpdated (70), MoveActorDelta (111), SetLocalPlayerAsInitialized (113),
LevelSoundEvent (123), NetworkSettings (143), PlayerAuthInput (144),
ItemStackRequest (147), ItemStackResponse (148),
SubChunk (174), UpdateAbilities (187), RequestNetworkSettings (193).

## Oynanış döngüsü (Phase 7)

- **Blok kırma:** `serverAuthBlockBreaking = false`; istemci tahmini kabul
  edilir. `CrackBlock` (18), `PredictDestroyBlock` (26) ve
  `CreativeDestroyBlock` (13) işlenir; dünya otoriter olarak güncellenir ve
  `UpdateBlock` **diğer** oyunculara yayınlanır.
- **Blok yerleştirme:** `InventoryTransaction` `ItemUse` (tip 2) varyantı;
  hedef hava ve yüklü olmalı, elde kayıtlı blok bulunmalı. Başarıda envanter
  −1, kullanıcıya `InventorySlot`, diğerlerine `UpdateBlock`.
- **Sağlık:** oyuncu başına 0..20; ölümde `PlayerState::Dead` ve etkileşim
  kapalı. İstemci `PlayerAction(Respawn)` gönderince `Respawn` +
  `SetHealth(20)` + `MovePlayer(TELEPORT)`.
- **Sınırlar:** menzil/ısınma doğrulaması ve sunucu yetkili kırma yoktur;
  yalnızca yüklü chunk, Y aralığı, `Spawned`/canlı olma ve oyuncu başına
  saniyede 30 mutasyon sınırı korunur.
- **Gerçek istemci:** hâlâ **test edilmedi**; `CLIENT_VERIFIED` `BLOCKED`.

## Oyuncu listesi ve olay paketleri (Phase 7.1)

- **`PlayerList` (63) bağlandı:** katılımda yeni oyuncuya tek liste (kendi
  girişi + diğerleri), diğerlerine tek giriş; ayrılmada liste silmesi.
  Sıra bağlayıcıdır ve testle zorlanır: `PlayerList` **her zaman**
  `AddPlayer`/`RemoveActor`'dan önce gider.
- **`ActorEvent` (27)**, **`LevelSoundEvent` (123)** ve
  **`MoveActorDelta` (111)** kodlanmış ve test edilmiştir. `MoveActorDelta`
  Phase 9'da düşen eşya hareketine bağlandı; `ActorEvent`/`LevelSoundEvent`
  hâlâ oynanışa bağlı değil.
- **Dürüst sınır — deri:** `SerializedSkin::blank()` gönderilir. JWT'deki
  deri base64 PNG'dir, tel biçimi ham RGBA bekler; PNG çözümü (GD/Imagick)
  Phase 7.1 kapsamı dışındadır. İstemcinin varsayılan deriyi çizmesi
  beklenir, **doğrulanmadı**.
- **Tel biçimi doğrulaması:** `Color` `BEARGB`'dir (alfa **düşük** bayt),
  `TrustedSkinFlag` string'dir, `PersonaPieceTintColour` iç sayım yazmaz.
  Üçü de `gophertunnel` kaynağına karşı doğrulanmıştır.

## Eşya düşürme ve toplama (Phase 7.2)

- **`AddItemActor` (15)** ve **`TakeItemActor` (17)** eklendi; registry
  **33 → 35** paket.
- **Düşürme sunucu yetkilidir:** `InventoryTransaction` (Normal, kaynak
  `World`) → yığın doğrulanır → varlık **önce** üretilir → envanterden düşülür.
  Düşüm başarısız olursa varlık geri alınır (`despawn`); böylece "eşya
  envanterden gitti ama yerde yok" durumu oluşmaz.
- **Toplama:** `TakeItemActor` → varlık sahipliği → **1.5 blok** menzil →
  envanterde yer var mı → sığmıyorsa eşya yerde kalır (kısmi toplama yok) →
  `InventorySlot` + `RemoveActor` yayını.
- **`RemoveActor` doğru alanla gider:** eşya varlıkları için `ActorUniqueID`
  (`entityId()`), runtime kimlik değil.
- **`SynchedActorData`** tel biçimi yazıldı: 8 veri tipi desteklenir, girdiler
  artan `id` sırasında yazılır, selector tipi ile legacy bayt aynı olmak
  zorundadır (uyuşmazlık açık hataya dönüşür).
- **Sınırlar:** eşya fiziği ve yaşam süresi Phase 9'da eklendi; toplanmayan
  eşya 300 saniye sonra silinir. NBT metadata (tip 5) desteklenmez; yatay
  çarpışma yoktur. Gerçek istemci kabulü **sınanmadı**.

## Chunk akışı ve dünya saati (Phase 8)

- **Dört yeni paket:** `SetTime` (10, S→C), `RequestChunkRadius` (69, C→S),
  `ChunkRadiusUpdated` (70, S→C), `SetLocalPlayerAsInitialized` (113, C→S).
  Registry **35 → 39** paket.
- **Görüş mesafesi akışı:** `ChunkStreamer` oyuncunun çevresindeki chunk'ları
  **bir kez** gönderir; her `PlayerAuthInput` sonrası yalnızca yeni sütunlar
  yayınlanır. Varsayılan yarıçap 2 (5×5 = 25 chunk).
- **Yarıçap otoritesi sunucuda:** istemcinin istediği değer `[1, viewDistance]`
  aralığına kırpılır ve gerçek değer `ChunkRadiusUpdated` ile döner.
- **Hazır kapısı:** istemci `SetLocalPlayerAsInitialized` göndermeden saat ve
  chunk akışı başlamaz. Paketteki runtime kimlik oturumla karşılaştırılır.
- **Tek serileştirici:** girişteki spawn chunk'ı ve akış aynı `ChunkSerializer`'ı
  kullanır; iki kopya zamanla ayrışıp bozuk chunk üretemez.
- **Dünya saati kalıcı:** `WorldProperties.time` (`world.json`); `SetTime`
  girişte ve hazır bildiriminde gönderilir.
- **Sınırlar (Phase 9'da güncellendi):** dünya saati artık her tick ilerliyor
  ve 10 saniyede bir yayınlanıyor; yarıçap büyütme yeni pencereyi hemen
  dolduruyor. **İstemci tarafında chunk boşaltma yok**
  (`NetworkChunkPublisherUpdate` uygulanmadı); sunucu yalnızca kendi
  defterini `evictOutside()` ile sınırlar. Gerçek istemci kabulü **sınanmadı**.

## Oyuncu öznitelikleri ve tick döngüsü (Phase 9)

- **İki yeni paket:** `UpdateAttributes` (29, S→C), `UpdateAbilities`
  (187, S→C). Registry **39 → 41** paket.
- **`UpdateAttributes` tel biçimi:** runtime ID varuint64, varuint32 öznitelik
  sayısı, her öznitelik Min/Max/Current/DefaultMin/DefaultMax/Default
  (float32) + ad + varuint32 modifier sayısı, sonra `Tick` varuint64.
  `AttributeModifier`: Id, Name, Amount, Operation (int32), Operand (int32),
  Serializable. Sıra gophertunnel `protocol.Attribute.Marshal` ile doğrulandı.
- **`UpdateAbilities` tel biçimi:** `SerializedAbilitiesData` — int64
  uniqueId, int8 oyuncu izni, uint8 komut izni, **varuint32** katman sayısı ve
  katmanlar (uint16 tür, uint32 set, uint32 değerler, üç float32 hız).
  Hayatta kalma katmanı: izin bitleri **63**, hız değerleri **548864**.
- **Açlık zinciri:** yalnızca **hareket eden** oyuncuda tükenme artar
  (`0.01`/tick); 4.0 tükenme → 1 açlık puanı; doygunluk önce harcanır; açlık
  negatife düşmez. **Açlıktan ölüm hasarı Phase 10.1'de eklendi.**
- **`GameplayTicker`:** dünya saatini ilerletir, 200 tick'te bir `SetTime`
  yayınlar, süresi dolan eşyaları siler, düşen eşyaları simüle eder.
  Ağdan bağımsızdır; birim testleri soket açmaz.
- **Eşya fiziği:** yerçekimi `0.04`, sönüm `0.98`; eşya ilk katı bloğun
  üstüne oturur, blok içine doğarsa yukarı itilir. Hareket eden eşya için
  `MoveActorDelta` yayınlanır; dinlenince paket üretilmez.
- **Zamanlayıcı:** `Scheduler` enjekte edilirse `ConnectionPipeline` kendini
  `gameplay.pipeline-tick` periyodik görevi olarak kaydeder (1 tick).
- **Sınırlar:** `Interact` hâlâ yalnızca günlüğe yazılır; `ActorEvent` /
  `LevelSoundEvent` oynanışa bağlanmadı; yatay eşya çarpışması, sıvı ve
  yuvarlanma yok. Gerçek istemci kabulü **sınanmadı**.

## Hayatta kalma sonuçları (Phase 10.1)

- **Açlıktan hasar:** açlık 0 iken her **80 tick'te** (4 sn) 1 hasar; oyuncu
  ölebilir. Java'nın zorluk tabanlı "1 cana kadar" sınırı bilinçli olarak
  uygulanmaz — sunucuda zorluk kavramı yok.
- **Doğal yenilenme:** açlık **≥ 18** ve can dolu değilken her **80 tick'te**
  1 can. Yenilenme hızı doygunluğa göre değişmez.
- **Zamanlayıcılar oyuncu başınadır ve yalnızca spawn edilmiş oyuncuda
  işler:** koşul bozulunca sıfırlanır; "79 tick biriktirip bir lokma yiyerek
  hemen iyileşme" mümkün değildir. Ölü oyuncu hareket etse bile açlık
  harcamaz.
- **Yayın:** sağlık yalnızca ilgili oyuncuya `SetHealth` (42) ile gider.
  Yeni paket eklenmedi; registry **41** kalır.
- **Sınırlar:** envanterden yemek tüketimi Phase 10.2'de eklendi; ölümde
  `ActorEvent(Death)` yayınlanmıyor; gerçek istemci kabulü **sınanmadı**.

## Yemek tüketimi (Phase 10.2)

- **İki giriş yolu, tek yönlendirici:** `PlayerAuthInput` bit 32
  (`PERFORM_ITEM_INTERACTION`) ve `InventoryTransaction` paketindeki
  `ItemUseInventoryTransaction`. `Action Type = Use` yemek tüketimine,
  `Place` blok yerleştirmeye gider.
- **`FoodTable`:** 37 yenilebilir eşya için açlık (1..20) ve doygunluk
  (0..20) değeri. Bilinmeyen eşya `null` — "sıfır besin" uydurulmaz.
- **Sunucu yetkilidir:** yığın `inventory()->getSlot()` ile okunur, istemcinin
  `item` tanımlayıcısına güvenilmez.
- **Kurallar:** yalnızca seçili hotbar yuvası + ana el; `hunger < 20`;
  `Player::canInteract()` (ölü/`Joining` yiyemez); oturum başına **1600 ms**
  soğuma; eşya başına 1 adet düşer.
- **Ağ kimliği zorunlu:** `ItemRegistry` kaydı yoksa tüketim reddedilir ve
  `warning` loglanır (aksi hâlde `InventorySlot` bildirimi gönderilemez).
- **Bildirim:** `InventorySlot` (konteyner 0) + `UpdateAttributes` aynı anda
  gönderilir; açlık çubuğu bir sonraki tick'i beklemez.
- **Sınırlar:** `ItemStackRequest` (147) Phase 10.3'te bağlandı; yemek yeme
  yolu hâlâ gerçek istemcide **sınanmış sayılmaz**. Zehir/açlık efekti,
  kase/şişe bırakma ve yaratıcı mod davranışı yoktur. Canlı sunucuda
  `ItemRegistry` önyüklemesi olmadığı için yemek kimlikleri yalnızca envanter
  içeriğiyle var olur.

## Envanter yığın istekleri (Phase 10.3)

- **İki paket:** `ItemStackRequest` (147, C→S) ve `ItemStackResponse` (148,
  S→C). Registry **41 → 43** paket. `PlayerAuthInput` bit 34 alanı da artık
  çözülür.
- **Sunucu yetkilidir:** istemcinin yığın durumu yok sayılır; kaynak ve hedef
  yuvalar sunucudaki envanterden okunur, doğrulama **kopya** üzerinde yapılır.
  Herhangi bir hata envanteri **ve** imleci hiç değiştirmez.
- **Kapsam:** yalnızca `Take` / `Place` / `Swap`; yalnızca
  `CombinedHotbarAndInventory` (12) yuva 0-35 ve `Cursor` (59) yuva 0. Diğer
  konteynerler `InvalidSourceContainer`, diğer aksiyonlar
  `ActionRequestNotAllowed` ile reddedilir.
- **Ağ kimliği doğrulanmaz:** sunucu yığın net id'si dağıtmıyor, bu yüzden
  istemcinin gönderdiği `netId` yok sayılır (tüm slot bilgileri `netId = null`).
- **İmleç kalıcı değildir:** bağlantı kapanırken `release()` imleçteki eşyayı
  envantere geri yazar (önce birleştir, sonra boş yuva); sığmayan miktar
  `warning` ile loglanır.
- **Sınırlar:** yaratıcı mod, zırh/offhand ve sandık konteynerleri kapsam
  dışı; `Drop`/`Destroy`/`Consume`/`Craft*` uygulanmaz. İmleçteki eşya
  envanter tamamen doluysa yere düşmez, loglanır. Gerçek istemci kabulü
  **sınanmadı**.

## Eşya kayıt defteri, geri bildirim ve deri (Phase 10.4)

- **`ItemRegistryPacket` (162):** 1934 gerçek eşya gönderilir
  (`resources/item-registry.json`, pmmp/BedrockData'dan üretildi; çalışma
  zamanı kimlikleri **-1125..846**). Registry **43 → 44** paket. Katılım
  sırası: `StartGame` → `ItemRegistry` → `LevelChunk`.
- **Tel normalizasyonu (bilinçli):** her girişte `componentBased = false`,
  `version = 2` ve boş NBT yazılır. Sunucu vanilla bileşen NBT'si
  üretemediği için `true` göndermek istemcide boş eşya tanımına yol açardı;
  `false` istemciyi yerleşik vanilla tanımlarına düşürür. Ham kaynak
  değerleri `ItemTable::all()` ile erişilebilir.
- **Oynanış geri bildirimi:** `ActorEvent` + `LevelSoundEvent` artık gerçekten
  gidiyor — hasar (`Hurt` + `hurt`), ölüm (`Death` + `death`, `Hurt`
  gönderilmez: istemcide titreme yapar), blok kırma/yerleştirme
  (`break`/`place`, `UpdateBlock`'tan **sonra**), eşya toplama (`pickup`) ve
  yemek yeme (`burp`).
- **Gerçek deri:** bağımlılıksız saf PHP `PngDecoder` (8 bit, renk tipi
  0/2/4/6, filtre 0-4, tüm chunk uzunlukları ve CRC'ler doğrulanır) → ham
  RGBA → `SerializedSkin`. Paletli/interlaced/16-bit PNG reddedilir. Çözüm
  başarısız olursa **oyuncu atılmaz**, `blank()` döner ve uyarı loglanır.
- **Oyun modu / yetenek geçişi:** `GameMode` (Survival 0 / Creative 1 /
  Adventure 2 / Spectator 6). `ConnectionPipeline::applyGameMode()`,
  `setFlying()` ve `refreshAbilities()` `UpdateAbilities` yeniden yayınlar;
  uçuşa izin vermeyen moda geçişte uçuş kapanır. `AddPlayer` gerçek oyun
  modunu ve yetenek katmanını taşır.
- **Sınırlar:** gerçek istemci kabulü **sınanmadı**. `Entity Data` hâlâ boş
  (isim etiketi yok), `NetworkChunkPublisherUpdate` yok, blok kırma menzili
  sunucuda doğrulanmıyor.

## `/reload` hakkında dürüst not

`/reload` **kaynak kodu yenilemez**. PHP, çalışan bir süreçte yüklenmiş
sınıfları değiştiremez. Reload yalnızca yapılandırma, veri ve kayıtları
yeniler. Kod değişikliği için sunucunun yeniden başlatılması gerekir.

Ayrıntı: [docs/adr/0003-reload-limits.md](docs/adr/0003-reload-limits.md)

## Mimari kararlar

- [ADR-0001 — NetherNet taşıyıcı seçimi](docs/adr/0001-transport-choice.md)
- [ADR-0002 — PHP sürümü ve araç zinciri](docs/adr/0002-php-version.md)
- [ADR-0003 — Reload'ın dürüst sınırları](docs/adr/0003-reload-limits.md)

Özet: oyun motoru **PHP**; taşıyıcı **Rust NetherNet sidecar**. İkisi yerel,
kimlik doğrulamalı bir IPC köprüsüyle konuşur.

## Dizin yapısı

| Klasör | Açıklama |
|---|---|
| `src/PickaxeMP/kernel` | Çekirdek, modül yaşam döngüsü, reload |
| `src/PickaxeMP/utils` | Config, ServerConfig, Logger |
| `src/PickaxeMP/scheduler` | Tick tabanlı görevler |
| `src/PickaxeMP/transport` | Taşıyıcı sözleşmeleri + sidecar köprüsü |
| `src/PickaxeMP/protocol` | Bedrock protokol motoru (paket, binary, sıkıştırma) |
| `src/PickaxeMP/exception` | İstisna sınıfları |
| `src/PickaxeMP/network` | Eski yer tutucu (protokol artık `protocol/` altında) |
| `src/PickaxeMP/world` | Dünya, blok kayıt defteri, chunk (Phase 4/7) |
| `src/PickaxeMP/player` | Oyuncu, hareket, envanter, oynanış döngüsü (Phase 5–7) |
| `src/PickaxeMP/item`, `src/PickaxeMP/inventory` | Eşya ve envanter motoru (Phase 6) |
| `src/PickaxeMP/entity` | Varlık yöneticisi/izleyicisi (Phase 6) |
| `src/PickaxeMP/connection` | Bağlantı hattı, yayın (`PacketBroadcaster`) |
| `src/PickaxeMP/plugin` | Eklentiler (Phase 8) |
| `transport/pickaxe-transport` | Rust NetherNet sidecar |
| `tests/unit`, `tests/integration` | PHPUnit testleri |
| `tools/` | Proje düzeyi PHP/Composer/lint araçları |
| `docs/` | Durum dosyası, ADR'ler, runbook'lar |
| `plugins/`, `resources/` | Eklenti ve varsayılan ayar dizinleri |

## Katkı

Bkz. [CONTRIBUTING.md](CONTRIBUTING.md). PSR-12, `declare(strict_types=1)`,
PSR-4.

## Lisans

`LICENSE` dosyası henüz **kesin bir lisans metni içermiyor**. Lisans seçilene
kadar bu projeyi dağıtılmış/açık kaynak olarak kabul etmeyin.

> PickaxeMP, Mojang AB veya Microsoft ile bağlantılı değildir.
