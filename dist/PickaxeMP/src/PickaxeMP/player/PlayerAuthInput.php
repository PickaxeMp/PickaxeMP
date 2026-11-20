<?php

declare(strict_types=1);

namespace PickaxeMP\player;

use PickaxeMP\entity\ItemActorManager;
use PickaxeMP\item\ItemDescriptorFactory;
use PickaxeMP\item\ItemRegistry;
use PickaxeMP\item\ItemStack;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\packets\InventorySlotPacket;
use PickaxeMP\protocol\packet\packets\InventoryTransactionPacket;
use PickaxeMP\protocol\packet\packets\LevelSoundEventPacket;
use PickaxeMP\protocol\packet\packets\PlayerActionPacket;
use PickaxeMP\protocol\packet\packets\PlayerAuthInputPacket;
use PickaxeMP\protocol\packet\packets\TakeItemActorPacket;
use PickaxeMP\protocol\packet\packets\UpdateAttributesPacket;
use PickaxeMP\protocol\packet\types\InventorySource;
use PickaxeMP\protocol\packet\types\ItemUseActionType;
use PickaxeMP\protocol\packet\types\ItemUseInventoryTransaction;
use PickaxeMP\protocol\packet\types\NetworkItemStackDescriptor;
use PickaxeMP\protocol\packet\types\Vec3;
use PickaxeMP\utils\Logger;
use PickaxeMP\world\World;

/**
 * `PlayerAuthInput` akışını koordine eder.
 *
 * Hareket, blok kırma (blok aksiyonları), blok yerleştirme (item use) ve
 * yemek tüketimi (Phase 10.2) işlerini ilgili yöneticilere yönlendirir.
 *
 * ## İki giden yol
 *
 * - `$send`: **yalnızca bu oyuncuya** giden paketler (pozisyon düzeltmesi,
 *   envanter yuvası bildirimi).
 * - `$broadcast`: **diğer oyunculara** giden dünya değişiklikleri
 *   (`UpdateBlock`). `null` ise yayın yapılmaz (birim testleri).
 */
final class PlayerAuthInput
{
    /** Oyuncu envanteri konteyner kimliği. */
    public const CONTAINER_INVENTORY = 0;

    public function __construct(
        private readonly MovementManager $movement,
        private readonly BlockActionManager $blockActions,
        private readonly Logger $logger,
        private readonly ?ItemRegistry $items = null,
        private readonly ?ItemActorManager $itemActors = null,
        private readonly ?FoodConsumptionManager $food = null
    ) {
    }

    /**
     * @param callable(PacketInterface): void $send
     * @param (callable(PacketInterface): void)|null $broadcast
     */
    public function process(
        Player $player,
        PlayerAuthInputPacket $packet,
        World $world,
        callable $send,
        ?callable $broadcast = null
    ): void {
        try {
            $this->movement->handle($player, $packet, $send);
        } catch (\Throwable $e) {
            $this->logger->warning('Hareket işleme hatası.', [
                'player' => $player->identity->displayName,
                'reason' => $e->getMessage(),
            ]);
        }

        try {
            if (!empty($packet->blockActions)) {
                $this->publish(
                    $this->blockActions->handleActions($player, $packet->blockActions, $world),
                    $send,
                    $broadcast
                );
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Blok kırma işleme hatası.', [
                'player' => $player->identity->displayName,
                'reason' => $e->getMessage(),
            ]);
        }

