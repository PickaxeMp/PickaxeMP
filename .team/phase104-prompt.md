# Phase 10.4 incelemesi — PickaxeMP (PHP 8.2, Bedrock 1.26.65 / protokol 2225)

Sen kıdemli bir Bedrock protokol + PHP uzmanısın. Aşağıdaki değişiklikleri
**acımasızca** incele. Övgü yazma; yalnızca gerçek hata, protokol uyumsuzluğu,
güvenlik açığı ve bakım riski ara.

## Kapsam

### 1. `ItemRegistryPacket` (162) + `ItemTable`
- `src/PickaxeMP/protocol/packet/packets/ItemRegistryPacket.php`
- `src/PickaxeMP/protocol/packet/types/ItemEntry.php`
- `src/PickaxeMP/item/ItemTable.php`
- `resources/item-registry.json` (1934 giriş, pmmp/BedrockData'dan üretildi)

Kritik kararlar:
- `ItemEntry` tel biçimi: `Name` (string), `Runtime ID` (**signed int16 LE**),
  `Component Based` (bool), `Version` (**varint32, ZigZag**), `NBT` (ham bayt,
  boş bileşik `0A 00 00`).
- Tel üzerinde **her** giriş `componentBased = false`, `version = 2`
  (`ItemEntryVersionNone`) gönderilir. Gerekçe: sunucu vanilla bileşen NBT'si
  üretemiyor; `true` göndermek istemcide boş eşya tanımına yol açar.
- `minecraft:air` (runtime id **-158**) bellek içi registry'den **çıkarılır**
  (`bootstrap()` negatif kimlikleri atlar, çünkü `ItemRegistry::register()`
  negatifi reddeder) ama 162 paketinde **gönderilir**.
- `ItemRegistryPacket::MAX_ENTRIES = 8192`, paket tavanı 512 KiB.
- Katılım sırası: `StartGame` → `ItemRegistry` → `LevelChunk`.

Sorulacaklar:
1. `Version` alanı gerçekten ZigZag varint32 mi, yoksa düz varuint32 mi?
   gophertunnel `protocol/item_registry.go` ile karşılaştır.
2. `componentBased = false` + `version = 2` kombinasyonu istemciyi gerçekten
   vanilla tanımlarına düşürür mü? `version = 0` daha mı doğru?
3. `Runtime ID` işaretli int16 mı olmalı? 846 ve -1125 bu aralığa sığıyor ama
   gelecekte taşma riski var mı?
4. 1934 giriş × ~43 bayt ≈ 83 KiB tek pakette sığar mı, istemci sınırı var mı?
5. `air`'i registry'den çıkarmak (ama pakette göndermek) tutarlı mı? Bir eşya
   `minecraft:air` ile karşılaştırıldığında ne olur?

### 2. Oynanış geri bildirimi (`ActorEvent` + `LevelSoundEvent`)
- `src/PickaxeMP/player/HealthManager.php` — hasar/ölüm sesleri
- `src/PickaxeMP/player/BlockSound.php` — `break`/`place`
- `src/PickaxeMP/connection/ConnectionPipeline.php::tick()` — ölüm yayını
- `src/PickaxeMP/player/PlayerAuthInput.php` — blok sesi + `pickup`

Kritik kararlar:
- Ölümde **yalnızca** `ActorEvent(Death)` + `LevelSoundEvent('death')`;
  `Hurt` gönderilmez (istemcide titreme). İkisi de `isGlobal: true`,
  `actorIdentifier: 'minecraft:player'`.
- Ölümcül olmayan hasarda `ActorEvent(Hurt)` + `'hurt'`.
- Blok sesleri `isGlobal: false`, konum blok merkezi `(x+0.5, y+0.5, z+0.5)`,
  `actorIdentifier` = blok adı, `UpdateBlock`'tan **sonra**.
- `pickup` sesi `actorUniqueId` = oyuncunun `entityId`, konum eşyanın son
  konumu; toplayana `send`, diğerlerine `broadcast`.
- `LevelSoundEventPacket` bu alan için sabit `writeInt64`, `data` için
  `writeVarInt` (ZigZag).

Sorulacaklar:
1. `LevelSoundEventPacket` alan sırası ve tipleri doğru mu? Özellikle
   `actorUniqueId` **varint64** mi yoksa sabit int64 mü? `data` alanı ZigZag
   varint mi?
2. `break`/`place` doğru ses kimlikleri mi? gophertunnel
   `protocol/level_sound_event.go` listesinde `break` var mı, `break.block` mı
   olmalı?
3. Ölümde `Hurt` göndermemek doğru mu? Vanilla ne yapıyor?
4. Blok sesini `UpdateBlock`'tan sonra göndermek doğru sıra mı?
5. `pickup` sesini **hem** toplayana hem diğerlerine göndermek çift ses
   üretir mi? Vanilla yalnızca `broadcast` mi yapıyor?
6. Ölüm yayını `tick()` içinde her tick tekrar eder mi (aynı oyuncu için
   `deaths` listesi temizleniyor mu)?

### 3. Gerçek deri gönderimi (saf PHP PNG çözücü)
- `src/PickaxeMP/auth/skin/PngDecoder.php`
- `src/PickaxeMP/auth/skin/PngImage.php`
- `src/PickaxeMP/auth/skin/SkinException.php`
- `src/PickaxeMP/auth/skin/SkinSerializer.php`

Kapsam: 8 bit derinlik, renk tipi 0/2/4/6, filtre 0-4. Paletli (3),
interlaced (Adam7) ve 16-bit reddedilir. Tüm chunk uzunlukları ve CRC'ler
doğrulanır; çözülen veri tam olarak `height * (stride + 1)` olmalı; boyut
sınırı 4096; dosya sınırı 64 MiB. Hata `SkinException` fırlatır ve çağıran
`SerializedSkin::blank()`'e düşer (oyuncu **atılmaz**).

Sorulacaklar:
1. Filtre 1 (Sub), 3 (Average), 4 (Paeth) uygulamaları doğru mu? Özellikle
   Paeth'in `bpp` ile çarpımı ve `left`/`up`/`upLeft` seçimi.
2. CRC doğrulaması gerçekten tüm chunk'ları kapsıyor mu? `IDAT` çoklu chunk
   birleştirmesi doğru mu?
3. `/v1/join`'den gelen base64 PNG **güvenilmeyen** girdi mi? ZIP bombası
   benzeri (`IDAT` çok büyük, çözülen veri küçük) saldırı engelleniyor mu?
   Bellek tüketimi sınırlı mı?
4. `SkinImage` ham RGBA bekliyor; `SerializedSkin` içindeki `SkinImage`
   uzunluğu `width * height * 4` ile doğrulanıyor mu?
5. `geometryData` base64 çözülüp mü gönderiliyor, yoksa base64 metni mi
   gönderilmeli? gophertunnel ne yapıyor?
6. `resourcePatch` üretimi (`{"geometry":{"default":...}}`) doğru mu?

### 4. Oyun modu / yetenek geçişi
- `src/PickaxeMP/player/GameMode.php`
- `src/PickaxeMP/player/PlayerAbilities.php`
- `src/PickaxeMP/connection/ConnectionPipeline.php::applyGameMode/setFlying/refreshAbilities`
- `src/PickaxeMP/player/PlayerSnapshot.php` (AddPlayer artık gerçek modu taşır)

Sorulacaklar:
1. `CREATIVE_ABILITIES` = `SURVIVAL_ABILITIES | MAY_FLY | INSTANT_BUILD`
   doğru mu? Vanilla yaratıcıda `ATTACK_PLAYERS`/`ATTACK_MOBS` var mı?
2. `ADVENTURE_ABILITIES` gerçekten `BUILD`/`MINE` içermiyor mu? Vanilla
   macera modunda `ATTACK_MOBS` var mı?
3. `SPECTATOR_ABILITIES` içinde `NO_CLIP` ve `FLYING` birlikte mi olmalı?
4. Uçuşa izin vermeyen moda geçişte uçuşu kapatmak doğru mu?
5. `abilityValues` (bayt olarak yetenek **değerleri**) moddan bağımsız sabit
   `SURVIVAL_VALUES` kalıyor — yaratıcıda yanlış mı?
6. `AddPlayer` içinde `playerGameType` alanı **varint** mi yoksa sabit int?
   Sıra doğru mu?

## Çıktı biçimi

Dosya: `.team/phase104-review.md`

```
# Phase 10.4 incelemesi

## Bulgular
| # | Ağırlık | Dosya | Bulgu | Öneri |
|---|---|---|---|---|
| 1 | kritik/orta/düşük | path:satır | ... | ... |

## Doğrulanamayanlar
- ...

## Onaylananlar (kısa)
- ...
```

**Ağırlık tanımı:** `kritik` = istemci bağlantıyı düşürür veya güvenlik açığı;
`orta` = yanlış davranış ama bağlantı ayakta; `düşük` = bakım/okunabilirlik.

En fazla 15 bulgu. Her bulgu **tek cümlelik** ve **somut** olsun; "iyileştirin"
gibi boş öneri yazma, tam ne yapılacağını yaz. Emin değilsen `Doğrulanamayanlar`
bölümüne taşı — uydurma.
