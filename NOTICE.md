PickaxeMP — üçüncü taraf bileşen bildirimleri
=============================================

Bu dosya, PickaxeMP'nin bağımlı olduğu üçüncü taraf bileşenlerin lisans
bildirimlerini içerir. PickaxeMP'nin kendi lisansı henüz kesinleşmemiştir
(bkz. LICENSE); bu dosya yalnızca bağımlılık bilgisidir.

--------------------------------------------------------------------------------
nethernet / nethernet-tokio
--------------------------------------------------------------------------------
Proje      : Bedrock Crustaceans — NetherNet
Kaynak     : https://github.com/bedrock-crustaceans/nethernet
Sürüm      : git revision ed552b0b60dc6f8d54e0d7d42a9c66a9b9bdfb99
Lisans     : Apache License 2.0
Kullanım   : transport/pickaxe-transport, NetherNet taşıyıcısı (WebRTC /
             HTTP signaling / kimlik doğrulama).
Not        : Upstream depoda NOTICE dosyası bulunmamaktadır; Apache-2.0
             Bölüm 4(d) uyarınca türev çalışmada ayrı NOTICE zorunluluğu
             doğmamaktadır. Apache-2.0 lisans metni upstream LICENSE
             dosyasındadır ve bağımlılık olarak korunur.

--------------------------------------------------------------------------------
Rust ekosistemi bağımlılıkları
--------------------------------------------------------------------------------
Aşağıdaki bileşenler Cargo.lock ile tam sürümlere sabitlenmiştir. Lisansları
ilgili crate'lerin kendi paketlerinde yer alır:

- tokio, tokio-util, bytes      : MIT
- serde, serde_json             : MIT / Apache-2.0
- tracing, tracing-subscriber   : MIT
- rand                          : MIT / Apache-2.0
- thiserror                     : MIT / Apache-2.0
- futures                       : MIT / Apache-2.0
- rustls, tokio-rustls          : MIT / Apache-2.0 / ISC
- hyper, hyper-util, http       : MIT
- reqwest                       : MIT / Apache-2.0
- p384, rsa, sha2, aes, hmac    : MIT / Apache-2.0
- ring                          : ISC

Tam liste ve kesin sürümler: transport/pickaxe-transport/Cargo.lock

--------------------------------------------------------------------------------
PHP bağımlılıkları
--------------------------------------------------------------------------------
- phpunit/phpunit : BSD-3-Clause (yalnızca geliştirme; çalışma zamanında yok)

Tam liste: composer.lock

--------------------------------------------------------------------------------
Uyarı
--------------------------------------------------------------------------------
PickaxeMP, Mojang AB veya Microsoft ile bağlantılı değildir. "Minecraft"
ilgili sahiplerinin tescilli markasıdır.
