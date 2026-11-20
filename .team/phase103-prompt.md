# Phase 10.3 inceleme istegi — `ItemStackRequest` (147/148)

Sen kıdemli bir Bedrock protokolü + sunucu güvenliği incelemecisisin. Aşağıda
PickaxeMP (PHP 8.2, sıfırdan yazılmış Bedrock sunucusu, protokol 2225 /
1.26.65) içinde yeni eklenen **sunucu yetkili `ItemStackRequest` uygulaması**
var. Görev: **gerçek hataları** bulmak. Övgü yazma; her bulgu için "hangi
girdiyle bozulur" ve "düzeltme ne" yaz. Emin değilsen "emin değilim" yaz.

## Sözleşme (kilitli, testle sabit)

- Yalnızca `Take` (0) / `Place` (1) / `Swap` (2) uygulanır. Diğer aksiyon
  türleri **tüm isteği** `ActionRequestNotAllowed` ile düşürür; yarım uygulama
  yok (doğrulama ve uygulama kopya üzerinde).
- Konteynerler: `CombinedHotbarAndInventory` (12) yuva 0-35 ve `Cursor` (59)
  yuva 0. Diğer konteynerler → `InvalidSourceContainer`.
- Ağ kimliği doğrulaması **yok**: sunucu hiç `netId` dağıtmıyor, tüm
  `ItemStackRequestSlotInfo` gönderimlerinde `netId = null`.
- Tek `ItemStackResponsePacket` yanıtı; başarıda yalnızca **değişen** yuvalar
  (`ItemStackResponseContainerInfo`) döner, hata yanıtında `containers = []`.
- İstek kimliği negatif **tek** sayı olmalı; değilse `Error` (1).

## Tel biçimi (gophertunnel/pmmp ile doğrulandı)

```
ItemStackRequest      requestId varint32, actions[], filterStrings[],
                      filterStringOrigin int32
Action                variant varuint32 (=type-2 if type>=9 else type),
                      type uint8, gövde türe özgü
Take/Place            amount uint8, source slotInfo, destination slotInfo
Swap                  source slotInfo, destination slotInfo
slotInfo              container FullContainerName, slot uint8, netId int32 LE (sabit)
```

## İncelenecek kod

