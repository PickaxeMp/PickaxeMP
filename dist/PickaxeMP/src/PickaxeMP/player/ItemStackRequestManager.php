<?php

declare(strict_types=1);

namespace PickaxeMP\player;

use PickaxeMP\item\ItemStack;
use PickaxeMP\protocol\packet\types\FullContainerName;
use PickaxeMP\protocol\packet\types\ItemStackNetResult;
use PickaxeMP\protocol\packet\types\ItemStackRequest;
use PickaxeMP\protocol\packet\types\ItemStackRequestAction;
use PickaxeMP\protocol\packet\types\ItemStackRequestSlotInfo;
use PickaxeMP\protocol\packet\types\ItemStackResponseContainerInfo;
use PickaxeMP\protocol\packet\types\ItemStackResponseInfo;
use PickaxeMP\protocol\packet\types\ItemStackResponseSlotInfo;
use PickaxeMP\utils\Logger;

/**
 * Sunucu yetkili `ItemStackRequest` uygulaması (Phase 10.3).
 *
 * ## Sözleşme
 *
 * - Yalnızca `Take` / `Place` / `Swap` uygulanır; diğer aksiyonlar
 *   `ActionRequestNotAllowed` ile **tüm isteği** düşürür (yarım uygulama yok).
 * - Konteynerler: `CombinedHotbarAndInventory` (12) yuva 0-35 ve `Cursor`
 *   (59) yuva 0. Diğerleri `InvalidSourceContainer`.
 * - Doğrulama ve uygulama **kopya** üzerinde yürür; herhangi bir hata
 *   envanteri ve imleci hiç değiştirmez.
 * - Miktar istemciden gelir ama **kaynak yığının gerçek miktarıyla**
 *   karşılaştırılır: fazlası `InvalidRemovedAmount`.
 * - Başarıda dönen `ItemStackResponseInfo` yalnızca **değişen** yuvaları
 *   taşır; istemci bu bildirimle tahminini doğrular.
 * - İmleç oturum başına tutulur; `forget()` bağlantı kapanışında siler.
 *
 * Bu sınıf **paket üretmez**; çağıran `ItemStackResponsePacket` yazar.
 */
final class ItemStackRequestManager
{
    /** `CombinedHotbarAndInventory`: 0-8 hotbar + 9-35 ana envanter. */
    public const CONTAINER_INVENTORY = 12;

    /** `Cursor`: taşınan yığın. Tek yuva (0). */
    public const CONTAINER_CURSOR = 59;

    public const CURSOR_SLOT = 0;

    public const INVENTORY_SIZE = PlayerInventory::SIZE;

    /** @var array<string, ItemStack> oturum kimliği => imleçteki yığın */
    private array $cursor = [];

    public function __construct(
        private readonly Logger $logger
    ) {
    }

