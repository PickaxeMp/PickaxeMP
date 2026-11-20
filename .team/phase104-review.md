# Phase 10.4 — dört model incelemesi

Tarih: 2026-11-19
Kapsam: `ItemRegistryPacket` (162) + `ItemTable`, oynanış geri bildirimi
(`ActorEvent`/`LevelSoundEvent`), saf PHP PNG çözücü + `SkinSerializer`,
oyun modu/yetenek geçişi.

## Kullanım (gerçek sayaçlar)

| Model | Prompt | Completion | Toplam | Süre |
|---|---|---|---|---|
| `nvidia/nvidia/nemotron-3.5-lightning-30b-a3b` | 2362 | 3000 | 5362 | 24.7 s |
| `nvidia/nvidia/nemotron-3.5-super-vl-120b-a12b` | 2362 | 3000 | 5362 | 28.0 s |
| `nvidia/minimaxai/minimax-m3.1-flash` | 2654 | 1600 | 4254 | 26.4 s |
| `nvidia/meta/muse-glimmer-30b` | 2157 | 1400 | 3557 | 263.3 s |

**Toplam:** 9535 prompt + 9000 completion = **18535 token**.

> Muse ilk denemede 600 s HTTP zaman aşımına düştü (MaxTokens 3000);
> `MaxTokens 1400` ile yeniden çalıştırıldı ve 263 s'de tamamlandı.

## Önemli dürüstlük notu

Dört modelin **hiçbirine dosya erişimi verilmedi**. Prompt yalnızca kararların
açıklamasını içeriyordu. Sonuç: modeller dosya okumaya çalıştı, okuyamadı ve
**çıkarım zincirlerini** (thinking trace) çıktı olarak döndürdü. Hiçbiri
istenen `## Bulgular` tablosunu üretmedi.

Bu yüzden bu inceleme **bulgu üretmedi**. Bunun yerine modellerin öne
sürdüğü iddiaları yerel referanslara (`docs/protocol/schemas/v1.26.65/`,
`.team/ref/*.go`) karşı **tek tek doğruladım**.

## Modellerin iddiaları ve gerçek sonuç

| # | İddia (model) | Ağırlık iddiası | Doğrulama | Sonuç |
|---|---|---|---|---|
| 1 | `ItemEntry.Version` **ZigZag** değil, düz varuint32; ZigZag yazmak paketi kaydırır | kritik (lightning, super) | `.team/ref/item_registry.go`: `r.Varint32(&x.Version)` — gophertunnel `Varint32` = **ZigZag** | ❌ **İddia yanlış.** Uygulama doğru; golden-byte testi (`040a0000`) kilitliyor |
| 2 | `ItemEntry.RuntimeID` imzalı int16 değil, işaretsiz olmalı; `-158` bozulur | kritik (lightning) | `.team/ref/item_registry.go`: `RuntimeID int16` + `r.Int16()` (sabit LE) | ❌ **İddia yanlış.** İmzalı int16 doğru |
| 3 | `version = 2` yanlış, `ItemEntryVersionNone = 0` olmalı | orta (super) | `.team/ref/item_registry.go`: `ItemEntryVersionLegacy = iota` → **0**, `DataDriven` → 1, `None` → **2** | ❌ **İddia yanlış.** `None = 2` doğru |
| 4 | `Spectator` oyun modu **3/4** olmalı (gophertunnel yorumu) | orta (super) | `schemas/v1.26.65/GameType.json`: `x-enum-binary-value` = `[-1,0,1,2,5,6,0]` → Spectator **6** | ❌ **İddia yanlış.** `6` doğru; gophertunnel yorumu eski sürümden kalma |
| 5 | `LevelSoundEvent.Actor Unique Id` varint64 olmalı, sabit int64 değil | orta (lightning) | `schemas/.../LevelSoundEventPacketPayload.json`: `x-underlying-type: int64`, `x-ordinal-index: 6` — **Compression yok** | ❌ **İddia yanlış.** Sabit int64 doğru (Compression yoksa fixed) |
| 6 | `break`/`place` yerine `break.block` kullanılmalı | orta (lightning) | `.team/ref/level_sound_event.go`: `SoundEventBreak = "break"`, `SoundEventPlace = "place"` | ❌ **İddia yanlış.** `break`/`place` doğru kimlikler |
| 7 | `air` registry'den çıkarılıp pakette gönderilmesi tutarsız | orta (lightning, super) | Tasarım gereği: `ItemRegistry::register()` negatifi reddeder; istemci yalnız pakete bakar | ⚠️ **Gerçek ama bilinçli.** Kod yorumuyla belgelenmiş; davranış etkisi yok |

**Sonuç: 7 iddianın 6'sı yanlış, 1'i bilinçli tasarım kararı.** Hiçbiri
uygulama değişikliği gerektirmedi.

## Bu incelemenin gerçek değeri

Modellerin "kritik" dediği her nokta yanlış çıktı, ama doğrulama süreci
**gerçek bir hatayı** ortaya çıkardı (aşağıda). Yani inceleme, bulduğu
yanlışlardan çok, doğrulama disiplini sayesinde işe yaradı.

## Dört model incelemesinin ortaya çıkardığı gerçek hata

`Entity Data` yolunu açarken bulundu (modeller değil, doğrulama süreci):

- `AddActorPacket::decode()` meta veriyi okuyordu (`$actorData`) ama
  **kurucuya geçirmiyordu** — sessizce atılıyordu. Aynı hata `AddPlayerPacket`
  içinde de vardı. İkisi de düzeltildi ve round-trip testleriyle kilitlendi
  (`testAddActorRoundTripPreservesActorMetadata`,
  `testAddPlayerRoundTripPreservesEntityMetadata`).
- Eski `testAddActorRejectsNonEmptyMetadata` testi artık **yanlış sebeple**
  geçiyordu (kesik tampon). Anlamlı iki teste bölündü.

## Doğrulanamayanlar

- Gerçek Bedrock 1.26.65 istemcisinin `ItemRegistry` (162) paketini kabulü.
- `componentBased = false` + 1934 girişin istemcide boş tanım üretmemesi.
- Ölümde `Hurt` gönderilmemesinin istemcide titreme yapmaması.
- PNG çözücünün gerçek Mojang derileriyle (`/v1/join` çıktısı) sınanması.
- `AddPlayer` içindeki isim etiketinin dünyada gerçekten çizilmesi.

Hepsi istemci gerektirir; `CLIENT_VERIFIED` **BLOCKED** kalır.