```php
<?php
// src/PickaxeMP/player/ItemStackRequestManager.php
final class ItemStackRequestManager
{
    public const CONTAINER_INVENTORY = 12;   // CombinedHotbarAndInventory
    public const CONTAINER_CURSOR = 59;      // Cursor
    public const CURSOR_SLOT = 0;
    public const INVENTORY_SIZE = 36;

    /** @var array<string, ItemStack> oturum kimliği => imleçteki yığın */
    private array $cursor = [];

    public function __construct(private readonly Logger $logger) {}

    public function handle(Player $player, ItemStackRequest $request): ItemStackResponseInfo
    {
        if (!$request->hasValidRequestId()) {
            return ItemStackResponseInfo::error($request->requestId, ItemStackNetResult::ERROR);
        }

        if (!$player->canInteract() || $request->actions === []) {
            return ItemStackResponseInfo::error($request->requestId, ItemStackNetResult::ACTION_REQUEST_NOT_ALLOWED);
        }

        $inventory = $player->inventory();
        $sessionId = $player->sessionId;

        /** @var array<int, ItemStack|null> $slots */
        $slots = [];
        foreach ($inventory->contents() as $index => $stack) {
            $slots[$index] = $stack?->copy();
        }

        $cursor = isset($this->cursor[$sessionId]) ? $this->cursor[$sessionId]->copy() : null;

        $touched = [];        // array<int,true>
        $cursorTouched = false;

        try {
            foreach ($request->actions as $action) {
                if (!$action->isSupported()) {           // Take/Place/Swap degil
                    throw ItemStackRequestFailure::of(
                        ItemStackNetResult::ACTION_REQUEST_NOT_ALLOWED,
                        'desteklenmeyen aksiyon'
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
            $this->logger->warning('Yigin istegi reddedildi.', [...]);
            return ItemStackResponseInfo::error($request->requestId, $failure->netResult);
        }

        // Uygula: yalnizca gercekten degisen yuvalar yazilir
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

    public function cursor(string $sessionId): ?ItemStack { return $this->cursor[$sessionId] ?? null; }
    public function forget(string $sessionId): void { unset($this->cursor[$sessionId]); }

    /** @return array{0:'inventory'|'cursor',1:int} */
    private function resolve(?ItemStackRequestSlotInfo $slot, string $role): array
    {
        if ($slot === null) {
            throw ItemStackRequestFailure::of(ItemStackNetResult::INVALID_REQUEST_ACTION_TYPE, $role . ' yuva bilgisi yok');
        }
        if ($slot->container->dynamicId !== null) {
            throw ItemStackRequestFailure::of(ItemStackNetResult::INVALID_SOURCE_CONTAINER, 'dinamik konteyner kimligi');
        }
        if ($slot->container->containerName === self::CONTAINER_INVENTORY) {
            if ($slot->slot < 0 || $slot->slot >= self::INVENTORY_SIZE) {
                throw ItemStackRequestFailure::of(ItemStackNetResult::INVALID_SOURCE_CONTAINER, 'yuva araligi');
            }
            return ['inventory', $slot->slot];
        }
        if ($slot->container->containerName === self::CONTAINER_CURSOR) {
            if ($slot->slot !== self::CURSOR_SLOT) {
                throw ItemStackRequestFailure::of(ItemStackNetResult::INVALID_SOURCE_CONTAINER, 'imlec yuvasi 0 olmali');
            }
            return ['cursor', self::CURSOR_SLOT];
        }
        throw ItemStackRequestFailure::of(ItemStackNetResult::INVALID_SOURCE_CONTAINER, 'konteyner desteklenmiyor');
    }

    private function sameNode(array $left, array $right): bool
    {
        return $left[0] === $right[0] && $left[1] === $right[1];
    }

    private function move(array &$slots, ?ItemStack &$cursor, array $source, array $destination, int $amount): void
    {
        $sourceStack = $this->read($slots, $cursor, $source);
        $destinationStack = $this->read($slots, $cursor, $destination);

        if ($sourceStack === null) {
            throw ItemStackRequestFailure::of(ItemStackNetResult::INVALID_REMOVED_AMOUNT, 'kaynak yuva bos');
        }
        if ($amount < 1 || $amount > $sourceStack->count()) {
            throw ItemStackRequestFailure::of(ItemStackNetResult::INVALID_REMOVED_AMOUNT, 'miktar kaynaktan fazla');
        }

        if ($destinationStack === null) {
            if ($amount > $sourceStack->maxStackSize()) {
                throw ItemStackRequestFailure::of(ItemStackNetResult::CANNOT_PLACE_ITEM, 'hedef yuvaya sigmiyor');
            }
            $this->write($slots, $cursor, $destination, $sourceStack->withCount($amount));
        } elseif (!$destinationStack->canMergeWith($sourceStack)) {
            throw ItemStackRequestFailure::of(ItemStackNetResult::CANNOT_PLACE_ITEM, 'farkli esya turleri');
        } elseif ($destinationStack->remainingSpace() < $amount) {
            throw ItemStackRequestFailure::of(ItemStackNetResult::INVALID_ADJUSTED_AMOUNT, 'yeterli bos alan yok');
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

    private function swap(array &$slots, ?ItemStack &$cursor, array $source, array $destination): void
    {
        $sourceStack = $this->read($slots, $cursor, $source);
        $destinationStack = $this->read($slots, $cursor, $destination);
        $this->write($slots, $cursor, $source, $destinationStack);
        $this->write($slots, $cursor, $destination, $sourceStack);
    }

    private function read(array $slots, ?ItemStack $cursor, array $node): ?ItemStack
    {
        return $node[0] === 'cursor' ? $cursor : ($slots[$node[1]] ?? null);
    }

    private function write(array &$slots, ?ItemStack &$cursor, array $node, ?ItemStack $stack): void
    {
        if ($node[0] === 'cursor') { $cursor = $stack; return; }
        $slots[$node[1]] = $stack;
    }

    private function touch(array &$touched, array $node): void
    {
        if ($node[0] === 'inventory') { $touched[$node[1]] = true; }
    }

    private function sameStack(?ItemStack $left, ?ItemStack $right): bool
    {
        if ($left === null || $right === null) { return $left === $right; }
        return $left->type() === $right->type()
            && $left->count() === $right->count()
            && $left->maxStackSize() === $right->maxStackSize();
    }

    /** @return list<ItemStackResponseContainerInfo> */
    private function containers(array $slots, ?ItemStack $cursor, array $touched, bool $cursorTouched): array
    {
        $containers = [];
        if ($touched !== []) {
            $slotInfos = [];
            foreach (array_keys($touched) as $index) {
                $slotInfos[] = new ItemStackResponseSlotInfo(
                    requestedSlot: $index, slot: $index,
                    amount: $slots[$index]?->count() ?? 0
                );
            }
            $containers[] = new ItemStackResponseContainerInfo(new FullContainerName(self::CONTAINER_INVENTORY), $slotInfos);
        }
        if ($cursorTouched) {
            $containers[] = new ItemStackResponseContainerInfo(
                new FullContainerName(self::CONTAINER_CURSOR),
                [new ItemStackResponseSlotInfo(
                    requestedSlot: self::CURSOR_SLOT, slot: self::CURSOR_SLOT,
                    amount: $cursor?->count() ?? 0
                )]
            );
        }
        return $containers;
    }
}
```

