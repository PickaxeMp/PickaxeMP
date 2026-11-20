# İnceleme — `ItemStackRequestManager`

## 1. Dupe / eşya kaybı

**Somut dupe yok; ancak "kaynak == hedef" kontrolü yalnızca aynı *node* için geçerli ve bu doğru.** Asıl risk, `move()` içinde **aynı `ItemStack` nesnesinin iki yuvaya yazılması** (aşağıda 3. madde) ve **başarısız istekte `ItemStack` nesne mutasyonu**.

Adım adım bir kayıp senaryosu:

1. İstek: `Take` amount=5, kaynak = envanter yuva 0 (stack 10), hedef = imleç (boş).
2. `move()`: hedef boş → hedefe `sourceStack->withCount(5)` yazılır. `withCount()` **yeni nesne** döndürür (kopya varsayımı) → sorun yok.
3. `amount(5) != sourceStack->count()(10)` → `sourceStack->remove(5)` → kaynak 5 kalır. Doğru.

**Kayıp senaryosu (imleç → envanter, hedef dolu ve merge edilebilir):**

1. İstek: `Take` amount=5, kaynak = imleç (stack 10), hedef = envanter yuva 3 (aynı tür, 60 adet, max 64).
2. `destinationStack->add(5)` → **slot 3'teki `ItemStack` nesnesi mutasyona uğrar** (kopya alınmadan). `slots[3]` aynı referansı taşıdığı için bu mutasyon `$slots` içinde de görünür — bu yüzden sonuç doğru (65 → 64 sınırına takılır).
3. Ancak `remainingSpace() < amount` kontrolü `add()` öncesinde yapılır, `add()` sonrası `MAX_COUNT`/`maxStackSize` aşımı kontrolü **yoktur**. `remainingSpace()` doğru hesaplanıyorsa sorun çıkmaz; `remainingSpace()` `maxStackSize - count` ise `MAX_COUNT=128` sınırı **ihmal edilir** (maxStackSize 128 olan türlerde 128→129 mümkün). → Emin değilim: `remainingSpace()`/`add()` implementasyonu verilmedi.

**Asıl somut hata: `swap()` kaynak ve hedefi takas ederken `null` yazma sorunu yok**, ama `swap()` **miktar kontrolü yapmaz** — bu doğru (takasta miktar yok).

**Gerçek dupe riski: `move()` hedefe yazarken kaynağı okumuş nesneyi kullanıyor.** Eğer `withCount()` **aynı nesneyi mutasyona uğratıp döndürüyorsa** (shallow kopya), kaynak 10 → hedef 5 yazılır, sonra `sourceStack->count()` artık 5 olur; `amount === sourceStack->count()` → kaynak `null` yazılır → **5 adet kaybolur**. `withCount(): self` imzası mutasyon vs. yeni nesne ayırt etmiyor → **emin değilim**, ancak `copy()` kullanılması bu riski ortadan kaldırmak için zorunlu.

## 2. Miktar / uç durumlar

- **`amount` 0 veya negatif**: `move()` `amount < 1` → `INVALID_REMOVED_AMOUNT`. Doğru. Ancak `amount` telde `uint8` olduğu için negatif **tel seviyesinde mümkün değil**; kontrol yine de savunma amaçlı doğru.
- **`amount > 64`**: kod `MAX_AMOUNT=64` sınırını **kontrol etmiyor**. Tel `uint8` olduğundan 65..255 mümkündür. `amount=100`, kaynak stack 128 → `amount > sourceStack->count()` değil, `maxStackSize()` kontrolü sadece **boş hedef** için var; dolu hedefte `remainingSpace()` kontrolü var. Sonuç: 100 taşınabilir (maxStackSize 64 ise boş hedefte `CANNOT_PLACE_ITEM` → doğru red). **Düzeltme:** `amount < MIN_AMOUNT || amount > MAX_AMOUNT` → `INVALID_REMOVED_AMOUNT` (veya `INVALID_REQUEST_ACTION_TYPE`).
- **`MAX_COUNT = 128`**: `move()` hedefe `withCount($amount)` yazarken `amount <= sourceStack->count()` olduğundan 128 aşılmaz. `add()` yolunda `remainingSpace()` kontrolü var ama `MAX_COUNT` **ayrıca** kontrol edilmiyor → `maxStackSize()` 128 olan türlerde 128'e ulaşmış yığın + `add()` → 129 mümkün. **Düzeltme:** `add()` öncesi `min(remainingSpace(), MAX_COUNT - count)` kontrolü.
- **Aynı kaynak/hedef**: `sameNode()` kontrolü var → `DST_CONTAINER_AND_SLOT_EQUAL_TO_SRC_CONTAINER_AND_SLOT`. Doğru. Ancak kontrol **her aksiyon için** yapılıyor, yani "aynı yuva" hatası tüm isteği düşürüyor — sözleşmeye uygun.
- **Boş kaynak**: `INVALID_REMOVED_AMOUNT` — protokolde `INVALID_SOURCE_CONTAINER`/`INVALID_REMOVED_AMOUNT` tartışmalı; kabul edilebilir.

## 3. Atomiklik / referans sorunu

**Atomiklik ihlali var.** `move()` içinde:

```php
$destinationStack->add($amount);
$this->write($slots, $cursor, $destination, $destinationStack);
```

`$destinationStack` `$slots[$index]`'in **aynı referansıdır** (slotlar `copy()` ile alınmış ama `add()` mutasyonudur). Bu, `$slots` kopyasını değiştirdiği için sonuç doğru kalır — **ama** `add()`/`remove()` gerçekten mutasyon yapan metotlarsa, `sourceStack` de `$slots[...]` ile **paylaşılan referanstır** ve `remove()` kaynağı doğrudan değiştirir. Bu durumda `$slots` ile gerçek envanter arasındaki kopya **yalnız `copy()` ile korunmuş**; `add()`/`remove()` mutasyonları kopyaya uygulanıyor, sorun yok.

**Asıl atomiklik ihlali: `ItemStack` nesneleri kopyalanmadan `add()`/`remove()` ile mutasyona u
