# Phase 7.1 — Oyuncu Listesi ve Olay Paketleri

Durum: **tamamlandı** (2026-11-19). Protokol **2225 / Bedrock 1.26.65**.
`CLIENT_VERIFIED`: **BLOCKED** (gerçek istemci yok).

## 1. Kapsam

Phase 7'nin ertelediği dört paket:

| ID | Paket | Yön | Kullanım |
|---|---|---|---|
| 27 | `ActorEvent` | S→C | Varlık olayları (ölüm/hasar/animasyon) |
| 63 | `PlayerList` | S→C | Sekme listesi ekle/sil |
| 111 | `MoveActorDelta` | S→C | Bileşen bazlı delta hareket |
| 123 | `LevelSoundEvent` | S→C | Seviye ses olayları |

Registry 29 → **33** paket.

## 2. Tel biçimi — doğrulanmış gerçekler

Kaynak: `gophertunnel` (`writer.go`, `skin.go`, `player.go`, `io.go`) ve
yerel `docs/protocol/schemas/v1.26.65/` aynası. **Üç taslak hatası bu adımda
bulundu**; aşağıdaki tablo "doğru" sütunudur.

| Alan | Kural |
|---|---|
| `Color` | `BEARGB`: `int32 = A \| R<<8 \| G<<16 \| B<<24`, big-endian. Alfa **düşük** bayt. `white() = -1`, `black() = 255`. Tel sırası MSB→LSB: `B,G,R,A`. |
| `Optional<T>` | `Bool` varlık baytı + değer (gophertunnel `OptionalFunc`). `MoveActorDelta`'nın her bileşeni bunu kullanır. |
| `PlayerListEntry` | `Varuint32 variant` (Add=1/Remove=0) **ve** eski `Uint8` action (Add=0/Remove=1) birlikte yazılır. Remove buradan sonra biter (UUID sonrası). |
| Add sırası | UUID → `ActorUniqueID` (zigzag varint64) → Username → XUID → PlayFabID → PlatformChatID → `Int32` BuildPlatform (sabit LE) → `Skin` → Teacher/Host/SubClient bool → `BEARGB` PlayerColour |
| `SerializedSkin` | v2225 **ordinal** sırası geçerlidir. gophertunnel `Skin.Marshal` **eski** protokoldür (GeometryData/ArmSize yok) — referans alınmadı. |
| `TrustedSkinFlag` | **string**: `"Unset"` / `"True"` / `"False"` (enum değil). |
| `PersonaPiece` | PieceID string, PieceType **sabit Uint32**, PacketID UUID, Default bool, ProductID string. |
| `PersonaPieceTintColour` | **string** pieceType (örn. `"hands"`) + 4 × `BEARGB`. **İç sayım yok.** |
| `ByteFloat(x)` | `byte(x / (360/256))` — döndürme paketleme. |
| `Slice` | varuint32 sayı; `SliceUint32Length` sabit uint32 sayı. |
| `LevelSoundEvent.Actor Unique Id` | **sabit int64** (zigzag değil). |
| `ActorEvent.Fire At Position` | yalnızca `FireworksExplode` olayında yazılır (`carriesFirePosition()`), kodlayıcı/çözücü simetrik. |

## 3. Uygulama

### 3.1 Değer tipleri (`protocol/packet/types`)

`Color`, `TintMapColor`, `PieceTintColor`, `SkinImage`, `ArmSize`,
`PieceType`, `TrustedSkinFlag`, `AnimatedImageData`, `AnimationExpression`,
`SerializedPersonaPieceHandle`, `SerializedSkin`, `ActorEventType`,
`PlayerListEntry`, `MoveActorDeltaData`.

### 3.2 Paketler (`protocol/packet/packets`)

`ActorEventPacket`, `PlayerListPacket`, `MoveActorDeltaPacket`,
`LevelSoundEventPacket`. `PacketRegistry::withSupportedPackets()` dördünü de
kaydeder. `PacketId` sabitleri + `name()` girdileri eklendi.

### 3.3 Kablolama (`player`, `connection`)

- `PlayerSnapshot::playerListEntry/add/remove` — `Actor Unique ID` olarak
  **`entityId`** (kalıcı kimlik), `runtimeId` değil.
- `ConnectionPipeline::announceSpawn()`
  1. Yeni oyuncuya **tek** `PlayerList` (kendi girişi + diğerleri).
  2. Diğerlerine yeni oyuncunun tek girişi.
  3. `AddPlayer` çift yönlü.
- `ConnectionPipeline::onConnectionClosed()`
  `PlayerList` silmesi → `RemoveActor` → envanter kaydı → indeksten düşürme.

Sıra testle zorlanır (`indexOf()` yardımcısı ile).

## 4. Testler

`tests/unit/protocol/PlayerListPacketTest.php` — **29 test / 106 assertion**:

- Paket kimlikleri ve isimleri; registry 33 ve yönler.
- `Color` altın baytlar (`56341278`), `white`/`black`, `toRgba()`.
- `ActorEvent` altın baytlar, gidiş-dönüş, `Fire At Position` simetrisi.
- `LevelSoundEvent` altın baytlar ve `Actor Unique Id`'nin **sabit int64**
  olduğunun kanıtı.
- `MoveActorDelta` altın baytlar, yok-olan bileşen maliyeti (12 bayt),
  `packRotation` sarma.
- `TintMapColor` 4 renk zorunluluğu, uzunluk öneki olmaması.
- `SerializedSkin::blank()` altın baytlar (60 bayt) + tam gidiş-dönüş.
- `PieceType` sabit uint32; `PieceTintColor` string anahtar.
- `PlayerList` add altın baytlar, remove minimal (19 bayt), variant+action
  birlikte yazımı, uyuşmazlık reddi, giriş sayısı sınırı (1001 → hata).

`tests/integration/ConnectionPipelineTest.php` — 21 test:
`testSecondJoinSendsPlayerListBeforeAddPlayer`,
`testDisconnectBroadcastsPlayerListRemoveBeforeRemoveActor` eklendi;
`completeJoin()` artık 3 spawn paketi bekliyor (2 + kendi liste girişi).

## 5. Kabul edilmiş sınırlar

1. **Deri gönderilmez** — `SerializedSkin::blank()`. JWT derisi base64 PNG;
   tel biçimi ham RGBA. PNG çözümü (GD/Imagick) bağımlılık kararı gerektirir.
   İstemcinin varsayılan deriyi çizmesi **beklenir, doğrulanmadı**.
2. `ActorEvent`/`LevelSoundEvent`/`MoveActorDelta` **kodlandı ama oynanışa
   bağlanmadı**; yayın Phase 8'de (chunk/entity akışı + ses geri bildirimi).
3. `FireAtPosition` varlık kuralı istemciyle doğrulanmadı.
4. `PlayerList` girişinde `BuildPlatform = 0`, `PlatformOnlineId = ''` —
   NetherNet taşıyıcısı bunları sağlamıyor.

## 6. Doğrulama

```
tools\composer.cmd lint            → 292 dosya, sözdizimi temiz
phpunit -c phpunit.xml             → OK (802 test, 8872 assertion)
graphify update .                  → 4038 node, 10980 edge, 175 community
```
