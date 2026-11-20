# AGENTS.md — PickaxeMP Geliştirici Talimatları

Bu dosya OpenCode ve diğer kod ajanları içindir. Her oturumun başında okunur.
Buradaki bilgiler tahmin değil, depodaki gerçek dosyaların incelenmesiyle
(17 Kasım 2026) doğrulanmıştır. Kod değiştikçe bu dosyayı da güncelleyin.

---

## 1. Proje Kimliği

- **Proje:** PickaxeMP
- **Dil:** PHP (>= 8.2, `declare(strict_types=1)`)
- **Hedef:** Minecraft Bedrock Edition
- **Amaç:** PocketMine-MP benzeri, fakat kendi bağımsız mimarisine sahip
  bir sunucu yazılımı.
- **GitHub:** https://github.com/PickaxeMp/PickaxeMP
- **Dal:** `main` (tek commit: "İlk sürüm")
- **Lisans:** `LICENSE` dosyası henüz bir yer tutucudur; gerçek lisans metni
  yoktur. Dağıtım/lisans varsayımı yapmayın.

## 2. Mevcut Durum (ÖNEMLİ)

Phase 0–10.1 kod ve test düzeyinde tamamlanmıştır: şifreli oturum, dünya,
hareket, envanter, blok kırma/yerleştirme, oyuncu listesi, eşya varlıkları,
chunk akışı, oyuncu öznitelikleri/yetenekleri, tick döngüsü, açlıktan hasar
ve doğal can yenilenmesi çalışır.
**Gerçek Bedrock istemcisiyle uçtan uca doğrulama hâlâ YOKTUR**; oyun
"oynanabilir" ilan edilemez. Eklenti API'si yoktur.

Güncel ve ayrıntılı durum: `docs/DEVELOPMENT_STATE.md`

| Alan | Durum |
|---|---|
| `Server`, `ServerKernel` | Çalışıyor (boot/kapanış, scheduler tick) |
| `utils/Config`, `ServerConfig`, `Logger` | Çalışıyor |
| `scheduler/Scheduler` | Çalışıyor — tick tabanlı |
| `kernel/ModuleRegistry`, `DependencyGraph`, `ReloadManager` | Çalışıyor |
| `transport/*` + Rust sidecar | Derleniyor, `/v1/join` doğrulandı |
| `protocol/binary/*` | Çalışıyor — VarInt, reader/writer, UUID |
| `protocol/packet/*` | Çalışıyor — **44 paket**, registry, yön doğrulama |
| `protocol/compression/*` | Çalışıyor — zlib, eşik, bomb koruması |
| `protocol/ProtocolHandshake` | Çalışıyor — auth kapısı test edildi |
| `auth/jwt/*`, `auth/certificate/*`, `auth/AuthenticationEngine` | Çalışıyor |
| `auth/crypto/*` (ECDH, AES-CTR) | Çalışıyor — `ServerToClientHandshake` bağlı |
| `session/*` | Çalışıyor — 10 durum, geçiş kontrolü |
| `world/*` (blok, chunk, üretici, kayıt) | Çalışıyor — SubChunk v9, atomik kayıt |
| `nbt/*` | Çalışıyor — LE ağ NBT + FNV-1a |
| `connection/*` | Çalışıyor — şifreli boru hattı, dispatch, chunk akışı |
| `player/*` | Çalışıyor — hareket, envanter, sağlık, açlık, yetenekler, hayatta kalma zamanlayıcıları |
| `gameplay/GameplayTicker` | Çalışıyor — saat, açlık, açlıktan hasar/yenilenme, eşya fiziği, ömür |
| `entity/*` | Çalışıyor — oyuncu ve eşya varlıkları |
| `network/*` | Eski yer tutucu (protokol `protocol/` altında) |
| `plugin/*` | Boş iskelet |
| `tests/` | **1059 PHP + 44 Rust** test geçiyor |

Doğrulanmış çalışma durumu:

- `tools/php.cmd --ini` → `tools/php.ini` yüklenir.
- `tools/composer.cmd install` → `vendor/autoload.php` üretir.
- `php start.php --check` → çıkış kodu 0.
- `tools/composer.cmd lint` → 341 dosya, sözdizimi temiz.
- `tools/composer.cmd test` → **1059 test / 18230 assertion** geçiyor.
- `cargo test` (transport) → 44 test geçiyor.
- `GET /v1/join` (gerçek sidecar) → 200 + Mojang JSON şeması.
- ES384 DER↔JOSE dönüşümü gerçek OpenSSL ile doğrulandı.
- Mojang kök anahtarı yüklendi, curve `secp384r1` doğrulandı.
- **Gerçek Bedrock istemcisi bağlantısı test EDİLMEDİ.**
- `CLIENT_VERIFIED` = **BLOCKED**.
- Deri gönderimi `SerializedSkin::blank()`; gerçek PNG→RGBA yok.
- Açlıktan ölüm ve doğal yenilenme Phase 10.1'de eklendi; yemek tüketimi
  Phase 10.2'de eklendi (`ItemUse` + `FoodTable`). Ölümde `ActorEvent` ve
  yetenek geçişi hâlâ yok; canlı sunucuda `ItemRegistry` önyüklemesi yok.
