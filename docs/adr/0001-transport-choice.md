# ADR-0001 — NetherNet taşıyıcı seçimi

- **Durum:** Kabul edildi (Phase 0)
- **Tarih:** 2026-11-17
- **Karar veren:** Proje sahibi + geliştirme ajanı

## Bağlam

Minecraft: Bedrock Edition, 26.60 sürümüyle birlikte oyuncu bağlantılarında
RakNet'i bıraktı. Güncel istemciler **NetherNet** (WebRTC + HTTP signaling)
kullanıyor. Sunucu listesindeki MOTD ve oyuncu sayısı da artık signaling
üzerinden alınıyor.

Kaynaklar:

- Bedrock Wiki — RakNet sayfası: "The 26.60 client ... no longer connects over RakNet"
- Minecraft Wiki — NetherNet: 26.50'den beri BDS varsayılanı, 26.60'ta RakNet deprecated
- GeyserMC blog (8 Ekim 2026): istemciler RakNet üzerinden bağlanmayı bırakıyor
- Mojang resmî NetherNet HTTP Signaling rehberi

NetherNet'in gerektirdikleri: ICE, DTLS, SCTP, WebRTC DataChannel, JWT tabanlı
kimlik doğrulama (ES384), TLS'li veya TOFU'lu signaling.

## Değerlendirilen seçenekler

### A) PHP + ext-rtc + amber-pm/nethernet

- `ext-rtc` yalnızca **PHP 8.4.27 / 8.5.12** için derlenmiş; proje PHP 8.2.
- GitHub Releases **boş**; hazır ikili yok.
- C++20 + libdatachannel derleme zinciri gerekir.
- `amber-pm/nethernet` lisansı **LGPL-3.0-only**.
- **Bugün kurulamaz.** Ağırlıklı puan: 1.95 / 5.

### B) PHP + Go sidecar (`df-mc/go-nethernet`)

- MIT, saf Go, cgo yok, tek ikili.
- WebRTC katmanı hazır (pion).
- Ancak Mojang'ın `/v1/join` HTTP signaling **sunucusu** ve sunucu kimlik
  imzalaması bizde kalıyor.
- README: "not yet feature-complete".
- **Ağırlıklı puan: 4.05 / 5.**

### C) PHP + Rust sidecar (`bedrock-crustaceans/nethernet`)

- **Apache-2.0** (izin verici, patent maddesi var, copyleft yükü yok).
- `HttpSignalingServer`, `NetherServer`, `NetherClient`, kimlik assertions,
  TLS, trusted proxy, bağlantı limitleri hazır.
- Mojang rehberinin §4 (HTTP signaling) ve §5 (identity) gereksinimlerini
  doğrudan karşılıyor.
- Sans-IO çekirdek sayesinde test edilebilirliği yüksek.
- **Ağırlıklı puan: 4.55 / 5.**

## Karar

**Seçenek C: PHP oyun motoru + Rust NetherNet sidecar.**

PHP oyun mantığının tek sahibi kalır; Rust yalnızca taşıyıcıyı üstlenir.
İki süreç arasında yerel, kimlik doğrulamalı bir IPC köprüsü kullanılır.

Phase 1'de uygulandı ve derlendi:

```
transport/pickaxe-transport/
  nethernet-tokio = { git = "...", rev = "ed552b0b60dc6f8d54e0d7d42a9c66a9b9bdfb99" }
```

Sabit revizyon kullanılır; `Cargo.lock` commit edilir; `main` dalı takip
edilmez.

Doğrulanan gerçek davranış (taklit değil, kütüphanenin kendisi):

```
GET /v1/join → 200 {"name":"PickaxeMP Server","protocol":2225,
                    "version":"1.26.60","level":"world",
                    "players":0,"maxPlayers":20,"gameType":0}
```

Uygulama notları: `docs/runbooks/nethernet-sidecar.md`.

## Sonuçlar

- PHP 8.2'de kalınabilir; PHP yükseltmesi gerekmez.
- Sidecar ayrı sürüm/derleme hattı gerektirir (`transport/` klasörü).
- İki süreçli mimari: Supervisor her ikisini birlikte yönetmelidir.
- IPC gecikmesi ölçülmelidir; hedef < 1 ms / mesaj (loopback).
- RakNet yalnızca ayrı, isteğe bağlı bir **legacy** taşıyıcı olarak tutulabilir;
  ana yol değildir.

## Riskler

- Sidecar bakım yükü (Rust ekosistemi).
- NetherNet spec değişebilir → sürüm pinlenmeli, sözleşme testleri yazılmalı.
- Sidecar çökerse PHP çekirdeği ayakta kalmalı ve yeniden bağlanmalı.
