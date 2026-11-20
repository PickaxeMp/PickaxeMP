# PickaxeMP — Kurulum & Kullanım (Windows / PowerShell)

## 1. Önkoşul

Bu depoda sistem genelinde `php.ini` **yoktur ve değiştirilmez**.  
Tüm PHP işlemleri proje düzeyindeki `tools/php.cmd` wrapper'ı ya da
kök dizindeki `start.cmd` aracılığıyla yapılır.

| Dosya | Amaç |
|-------|------|
| `tools/php.cmd` | PHP'yi `tools/php.ini` ile çalıştırır (mbstring, openssl, vb.) |
| `tools/composer.cmd` | Composer'ı `tools/php.ini` ile çalıştırır |
| `start.cmd` | Sunucuyu başlatır (`tools/php.ini` yapılandırmasıyla) |

## 2. Bağımlılıkları yükle

PowerShell'de PickaxeMP klasöründe:

```powershell
.\tools\composer.cmd install
```

Bu komut `vendor/autoload.php`'ı oluşturur (veya günceller).  
Sunucu bu dosya olmadan başlayamaz.

## 3. Sunucu bootunu doğrula

```powershell
.\start.cmd --check
```

Beklenen çıktı:

```
[...] PickaxeMP 0.1.0-dev baslatiliyor...
[...] Cekirdek baslatildi.
[...] Yapilandirma yuklendi.
[...] Boot kontrolu tamamlandi (--check).
[...] Cekirdek kapatildi.
```

Çıkış kodu `0` olmalı.

## 4. Sunucuyu başlat

```powershell
.\start.cmd
```

Sunucu `19132` portunda çalışmaya başlar ve sonsuz döngüde kalır.  
**Durdurmak için:** konsolda `CTRL+C` tuşlarına bas.

> Not: Phase 0'da ağ/oyuncu/dunya sistemleri henüz yok, bu yüzden
> `Phase 0: ag, oyuncu, dunya ve eklenti sistemleri henuz YOK.` uyarısı
> normaldir.

## 5. PHPUnit testleri

```powershell
.\tools\php.cmd vendor\bin\phpunit
```

## 6. Kod sözdizimi denetimi

```powershell
.\tools\composer.cmd lint
```

## Önemli kurallar

- **Asla** doğrudan `php start.php` çalıştırma (sistem `php.ini`'nde `mbstring`/`openssl` yok, çöker).
- Her zaman `.\start.cmd` veya `.\tools\php.cmd ...` kullan.
- Sistem `php.ini` **değiştirilmez**.
