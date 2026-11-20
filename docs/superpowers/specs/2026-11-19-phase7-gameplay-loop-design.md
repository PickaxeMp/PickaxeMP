# Phase 7 — Oynanış Döngüsü (tasarım)

Tarih: 2026-11-19 · Hedef protokol: **1.26.65 / 2225** · Durum: onaylandı
(kullanıcı normal geliştirme kararlarını devretti; aşağıdaki "benim kararım"
işaretli maddeler bu devir kapsamındadır).

## 1. Niyet ve neden

Phase 6 sonunda sunucu bir oyuncuyu dünyaya sokabiliyor, hareketini
işleyebiliyor ve envanterini kalıcı tutabiliyor — ama oyuncu **hiçbir şeyi
değiştiremiyor**: blok kıramıyor, blok koyamıyor, hasar alamıyor, ölmüyor,
başka oyuncuyu göremiyor. Phase 7 bu boşluğu kapatır: "gir, hareket et, blok
kır/koy, hasar al, öl, yeniden doğ" tamamlanır.

Başarı ölçütü (bitirme koşulu): gerçek Bedrock 1.26.65 istemcisi düz dünyaya
girer, hareket eder, bloğu kırar ve yerleştirir. Gerçek istemci yoksa
`CLIENT_VERIFIED` yine de `BLOCKED` kalır — kod ve testler bunu iddia etmez.

## 2. Hedefler

1. `Interact` (33), `PlayerAction` (36), `UpdateBlock` (21), `SetHealth` (42),
   `Respawn` (45) paketlerinin tel biçimi (Mojang v1.26.65 şeması).
2. `ItemUseInventoryTransaction` varyantının `InventoryTransaction` içinde
   çözülmesi — blok yerleştirmenin gerçek girdisi budur.
3. Dünya mutasyonu: `World::setBlock()` + `BlockRegistry::fromNetworkId()`.
4. Blok kırma/yerleştirme iş akışı: yetki, mutasyon, `UpdateBlock` yayını,
   envanter düşümü.
5. Yayın (broadcast) altyapısı: `PacketBroadcaster`.
6. Spawn/despawn yayını: `AddPlayer` (12) / `RemoveActor` (14).
7. Sağlık/ölüm/yeniden doğma: `SetHealth`, `Respawn`, sunucu tarafı sağlık.

## 3. Hedef olmayanlar (dürüst sınır)

> **Güncelleme (Phase 7.1 tamamlandı):** `PlayerList` (63), `ActorEvent` (27),
> `LevelSoundEvent` (123) ve `MoveActorDelta` (111) artık **kodlandı ve test
> edildi**; `PlayerList` spawn/ayrılma akışına bağlandı. Ayrıntı:
> `docs/superpowers/plans/2026-11-19-phase71-playerlist-events.md`. Deri
> `SerializedSkin::blank()` gönderilir (base64 PNG → RGBA çözümü yok).

| Ertelenen | Neden | Nereye |
|---|---|---|
| `PlayerList` (63, `SerializedSkin`) | Deri tel biçimi gerçek istemci olmadan doğrulanamaz; yanlış biçim oyuncu listesini bozar | ✅ Phase 7.1 |
| `ItemStackRequest` (147/148) çözümü | Phase 6.1 kapsamı; aksiyon alt tipleri çok geniş | Phase 6.1 |
| `ActorEvent` (27) | `Fire At Position` alanının koşullu varlığı şemada makine okunur değil; ölüm `SetHealth(0)` ile gösterilir | ✅ Phase 7.1 |
| `LevelSoundEvent` (123) / `LevelEvent` kırılma parçacıkları | Geri bildirim; oynanışı engellemez | ✅ Phase 7.1 (paket) / Phase 8 (yayın) |
| `MoveActorDelta` (111) | `MoveActorAbsolute` yeterli | ✅ Phase 7.1 (paket) / Phase 8 (yayın) |
| Açlık, nitelik, yetenek (`UpdateAbilities`) | Ayrı alt sistem | Phase 8 |
| Eşya düşürme/toplama varlıkları | `AddItemActor` + toplama | Phase 7.2 |
| Chunk akışı (oyuncu çevresi) | Yalnız spawn chunk'ı gönderiliyor | Phase 8 |

## 4. Yaklaşımlar

**A. Blok kırmayı istemci tahminine bırak (`serverAuthBlockBreaking=false`).**
İstemci kırılma animasyonunu yerel oynatır, bitince `CrackBlock` (2) yollar;
sunucu mutasyonu yapar ve `UpdateBlock` yayınlar.

**B. Sunucu yetkili kırma (`serverAuthBlockBreaking=true`).**
İstemci `ContinueDestroyBlock` (27) ile ilerleme bildirir; sunucu her adımda
`LevelEvent` kırılma paketi yayınlamak ve `PredictDestroyBlock` (26)
beklemek zorundadır.

**C. Yalnız hareket/sağlık, blok etkileşimi Phase 8'e.**
En az risk, ama bitirme koşulu karşılanmaz.

