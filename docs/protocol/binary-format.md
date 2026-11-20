# İkili biçim (binary format)

PickaxeMP'nin ikili kodlama kuralları. Kaynak: Mojang
[Primitives](https://mojang.github.io/bedrock-protocol-docs/latest/primitives/)
belgesi.

## Endian kuralı

**Varsayılan little-endian.** Şemada bir alan `"x-serialization-options":
["Big Endian"]` ile işaretlenmişse büyük-endian kullanılır.

Doğrulama örnekleri (Mojang belgesinden):

| Değer | Tür | Baytlar |
|---|---|---|
| 300 | uint16 | `2C 01` (LE) |
| 3.5 | float | `00 00 60 40` (LE) |
| 64 | varint32 | `80 01` (ZigZag) |
| 255 | varuint32 | `FF 01` |
| "Hi" | string | `02 48 69` |

## Tam sayılar

| Tür | Kodlama | Sınır |
|---|---|---|
| `bool` | 1 bayt (0/1); okuyucu sıfır olmayanı `true` sayar | — |
| `int8` | ikiye tümleyen | −128..127 |
| `uint8` | ham | 0..255 |
| `int16` | 2 bayt LE | −32768..32767 |
| `uint16` | 2 bayt LE | 0..65535 |
| `int32` | 4 bayt LE | ±2^31 |
| `uint32` | 4 bayt LE | 0..2^32−1 |
| `int64` | 8 bayt LE | ±2^63 |
| `uint64` | 8 bayt LE | bit deseni |

`*BigEndian` metotları aynı değerleri büyük-endian yazar/okur. Şemada
`Big Endian` işaretli alanlar: `RequestNetworkSettings.clientNetworkVersion`,
`Login.clientNetworkVersion`, `PlayStatus.status`.

## Değişken uzunluklu tam sayılar

| Tür | Kodlama | Maks. bayt |
|---|---|---|
| `varint32` | **ZigZag** + base-128 | 5 |
| `varint64` | ZigZag + base-128 | 10 |
| `varuint32` | base-128 (ZigZag **yok**) | 5 |
| `varuint64` | base-128 (ZigZag yok) | 10 |

ZigZag: `(v << 1) ^ (v >> 31)` (32-bit) ve `(v << 1) ^ (v >> 63)` (64-bit).

Base-128: her bayt 7 bit veri + yüksek bit "devam" işareti.

**Kritik:** String uzunluk öneki `varuint32`'dir (ZigZag'sız). ZigZag
kullanmak uzunlukları bozar (0..127 → 0..254 çift sayılar).

## String

```
varuint32 uzunluk (bayt) | ham baytlar
```

Uzunluk **bayt** cinsindendir, karakter değil. UTF-8 doğrulaması varsayılan
olarak yapılmaz (Mojang: "stream does not transcode or validate"); PickaxeMP
`readUtf8String()` ile isteğe bağlı doğrulama sunar.

Sınırlar: `BinaryReader::DEFAULT_MAX_STRING` = 4 MiB; per-okuma sınırı
verilebilir.

## UUID

`mce::UUID` = 128 bit, 16 bayt:

```
MSB (uint64 LE) | LSB (uint64 LE)
```

Metin biçimi kanonik `8-4-4-4-12`.

## Opsiyonel alan

```
bool varlık baytı | değer (varsa)
```

## Paket çerçevesi

Paket düzeyi:

```
varuint32 paket kimliği | paket gövdesi
```

Zarf (batch) katmanı ayrıdır ve **doğrulanmamıştır** — bkz.
`docs/protocol/1.26.65.md` §Doğrulanmamış noktalar.

## Güvenlik sınırları

- Her okuma sınır denetimli; eksik veri `ProtocolException`.
- Negatif uzunluk reddedilir.
- Aşırı uzun string/dizi reddedilir.
- VarInt en fazla 5, VarLong en fazla 10 bayt.
- zlib açma çıktısı sınırlı (varsayılan 8 MiB) — decompression bomb koruması.
- Kaynak tampon asla değiştirilmez.
