# ADR-0002 — PHP sürümü ve proje düzeyi araç zinciri

- **Durum:** Kabul edildi (Phase 0)
- **Tarih:** 2026-11-17

## Bağlam

Geliştirme makinesinde PHP 8.2.35 (ZTS, x64, Windows) kurulu. Sistem genelinde
`php.ini` **yok**; bu yüzden `openssl`, `mbstring`, `fileinfo`, `curl`, `zip`
kapalı görünüyordu ve `extension_dir` var olmayan `C:\php\ext` yolunu
gösteriyordu.

Composer kurulu değildi. Proje `vendor/autoload.php` beklediği için
`php start.php` fatal error veriyordu.

## Karar

1. **PHP 8.2 korunur.** Sidecar mimarisi (ADR-0001) PHP yükseltmesi
   gerektirmez. `composer.json` içinde `php: >=8.2` sabittir.
2. **Sistem geneli `php.ini` DEĞİŞTİRİLMEZ.** Bunun yerine proje düzeyinde
   `tools/php.ini` kullanılır. `extension_dir = "ext"` göreli yol olduğu için
   makineye özgü mutlak yol saklanmaz.
3. **Composer proje içine kurulur:** `tools/composer.phar`, resmî kurulum
   betiği ve SHA-384 imza doğrulaması ile. `tools/composer.cmd` ve
   `tools/composer.sh` sarmalayıcıları `php -c tools/php.ini` kullanır.
4. **PHPUnit 11** `require-dev` altında; `composer.lock` commit edilir.

## Gerekçe

- Global ayar değişikliği başka projeleri etkileyebilir; geri alınması zordur.
- Proje düzeyi yapılandırma CI'da ve başka makinelerde tekrarlanabilir.
- Göreli `extension_dir` Windows/Linux taşınabilirliğini artırır.

## Sonuçlar

- Geliştiriciler `php` yerine `tools/php.cmd` (Windows) veya `tools/php.sh`
  kullanmalıdır.
- CI, `shivammathur/setup-php` ile eklentileri kendisi açar; `tools/php.ini`
  CI'da gerekmez.
- `tools/composer.phar` sürüm kontrolüne **girmez** (`.gitignore`).

## Doğrulama

- `tools/php.cmd --ini` → `tools/php.ini` yüklü.
- `tools/php.cmd -m` → `openssl`, `mbstring`, `fileinfo`, `curl`, `zip` var.
- `php start.php --check` → çıkış kodu 0.