- Oturum akışı kimlik doğrulanmadan `Playing`'e geçmez; bu testlerle korunuyor.

Bu tablo aksini göstermedikçe hiçbir özelliği "çalışıyor" diye raporlamayın.
README'deki işaretlenmemiş kutular hedeftir, durum bilgisi değildir.

## 3. Ortam ve Bilinen Tuzaklar

- Geliştirme makinesi: Windows, PowerShell. CI: Ubuntu.
- **Sistem geneli `php.ini` yok ve DEĞİŞTİRİLMEYECEK.** Proje düzeyinde
  `tools/php.ini` kullanılır; `extension_dir = "ext"` göreli yoldur.
- `php` yerine `tools/php.cmd` (Windows) / `tools/php.sh` (Linux) kullanın;
  aksi halde `openssl` ve `mbstring` yüklenmez.
- `composer` komutu yok. `tools/composer.cmd` / `tools/composer.sh` kullanın.
  `tools/composer.phar` `.gitignore` içindedir.
- Bu PHP derlemesinde `pcntl_*` ve `posix_*` **yoktur**. Süreç kontrolü
  `proc_open` + çıkış kodu + kontrol kanalı ile yapılmalıdır.
- `opcache` yok. `opcache_reset()` çağrılamaz; çağrılsa bile sınıf boşaltmaz.
- `stream_socket_server("udp://...")` UDP için çalışıyor (`ext-sockets`
  gerekmez).
- **Windows'ta `proc_open` pipe'ları bloklar.** `stream_set_blocking()` etkisiz
  kalır. Süreç çıktısını dosyaya yönlendirip dosyadan okuyun
  (`SidecarProcess` böyle yapar).
- **Rust sidecar** `transport/pickaxe-transport` altındadır. Bağımlılık sabit
  git revizyonuna pinlidir; `main` dalını takip etmeyin. `Cargo.lock` commit
  edilir.
- Sidecar ikilisi yoksa PHP entegrasyon testleri `markTestSkipped` ile atlanır;
  bu "geçti" sayılmaz.
- Kimlik PEM'i (`server-identity.pem`, `*.pem`, `*.key`) `.gitignore`
  içindedir; asla commit edilmez.
- **Protokol şeması sürüme bağımlıdır.** Şemayı `main` dalından değil,
  hedef etiketten indirin:
  `raw.githubusercontent.com/Mojang/bedrock-protocol-docs/v1.26.65/json/<Paket>.json`.
  `main` (2259) ile 2225 arasında `NetworkSettings` ve `ResourcePackStack`
  alanları **farklıdır**. Paket ID'sini veya alan sırasını tahmin etmeyin.