    public function handle(Player $player, ItemStackRequest $request): ItemStackResponseInfo
    {
        if (!$request->hasValidRequestId()) {
            return ItemStackResponseInfo::error($request->requestId, ItemStackNetResult::ERROR);
        }

        if (!$player->canInteract() || $request->actions === []) {
            return ItemStackResponseInfo::error(
                $request->requestId,
                ItemStackNetResult::ACTION_REQUEST_NOT_ALLOWED
            );
        }

        $inventory = $player->inventory();
        $sessionId = $player->sessionId;

        /** @var array<int, ItemStack|null> $slots */
        $slots = [];

        foreach ($inventory->contents() as $index => $stack) {
            $slots[$index] = $stack?->copy();
        }

        $cursor = isset($this->cursor[$sessionId]) ? $this->cursor[$sessionId]->copy() : null;

        /** @var array<int, true> $touched */
        $touched = [];
        $cursorTouched = false;

        try {
            foreach ($request->actions as $action) {
                if (!$action->isSupported()) {
                    throw ItemStackRequestFailure::of(
                        ItemStackNetResult::ACTION_REQUEST_NOT_ALLOWED,
                        'desteklenmeyen aksiyon: ' . ItemStackRequestAction::name($action->type)
                    );
                }

                $source = $this->resolve($action->source, 'kaynak');
                $destination = $this->resolve($action->destination, 'hedef');

                if ($this->sameNode($source, $destination)) {
                    throw ItemStackRequestFailure::of(
                        ItemStackNetResult::DST_CONTAINER_AND_SLOT_EQUAL_TO_SRC_CONTAINER_AND_SLOT,
                        'kaynak ve hedef ayni yuva'
                    );
                }

                if ($action->type === ItemStackRequestAction::SWAP) {
                    $this->swap($slots, $cursor, $source, $destination);
                } else {
                    $this->move($slots, $cursor, $source, $destination, $action->amount);
                }

                $this->touch($touched, $source);
                $this->touch($touched, $destination);

                $cursorTouched = $cursorTouched
                    || $source[0] === 'cursor'
                    || $destination[0] === 'cursor';
            }
        } catch (ItemStackRequestFailure $failure) {
            $this->logger->warning('Yigin istegi reddedildi.', [
                'player' => $player->identity->displayName,
                'request' => $request->requestId,
                'result' => ItemStackNetResult::name($failure->netResult),
                'reason' => $failure->getMessage(),
            ]);

            return ItemStackResponseInfo::error($request->requestId, $failure->netResult);
        }

        // --- Uygula: yalnızca gerçekten değişen yuvalar yazılır -------------

        ksort($touched);

        foreach (array_keys($touched) as $index) {
            $before = $inventory->getSlot($index);
            $after = $slots[$index] ?? null;

            if (!$this->sameStack($before, $after)) {
                $inventory->setSlot($index, $after);
            }
        }

        if ($cursorTouched) {
            if ($cursor === null) {
                unset($this->cursor[$sessionId]);
            } else {
                $this->cursor[$sessionId] = $cursor;
            }
        }

        return ItemStackResponseInfo::success(
            $request->requestId,
            $this->containers($slots, $cursor, $touched, $cursorTouched)
        );
    }

    /**
     * İmleçteki yığın (teşhis/test için).
     */
    public function cursor(string $sessionId): ?ItemStack
    {
        return $this->cursor[$sessionId] ?? null;
    }

    /** Oturum kapandığında imleç kaydını siler. */
    public function forget(string $sessionId): void
    {
        unset($this->cursor[$sessionId]);
    }

    /**
     * Faz 10.3 incelemesi: imleç **kalıcı değildir**. Oyuncu imleçte eşya
     * varken ayrılırsa eşya yok olmamalı; bu yöntem imleci envantere geri
     * yazar ve kaydı siler.
     *
     * Sıra: önce aynı türde yeri olan yığınlara birleştir, sonra boş yuvalara
     * yeni yığın olarak koy. Hiç yer yoksa eşya **düşer** ve `warning` yazılır
     * (sessiz kayıp yok).
     */
    public function release(string $sessionId, PlayerInventory $inventory): void
    {
        $cursor = $this->cursor[$sessionId] ?? null;

        if ($cursor === null) {
            return;
        }

        unset($this->cursor[$sessionId]);

        $remaining = $cursor->count();

        foreach ($inventory->contents() as $index => $stack) {
            if ($remaining === 0) {
                break;
            }

            if ($stack === null || !$stack->canMergeWith($cursor)) {
                continue;
            }

            $take = min($stack->remainingSpace(), $remaining);

            if ($take < 1) {
                continue;
            }

            $stack->add($take);
            $inventory->setSlot($index, $stack);
            $remaining -= $take;
        }

        foreach ($inventory->contents() as $index => $stack) {
            if ($remaining === 0) {
                break;
            }

            if ($stack !== null) {
                continue;
            }

            $put = min($remaining, $cursor->maxStackSize());
            $inventory->setSlot($index, $cursor->withCount($put));
            $remaining -= $put;
        }

        if ($remaining > 0) {
            $this->logger->warning('Imlecteki esya envantere sigmadi.', [
                'session' => $sessionId,
                'item' => $cursor->type(),
                'dropped' => $remaining,
            ]);
        }
    }

