### 1. Dupe / eşya kaybı — VAR

**Aşama adımları (Take → Place aynı yığın):**
1. Envanter yuva 5'te `64 × Kaya` var, imleç boş.
2. İstemci `Take` gönderir: kaynak envanter/5, hedef imleç/0, `amount = 64`.
3. `move()`: imleç = `64 × Kaya`, kaynak = null. `touched = {5}`, `cursorTouched = true`.
4. İstemci hemen `Place` gönderir: kaynak imleç/0, hedef envanter/5, `amount = 64`.
5. `move()`: hedef boş, `amount ≤ maxStackSize`, yazılır: envanter/5 = `64 × Kaya`, imleç = null.

**Sonuç:** Hiçbir şey değişmedi, ama istemciye iki ayrı `ItemStackResponseContainerInfo` dönmüş gibi görünür (veya istemci iki işlemi onaylar). Daha kritik olan, **iki işlem tek bir `ItemStackRequest` içinde ayrı ayrı onaylanıyor** ve istemci bu arabelleği kullanarak “geri al” diyemeyebilir. Tek paket içinde birden fazla mutasyon atomik değil.

**Düzeltme:** Tüm eylemleri geçici kopya üzerinde doğrula ve uygula; tek bir yanıt dön. İstemciye “bu paket atomik” sinali ver. Şu anki kod zaten kopya üzerinde çalışıyor, fakat `move` içinde `write` anında kopya mutasyon ediliyor; hata olursa geri alınmıyor (bkz. madde 3).

---

### 2. Miktar sınırları / uç durumlar

| Durum | Davranış | Sorun |
|-------|----------|-------|
| `amount = 0` (Take/Place) | `move()` `amount < 1` ile hata fırlatır. | Doğru reddediliyor. |
| `amount > source->count()` | `INVALID_REMOVED_AMOUNT` hatası. | Doğru. |
| `amount > 64` (telden gelirse) | Kodlayıcı `uint8`’de 255’e kadar izin verir; kod `MAX_AMOUNT = 64`’i **kontrol etmiyor**. | `amount = 100` geçerli sayılır; `sourceStack->remove(100)` negatif sayım üretebilir veya `ItemStack` sınıfında hata fırlatır. |
| `amount > maxStackSize` boş hedefe | `CANNOT_PLACE_ITEM` hatası. | Doğru. |
| `remainingSpace < amount` dolu hedef | `INVALID_ADJUSTED_AMOUNT` hatası. | Doğru. |
| `Swap` amount | `Swap` gövdesinde `amount` yok; kod `move`’u çağırmaz. | Sorunsuz. |
| Aynı kaynak/hedef | `DST_CONTAINER_AND_SLOT_EQUAL_TO_SRC_CONTAINER_AND_SLOT` hatası. | Doğru. |

**Düzeltme:** `Take`/`Place` başında `if ($amount > ItemStackRequestAction::MAX_AMOUNT)` kontrolü ekle (veya kodlayıcı katmanda zorla).

---

### 3. Atomiklik / kopya sorunu — VAR

`$slots` ve `$cursor` **derin kopya** üzerinden işleniyor, bu iyi. Ancak:

- `move()` içinde `write()` anında kopya mutasyon ediliyor. Bir sonraki eylemde hata fırlatılırsa (ör. 2. eylem geçersiz), **ilk eylemin etkisi `$slots`/`$cursor` kopyasında kalır**, ama `catch` bloğu sadece hata yanıtı döner; `$inventory` ve `$this->cursor` **hiç güncellenmez**. Bu yüzden kalıcı mutasyon yok — **ancak** istemciye “kısmi başarılı” izlenimi verebilir (iki eylemli pakette ilk eylem uygulanmış gibi hissedebilir).
- `sameStack()` sadece `type`, `count`, `maxStackSize` karşılaştırır. `nbt`, `metadata`, `damage` gibi diğer alanlar yok sayılır. İki farklı NBT’li yığın aynı sayıldığında envantere yazılmayabilir (istemci eski NBT’li görmeye devam eder).

**Düzeltme:** Hata durumunda kopya atılmalı (zaten atılıyor), ama yanıtta “bu paket reddedildi, hiçbir şey değişmedi” net olmalı. `sameStack()`’e tüm ilgili alanları ekle.

---