- Mojang "Primitives": varsayılan **little-endian**; yalnızca şemada
  `"Big Endian"` işaretli alanlar büyük-endian. `varint32/64` **ZigZag**'lıdır,
  `varuint32/64` değildir. String uzunluk öneki **varuint32** (ZigZag'sız) —
  ZigZag kullanmak uzunlukları bozar.
- Bilinmeyen paket kimliği **açık hata** üretir; sessizce atlanmaz.
- `ProtocolHandshake` kimlik doğrulanmadan `Ready` durumuna geçmez. Bu kapıyı
  testlerle koruyun; "geçici olarak" bile açmayın.
- Testlerde `mt_srand` sabit tohum kullanın; fuzz sonuçları tekrarlanabilir
  olmalı.
- **OpenSSL 3.x bu makinede `openssl.cnf` olmadan çalışmaz.** `openssl_pkey_new`
  şu hatayı verir: `configuration file routines::no such file`. Çözüm:
  `openssl_pkey_new(['config' => tools/openssl.cnf])` veya `OPENSSL_CONF`
  ortam değişkeni. `tools/php.cmd` bunu otomatik ayarlar; testlerde
  `CryptoFixture` açık `config` yolu geçirir.
- **JOSE ECDSA imzası ham `R||S`'dir (ES384 → 96 bayt); OpenSSL DER bekler.**
  Dönüşüm olmadan ES384 asla geçmez. `EcdsaSignature::rawToDer()` /
  `derToRaw()` kullanın; elle dönüşüm yazmayın.
- İmza doğrulamasında **orijinal `header.payload` baytları** kullanılır.
  JSON'u yeniden kodlamak imzayı bozar.
- `alg=none` ve bilinmeyen algoritmalar reddedilir (`JwtAlgorithm::fromHeader`).
- Zincirde **Mojang kök anahtarı yoksa** kimlik doğrulanmaz
  (`CertificateValidator`). Bu kapıyı gevşetmeyin.
- `Session::markAuthenticated()` tek kimlik atama yoludur; başka yol yoktur.
- **Blok ağ kimliği FNV-1a 32'dir** (BDS `block-network-ids-are-hashes=true`).
  Girdi: `{name, states}` little-endian NBT, anahtarlar sıralı. Sıralı runtime
  ID tablosu kullanmayın.
- **SubChunk tel biçimi sürüm 9'dur**: `version(9) | layerCount | int8 index |
  header | palette | bit-packed indices`. Hedef sürümde v8 kullanmayın.
- Dünya yüksekliği **−64..319**, subchunk indeksi **−4..19** (24 adet).
- `StartGame` 25 alanı **sabit sırada** yazar; alan atlamak istemci
  ayrıştırmasını kaydırır. `StartGamePacketTest` sırayı kilitler.
- Dünya kaydı **atomik** olmalı: geçici dosya → fsync → yedekle → rename.
  Bozuk dosya sessizce boş dünyaya dönüşmemeli; `WorldException` fırlatmalı.
- `fwrite()` çıktısı PHP output buffering'i atlar. Testlerde logger'ı
  `php://memory` akışına enjekte edin; `ob_start()` yeterli değildir.
- POSIX'e özgü fonksiyonları çekirdek kodda kullanmayın; platform kontrolü
  yapın.
- Ad alanı klasörleri **küçük harf** (`PickaxeMP\kernel`, `PickaxeMP\utils`).
  PSR-4 ile uyumludur; bu düzeni koruyun.

## 3.5 PICKAXEMP PHP ÇALIŞTIRMA KURALI

Windows üzerinde hiçbir PHP testini veya sunucu başlatma işlemini doğrudan
`php` komutuyla çalıştırma. Her zaman proje wrapper'ını kullan:

| İşlem | Yanlış | Doğru |
|---|---|---|
| Sunucu kontrolü | `php start.php --check` | `.\tools\php.cmd start.php --check` |
| Sunucu başlatma | `php start.php` | `.\tools\php.cmd start.php` |
| PHPUnit | `php vendor\bin\phpunit` | `.\tools\php.cmd vendor\bin\phpunit` |
| Composer lint | `composer lint` | `.\tools\composer.cmd lint` |
| Composer test | `composer test` | `.\tools\composer.cmd test` |

PHP eklentileri (`mbstring`, `openssl` vb.) ve OpenSSL yapılandırması
proje içindeki `tools/php.ini` ve `tools/openssl.cnf` üzerinden yüklenir.
Sistem genelindeki `php.ini` **değiştirilmez**.

## 4. Temel Kurallar

1. Öncelikle mevcut kaynak kodunu incele.
2. Mevcut sistemleri anlamadan kodları değiştirme.
3. PHP uyumluluğunu mevcut proje yapılandırmasına göre koru (PHP >= 8.2).
4. Modüler, nesne yönelimli ve genişletilebilir mimari kullan.
5. Gereksiz dosya oluşturma.
6. Çalışmayan özellikleri tamamlanmış gibi gösterme.
7. Her yeni özellik için test hazırla.
8. Hata durumlarını kontrol et.
9. Mevcut çalışan özellikleri bozma.
10. Gereksiz bağımlılık ekleme.
11. Kodları okunabilir ve sürdürülebilir yaz.
12. Kullanıcıdan izin almadan GitHub'a push veya commit yapma.
13. Sunucu verilerini ve gizli yapılandırmaları koru.

## 5. Geliştirme Öncelikleri

1. RakNet UDP altyapısı.
2. Bedrock Ping/Pong.
3. RakNet bağlantı yönetimi.
4. Bedrock paket sistemi.
5. Oyuncu giriş ve oturum sistemi.
6. Chunk ve dünya motoru.
7. Oyuncu hareketi.
8. Blok kırma ve koyma.
9. Envanter ve eşya motoru.
10. PHP Plugin API.
11. Event ve Command API.
12. Yönetim ve geliştirici araçları.

## 6. Çalışma Yöntemi

- Önce analiz yap.
- Sonra geliştirme planı oluştur.
- Bir seferde tek bir sistemi geliştir.
- Kodları test et.
- PHP sözdizimini kontrol et (`php -l`).
- Hataları düzelt.
- Değiştirilen dosyaları raporla.
- Sonraki aşamaya geçmeden önce kullanıcıya bilgi ver.

## 7. Mimari ve Kod Stili

- **Autoload:** PSR-4, `PickaxeMP\` → `src/PickaxeMP/`.
- **Stil:** PSR-12; 4 boşluk; `declare(strict_types=1)` zorunlu.
- **Tipler:** Parametre, dönüş ve özellik tipleri açıkça yazılmalı.
  Mümkünse `readonly` ve constructor promotion kullanın.
- **Sınıflar:** Tek sorumluluk; ağ, dünya, oyuncu ve eklenti katmanlarını
  birbirine sızdırmayın. Protokol kodunu oyun mantığından ayrı tutun.
- **Sihirli sayılar:** Protokol sabitlerini anlamlı `const` olarak tanımlayın.
- **Hata yönetimi:** Sessiz `@` bastırması yerine açık istisnalar ve
  doğrulama kullanın; paket girdisi her zaman güvenilmez kabul edilir.
- **Günlük:** `echo` yerine ortak bir logger soyutlaması hedefleyin; henüz
  logger yok, eklerken mevcut `echo` çıktısını bozmayın.
- **Yeni dosyalar:** Yalnızca gerçekten ihtiyaç varsa; boş "gelecek için"
  sınıfları eklemeyin.

## 8. Protokol ve Bağımsızlık Sınırı

- PickaxeMP bağımsız bir sunucu yazılımıdır. **PocketMine-MP'nin kaynak
  kodunu, sınıflarını veya bağımlılıklarını izinsiz kopyalamayın.**
- Genel, herkese açık protokol belgelerinden (RakNet ve Bedrock protocol
  açıklamaları) yararlanmak serbesttir; ancak kod kendi yazımımız olmalıdır.
- Üçüncü taraf bir paket eklemeden önce lisansını ve gerekliliğini
  kullanıcıya bildirin.
- Ağ paketlerinde boyut, okuma sınırı ve tip doğrulaması yapın; hatalı
  paket sunucuyu düşürmemeli.

## 9. Doğrulama ve Test

- Her değişiklikten sonra: `tools/composer.cmd lint` ve
  `tools/composer.cmd test`.
- Test edilemeyen davranışı "test edildi" saymayın; raporda **doğrulanmadı**
  yazın.
- Yeni özellik = kod + test + sözdizimi kontrolü + kısa rapor.
- Ağ davranışını soketten bağımsız test edilebilir tasarlayın (kodlama/çözme
  mantığı I/O'dan ayrı olmalı).
- Testlerde logger'ı `php://memory` akışına enjekte edin; `ob_start()` ise
  yaramaz çünkü `fwrite()` tamponu atlar.
- CI'yi bozacak platforma özgü komutlar eklemeyin (`find`, `xargs`, `rm`).
  Platformdan bağımsız `tools/lint.php` kullanın.
- `composer.lock` commit edilir; bağımlılık sürümleri sabitlenir.

## 10. Git Kuralları

- Kullanıcıdan açık izin olmadan `git commit`, `git push`, `git tag`,
  force-push veya geçmişi değiştiren işlem yapmayın.
- Kullanıcı işine ait dosyaları silmeyin veya üzerine yazmayın.
- Değişiklikleri dosya bazında raporlayın; hangi dosyanın neden değiştiğini
  yazın.

## 11. Görev Sonu Rapor Formatı

Her görev sonunda şunları yazın:

1. **Yapılanlar:** kısa madde listesi.
2. **Değişen dosyalar:** yol + ne değişti.
3. **Doğrulama:** çalıştırılan komutlar ve sonuçları (ör. `php -l`).
4. **Çalışan / çalışmayan:** dürüst ayrım; test edilmeyenler belirtilir.
5. **Sonraki adım:** tek, net öneri.

## 12. Tanım: "Bitti"

Bir iş ancak şu koşullarda bitmiş sayılır:

- Kod mevcut mimariye uyuyor ve PSR-12/PSR-4 kurallarını ihlal etmiyor.
- `php -l` temiz.
- Davranış test edildi veya test edilemediği açıkça yazıldı.
- README/AGENTS.md'de yanlış bir "çalışıyor" iddiası bırakılmadı.
- Kullanıcıya değişiklik özeti ve sonraki adım bildirildi.

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

When the user types `/graphify`, use the installed graphify skill or instructions before doing anything else.

Rules:
- When graphify-out/graph.json exists, run `graphify query "<question>"` before your first grep, search or source-file read for a codebase question, and put this rule in every subagent prompt that explores code. Read raw files only for lines the graph or an earlier read already located. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. When the answer is under a Markdown heading, `graphify section "<heading>"` prints that section's text, so the .md need not be opened. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- Dirty graphify-out/ files are expected after hooks or incremental updates; dirty graph files are not a reason to skip graphify. Only skip graphify if the task is about stale or incorrect graph output, or the user explicitly says not to use it.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
- A committed graphify-out/graph.json is rewritten whole on every build, so a large diff to it in a pull request is expected. Do not flag it as churn or suggest skipping the graph rebuild or its hooks.
