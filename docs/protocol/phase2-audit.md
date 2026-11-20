# Phase 2 denetimi — açık noktaların kapatılması

Bu belge, Phase 2 raporunda "doğrulanmamış" olarak bırakılan 6 konunun
durumunu **kanıt, karar ve testle** kaydeder.

Hedef sürüm: **1.26.65 / protocol 2225** (Mojang `v1.26.65` etiketi).

---

## 1. `ResourcePackClientResponse` birleşim kodlaması

**Durum: KARAR VERİLDİ, gerçek istemci doğrulaması BEKLİYOR**

**Kanıt (Mojang şeması, v1.26.65):**

```json
"Response": {
  "oneOf": [ {"$ref": "…Cancel"}, {"$ref": "…Downloading"}, … ],
  "x-control-value-type": "uint32",
  "x-ordinal-index": 0
}
```

Alt yapıların her birinde `Response Type` alanı `int8` + `const` olarak
işaretli ve `x-ordinal-index: 0`. Cereal'ın bu iki işareti **bir mi iki mi**
kez yazdığı resmî belgede açık değildir.

**Karar:** tek bir `uint32` kontrol değeri yazılır/okunur. Gerekçe:
`x-control-value-type` üst düzeyde açıkça `uint32` der ve Bedrock'ın
tarihsel davranışı tek bir ayırıcı (discriminant) yazmaktır.

**Test:** `PacketCodecTest::testResourcePackClientResponseRoundTrip` ve
`testResourcePackClientResponseDownloadingCarriesList`.

**Kalan risk:** Gerçek istemci bu paketi gönderdiğinde bayt sayısı
doğrulanmalı. Yanlışsa bağlantı "resource pack" aşamasında takılır — bu,
Phase 4'te gözlemlenebilir bir belirti olur.

---

## 2. `Experiments.Toggles` sayımı

**Durum: KARAR VERİLDİ, gerçek istemci doğrulaması BEKLİYOR**

**Kanıt (Mojang şeması):**

```json
"Toggles": {
  "type": "array",
  "items": {"$ref": "…ExperimentToggle"},
  "x-serialization-options": ["No size compression"],
  "x-ordinal-index": 0
}
```

`"No size compression"`, Mojang "Primitives" belgesinde şöyle tanımlı:

> Writes a collection size without the normal variable-length size encoding.

**Karar:** sayım **sabit genişlikli `uint32`** (little-endian) olarak
yazılır. Cereal'ın varsayılan sabit genişliği `uint32`'dir; şema başka bir
genişlik belirtmiyor.

**Test:** `PacketCodecTest::testResourcePackStackWithInstance`.

**Kalan risk:** Cereal `uint16` kullanıyorsa paket 2 bayt kayar. Pratikte
`Toggles` neredeyse her zaman boş olduğu için etki alanı dardır (boş dizi
= yalnızca sayım baytları).

---

## 3. NetherNet payload zarfı: `raw` mı `0xFE` mi?

**Durum: KARAR VERİLDİ (kanıt güçlü), gerçek istemci doğrulaması BEKLİYOR**

**Kanıtlar:**

1. **Mojang resmî NetherNet rehberi** (§6.1 "NetherNet Data Framing")
   yalnızca **1 baytlık** bir çerçeve başlığı tanımlar:
   - `0` → tam mesaj veya son parça
   - `N > 0` → ardından N parça daha var
   Bu başlık `nethernet-tokio` tarafından zaten işlenir; PHP'ye kalan
   **uygulama yüküdür**.

2. Rehberde `0xFE`, "batch" veya "game packet wrapper" ifadesi **hiç
   geçmez**. "Game data" bölümü yalnızca Bedrock paket dokümanına atıf yapar.

3. `df-mc/go-nethernet` belgesi: *"Packets themselves are encoded the same
   as they would be over RakNet, except: **Packets are not batched**; they
   are sent individually."*

**Karar:** varsayılan strateji **`raw`** — `0xFE` zarfı yok. Her
DataChannel mesajı bir Bedrock paketi taşır.

**Kod:** `PacketBatch` iki strateji sunar; `raw` varsayılandır.
`raknet` yalnızca karşılaştırma/teşhis için açıkça istenebilir.

**Test:** `PacketBatchTest` her iki stratejiyi de kapsar.

