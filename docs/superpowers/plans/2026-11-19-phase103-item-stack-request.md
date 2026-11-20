# Phase 10.3 — `ItemStackRequest` (147/148) Desteği

**Tarih:** 2026-11-19
**Durum:** ✅ Tamamlandı (kod + test; gerçek istemci doğrulaması yok)

## Amaç

Gerçek Bedrock istemcisi envanterde eşya taşırken sunucu yetkili
`ItemStackRequest` (147) yolu kullanılır. Bu paket şu ana kadar **kayıtsızdı**:

- `PacketRegistry` 147'yi tanımadığı için `ProtocolException::unknownPacket`
  → `failConnection`. Yani istemci envanterde tek eşya taşıdığında bağlantı
  düşüyordu.
- `PlayerAuthInput` bit 34 (`PERFORM_ITEM_STACK_REQUEST`) alanı
  `UnsupportedPacketFieldException` ile **yalnızca o paketi** düşürüyordu;
  istek hiç yanıtlanmadığı için istemci envanter tahminini geri alıyordu.

Phase 10.3 bu yolu açar:

1. `ItemStackRequestPacket` (147) + `ItemStackResponsePacket` (148) tel
   biçimleri.
2. `PlayerAuthInput` içindeki `Item Stack Request` alanı (bit 34).
3. Sunucu yetkili uygulama: `Take` / `Place` / `Swap` (envanter ↔ imleç).
4. Yanıt: başarıda `ItemStackResponse` (değişen yuva miktarları), hatada
   `ItemStackNetResult` kodu ve **hiçbir mutasyon**.

## Kapsam

| # | İş | Durum |
|---|---|---|
| 1 | `ContainerName`, `ItemStackRequestSlotInfo`, `ItemStackRequestAction`, `ItemStackRequest` | ✅ |
| 2 | `ItemStackNetResult`, `ItemStackResponse*` tipleri | ✅ |
| 3 | `ItemStackRequestPacket` (147) / `ItemStackResponsePacket` (148) | ✅ |
| 4 | `PlayerAuthInputPacket` bit 34 alanı | ✅ |
| 5 | `ItemStackRequestManager` — doğrulama + atomik uygulama | ✅ |
| 6 | `PlayerAuthInput` / `ConnectionPipeline` kablolaması | ✅ |
| 7 | Birim + entegrasyon testleri | ✅ |

Registry: **41 → 43** paket.

## Tel biçimi (Mojang v1.26.65, 2225)

```
ItemStackRequestPacket (147):
  Requests              varuint32 sayı (en fazla 100)
  her istek:
    Client Request Id   varint32 (zigzag)
    Actions             varuint32 sayı (en fazla 100)
      her aksiyon:      varuint32 varyant + uint8 tür + gövde
    Strings To Filter   varuint32 sayı + string (en fazla 1000 bayt)
    StringsToFilterOrigin int32 (sabit LE)

Aksiyon gövdeleri:
  0 Take    : uint8 miktar, Source, Destination
  1 Place   : uint8 miktar, Source, Destination
  2 Swap    : Source, Destination
  3 Drop    : uint8 miktar, Source, bool randomly
  4 Destroy : uint8 miktar, Source
  5 Consume : uint8 miktar, Source
  6 Create  : uint8 sonuç indeksi
  9  LabTableCombine : (gövde yok)
 10  BeaconPayment   : varint32, varint32
 11  MineBlock       : varint32, varint32, int32
 12  CraftRecipe     : varuint32, uint8
 14  CraftCreative   : varuint32, uint8
 15  CraftRecipeOptional : varuint32, int32
 16  CraftRepairAndDisenchant : varuint32, uint8, varint32
 17  CraftLoom       : string, uint8
 18  CraftReserved   : string, uint8
 19  CraftNonImplemented : (gövde yok)

Slot bilgisi (Source/Destination):
  Full Container Name   uint8 + isteğe bağlı uint32 dinamik kimlik
  Slot                  uint8
  Net Id Variant        int32 (sabit LE)
```