    // --- Doğrulama ---------------------------------------------------------

    /**
     * @return array{0: 'inventory'|'cursor', 1: int}
     */
    private function resolve(?ItemStackRequestSlotInfo $slot, string $role): array
    {
        if ($slot === null) {
            throw ItemStackRequestFailure::of(
                ItemStackNetResult::INVALID_REQUEST_ACTION_TYPE,
                $role . ' yuva bilgisi yok'
            );
        }

        if ($slot->container->dynamicId !== null) {
            throw ItemStackRequestFailure::of(
                ItemStackNetResult::INVALID_SOURCE_CONTAINER,
                $role . ' dinamik konteyner kimligi tasiyor'
            );
        }

        if ($slot->container->containerName === self::CONTAINER_INVENTORY) {
            if ($slot->slot < 0 || $slot->slot >= self::INVENTORY_SIZE) {
                throw ItemStackRequestFailure::of(
                    ItemStackNetResult::INVALID_SOURCE_CONTAINER,
                    $role . ' envanter yuvasi araligin disinda: ' . $slot->slot
                );
            }

            return ['inventory', $slot->slot];
        }

        if ($slot->container->containerName === self::CONTAINER_CURSOR) {
            if ($slot->slot !== self::CURSOR_SLOT) {
                throw ItemStackRequestFailure::of(
                    ItemStackNetResult::INVALID_SOURCE_CONTAINER,
                    $role . ' imlec yuvasi 0 olmali: ' . $slot->slot
                );
            }

            return ['cursor', self::CURSOR_SLOT];
        }

        throw ItemStackRequestFailure::of(
            ItemStackNetResult::INVALID_SOURCE_CONTAINER,
            $role . ' konteyner desteklenmiyor: ' . $slot->container->containerName
        );
    }

    /**
     * @param array{0: 'inventory'|'cursor', 1: int} $left
     * @param array{0: 'inventory'|'cursor', 1: int} $right
     */
    private function sameNode(array $left, array $right): bool
    {
        return $left[0] === $right[0] && $left[1] === $right[1];
    }

    // --- Uygulama (kopya üzerinde) ----------------------------------------

    /**
     * @param array<int, ItemStack|null> $slots
     * @param array{0: 'inventory'|'cursor', 1: int} $source
     * @param array{0: 'inventory'|'cursor', 1: int} $destination
     */
    private function move(array &$slots, ?ItemStack &$cursor, array $source, array $destination, int $amount): void
    {
        // Referans semantigi icin yerel degiskenlere alinir.
        $sourceStack = $this->read($slots, $cursor, $source);
        $destinationStack = $this->read($slots, $cursor, $destination);

        if ($sourceStack === null) {
            throw ItemStackRequestFailure::of(
                ItemStackNetResult::INVALID_REMOVED_AMOUNT,
                'kaynak yuva bos'
            );
        }

        if ($amount < 1 || $amount > $sourceStack->count()) {
            throw ItemStackRequestFailure::of(
                ItemStackNetResult::INVALID_REMOVED_AMOUNT,
                sprintf('kaynakta %d var, %d istendi', $sourceStack->count(), $amount)
            );
        }

        if ($destinationStack === null) {
            if ($amount > $sourceStack->maxStackSize()) {
                throw ItemStackRequestFailure::of(
                    ItemStackNetResult::CANNOT_PLACE_ITEM,
                    'hedef yuvaya sigmiyor'
                );
            }

            $this->write($slots, $cursor, $destination, $sourceStack->withCount($amount));
        } elseif (!$destinationStack->canMergeWith($sourceStack)) {
            throw ItemStackRequestFailure::of(
                ItemStackNetResult::CANNOT_PLACE_ITEM,
                'farkli esya turleri birlestirilemez'
            );
        } elseif ($destinationStack->remainingSpace() < $amount) {
            throw ItemStackRequestFailure::of(
                ItemStackNetResult::INVALID_ADJUSTED_AMOUNT,
                'hedef yuvada yeterli bos alan yok'
            );
        } else {
            $destinationStack->add($amount);
            $this->write($slots, $cursor, $destination, $destinationStack);
        }

        if ($amount === $sourceStack->count()) {
            $this->write($slots, $cursor, $source, null);
        } else {
            $sourceStack->remove($amount);
            $this->write($slots, $cursor, $source, $sourceStack);
        }
    }

