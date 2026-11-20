# Phase 4 — World Engine

Hedef: **gerçek Bedrock istemcisinin düz dünyayı görebilmesi**.
Protokol: 2225 / oyun sürümü 1.26.65.

---

## 1. Eklenen sistemler

```
src/PickaxeMP/nbt/
├─ NbtWriter.php            little-endian ağ NBT (blok hash girdisi)
└─ Fnv1a32.php              FNV-1a 32 (blok ağ kimliği)

src/PickaxeMP/world/
├─ BlockPosition.php
├─ WorldException.php
├─ WorldProperties.php      world.json modeli + doğrulama
├─ World.php                chunk tembel yükleme, kaydetme
├─ WorldManager.php         oluştur/yükle/kapat
├─ block/
│  ├─ BlockState.php        ad + özellik + ağ kimliği
│  ├─ BlockType.php         sertlik/katılık/opaklık
│  └─ BlockRegistry.php     6 blok (Phase 4 kapsamı)
├─ chunk/
│  ├─ ChunkPosition.php
│  ├─ SubChunk.php          4096 blok, X→Z→Y sırası
│  ├─ SubChunkSerializer.php  **v9** tel biçimi
│  └─ Chunk.php             24 subchunk (−4..19)
├─ generator/FlatGenerator.php
└─ storage/
   ├─ WorldStorageInterface.php
   └─ FileWorldStorage.php  atomik yazma + CRC32

src/PickaxeMP/protocol/packet/packets/
├─ StartGamePacket.php      25 alan, sıra kilitli
├─ LevelChunkPacket.php     8 alan
├─ SubChunkPacket.php       4 alan
└─ SubChunkPacketData.php   5 alan
```

---

## 2. Blok ağ kimliği — FNV-1a 32 (doğrulanmış)

`block-network-ids-are-hashes` **BDS varsayılanı `true`**. Mojang
`server.properties` belgesi:

> If true, the server will send hashed block network IDs instead of
> consecutive IDs starting at 0.

Hash girdisi: `{name, states}` compound'unun **little-endian NBT**'si,
anahtarlar sıralı. Algoritma **FNV-1a 32** (offset `0x811C9DC5`, prime
`0x01000193`).

Test vektörleri (`BlockStateTest::testFnv1aKnownVectors`):

| Girdi | Hash |
|---|---|
| `""` | `0x811C9DC5` |
| `"a"` | `0xE40C292C` |
| `"foobar"` | `0xBF9CF968` |

**Avantaj:** sürüm bağımsız; 2000+ blokluk runtime ID tablosu gerekmez.
Sunucu yalnızca kullandığı blokları tanımlar.

**Dürüst sınır:** algoritma doğrulanmıştır; üretilen **değerler** gerçek
istemciyle sınanmamıştır. İstemci hash'i kendi kayıt defterinde bulamazsa
blok "görünmez" olur (bağlantı düşmez) — bu, Phase 4 kapanışında
gözlemlenecek ilk belirtidir.

---

## 3. SubChunk tel biçimi — sürüm 9

minecraft.wiki: *"9 Version byte, storage-layer count, and signed one-byte
subchunk Y index"*.

```
version      uint8  = 9
layerCount   uint8  = 1
subChunkIndex int8          (dikey indeks)
header       uint8  = (bitsPerBlock << 1) | 1
paletteCount varuint32      (header == 0 ise yok)
palette[]    varuint32 × N
indices      uint32 × M     (bit-packed, LE)
```

`M = ceil(4096 / (32 / bitsPerBlock))`, `bitsPerBlock = ceil(log2(N))`.

**Doğrulanmış testler:**

- Tek blok → 2 giriş, 1 bit, 128 kelime (512 bayt indeks)
- 3 farklı blok + hava → round-trip
- 4096 rastgele blok (491 benzersiz) → round-trip ✅
- Boş subchunk → `bitsPerBlock = 0`

**Bulunan hata:** palet üst sınırım başta 256'ydı; 491 benzersiz blokla
gerçek bir veri kaybı riski vardı. **65536**'ya çıkarıldı (16 bit sınırı).

---

## 4. Dünya dikey aralığı

Mojang "SubChunk request system v1.18.10": *"World height changed from
0-255 to **-64 to +319**"*.

- `MIN_Y = -64`, `MAX_Y = 319` → 384 blok
- Subchunk indeksi `y >> 4` → **-4 .. 19**, toplam **24**

`Chunk::subChunkIndexFor(-64) === -4` testi bunu kilitler.

---

## 5. FlatGenerator

| Y | Blok |
|---|---|
| −64 | bedrock |
| −63 … −61 | stone |
| −60 … −59 | dirt |
| −58 | grass_block |
| −57 | (hava — spawn) |