**Kalan risk:** "Not batched" ifadesi bir topluluk gözlemidir; Mojang
bunu doğrudan yazmamıştır. Gerçek istemci ilk paketi gönderdiğinde
`0xFE` görülürse `--batch-strategy=raknet` ile geçiş yapılabilir (kod
hazır). Bu **tek satırlık** bir yapılandırma değişikliğidir.

---

## 4. Sıkıştırma eşiğinin ölçüm birimi

**Durum: KARAR VERİLDİ**

**Kanıt (Mojang şeması, `NetworkSettingsPacket`):**

> `Compression Threshold`: "Determines the smallest size of **raw network
> payload** to compress. NOTE: 0 is disable compression, 1 is compress
> everything 1 byte or larger (so everything)"

**Karar:** "raw network payload" = zarf içindeki **sıkıştırılmamış yük**,
yani **paket kimliği dahil** tam paket. `PacketBatch::wrap()` sıkıştırma
kararını `strlen($payload)` üzerinden verir ve `$payload` zaten kimlik
dahil kodlanmış pakettir.

**Test:** `CompressionTest::testThresholdIsInclusive`,
`testManagerCompressesAboveThreshold`.

**Kalan risk:** Eşik sınırında (tam N bayt) 1 baytlık sapma istemci
tarafından tolere edilir; Bedrock istemcisi sıkıştırılmamış yükü de
kabul eder (sıkıştırma zorunlu değildir).

---

## 5. UUID bayt sırası

**Durum: KARAR VERİLDİ**

**Kanıt (Mojang şeması, `mce::UUID` + "Primitives"):**

```json
"Most Significant Bits":  {"x-underlying-type": "uint64", "x-ordinal-index": 0},
"Least Significant Bits": {"x-underlying-type": "uint64", "x-ordinal-index": 1}
```

"Primitives" belgesi `uint64` için: *"Raw unsigned integer bytes in
**native byte order**."* Native = little-endian (belgedeki tüm örnekler
LE'dir; örn. `uint16 300 → 2C 01`).

**Karar:** `MSB (uint64 LE)` + `LSB (uint64 LE)`, toplam 16 bayt.

**Test:** `BinaryRoundTripTest::testUuidRoundTrip` (gerçek UUID metniyle
round-trip), `testUuidEmptyIsZero`, `BinaryFuzzTest::testUuidRoundTripOnRandomValues`.

**Kalan risk:** Dünya/entity paketlerinde UUID kullanıldığında gerçek
istemciyle sınanacak (Phase 4).

---

## 6. Snappy desteği

**Durum: KARAR VERİLDİ — desteklenmiyor, açık hata**

**Kanıt:**

- Mojang `PacketCompressionAlgorithm` (2225): `ZLib=0`, `Snappy=1`, `None=65535`.
- Bu PHP derlemesinde `snappy` eklentisi **yoktur**
  (`extension_loaded('snappy') === false`).

**Karar:**

1. Sunucu **her zaman `ZLib` önerir** (`NetworkSettings` içinde).
2. İstemci `Snappy` talep ederse `CompressionManager::negotiate()` bunu
   `ZLib`'e indirger — sunucu algoritmayı seçer.
3. Snappy ile sıkıştırma/çözme **çağrılırsa** `ProtocolException` fırlatılır
   (sessizce yanlış veri gönderilmez).

**Test:** `CompressionTest::testManagerRejectsSnappyWhenUnavailable`,
`testNegotiateFallsBackToZlibForUnsupportedAlgorithm`.

**Kalan risk:** Yok — davranış açık ve fail-closed.

---

## Özet tablo

| # | Konu | Durum | Gerçek istemci gerekli? |
|---|---|---|---|
| 1 | ResourcePackClientResponse | Karar verildi (uint32) | Evet, doğrulama |
| 2 | Experiments.Toggles sayımı | Karar verildi (uint32) | Evet, doğrulama |
| 3 | NetherNet zarfı | Karar verildi (`raw`) | Evet, doğrulama |
| 4 | Sıkıştırma eşiği | Karar verildi (kimlik dahil) | Hayır |
| 5 | UUID bayt sırası | Karar verildi (LE) | Phase 4'te |
| 6 | Snappy | Karar verildi (desteklenmiyor) | Hayır |

**Hiçbiri "tam doğrulanmış" sayılmadı.** Phase 2 bu nedenle gerçek istemci
testi yapılmadan kapanmış ilan edilmez; ancak hiçbir konu **belirsiz
bırakılmadı** — her biri için kanıta dayalı bir karar ve test vardır.