### 4. Oturum imleç yaşam döngüsü — VAR

- `forget()` çağrılırsa imleç silinir. Oyuncu yeniden bağlandığında yeni `Player` nesnesi ve yeni `sessionId` olur; eski imleç haritada kalır (sızıntı), ama yeni oturum için erişilmez.
- `canInteract() == false` olduğunda metot erken hata döner; imleç **değiştirilmez**, sadece okunur. Sızıntı yok.
- Oyuncu ayrılırken `forget()` çağrılmazsa imleçteki eşya **kalıcı olarak kaybolur** (imleç kalıcı değil, bellekte sadece oturum boyunca).

**Düzeltme:** `Player::logout` / session sonu olayında `forget($sessionId)` çağrılmalı. Eğer imleç kalıcı olmasınsa, kopma anındaki kayıp kabul edilebilir (Bedrock’ta da genellikle öyledir), ama bunu belgele.

---

### 5. Sunucu yetkisi / reddedilen istek mutasyonu

- Tüm doğrulama kopya (`$slots`, `$cursor`) üzerinde. Hata fırlatıldığında `catch` bloğu sadece yanıt döner; `$inventory->setSlot()` ve `$this->cursor[$sessionId]` **hiç çalıştırılmaz**. Reddedilen istekte **hiç mutasyon kalmıyor**.
- `netId` doğrulaması yok (sözleşmeyle öyle), bu kabul edilebilir.
- `canInteract()` false ise hiçbir işlem yapılmaz.

**Sonuç:** Bu kısım doğru.

---

### 6. Yanıt (`ItemStackResponseInfo`) — KISMI SORUNLU

- `requestId` ve `result` kodu doğru.
- Başarıda sadece değişen yuvalar dönüyor (`touched` üzerinden `sameStack` filtresi). Bu sözleşmeye uygun.
- **Sorun:** `containers` sırası sabit değil; önce envanter, sonra imleç. İstemci bu sırayı bekliyorsa sorun değil, ancak protokol belgesinde sıraya dair kesin bir kural yoksa “emin değilim”.
- Boşaltılan yuva `amount: 0` olarak dönüyor (`$slots[$index]?->count() ?? 0`). Doğru.
- Hata yanıtında `containers = []`. Doğru.

---

### 7. Protokol uyumu — VAR

- `requestId` negatif **tek** sayı olmalı. Kodda `hasValidRequestId()` var ama içeriği gösterilmiyor. Eğer bu kontrol çift/pozitifleri reddetmiyorsa, istemci protokol hatasıyla düşer.
- `type >= 9` olduğunda `variant = type - 2` kuralı kodda görünmüyor. `isSupported()` sadece Take/Place/Swap’i kabul ediyorsa, diğerleri zaten düşülüyor; varyant kodlaması sunucu tarafında önemli değil (isteği okurken kodlayıcı zaten çözmüş olmalı).
- `netId = null` gönderimi sözleşmeyle uyumlu.

**Düzeltme:** `hasValidRequestId()`’nin `requestId < 0 && requestId % 2 !== 0` kontrolünü yaptığından emin ol.

---

### 8. Kalıcılık / kopma

- `PlayerInventory::setSlot` dirty işaretini günceller, bu veritabanına kaydı tetikler.
- Imleç **kalıcı değil** (`$this->cursor` bellekte). Oyuncu koparken imleçteki eşya kaybolur.
- Bu, minimal bir sunucu için kabul edilebilir olabilir, ama “eşya kaybı” olarak değerlendirilmeli.

**Düzeltme:** Oturum sonu olayında imleci envantere geri yazmak veya en azından `forget()` çağrısı eklemek daha güvenlidir.

---

### Özet öneriler

1. `amount` için `MAX_AMOUNT = 64` sunucu tarafında zorla.
2. `hasValidRequestId()`’nin negatif-tek kontrolünü doğrula.
3. `sameStack()`’e NBT/metadata alanlarını ekle.
4. Çoklu eylemli paketlerde atomik davranışı netleştir; hata durumunda kopya atılıyor, bu doğru ama istemciye kısmi uygulama izlenimi verme.
5. Oturum sonu / oyuncu çıkışında `forget($sessionId)` çağrısı ekle.
6. İstemciye dönen konteyner sırası protokol belgesiyle karşılaştırılmalı (emin değilim).