Özellikler:

- **Deterministik:** aynı konum → aynı bloklar (test ile kanıtlı)
- **Seed bağımsız:** tüm seed'ler aynı düz arazi (bilinçli)
- **Üretilen chunk temiz** (`isDirty() === false`)
- **Sadece 1 non-empty subchunk** (indeks −4)

---

## 6. Depolama

```
worlds/<name>/world.json
worlds/<name>/chunks/<x>_<z>.chunk
```

Chunk dosyası:

```
"PKCH" | version(u16) | chunkX(i32) | chunkZ(i32) | count(u16)
  per subchunk: index(i8) | length(u32) | data
crc32(u32)
```

**Güvenli yazma sırası:** geçici dosya → `fflush` → `fsync` (varsa) →
hedefi `.bak`'a taşı → `rename` → `.bak` sil. Başarısızlıkta eski dosya
korunur.

**Testler:** CRC bozulması reddedilir, kesik dosya reddedilir, bozuk JSON
reddedilir, path traversal reddedilir, geçici dosya sızmaz.

---

## 7. Paketler (şemadan doğrulanmış)

| Paket | ID | Alan sayısı |
|---|---|---|
| StartGame | 11 | 25 |
| LevelChunk | 58 | 8 |
| SubChunk | 174 | 4 (+5 alt) |

`StartGame` alan sırası **test ile kilitlidir**
(`testStartGameFieldOrderIsLocked`): her alan farklı bir değere ayarlanır ve
round-trip sonrası hepsi tek tek doğrulanır.

### Dürüst notlar

| Alan | Durum |
|---|---|
| `Player Property Data` (17) | Şemada tip yok → **boş NBT** (`0A 00 00`) |
| `Server Configuration Join Info` (23) | 3 opsiyonel nesne → hepsi yok (`0x00 × 3`) |
| `SpawnSettings` (LevelSettings 1) | Alt şema yayımlanmamış → 4 × int32 varsayıldı |
| `Rule Data` | Boş liste (`count = 0`) — kurallar Phase 5+ |
| `Height Map Data` (SubChunk) | Boş (tür 0, 0 giriş) |
| `Block Properties` | Boş dizi |

Bu varsayımlar yanlışsa **istemci ayrıştırması kayar** ve bağlantı düşer.
Gerçek istemci testi bunları kesinleştirecektir.

---

## 8. Phase 3'ten devredilen eksikler (hâlâ açık)

| Eksik | Durum |
|---|---|
| ECDH anahtar değişimi | ❌ Yok |
| `ServerToClientHandshake` | ❌ Yok |
| `ClientToServerHandshake` | ❌ Yok |
| Şifreli oturum veri akışı | ❌ Yok |
| `extraData` skin JWT doğrulaması | ❌ Yok |
| `Xbox Live` canlı servis sorgusu | ❌ Bilinçli olarak yok |

**ÖNEMLİ:** `online-mode` ile bağlanan gerçek istemci `ServerToClientHandshake`
bekler. Bu olmadan istemci **bağlantıyı şifrelemez** ve modern istemciler
veri akışını reddedebilir. Phase 5'in ilk işi bu olmalıdır.

---

## 9. Oyuncu spawn akışı — HENÜZ BAĞLANMADI

`SessionAuthenticator` şu an kimlik doğrulamasından sonra
`PlayStatus + ResourcePacksInfo + ResourcePackStack` gönderir. `StartGame`
ve `LevelChunk` paketleri **yazıldı ve test edildi** ancak oturum akışına
**bağlanmadı**, çünkü:

1. Şifreleme el sıkışması (`ServerToClientHandshake`) yok.
2. Şifreleme olmadan `StartGame` göndermek gerçek istemcide çalışmaz.
3. Sahte bir "oyuncu dünyaya girdi" iddiası üretmek istemedim.

**Sıradaki adım:** Phase 5'te ECDH + şifreleme tamamlandıktan sonra
`StartGame` → `LevelChunk` zinciri `SessionAuthenticator`'a bağlanacak.

---

## 10. Test özeti

| Dosya | Test |
|---|---|
| `BlockStateTest` | 16 |
| `ChunkTest` | 23 |
| `FlatGeneratorTest` | 8 |
| `WorldPropertiesTest` | 6 |
| `WorldStorageTest` | 13 |
| `WorldPacketTest` | 14 |
| **Phase 4 toplam** | **80** |

Phase 4 sonunda: **477 test / 7672 assertion**. Güncel toplam için
`docs/DEVELOPMENT_STATE.md` §5'e bakın.
