<?php

declare(strict_types=1);

namespace PickaxeMP\player;

use PickaxeMP\item\ItemRegistry;
use PickaxeMP\item\ItemStack;
use PickaxeMP\protocol\packet\types\HandSlot;
use PickaxeMP\protocol\packet\types\ItemUseActionType;
use PickaxeMP\protocol\packet\types\ItemUseInventoryTransaction;
use PickaxeMP\utils\Logger;

/**
 * Yemek tüketimi — sunucu yetkilidir ve ağdan bağımsızdır.
 *
 * ## Sözleşme
 *
 * - Yığın **envanterden** okunur; istemcinin `item` tanımlayıcısına
 *   güvenilmez.
 * - Yalnızca **seçili hotbar** yuvasındaki, **ana eldeki** yemek yenir.
 * - Ağ kimliği kayıtlı olmayan eşya tüketilmez: `InventorySlot` bildirimi
 *   gönderilemez, sunucu-istemci ayrışması olur.
 * - Tok oyuncu (`hunger >= MAX_HUNGER`) yiyemez.
 * - Ölü / henüz spawn olmamış oyuncu yiyemez (`Player::canInteract`).
 * - İki tüketim arasında {@see EAT_COOLDOWN_MS} vardır; oturum başına
 *   izlenir, `forget()` ile silinir.
 *
 * Bu sınıf **paket üretmez**; yalnızca sonucu döndürür. Bildirim
 * (`InventorySlot` + `UpdateAttributes`) çağıranın işidir.
 */
final class FoodConsumptionManager
{
    /** İki tüketim arasındaki en kısa süre (ms). */
    public const EAT_COOLDOWN_MS = 1600;

    /** @var array<string, int> oturum kimliği => son tüketim zamanı (ms) */
    private array $lastEatAt = [];

    /**
     * @param (\Closure(): int)|null $clock milisaniye döndüren saat; test
     *        edilebilirlik için enjekte edilir.
     */
    public function __construct(
        private readonly Logger $logger,
        private readonly FoodTable $table,
        private readonly ?ItemRegistry $registry,
        private readonly ?\Closure $clock = null
    ) {
    }

    public function consume(Player $player, ItemUseInventoryTransaction $transaction): ?FoodConsumptionResult
    {
        if ($transaction->actionType !== ItemUseActionType::Use) {
            return null;
        }

        if ($transaction->hand !== HandSlot::MainHand) {
            return null;
        }

        if (!$player->canInteract()) {
            return null;
        }

        $slot = $player->selectedHotbarSlot();

        // İstemci başka bir yuvayı işaret ediyorsa sunucu seçimi geçerlidir;
        // uyuşmazlık ayrışma/istismar işaretidir.
        if ($transaction->slot !== $slot) {
            return null;
        }

        $stack = $player->inventory()->getSlot($slot);

        if ($stack === null || $stack->isEmpty()) {
            return null;
        }

        $type = $stack->type();

        $nutrition = $this->table->nutritionFor($type);

        if ($nutrition === null) {
            return null;
        }

        if ($this->registry === null || !$this->registry->has($type)) {
            $this->logger->warning('Yemek reddedildi: esya ag kimligi kayitli degil.', [
                'player' => $player->identity->displayName,
                'item' => $stack->type(),
            ]);

            return null;
        }

        if ($player->attributes()->hunger() >= PlayerAttributes::MAX_HUNGER) {
            return null;
        }

        $now = $this->now();
        $last = $this->lastEatAt[$player->sessionId] ?? null;

        if ($last !== null && $now - $last < self::EAT_COOLDOWN_MS) {
            return null;
        }

        // **Önce** envanter düşer: `removeFromSlot` ön koşulları tutmazsa
        // istisna fırlatır ve oyuncu besini bedavaya almış olmaz. `eat()`
        // istisna fırlatmaz (değerler `FoodNutrition` içinde doğrulanmıştır,
        // aşım üst sınıra kırpılır), bu yüzden "eşya gitti ama açlık gelmedi"
        // durumu oluşamaz.
        $player->inventory()->removeFromSlot($slot, 1);
        $player->eat($nutrition->hunger, $nutrition->saturation);

        $this->lastEatAt[$player->sessionId] = $now;

        // Kalan miktar **envanterden** okunur: istemcinin bildirdiği sayıya
        // güvenilmez, tüketim sonrası gerçek durum esas alınır.
        $remaining = $player->inventory()->getSlot($slot);

        if ($remaining === null) {
            return FoodConsumptionResult::emptySlot($slot, $nutrition, $type);
        }

        return new FoodConsumptionResult($slot, $remaining, $nutrition, $type);
    }

    /** Oturum kapandığında soğuma kaydını siler. */
    public function forget(string $sessionId): void
    {
        unset($this->lastEatAt[$sessionId]);
    }

    private function now(): int
    {
        if ($this->clock !== null) {
            return ($this->clock)();
        }

        return (int) floor(microtime(true) * 1000.0);
    }
}
