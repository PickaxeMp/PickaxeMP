# Phase 10.3 inceleme raporu — `ItemStackRequest`

Aşağıda, sağlanan PHP koddaki güvenlik açıkları, mantıksal hatalar ve uygulama sorunları detaylandırılmıştır. Her bulgu, hatayı tetikleyen girdi ve gerekirse önerilen düzeltme ile birlikte sunulmuştur.

## 1. Dupe (Kopyalama) ve Eşya Kaybı Açıkları

Evet, envanter ve imleç tutarlılığını ihlal eden somut aksiyon dizileri bulunur.

### Bulgu 1: `Swap` aksiyonu ile `Take` miktarı > 1
`swap()` fonksiyonu, kaynak ve hedef yuvalardaki yığınları tamamen değiştirir. `move()` fonksiyonu ise miktar kontrolü yapar.

*   **Girdi:** Kaynak yuvada 64 adet taş, hedef yuvada boşluk var. `Take` (0) aksiyonu ile 32 miktar ile `Swap` (2) aksiyonu sırasıyla gönderildiğinde.
*   **Hata:** `swap()` fonksiyonu okumadan (`read`) hemen yazma (`write`) yapar. Kaynak yuvadan 64, hedef yuvaya 64 yazar. Ardından `move()` mantığı uygulanmaz, bu da kaynaktan eksik miktarın alınmamasına ve hedefe eksiz miktarın yazılmasına yol açar. Eğer `Take` öncesinde `Swap` yapılırsa, oyuncu 32'yi almak yerine 64'ü alabilir veya kaybedebilir.
*   **Düzeltme:** `swap()` fonksiyonunun başına `move()`'daki miktar validasyonu eklenmeli veya `swap()` fonksiyonunun mantığı `move()`'in mantığıyla uyumlu hale getirilmeli.

### Bulgu 2: `Place` sonrası `Swap` (Eşya Kaybı)
`move()` fonksiyonu, miktarın tamamı hedefe yazıldığında kaynağı `null` yapar.

*   **Girdi:** 32 adet altın bloğu kaynaktan alınıp (`Take`), aynı yuvaya 16 adet daha yerleştirildiğinde (`Place`). Ardından bu yuvayı başka bir yuva ile `Swap` edilmesi istenildiğinde.
*   **Hata:** `move()` 32'yi hedefe yazdıktan sonra kaynağı `null` yapar. Ardın `swap()` kaynak yuvayı `null` olarak okur ve hedefi kaynağına yazar. Sonuçta oyuncu 32'yi kaybeder, çünkü kaynağın üzerine yazılmış veya sıfırlanmış olması nedeniyle "kayıp" sayılır. Eğer kaynak yuva tamamen boşalmışsa, `Swap` sonucu eşya geri döndüğünü düşünür ama sunucu durumu böyle değildir.
*   **Düzeltme:** `swap()` işlemi yapılıyor önce kaynak yuvadaki mevcut miktarın `move()` ile kontrol edilip güncellenmesi ya da `swap()` mantığının `move()`'ın son durumunu (miktar azalmışsa) yansıması gerekir.

### Bulgu 3: Eksik `amount` kontrolü `Swap` için
`Swap` gövdesinde `amount` değişkeni yoktur (tel biçimi `0` olarak kalır), ancak kod `move()`'ı çağırır.

*   **Girdi:** `Swap` aksiyonu kaynak ve hedef arasında gönderildiğinde, kod `move($slots, $cursor, $source, $destination, $action->amount)` çağrısı yapar.
*   **Hata:** `move()` fonksiyonu `amount` değişkenini kullanır. Eğer `amount` 0 gönderilirse, `if ($amount < 1 ...)` kontrolü hata fırlatır (`INVALID_REMOVED_AMOUNT`). Ancak protokol belirtimine göre `Swap` için `amount = 0` beklenir. Kodun `amount` kontrolü `Swap` için uygun değilse, geçerli `Swap` istekleri reddedilebilir.
*   **Düzeltme:** `move()` çağrısı `Swap` için öncelikle iptal edilmeli ya da `move()` içindeki `amount` kontrolü `Swap` durumu için gevşetilmelidir.

## 2. `ItemStack` Sınırları ve Uç Durumlar

### Bulgu 4: `MAX_COUNT` ve `maxStackSize()` uyuşmazlığı
`ItemStack` sınıfı `MAX_COUNT = 128` sabitine ve `maxStackSize()` yöntemine sahiptir. Bu iki değer farklı olabilir.

*   **Girdi:** `maxStackSize()` 64 döndürürken, envanter yuvası `MAX_COUNT` (128) izin verir.
*   **Hata:** `move()` fonksiyonu `if ($amount > $sourceStack->maxStackSize())` kontrolüyle miktarı kontrol eder. Ancak `write()` fonksiyonu `withCount($amount)` kullanarak yuvaya yazar. Eğer `amount` 100 olsun ve `maxStackSize()` 64 olsun, kod "yeterli alan yok" hatası verir ama mantıksal olarak 64'ü yazabilir veya 100'ü yazmaya çalışır. Yanıt paketi (`ItemStackResponseContainerInfo`) o yuvadaki gerçek miktarı (100) yansıtabiliyorsa, istemci 128'e kadar taşımaya çalışırken sunucu 64'ü sınırlar.
*   **Düzeltme:** `move()` içinde `max($amount, $destinationStack->remainingSpace())` veya `min($amount, $destinationStack->maxStackSize())` mantığı eklenmeli.

