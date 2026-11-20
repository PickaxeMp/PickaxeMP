Phase 10.2 (yemek tüketimi) yeni tamamlandı. Amaç: envanterdeki yenilebilir eşyayı tüketip açlık/doygunluk eklemek.

Bağlam:
- `ItemUseInventoryTransaction` (`Action Type = Use`) iki giriş yolundan da gelir: `PlayerAuthInput` bit 32 (`PERFORM_ITEM_INTERACTION`) ve `InventoryTransaction` paketinin `itemUse` alanı. İkisi de `PlayerAuthInput::handleItemUse()` içinde tek noktada yönlendirilir; `Place` eylemi eskisi gibi `BlockActionManager::place()` yoluna gider.
- `FoodTable` (37 eşya) kimlik -> `FoodNutrition(hunger, saturation)`. Bilinmeyen eşya `null` döner.
- `FoodConsumptionManager::consume(Player, ItemUseInventoryTransaction): ?FoodConsumptionResult` kuralları uygular: `actionType === Use`, `hand === MainHand`, `canInteract()`, `transaction->slot === selectedHotbarSlot()`, yuva dolu, eşya `FoodTable`'da var, `ItemRegistry` kaydı var, `hunger < 20`, oturum başına 1600 ms soğuma. Sonra `Player::eat()` + `inventory()->removeFromSlot($slot, 1)`.
- `PlayerAuthInput` sonucu `InventorySlotPacket` (konteyner 0) + `UpdateAttributesPacket` olarak yollar.
- `ConnectionPipeline::onConnectionClosed()` -> `FoodConsumptionManager::forget($sessionId)`.
- Testler: `tests/unit/player/FoodTableTest.php`, `tests/unit/player/FoodConsumptionManagerTest.php`, entegrasyon `testEatingFoodRestoresHungerAndReportsInventorySlot` + `testEatingWhenFullIsRejectedWithoutPackets`. Depo geneli: 954 test / 11944 assertion geçiyor.

Kod (özet, gerçek dosyadan):

```php
public function consume(Player $player, ItemUseInventoryTransaction $transaction): ?FoodConsumptionResult
{
    if ($transaction->actionType !== ItemUseActionType::Use) { return null; }
    if ($transaction->hand !== HandSlot::MainHand) { return null; }
    if (!$player->canInteract()) { return null; }

    $slot = $player->selectedHotbarSlot();
    if ($transaction->slot !== $slot) { return null; }

    $stack = $player->inventory()->getSlot($slot);
    if ($stack === null || $stack->isEmpty()) { return null; }

    $nutrition = $this->table->nutritionFor($stack->type());
    if ($nutrition === null) { return null; }

    if ($this->registry === null || !$this->registry->has($stack->type())) {
        $this->logger->warning('Yemek reddedildi: esya ag kimligi kayitli degil.', [...]);
        return null;
    }

    if ($player->attributes()->hunger() >= PlayerAttributes::MAX_HUNGER) { return null; }

    $now = $this->now();
    $last = $this->lastEatAt[$player->sessionId] ?? null;
    if ($last !== null && $now - $last < self::EAT_COOLDOWN_MS) { return null; }

    $player->eat($nutrition->hunger, $nutrition->saturation);
    $player->inventory()->removeFromSlot($slot, 1);
    $this->lastEatAt[$player->sessionId] = $now;

    $remaining = $player->inventory()->getSlot($slot);
    if ($remaining === null) { return FoodConsumptionResult::emptySlot($slot, $nutrition); }
    return new FoodConsumptionResult($slot, $remaining, $nutrition);
}
```

```php
private function handleItemUse(Player $player, ItemUseInventoryTransaction $use, World $world, callable $send, ?callable $broadcast): void
{
    if ($this->food !== null && $use->actionType === ItemUseActionType::Use) {
        $result = $this->food->consume($player, $use);
        if ($result === null) { return; }

        $descriptor = $this->descriptorFor($result->remaining);
        if ($descriptor !== null) {
            $send(new InventorySlotPacket(containerId: self::CONTAINER_INVENTORY, slot: $result->slot, item: $descriptor));
        }
        $send(new UpdateAttributesPacket($player->runtimeId, $player->attributeList()));
        return;
    }
    $this->publish($this->blockActions->place($player, $use, $world), $send, $broadcast);
}
```

Bilinen dürüst sınırlar (bunları "hata" olarak raporlama, bağlam olarak ver):
- `ItemStackRequest` (147) hâlâ kayıtsız; gerçek istemci yemek yemeği bu yolla bildirebilir.
- Sunucu önyüklemesinde canlı `ItemRegistry` yok; yemek kimlikleri yalnızca envanter içeriğiyle var olur, kayıtsız eşya reddedilir.
- Zehir/durum etkisi, kase/şişe bırakma, yaratıcı mod davranışı yok.
- Gerçek Bedrock istemcisiyle doğrulama yok.

Lütfen yalnızca **gerçek hatalara** odaklan: güvenlik (eşya çoğaltma, yetkisiz tüketim), veri tutarlılığı (envanter/açlık ayrışması), protokol uyumu (paket alanları/sırası), yarış durumları ve sınır değerleri (0/20, 1600 ms, son eşya, ölü oyuncu). Her bulgu için: dosya/satır, neden hata olduğu, önerilen düzeltme. Yanlış pozitif üretmektense "bulgu yok" demeyi tercih et. Yanıtı Türkçe ve madde madde ver.
