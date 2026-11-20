# ADR-0003 — Reload'ın dürüst sınırları

- **Durum:** Kabul edildi (Phase 0)
- **Tarih:** 2026-11-17

## Bağlam

PocketMine/Java sunucularındaki `/reload` beklentisi, "kodu değiştir, çalışan
sunucu yeni kodu kullanmaya başlasın" şeklinde anlaşılıyor. PHP'de bu
**mümkün değildir**.

Doğrulanan gerçekler:

```
php -r 'class A{} class A{}'
→ Fatal error: Cannot declare class A, because the name is already in use
```

- Aynı süreçte sınıf yeniden tanımlanamaz; bu hata `try/catch` ile
  yakalanamaz.
- `opcache_reset()` bu derlemede mevcut değil; mevcut olsaydı bile yüklü
  sınıfları bellekten kaldırmazdı.
- Windows'ta `pcntl_*` / `posix_*` yoktur; süreç kontrolü `proc_open` +
  çıkış kodu + kontrol kanalı ile yapılmalıdır.

## Karar

`/reload` **yalnızca şunları** yeniler:

| Katman | Yenilenir mi |
|---|---|
| Yapılandırma değerleri | Evet |
| Dil/mesaj dosyaları | Evet |
| İzin tanımları | Evet |
| Komut kayıtları | Evet |
| Zamanlayıcı görevleri | Kısmen (yeni/iptal) |
| Eklenti config'leri | Evet (eklenti sözleşmeye uyarsa) |
| Eklenti enable/disable | Kısmen (aynı yüklü sınıflarla) |
| Eklenti kaynak kodu | **Hayır** |
| Çekirdek sınıf kodu | **Hayır** |
| Dünya verisi | Evet (korumalı) |
| Veritabanı bağlantısı | Kısmen (yeni bağlantı + sağlık kontrolü) |
| Resource pack | Kısmen (yeniden gönderim gerekir) |

Kaynak kodu değişikliği için **restart** gerekir.

## Sözleşme

```php
interface ReloadableInterface extends ModuleInterface
{
    public function prepareReload(ReloadRequest $request): ReloadPlan;
    public function validateReload(ReloadPlan $plan): void;
    public function commitReload(ReloadPlan $plan): void;
    public function rollbackReload(ReloadPlan $plan): void;
}
```

Sıra: `prepare` → `validate` → `commit`. Herhangi bir adımda hata çıkarsa
commit edilmiş modüller ters sırayla `rollback` edilir.

## Dürüst sınırlar

1. **Atomiklik kısmidir.** `rollbackReload()` telafi edici bir işlemdir, ACID
   geri alma değildir.
2. **`commitReload` içinde geri alınamaz yan etki yasaktır** (ağ isteği, dosya
   silme, ödeme). Aksi halde rollback yalan olur.
3. **Zaman aşımı işbirliklidir.** Adımlar arasında ölçülür; çalışan bir PHP
   fonksiyonu zorla kesilemez.
4. **Eşzamanlılık:** süreç içi bayrak + `flock` ile aynı anda tek reload.
5. **Hatalı eklenti sunucuyu düşürmez:** her modül çağrısı ayrı yakalanır.

## Sonuçlar

- README ve kullanıcı dokümanında "hot code reload" ifadesi **kullanılmaz**.
- Restart, Supervisor tarafından yönetilir (ayrı ADR, Phase 7).
- Bu ADR, Phase 7 Reload Motoru'nun temelini oluşturur; Phase 0'da yalnızca
  sözleşme ve çalışan minimal `ReloadManager` mevcuttur.