    /**
     * @param array<int, ItemStack|null> $slots
     * @param array{0: 'inventory'|'cursor', 1: int} $source
     * @param array{0: 'inventory'|'cursor', 1: int} $destination
     */
    private function swap(array &$slots, ?ItemStack &$cursor, array $source, array $destination): void
    {
        $sourceStack = $this->read($slots, $cursor, $source);
        $destinationStack = $this->read($slots, $cursor, $destination);

        $this->write($slots, $cursor, $source, $destinationStack);
        $this->write($slots, $cursor, $destination, $sourceStack);
    }

    /**
     * @param array<int, ItemStack|null> $slots
     * @param array{0: 'inventory'|'cursor', 1: int} $node
     */
    private function read(array $slots, ?ItemStack $cursor, array $node): ?ItemStack
    {
        return $node[0] === 'cursor' ? $cursor : ($slots[$node[1]] ?? null);
    }

    /**
     * @param array<int, ItemStack|null> $slots
     * @param array{0: 'inventory'|'cursor', 1: int} $node
     */
    private function write(array &$slots, ?ItemStack &$cursor, array $node, ?ItemStack $stack): void
    {
        if ($node[0] === 'cursor') {
            $cursor = $stack;

            return;
        }

        $slots[$node[1]] = $stack;
    }

    /**
     * @param array<int, true> $touched
     * @param array{0: 'inventory'|'cursor', 1: int} $node
     */
    private function touch(array &$touched, array $node): void
    {
        if ($node[0] === 'inventory') {
            $touched[$node[1]] = true;
        }
    }

    private function sameStack(?ItemStack $left, ?ItemStack $right): bool
    {
        if ($left === null || $right === null) {
            return $left === $right;
        }

        return $left->type() === $right->type()
            && $left->count() === $right->count()
            && $left->maxStackSize() === $right->maxStackSize();
    }

    // --- Yanıt -------------------------------------------------------------

    /**
     * @param array<int, ItemStack|null> $slots
     * @param array<int, true> $touched
     *
     * @return list<ItemStackResponseContainerInfo>
     */
    private function containers(
        array $slots,
        ?ItemStack $cursor,
        array $touched,
        bool $cursorTouched
    ): array {
        $containers = [];

        if ($touched !== []) {
            $slotInfos = [];

            foreach (array_keys($touched) as $index) {
                $slotInfos[] = new ItemStackResponseSlotInfo(
                    requestedSlot: $index,
                    slot: $index,
                    amount: $slots[$index]?->count() ?? 0
                );
            }

            $containers[] = new ItemStackResponseContainerInfo(
                new FullContainerName(self::CONTAINER_INVENTORY),
                $slotInfos
            );
        }

        if ($cursorTouched) {
            $containers[] = new ItemStackResponseContainerInfo(
                new FullContainerName(self::CONTAINER_CURSOR),
                [
                    new ItemStackResponseSlotInfo(
                        requestedSlot: self::CURSOR_SLOT,
                        slot: self::CURSOR_SLOT,
                        amount: $cursor?->count() ?? 0
                    ),
                ]
            );
        }

        return $containers;
    }
}
