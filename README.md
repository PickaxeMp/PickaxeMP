# PickaxeMP

> Minecraft: Bedrock Edition için PHP ile yazılan açık kaynaklı sunucu yazılımı.

**Durum:** 🚧 Geliştirme aşamasında, henüz kullanıma hazır değil.

## Hedefler
- [ ] RakNet üzerinden ping/pong (sunucu listesinde görünme)
- [ ] Oyuncu girişi (login) ve oturum
- [ ] Düz (flat) dünya gönderme
- [ ] Hareket ve blok koyma/kırma
- [ ] Eklenti (plugin) sistemi
- [ ] Komutlar ve izinler

## Gereksinimler
- PHP 8.2+
- Composer

## Kurulum
```bash
git clone https://github.com/KULLANICI_ADINIZ/PickaxeMP.git
cd PickaxeMP
composer install
php start.php
```

## Dizin yapısı
| Klasör | Açıklama |
|---|---|
| `src/PickaxeMP/network` | RakNet ve paket işleme |
| `src/PickaxeMP/world` | Dünya, chunk, blok |
| `src/PickaxeMP/player` | Oyuncu oturumu |
| `src/PickaxeMP/plugin` | Eklenti yükleyici |
| `src/PickaxeMP/command` | Komut sistemi |
| `src/PickaxeMP/event` | Olay sistemi |
| `plugins/` | Eklentiler |
| `resources/` | Varsayılan ayar dosyaları |

## Katkı
Bkz. [CONTRIBUTING.md](CONTRIBUTING.md).

## Lisans
Bkz. [LICENSE](LICENSE).

> PickaxeMP, Mojang AB veya Microsoft ile bağlantılı değildir.
