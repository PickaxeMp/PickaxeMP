# NetherNet taşıyıcısı — çalıştırma notları

Bu belge `transport/pickaxe-transport` sidecar'ının nasıl derlendiğini,
çalıştırıldığını ve PHP çekirdeğiyle nasıl konuştuğunu açıklar.

## 1. Derleme

```bash
cd transport/pickaxe-transport
cargo build --release
```

Çıktı:

- Windows: `transport/pickaxe-transport/target/release/pickaxe-transport.exe`
- Linux  : `transport/pickaxe-transport/target/release/pickaxe-transport`

Bağımlılık **sabit git revizyonuna** pinlidir:

```
nethernet-tokio = { git = "https://github.com/bedrock-crustaceans/nethernet.git",
                    rev = "ed552b0b60dc6f8d54e0d7d42a9c66a9b9bdfb99" }
```

`Cargo.lock` commit edilir. `main` dalı **asla** takip edilmez.

## 2. Doğrulama komutları

```bash
cargo fmt --all --check
cargo check --all-targets
cargo test
cargo build --release
```

## 3. Elle çalıştırma

```bash
./pickaxe-transport \
  --signaling 0.0.0.0:19132 \
  --identity-path run/server-identity.pem \
  --identity-domain ornek.example \
  --server-name "PickaxeMP" \
  --level-name world \
  --protocol-version 2225 \
  --game-version 1.26.60 \
  --max-players 20
```

Sidecar stdout'a iki satır yazar:

```
PICKAXE_IPC <port> <token>
PICKAXE_SIGNALING <addr>
```

- `PICKAXE_IPC`: PHP çekirdeğinin bağlanacağı localhost portu ve tek seferlik
  token. Token diske yazılmaz.
- `PICKAXE_SIGNALING`: gerçek signaling adresi (`:0` verildiğinde gerçek port).

## 4. Önemli bayraklar

| Bayrak | Anlam |
|---|---|
| `--signaling <addr>` | HTTP signaling adresi (TCP). Varsayılan `0.0.0.0:19132` |
| `--media <addr>` | WebRTC UDP adresi. `none` = oturum başına geçici port |
| `--identity-path <path>` | Sunucu kimlik PEM'i. Yoksa üretilir, sonra **aynı kalır** |
| `--identity-domain <domain>` | İlk-kullanım isteminde gösterilen operatör alanı |
| `--tls-cert` / `--tls-key` | HTTPS signaling. İkisi birlikte gerekir |
| `--protocol-version <n>` | Bedrock protokol sürümü (varsayılan 2225 / 1.26.60) |
| `--no-token-verification` | **Yalnızca yerel test.** Oyuncu sahipliği doğrulanmaz |

## 5. Güvenlik notları (dürüst liste)

1. `allow_unsigned_answers` **hiçbir zaman** açılmaz: vanilla istemci imzasız
   SDP answer'ı reddeder (Mojang rehberi §5.2).
2. `token_trust` asla `None` yapılmaz; en zayıf hâli `TokenTrust::Any`'dir ve o
   bile token'ı DTLS sertifikasına bağlar. `--no-token-verification` yalnızca
   test içindir ve log'a uyarı yazar.
3. IPC yalnızca `127.0.0.1` üzerinde dinler; loopback dışı bağlantı reddedilir.
4. IPC ilk çerçevesi `HELLO_ACK` + token olmalıdır; aksi hâlde bağlantı kapanır.
5. Tek çerçeve en fazla 1 MiB yüktür; aşan çerçeve protokol hatasıdır.
6. Kimlik PEM'i ve TLS anahtarları `.gitignore` içindedir.
7. Token hiçbir log satırına yazılmaz.

## 6. Gerçek Bedrock istemcisi testi için gerekenler

Bu test **henüz yapılmadı**. Yapılabilmesi için:

1. Windows/macOS/iOS/Android üzerinde **Bedrock 26.60 veya üzeri** istemci.
2. İstemcinin sunucuya **ağ üzerinden** erişebilmesi:
   - Signaling TCP portu (varsayılan 19132) açık olmalı.
   - WebRTC UDP port aralığı yönlendirilmiş olmalı (`--media`).
   - Aynı makinede test edilecekse `127.0.0.1` yerine LAN IP'si kullanılmalı;
     istemci loopback adresine bağlanmayı reddedebilir.
3. `--no-token-verification` **kullanılmamalı**: gerçek oyuncu doğrulaması test
   edilmeli. Bu, Minecraft auth servisine ağ erişimi gerektirir.
4. HTTPS signaling için geçerli sertifika (aksi hâlde istemci ilk-kullanım
   onayı gösterir).
5. Sunucu listesine `IP:port` olarak eklenip `GET /v1/join` yanıtındaki
   isim/oyuncu sayısının görünmesi doğrulanmalı.

Doğrulandığında `docs/DEVELOPMENT_STATE.md` güncellenmelidir. O ana kadar
Phase 1 "tamamlandı" sayılmaz.