### Bulgu 5: Sıfır ve Negatif `amount`
`amount` tipi `uint8` olduğu için 0-255 aralığında olmalı, ancak kod mantığı bu sınırları aşabilir.

*   **Girdi:** `Take` aksiyonunda `amount = 0` veya `amount = 256` (paketleme hatası ile).
*   **Hata:** `if ($amount < 1 || $amount > $sourceStack->count())` kontrolü 0'u reddeder. 256 ise `uint8` overflow ile 0 olur veya kod hata verir. `maxStackSize()` kontrolü `amount > maxStackSize` ile yapıldığında, 255 gibi değerler beklenmedik davranışlara yol açabilir.
*   **Düzeltme:** Girişte `amount` için `if ($action->type === ItemStackRequestAction::TAKE || $action->type === ItemStackRequestAction::PLACE) { if ($amount < 1 || $amount > 64) ... }` gibi ek bir sınırlama eklenmeli.

### Bulgu 6: `remainingSpace()` mantığı kusurları
`canMergeWith` ve `remainingSpace` kontrolleri zincirli olarak kontrol edilir.

*   **Girdi:** Hedef yuvada aynı türde ama farklı miktarda bir yığın var. `remainingSpace()` `maxStackSize - count` olarak hesaplanır.
*   **Hata:** Eğer hedef yuvada 64 adet varsa ve kaynaktan 10 alındığında, `remainingSpace()` 0 dönebilir. Kod `elseif ($destinationStack->remainingSpace() < $amount)` ile "yeterli alan yok" hatası verir. Ancak `canMergeWith` aynı tür olduğu için birleştirmeyi deneyebilir. Eğer `canMergeWith` yanlışlıkla `true` dönerse, `remainingSpace` kontrolü eksik kalabilir ve 10 adet yazılır, ancak `maxStackSize` aşıldıysa veri bozulabilir.
*   **Düzeltme:** `canMergeWith` ve `remainingSpace` kontrollerinin `maxStackSize` kıyasla tutarlı olduğundan emetlenmeli.

## 3. Atomiklik ve Kopyala-uygula Mantığı

### Bulgu 7: `ItemStack` referans ve kopya sorunu
Yorum satırı "kopya yüzeysel mi kalıyor?" sorusuna cevap evet.

*   **Girdi:** `$slots` dizisi `Inventory::contents()` ile doldurulur. Bu yığınlar `ItemStack` nesneleridir.
*   **Hata:** `$this->read($slots, $cursor, $source)` ile yuva okunur. `$this->write($slots, $cursor, $source, $destinationStack)` ile yazma işlemi `$slots[$node[1]] = $stack;` ile yapılır. `$stack` nesnesi aslında referanstır. Eğer `$slots[$index]` üzerinden başka bir işlem yapılırsa (örneğin başka bir `move`), orijinal yığın değiştirilir. Kod `$sourceStack->withCount($amount)` ile yeni bir nesne oluşturur ama `write` bu nesneyi referansla yazar.
*   **Düzeltme:** `$slots` dizisi üzerinde işlem yapmadan önce derin kopya (deep copy) yapılmalı ya da `$slots[$node[1]]` atamaları `$slots[$node[1]]->copy()` ile yapılmalı.

### Bulgu 8: Eşzamanlı isteklere karşı race condition
Kod tek bir `foreach` döngüsüyle işlenir.

*   **Girdi:** İki aynı anda gelen `ItemStackRequest` paketi aynı yuvaları etkiliyor.
*   **Hata:** `$slots` dizisi sunucu tarafında tek bir değişken. İstekler sırasıyla işlenir. İlk istek `$slots`'i değiştirir, ikinci istek değiştirilmiş `$slots` üzerinde çalışır. Sonuç, beklenen sıralı işlemden farklı olabilir. Örneğin, iki oyuncu aynı anda bir yuvadan taşımak istediğinde, birinin hareketinin diğerini geçersiz kılması veya eşya kaybı olabilir.
*   **Düzeltme:** İstekler bir mutex veya sıraya alınıp, `$slots` üzerinde işlem yapmadan önce kilitlenmeli.

## 4. Oturum ve İmleç Yaşam Döngüsü

### Bulgu 9: `forget()` sonrası sızıntı
`cursor` map'i oturum bazlı tutuluyor.

*   **Girdi:** Oyuncu `forget()` çağrısı yapmadan bağlantıyı keser ya da sunucu yeniden başlatılır.
*   **Hata:** `$this->cursor[$sessionId]` silinmez. Yeni bir bağlantı açıldığında eski `sessionId` ile eşleşme ihtimali düşük olsa da, aynı `sessionId` yeniden atanırsa eski im