**Karar: A**, ve B'nin girdisi de kabul edilir (26/27 geldiğinde de kırma
uygulanır). Gerekçe: A'da görsel geri bildirim istemcide bedava; `LevelEvent`
tel biçimini doğrulamadan uygulamak B'yi kırılgan yapar. C bitirme koşulunu
karşılamıyor.

## 5. Kararlar (benim kararlarım)

### 5.1 Tel biçimleri (şemadan; ordinal sırası)

| Paket | ID | Alanlar |
|---|---|---|
| `Interact` | 33 | `Action` uint8 · `Target Runtime ID` varuint64 · **yalnız `Action==4` (InteractUpdate)** `Position` Vec3 |
| `PlayerAction` | 36 | `Player Runtime ID` varuint64 · `Action` varint32 (zigzag) · `Block Position` BlockPos · `Result Pos` BlockPos · `Face` varint32 |
| `UpdateBlock` | 21 | `Block Position` BlockPos · `Block Runtime ID` varuint32 · `Flags` varuint32 · `Layer` varuint32 |
| `SetHealth` | 42 | `Health` varint32 (zigzag) |
| `Respawn` | 45 | `Position` Vec3 · `State` uint8 · `Player Runtime Id` varuint64 |

Varsayım (gerçek istemciyle sınanmadı): `Interact.Position` yalnız
`InteractUpdate` (4) için yazılır — gophertunnel/pmmp davranışı; şema alanı
`required` listesinde değil.

### 5.2 Dünya mutasyonu

```php
World::getBlock(int $x, int $y, int $z): ?int      // null: chunk yüklü değil
World::setBlock(int $x, int $y, int $z, int $networkId): bool  // false: değişmedi
World::blockPositionLoaded(int $x, int $y, int $z): bool
BlockRegistry::fromNetworkId(int $networkId): ?BlockState
```

`setBlock` **chunk yüklemez** (istemci tetikli yükleme, bellek büyütme vektörü
olur). Yüklü değilse `false`. Y `-64..319` dışındaysa `WorldException::outOfBounds`.

### 5.3 Yayın altyapısı

`PickaxeMP\connection\PacketBroadcaster` (arayüz) — tek uygulama
`ConnectionPipeline`'ın kendisi:

```php
sendTo(string $connectionId, PacketInterface $packet): void
broadcastExcept(?string $exceptConnectionId, PacketInterface $packet): void
```

`broadcastExcept` yalnız **şifrelemesi kanıtlanmış ve spawn olmuş**
bağlantılara gönderir (`encryptionEstablished && spawned`); aksi halde istemci
çözemeyeceği paketi alır. Gönderim hatası tek bağlantıyı düşürür, tur devam eder.

### 5.4 Blok kırma/yerleştirme

`BlockBreakManager` → **`BlockActionManager`** (yeniden adlandırma; artık
yerleştirmeyi de yapar). Bağımlılıklar: `Logger`, `PacketBroadcaster`,
`PlayerManager`. Akış:

- Kır: hedef yüklü mü → hava mı → `setBlock(air)` → `UpdateBlock` yayınla.
- Koy: hedef hava mı, y `-64..318` aralığında mı → elde tutulan yığın var mı →
  `setBlock(held)` → `UpdateBlock` yayınla → yığından 1 eksilt →
  `InventorySlot` ile sahibine yeni durumu bildir.
- Yetki: yalnız `PlayerState::Spawned` ve `session->Playing`.
- Hız sınırı: oyuncu başına saniyede 30 blok mutasyonu (kötüye kullanım) —
  aşılırsa işlem yok sayılır ve `warning` loglanır.

### 5.5 Sağlık/ölüm/yeniden doğma

Sağlık **oyuncu başına** tutulur (`Player::$health`, 0..20). Phase 5'teki
`HealthManager` tek bir `$health` alanını tüm oyuncular için paylaşıyordu —
gerçek hata, düzeltiliyor.

- `damage()` → `SetHealth` gönder; 0 ise `PlayerState::Dead` + spawn noktasına
  ışınlama yok, hareket kilitli.
- Yeniden doğma: istemci `PlayerAction(Respawn=7)` yollar → sunucu
  `Respawn(state=1 ReadyToSpawn, position=spawn)` + `SetHealth(20)` +
  `MovePlayerPacket(teleport)` gönderir.
- `PlayerState::Dead` eklenir; `isActive()` korunur, `canInteract()` yeni metot.

## 6. Test stratejisi

- Tel biçimi: her paket için sabit bayt dizisi (golden bytes) + gidiş-dönüş.
- Regresyon: paket kimlikleri şemayla eşleşmeli; registry 29 paket.
- Dünya: `setBlock` yüklü olmayan chunk'ta `false`, aralık dışında istisna.
- Blok akışı: kır → `UpdateBlock` yayını; koy → envanter düşümü + `InventorySlot`.
- Yayın: şifrelemesi kanıtlanmamış bağlantı yayın almaz.
- Sağlık: iki oyuncunun sağlığı birbirinden bağımsız; ölüm/doğma sırası.

Gerçek istemci doğrulaması: **BLOCKED** (istemci yok). Testler bunu iddia etmez.