`varyant` ile `tür` aynı aksiyonu iki kez taşır (vanilla böyle yazar):
`tür <= 6` için `varyant = tür`, `tür >= 9` için `varyant = tür - 2`.
İki alan uyuşmazsa paket reddedilir.

`CraftRecipeAuto` (13) ve `CraftResults` (20) gövdeleri eşya listesi taşır;
bunlar çözülmez ve `UnsupportedPacketFieldException` ile **yalnızca o paket**
düşürülür (bağlantı yaşar). Bu projede üretim/tezgâh akışı yok.

```
ItemStackResponsePacket (148):
  Responses             varuint32 sayı
  her yanıt:
    Result              uint8 (ItemStackNetResult)
    Client Request Id   varint32 (zigzag)
    Containers          isteğe bağlı (varlık baytı + liste)
      Full Container Name
      Slots             varuint32 sayı
        Requested Slot  uint8
        Slot            uint8
        Amount          uint8
        Net Id          isteğe bağlı (miktar > 0 iken varint32)
        Custom Name     string
        Filtered Name   isteğe bağlı string
        Durability      varint32
```

## Kurallar

| Kural | Değer | Gerekçe |
|---|---|---|
| Desteklenen aksiyonlar | `Take`, `Place`, `Swap` | Envanter UI'sinde eşya taşıma; diğerleri ayrı faz |
| Envanter konteyneri | `CombinedHotbarAndInventory` (12) | 0-8 hotbar + 9-35 ana envanter; modelle birebir |
| İmleç konteyneri | `Cursor` (59), yuva 0 | İki adımlı taşıma (yuva → imleç → yuva) |
| Diğer konteynerler | `InvalidSourceContainer` | `Hotbar` (28) / `Inventory` (29) yuva eşlemesi gerçek istemcide doğrulanmadı; yanlış eşleme yanlış eşyayı taşır |
| Miktar | 1..64 | Şema sınırı |
| Ölü / `Joining` oyuncu | `ActionRequestNotAllowed` | `Player::canInteract()` |
| Desteklenmeyen aksiyon | `ActionRequestNotAllowed` | Fail-closed; yarım uygulama yok |
| Net id doğrulaması | Yok | Sunucu ağ kimliği üretmiyor (tüm `InventorySlot` tanımlayıcıları `netId = null`); istemci de 0 gönderir |

- **Atomik:** tüm aksiyonlar yuva haritasının **kopyası** üzerinde doğrulanır;
  herhangi biri hata verirse envanter ve imleç **hiç değişmez**.
- **Sunucu yetkilidir:** istemcinin miktarını değil, envanterdeki gerçek yığın
  miktarını esas alır; yetersiz kaynak `InvalidRemovedAmount`.
- **Yanıt tek paket:** başarıda yalnızca `ItemStackResponsePacket` gider;
  değişen yuvalar o paketin `Containers` alanında taşınır (protokolün amacı).
- **İmleç oturum başına** izlenir; bağlantı kapanınca `forget()` ile silinir.

## Dürüst sınırlar

- Gerçek Bedrock istemcisi bu yolda sınanmadı; `CLIENT_VERIFIED` `BLOCKED`.
- `Drop` / `Destroy` / `Consume` / `Create` ve üretim aksiyonları
  desteklenmez (`ActionRequestNotAllowed`). Envanterden sürükleyip atma
  çalışmaz; `Q` ile atma `InventoryTransaction` yolundan çalışır.
- Yaratıcı envanteri (`CraftCreative`) ve üretim (`CraftRecipe`) yok.
- `Hotbar` (28) / `Inventory` (29) konteynerleri reddedilir: yuva eşlemesi
  doğrulanmadı.
- Ağ kimliği üretilmediği için istemci "legacy" modda kabul edilir; şema
  `Amount > 0` iken net id bekler, biz göndermiyoruz. Gerçek istemci kabulü
  sınanmalı.
- İmleç içeriği bağlantı kapanınca kaybolur (kalıcı değil).
