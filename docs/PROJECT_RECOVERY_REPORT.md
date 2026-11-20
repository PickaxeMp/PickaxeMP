# PickaxeMP — PROJECT RECOVERY REPORT

**Tarih:** 2026-11-18 22:40

## Durum Özeti
- Phase 3 ECDH / ServerToClientHandshake / AES-CTR entegrasyonu tamamlandı.
- `ConnectionPipeline` artık login başarılı olduğunda ECDH anahtar çifti üretir, `ServerToClientHandshake` JWT gönderir, istemciden `ClientToServerHandshake` alındığında şifrelemeyi etkinleştirir.
- `PipelineConnection` yeni alanlar: `ecdh`, `handshakeSalt`, `cipher`, `encryptionEstablished`.
- Gönderimde ServerToClientHandshake öncesi paketler açık gönderilir; sonrası AES-CTR ile şifrelenir.
- Alımda şifreleme aktifse paketler `AesCtrCipher::decrypt` ile çözülür.

## Gerçekçi Sınırlar
- Gerçek Bedrock istemcisiyle test edilmedi.
- Paket çerçevesi şifreli olduğu için paket kimliği çözme akışı pratikte daha karmaşık; mevcut kod basitleştirilmiştir.
- `extraData` skin JWT hala eksik.

## Sonraki Adımlar
- PlayerAuthInput, MovementManager, BlockBreakManager iskeletleri.
- StartGame/LevelChunk paketlerini şifreli oturum sonrası gönderim doğrulaması.
- Gerçek istemci testi ortamı kurulumu.

## Dosya Değişiklikleri
- `src/PickaxeMP/connection/PipelineConnection.php` — şifreleme durumu eklendi
- `src/PickaxeMP/connection/ConnectionPipeline.php` — ECDH başlatma, handshake, şifreleme akışı
- `docs/DEVELOPMENT_STATE.md` — güncellendi