        try {
            if ($packet->itemUseTransaction !== null) {
                $this->handleItemUse($player, $packet->itemUseTransaction, $world, $send, $broadcast);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Esya kullanimi isleme hatasi.', [
                'player' => $player->identity->displayName,
                'reason' => $e->getMessage(),
            ]);
        }
    }

    /**
     * `PlayerAction` içindeki blok kırma eylemlerini işler.
     *
     * Blok kırma iki kaynaktan gelebilir: `PlayerAuthInput`'un blok aksiyonları
     * ve `PlayerAction` (`CrackBlock` / `PredictDestroyBlock` /
     * `CreativeDestroyBlock`). İkisi de aynı `BlockActionManager` yoluna gider.
     *
     * @param callable(PacketInterface): void $send
     * @param (callable(PacketInterface): void)|null $broadcast
     */
    public function handlePlayerAction(
        Player $player,
        PlayerActionPacket $packet,
        World $world,
        callable $send,
        ?callable $broadcast = null
    ): void {
        if (!$packet->action->destroysBlock()) {
            return;
        }

        try {
            $this->publish(
                $this->blockActions->breakBlock(
                    $player,
                    $packet->blockPosition->x,
                    $packet->blockPosition->y,
                    $packet->blockPosition->z,
                    $world
                ),
                $send,
                $broadcast
            );
        } catch (\Throwable $e) {
            $this->logger->warning('PlayerAction blok kirma hatasi.', [
                'player' => $player->identity->displayName,
                'action' => $packet->action->name,
                'reason' => $e->getMessage(),
            ]);
        }
    }

    /**
     * `InventoryTransaction` paketini işler.
     *
     * İki yol vardır:
     *  - `ItemUse` varyantı → blok yerleştirme (`BlockActionManager::place`).
     *  - `Normal` varyantı → yere eşya bırakma (kaynak türü `World`).
     *
     * @param callable(PacketInterface): void $send
     * @param (callable(PacketInterface): void)|null $broadcast
     */
    public function handleInventoryTransaction(
        Player $player,
        InventoryTransactionPacket $packet,
        World $world,
        callable $send,
        ?callable $broadcast = null
    ): void {
        if ($packet->itemUse === null) {
            $this->handleDrop($player, $packet, $world, $send, $broadcast);

            return;
        }

        try {
            $this->handleItemUse($player, $packet->itemUse, $world, $send, $broadcast);
        } catch (\Throwable $e) {
            $this->logger->warning('Envanter islemi (item use) hatasi.', [
                'player' => $player->identity->displayName,
                'reason' => $e->getMessage(),
            ]);
        }
    }

    /**
     * `ItemUse` yolunu tek noktadan dağıtır.
     *
     * `Use` eylemi yenilebilir eşyayı tüketir (Phase 10.2); diğer eylemler
     * (özellikle `Place`) blok yerleştirmeye gider. İki giriş yolu —
     * `PlayerAuthInput` bit 32 ve `InventoryTransaction` — aynı yönlendirmeyi
     * paylaşır ki kurallar iki yerde ayrışmasın.
     *
     * @param callable(PacketInterface): void $send
     * @param (callable(PacketInterface): void)|null $broadcast
     */
    private function handleItemUse(
        Player $player,
        ItemUseInventoryTransaction $use,
        World $world,
        callable $send,
        ?callable $broadcast
    ): void {
        if ($this->food !== null && $use->actionType === ItemUseActionType::Use) {
            $result = $this->food->consume($player, $use);

            if ($result === null) {
                return;
            }

            $descriptor = $this->descriptorFor($result->remaining);

            if ($descriptor !== null) {
                $send(new InventorySlotPacket(
                    containerId: self::CONTAINER_INVENTORY,
                    slot: $result->slot,
                    item: $descriptor
                ));
            }

            // Açlık çubuğu bir sonraki tick'i beklemeden güncellenir.
            $send(new UpdateAttributesPacket($player->runtimeId, $player->attributeList()));

            // Phase 10.5: yemek / içki sesi.
            $soundEvent = $result->consumedType === 'minecraft:honey_bottle' ? 'drink' : 'eat';
            $sound = new LevelSoundEventPacket(
                $soundEvent,
                new Vec3($player->x(), $player->y(), $player->z()),
                actorIdentifier: 'minecraft:player',
                isGlobal: false,
                actorUniqueId: $player->entityId
            );

            $send($sound);
            if ($broadcast !== null) {
                $broadcast($sound);
            }

            return;
        }

        $this->publish($this->blockActions->place($player, $use, $world), $send, $broadcast);
    }

    /**
     * `Normal` envanter işlemindeki "yere bırak" aksiyonlarını işler.
     *
     * Bedrock'ta bırakma, kaynağı `World` olan bir aksiyonla temsil edilir:
     * `fromItem` bırakılan yığın, `toItem` boştur. Sunucu istemcinin
     * bildirdiği miktara güvenmeden önce yığını **envanterden** düşürür;
     * envanterde o kadar eşya yoksa hiçbir şey yapılmaz (eşya çoğaltma
     * savunması).
     *
     * Bırakılan eşya varlığı hem bırakan oyuncuya (`send`) hem diğerlerine
     * (`broadcast`) duyurulur: istemcinin yerel tahmini farklı bir runtime
     * kimliği kullanabileceği için otoriter kimlik iletilmelidir.
     *
     * @param callable(PacketInterface): void $send
     * @param (callable(PacketInterface): void)|null $broadcast
     */
    private function handleDrop(
        Player $player,
        InventoryTransactionPacket $packet,
        World $world,
        callable $send,
        ?callable $broadcast
    ): void {
        if ($this->itemActors === null || !$player->canInteract()) {
            return;
        }

        foreach ($packet->actions as $action) {
            if ($action->source->type !== InventorySource::TYPE_WORLD) {
                continue;
            }

            try {
                $stack = $this->itemActors->stackFromDescriptor($action->fromItem);

                if ($stack === null) {
                    continue;
                }

                // Varlık **önce** doğrulanmış konumda oluşturulur; envanter
                // düşümü başarısız olursa varlık geri alınır. Böylece "eşya
                // envanterden düştü ama yerde yok" durumu oluşmaz.
                $spawn = $this->itemActors->spawn(
                    $world,
                    $stack,
                    $player->x(),
                    $player->y() + 1.0,
                    $player->z(),
                    $player->sessionId
                );

                if ($spawn === null) {
                    continue;
                }

                $updates = $this->itemActors->consume($player->inventory(), $stack);

                if ($updates === null) {
                    // Envanterde bildirilen esya yok: istek yok sayilir.
                    $this->itemActors->despawn($spawn->targetRuntimeId);

                    $this->logger->warning('Yere birakma reddedildi: esya envanterde yok.', [
                        'player' => $player->identity->displayName,
                        'item' => $stack->type(),
                        'count' => $stack->count(),
                    ]);

                    continue;
                }

                foreach ($updates as $slot => $newStack) {
                    $descriptor = $this->descriptorFor($newStack);

                    if ($descriptor === null) {
                        continue;
                    }

                    $send(new InventorySlotPacket(
                        containerId: self::CONTAINER_INVENTORY,
                        slot: $slot,
                        item: $descriptor
                    ));
                }

                $send($spawn);

                if ($broadcast !== null) {
                    $broadcast($spawn);
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Yere birakma hatasi.', [
                    'player' => $player->identity->displayName,
                    'reason' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * `TakeItemActor` — istemcinin yerdeki eşyayı toplama isteği.
     *
     * Paket yalnızca "hangi varlığı istiyorum" der; mesafe ve envanter
     * sunucuda doğrulanır. Toplama başarılıysa varlık kaldırılır ve değişen
     * envanter yuvaları sahibine bildirilir.
     *
     * @param callable(PacketInterface): void $send
     * @param (callable(PacketInterface): void)|null $broadcast
     */
    public function handleTakeItemActor(
        Player $player,
        TakeItemActorPacket $packet,
        World $world,
        callable $send,
        ?callable $broadcast = null
    ): void {
        if ($this->itemActors === null) {
            return;
        }

        // `Actor Runtime ID` istemci tarafından iddia edilir; başka bir
        // oyuncunun adına toplama yapılamaz.
        if ($packet->actorRuntimeId !== $player->runtimeId) {
            $this->logger->warning('TakeItemActor: runtime id uyusmuyor, yok sayildi.', [
                'player' => $player->identity->displayName,
                'claimed' => $packet->actorRuntimeId,
                'actual' => $player->runtimeId,
            ]);

            return;
        }

        try {
            $result = $this->itemActors->pickup($player, $packet->itemRuntimeId, $world);

            if ($result === null) {
                return;
            }

            foreach ($result->slotUpdates as $slot => $newStack) {
                $descriptor = $this->descriptorFor($newStack);

                if ($descriptor === null) {
                    continue;
                }

                $send(new InventorySlotPacket(
                    containerId: self::CONTAINER_INVENTORY,
                    slot: $slot,
                    item: $descriptor
                ));
            }

            $send($result->removeActor);

            if ($broadcast !== null) {
                $broadcast($result->removeActor);
            }

            // Toplama sesi: esyanin son konumunda calar. `isGlobal: false`
            // cunku yalnizca yakin oyuncular duymali; toplayan oyuncuya
            // `send` ile, digerlerine `broadcast` ile ayni paket gider.
            $sound = new LevelSoundEventPacket(
                'pickup',
                $result->position,
                actorIdentifier: 'minecraft:item',
                isGlobal: false,
                actorUniqueId: $player->entityId
            );

            $send($sound);

            if ($broadcast !== null) {
                $broadcast($sound);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Esya toplama hatasi.', [
                'player' => $player->identity->displayName,
                'reason' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param callable(PacketInterface): void $send
     * @param (callable(PacketInterface): void)|null $broadcast
     */
    private function publish(BlockActionResult $result, callable $send, ?callable $broadcast): void
    {
        foreach ($result->changes as $change) {
            if ($broadcast !== null) {
                $broadcast($change->toPacket());
            }
        }

        // Ses, blok değişikliğinden **sonra** yayınlanır: istemci bloğu
        // güncellemeden sesi çalarsa kırma görsel olarak geç kalır.
        foreach ($result->sounds as $sound) {
            if ($broadcast !== null) {
                $broadcast($sound->toPacket());
            } else {
                $send($sound->toPacket());
            }
        }

        if ($result->inventorySlot === null) {
            return;
        }

        $descriptor = $this->descriptorFor($result->inventoryStack);

        if ($descriptor === null) {
            // Eşya kayıt defteri yoksa ya da eşya tanımlı değilse istemciye
            // yanlış tanımlayıcı göndermektense hiç göndermemek doğrudur.
            return;
        }

        $send(new InventorySlotPacket(
            containerId: self::CONTAINER_INVENTORY,
            slot: $result->inventorySlot,
            item: $descriptor
        ));
    }

    private function descriptorFor(?ItemStack $stack): ?NetworkItemStackDescriptor
    {
        return ItemDescriptorFactory::forStack($stack, $this->items);
    }
}
