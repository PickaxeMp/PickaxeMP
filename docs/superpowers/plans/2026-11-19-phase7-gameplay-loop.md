# Phase 7 — Oynanış Döngüsü (uygulama planı)

Tasarım: `docs/superpowers/specs/2026-11-19-phase7-gameplay-loop-design.md`.
Hedef protokol **1.26.65 / 2225**; şema kaynağı `docs/protocol/schemas/v1.26.65/`.

Her alt görev sonunda: `phpunit`, `composer lint`, `graphify update .`,
`docs/DEVELOPMENT_STATE.md` güncellemesi.

## 7.0 Ön hazırlık

- [x] `UnsupportedPacketFieldException extends ProtocolException` — akışı
      bozan ama bağlantıyı öldürmemesi gereken alanlar için.
- [x] `tests/unit/` altında `GameplayPacketTest.php` iskeleti.

## 7.1 Paket kodlayıcıları (5 paket, registry 24 → 29)

| Paket | ID | Yön | Sınıf |
|---|---|---|---|
| `UpdateBlock` | 21 | S→C | `UpdateBlockPacket` |
| `Interact` | 33 | C→S | `InteractPacket` |
| `PlayerAction` | 36 | C→S | `PlayerActionPacket` |
| `SetHealth` | 42 | S→C | `SetHealthPacket` |
| `Respawn` | 45 | S→C | `RespawnPacket` |

Yeni tipler: `PlayerActionType` (enum, `x-enum-binary-value` eşlemesi),
`InteractAction` (enum 0,3,4,5,6), `RespawnState` (enum 0,1,2).

- [x] `PacketId` sabitleri + `all()` adları
- [x] `PacketRegistry::withSupportedPackets()` kaydı + yönler
- [x] 5 kodlayıcı (`encode`/`decode`, sıkı modda artık bayt reddi)
- [x] `tests/unit/protocol/GameplayPacketTest.php`: kimlik doğrulama, golden
      bytes, gidiş-dönüş, `Interact` koşullu konum, `SetHealth` zigzag sınırları

## 7.2 `PlayerBlockAction` düzeltmesi

`PREDICT_DESTROY_BLOCK` 18 → **26**, `CONTINUE_DESTROY_BLOCK` 19 → **27**
(şemadaki `PlayerActionType` değerleri). `CREATIVE_DESTROY_BLOCK = 13` eklenir.

- [x] Sabitler + `isBreak()` (2, 26) + `isStartBreak()`/`isAbortBreak()`
- [x] Test: sabit değerleri şemayla eşleşiyor

## 7.3 Dünya mutasyonu

- [x] `BlockRegistry::fromNetworkId()` (ters harita, tembel kurulum)
- [x] `World::getBlock()`, `World::setBlock()`, `World::blockPositionLoaded()`
- [x] Testler: yüklü olmayan chunk → `null`/`false`; aralık dışı → istisna;
      mutasyon chunk'ı kirli işaretler

## 7.4 Yayın altyapısı

- [x] `PickaxeMP\connection\PacketBroadcaster` arayüzü
- [x] `ConnectionPipeline` uygular: `sendTo`, `broadcastExcept`
      (yalnız `encryptionEstablished && spawned`)
- [x] Test: şifrelemesi kanıtlanmamış bağlantı yayın almaz; hatalı bağlantı
      turu düşürmez

## 7.5 Spawn/despawn yayını

- [x] `PlayerSnapshot::addPlayer(Player): AddPlayerPacket` (yardımcı)
- [x] `PlayerJoinService::complete()` sonrası: yeni oyuncunun `AddPlayer`'ı
      diğerlerine; diğerlerinin `AddPlayer`'ı yeni oyuncuya
- [x] Kapanışta `RemoveActor(runtimeId)` yayını
- [x] `StartGame` girişinde `serverAuthBlockBreaking: false`
- [x] Test: iki oyuncu birbirini görür; çıkışta `RemoveActor`

## 7.6 Blok kırma/yerleştirme

- [x] `BlockBreakManager` → `BlockActionManager` (kır + koy + hız sınırı)
- [x] `InventoryTransactionPacket` **varyant 2** (`ItemUseInventoryTransaction`)
      desteği: 13 alan, `InventoryTransaction` tabanı
- [x] `PlayerAuthInputPacket` bit 32'yi çözer (ItemUse), bit 34'te
      `UnsupportedPacketFieldException` (paket düşer, bağlantı yaşar)
- [x] Yerleştirme: hedef hava, elde yığın, `UpdateBlock` + `InventorySlot`
- [x] Test: kır → `UpdateBlock`; koy → envanter −1 + `InventorySlot`; hız sınırı

## 7.7 Sağlık/ölüm/yeniden doğma

- [x] `Player::$health`, `damage()`, `heal()`, `health()`, `isDead()`
- [x] `PlayerState::Dead` + `canInteract()`
- [x] `HealthManager` (oyuncu başına, `SetHealth` gönderen) yeniden yazılır
- [x] `PlayerAction(Respawn)` → `Respawn` + `SetHealth(20)` + ışınlama
- [x] Test: iki oyuncu bağımsız sağlık; ölüm → hareket reddi; doğma → 20

## 7.8 Boru hattı bağlantısı ve kapanış

- [x] `ConnectionPipeline` dağıtımı: `Interact`, `PlayerAction`,
      `InventoryTransaction`
- [x] `onPayload` içinde `UnsupportedPacketFieldException` → yalnız paket düşer
- [x] `docs/DEVELOPMENT_STATE.md` Phase 7 bölümü, `README.md` tazeleme
- [x] `graphify update .`

## Doğrulama

```
tools\php.cmd -c tools\php.ini vendor\bin\phpunit --configuration phpunit.xml
tools\composer.cmd lint
graphify update .
```

## 7.1 Devamı

Phase 7.1 (PlayerList / ActorEvent / MoveActorDelta / LevelSoundEvent)
tamamlandı — ayrıntılar:
`docs/superpowers/plans/2026-11-19-phase71-playerlist-events.md`.

Gerçek istemci: **BLOCKED** (istemci yok) - `CLIENT_VERIFIED` `BLOCKED` kalır.
