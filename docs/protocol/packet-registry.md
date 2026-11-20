# Paket kayıt defteri (packet registry)

Tüm paket kimlikleri `PickaxeMP\protocol\packet\PacketId` içinde **tek
kaynaktan** yönetilir. Rastgele ID atanmaz; değerler Mojang'ın `v1.26.65`
şemasındaki `[cereal:packet]` alanlarından gelir.

## Kayıtlı paketler

| ID (dec) | ID (hex) | Sınıf | Yön | Maks. boyut |
|---|---|---|---|---|
| 1 | 0x01 | `LoginPacket` | C→S | 512 KiB |
| 2 | 0x02 | `PlayStatusPacket` | S→C | 8 MiB |
| 3 | 0x03 | `ServerToClientHandshakePacket` | S→C | 8 MiB |
| 4 | 0x04 | `ClientToServerHandshakePacket` | C→S | 1 KiB |
| 6 | 0x06 | `ResourcePacksInfoPacket` | S→C | 8 MiB |
| 7 | 0x07 | `ResourcePackStackPacket` | S→C | 8 MiB |
| 8 | 0x08 | `ResourcePackClientResponsePacket` | C→S | 8 MiB |
| 10 | 0x0A | `SetTimePacket` | S→C | 1 KiB |
| 11 | 0x0B | `StartGamePacket` | S→C | 8 MiB |
| 12 | 0x0C | `AddPlayerPacket` | S→C | 8 MiB |
| 13 | 0x0D | `AddActorPacket` | S→C | 8 MiB |
| 14 | 0x0E | `RemoveActorPacket` | S→C | 8 MiB |
| 15 | 0x0F | `AddItemActorPacket` | S→C | 1 KiB |
| 17 | 0x11 | `TakeItemActorPacket` | C→S | 1 KiB |
| 18 | 0x12 | `MoveActorAbsolutePacket` | S→C | 8 MiB |
| 19 | 0x13 | `MovePlayerPacket` | S→C | 8 MiB |
| 21 | 0x15 | `UpdateBlockPacket` | S→C | 8 MiB |
| 27 | 0x1B | `ActorEventPacket` | S→C | 1 KiB |
| 29 | 0x1D | `UpdateAttributesPacket` | S→C | 8 MiB |
| 30 | 0x1E | `InventoryTransactionPacket` | C→S | 64 KiB |
| 31 | 0x1F | `MobEquipmentPacket` | S→C | 8 MiB |
| 33 | 0x21 | `InteractPacket` | C→S | 1 KiB |
| 36 | 0x24 | `PlayerActionPacket` | C→S | 1 KiB |
| 42 | 0x2A | `SetHealthPacket` | S→C | 8 MiB |
| 45 | 0x2D | `RespawnPacket` | S→C | 8 MiB |
| 46 | 0x2E | `ContainerOpenPacket` | S→C | 8 MiB |
| 47 | 0x2F | `ContainerClosePacket` | S→C | 8 MiB |
| 49 | 0x31 | `InventoryContentPacket` | S→C | 256 KiB |
| 50 | 0x32 | `InventorySlotPacket` | S→C | 8 MiB |
| 58 | 0x3A | `LevelChunkPacket` | S→C | 8 MiB |
| 63 | 0x3F | `PlayerListPacket` | S→C | 1 MiB |
| 69 | 0x45 | `RequestChunkRadiusPacket` | C→S | 1 KiB |
| 70 | 0x46 | `ChunkRadiusUpdatedPacket` | S→C | 1 KiB |
| 111 | 0x6F | `MoveActorDeltaPacket` | S→C | 1 KiB |
| 113 | 0x71 | `SetLocalPlayerAsInitializedPacket` | C→S | 1 KiB |
| 123 | 0x7B | `LevelSoundEventPacket` | S→C | 1 KiB |
| 143 | 0x8F | `NetworkSettingsPacket` | S→C | 8 MiB |
| 144 | 0x90 | `PlayerAuthInputPacket` | C→S | 64 KiB |
| 147 | 0x93 | `ItemStackRequestPacket` | C→S | 64 KiB |
| 148 | 0x94 | `ItemStackResponsePacket` | S→C | 64 KiB |
| 162 | 0xA2 | `ItemRegistryPacket` | S→C | 512 KiB |
| 174 | 0xAE | `SubChunkPacket` | S→C | 8 MiB |
| 187 | 0xBB | `UpdateAbilitiesPacket` | S→C | 1 KiB |
| 193 | 0xC1 | `RequestNetworkSettingsPacket` | C→S | 8 MiB |

Toplam **44 paket** (Phase 2: 7 · Phase 3: +2 · Phase 4: +3 · Phase 5: +2 ·
Phase 6: +10 · Phase 7: +5 · Phase 7.1: +4 · Phase 7.2: +2 · Phase 8: +4 ·
Phase 9: +2 · Phase 10.3: +2 · Phase 10.4: +1).

> **`ItemRegistryPacket` (162) notu:** 1934 gerçek eşya gönderilir
> (`resources/item-registry.json`, pmmp/BedrockData'dan üretildi). Tel
> üzerinde `componentBased = false` ve `version = 2` yazılır; istemci kendi
> **vanilla** tanımlarını kullanır. Sunucu bileşen NBT'si üretemediği için
> `true` göndermek istemcide boş eşya tanımına yol açardı.

`Login` için 512 KiB seçilmesinin nedeni: şemada paket "about 100k" olarak
tanımlı; JWT zinciri büyüse de makul bir tavan kalıyor.

## Yeni paket ekleme

1. Şemayı indir: `raw.githubusercontent.com/Mojang/bedrock-protocol-docs/v1.26.65/json/<Paket>.json`
2. `[cereal:packet]` değerini not al.
3. `x-ordinal-index` sırasına göre alanları yaz — sıra yanlışsa istemci
   paketi yanlış çözer.
4. `x-underlying-type` → kullanılacak `BinaryReader`/`BinaryWriter` metodu.
5. `x-serialization-options` içinde `"Big Endian"` varsa `*BigEndian` metodu.
6. `PacketId` sabiti ekle, `PacketRegistry::withSupportedPackets()` içine kaydet.
7. Encode → decode round-trip testi yaz.
8. Bozuk/eksik veri testi yaz.

## Yön doğrulaması

Registry her paket için yön tutar. Yanlış yönde gelen paket
`ProtocolException` fırlatır — sunucuya istemciden `PlayStatus` gelmesi gibi
durumlar sessizce kabul edilmez.

## Bilinmeyen paketler

Bilinmeyen kimlik **açık hata** üretir (`ProtocolException::unknownPacket`).
Sessizce atlamak akışı bozar: bir sonraki paketin sınırları kayar.

Çağıran katman (oturum) bu hatayı bağlantı bazında ele almalı ve o bağlantıyı
kapatmalıdır; sunucu çökmemelidir.

## Durum makinesi ile ilişki

`ProtocolHandshake`, hangi durumda hangi paketin beklendiğini bilir. Beklenmeyen
paket `HandshakeException` üretir. Bu iki doğrulama birbirini tamamlar:

- Registry → "bu ID var mı, yönü doğru mu?"
- Handshake → "bu paket şu anda sırada mı?"

## Kapsam dışı (şimdilik)

Phase 2 yalnızca login öncesi paketleri kapsıyordu. `StartGame`, `LevelChunk`,
`PlayerAuthInput`, envanter/varlık, Phase 8 dünya akışı ve Phase 9
öznitelik/yetenek paketleri sonraki fazlarda eklendi.
Kayıtlı olmayan bir paket gelirse bağlantı kapatılır - "yarım destek" yok.