Yardımcı bilgiler:

- `ItemStack`: `type(): string`, `count(): int`, `maxStackSize(): int`,
  `remainingSpace(): int`, `canMergeWith(ItemStack): bool`,
  `withCount(int): self`, `copy(): self`, `add(int)`, `remove(int)`,
  `isEmpty(): bool`. `MAX_COUNT = 128`.
- `Inventory::contents()` 36 yuvalık diziyi döndürür (boş yuvalar `null`).
  `setSlot()` `PlayerInventory` üzerinde `dirty` işaretini günceller.
- `ItemStackRequestAction::MIN_AMOUNT = 1`, `MAX_AMOUNT = 64` (tel biçimi
  `uint8`; kodlayıcı bu aralığı zorlar). `amount` yalnızca `Take`/`Place`
  gövdelerinde vardır; `Swap` gönderiminde `amount = 0` kalır.
- `Player::canInteract()` = state oynanıyor && `health > 0`.

## Kapsam dışı (bilinen sınırlar — bulgu olarak yazma)

- `ItemRegistry` canlı önyüklemesi yok; yığın türü yalnızca dizgi (`type`).
- `PlaceInContainer` / `TakeOutContainer` / `CraftRecipeAuto` / `CraftResults`
  gövdeleri çözülmüyor (paket düşürülür).
- Yaratıcı mod, zırh/offhand, sandık konteynerleri, düşürme (`Drop`) kapsam dışı.
- Gerçek Bedrock istemcisiyle doğrulama yapılmadı.

## Sorular

1. Envanter/imleç tutarlılığı açısından **dupe** veya **eşya kaybı** üreten
   somut bir aksiyon dizisi var mı? Varsa adım adım yaz.
2. `ItemStack` miktar sınırları (`MAX_COUNT`, `maxStackSize()`), negatif/sıfır
   miktar, 64'ten büyük `amount`, aynı kaynak/hedef gibi uç durumlarda ne
   kırılır?
3. Kopyala-uygula (validate-then-apply) mantığında **atomiklik** ihlali var mı?
   `ItemStack` nesneleri referans mı taşınıyor; kopya yüzeysel mi kalıyor?
4. Oturum başına imleç (`cursor` map) yaşam döngüsü: `forget()` sonrası,
   yeniden bağlanmada, `canInteract()` false iken sızıntı/yanlış durum olur mu?
5. Sunucu yetkisi: istemcinin gönderdiği miktar/yuva ile sunucu durumu
   arasındaki farklar doğru reddediliyor mu; reddedilen istekte **hiçbir**
   mutasyon kalmıyor mu?
6. Yanıt (`ItemStackResponseInfo`) doğru mu: `requestId`, `result` kodu, yuva
   miktarları (boşaltılan yuva 0), konteyner sırası?
7. Protokol uyumu: `requestId` işareti/tekliği, `MAX_REQUESTS`/`MAX_RESPONSES`,
   `netId` yokluğu, `type>=9` varyant kuralı — istemciyi bozacak bir şey var mı?
8. Dosya/oturum kalıcılığı: `PlayerInventory::setSlot` dirty işaretini
   günceller; imleç **kalıcı değil**. Kopma anında imleçteki eşya kaybı kabul
   edilebilir mi, yoksa envantere geri yazılmalı mı?

Yanıtı Türkçe yaz, madde madde, en fazla 900 kelime.
