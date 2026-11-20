Sen PickaxeMP (PHP Bedrock sunucusu, protokol 2225 / 1.26.65) için kıdemli bir kod inceleme uzmanısın.

Phase 7 (oynanış döngüsü) yeni tamamlandı. Yalnızca şu dosyaları ve davranış sözleşmesini değerlendir:

- `src/PickaxeMP/connection/ConnectionPipeline.php`: `PacketBroadcaster` uygular (`sendTo`, `broadcastExcept`), `announceSpawn()` katılım sonrası iki yönlü `AddPlayer`, kapanışta `RemoveActor`, `PlayerAction(Respawn)` işleme, `UnsupportedPacketFieldException` yakalayıp yalnızca paketi düşürme.
- `src/PickaxeMP/player/BlockActionManager.php`: kırma (`CrackBlock` 18, `PredictDestroyBlock` 26, `CreativeDestroyBlock` 13), yerleştirme (`ItemUse` tip 2, hedef hava + kayıtlı blok), oyuncu başına saniyede 30 mutasyon.
- `src/PickaxeMP/player/PlayerAuthInput.php`: blok aksiyonlarını ve ItemUse işlemlerini uygular, `BlockChange`/`BlockActionResult` yayınlar.
- `src/PickaxeMP/player/Player.php`: oyuncu başına sağlık 0..20, `Dead` durumu, `canInteract()`.
- `src/PickaxeMP/world/World.php`: `getBlock`/`setBlock` asla chunk yüklemez; yüklü değilse `null`/`false`.

Kısıtlar: gerçek Bedrock istemcisi yok; `serverAuthBlockBreaking=false` (istemci tahminli kırma); metadata boş; `PlayerList`/`ActorEvent`/`LevelSoundEvent`/`MoveActorDelta` ertelendi.

Görev: 5 maddeden fazla olmayan, önceliklendirilmiş bir inceleme yaz. Her maddede (1) dosya/satır veya yöntem, (2) somut risk, (3) en küçük düzeltme olsun. Övgü ve özet yazma; yalnızca bulgular. Emin olmadığın noktayı "doğrulanamadı" olarak işaretle.
