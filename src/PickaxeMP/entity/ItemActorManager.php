<?php

declare(strict_types=1);

namespace PickaxeMP\entity;

use PickaxeMP\Entity;
use PickaxeMP\item\ItemDescriptorFactory;
use PickaxeMP\item\ItemRegistry;
use PickaxeMP\item\ItemStack;
use PickaxeMP\player\Player;
use PickaxeMP\player\PlayerInventory;
use PickaxeMP\protocol\packet\packets\AddItemActorPacket;
use PickaxeMP\protocol\packet\packets\MoveActorDeltaPacket;
use PickaxeMP\protocol\packet\packets\RemoveActorPacket;
use PickaxeMP\protocol\packet\types\MoveActorDeltaData;
use PickaxeMP\protocol\packet\types\NetworkItemStackDescriptor;
use PickaxeMP\protocol\packet\types\Vec3;
use PickaxeMP\utils\Logger;
use PickaxeMP\world\block\BlockRegistry;
use PickaxeMP\world\chunk\Chunk;
use PickaxeMP\world\World;

/**
 * Yere dusen esya varliklarinin yasam dongusu ve toplama mantigi.
 *
 * ## Sorumluluk siniri
 *
 * Bu sinif **tasima katmanini bilmez**. `AddItemActor`/`RemoveActor`
 * paketlerini uretir, yayinini cagirana birakir. Boylece birim testleri
 * gercek ag olmadan akisi dogrulayabilir.
 *
 * ## Kimlik ve guvenlik
 *
 * - Eşya varliklari {@see EntityManager} uzerinden **monoton** kimlik alir.
 *   Bu defter oyuncu kimliklerini ureten defterden **ayri** olmali ve
 *   cakismamasi icin yuksek bir tabandan baslatilmalidir (ornegin
 *   `new EntityManager(1, 1000, 1000)`); aksi hâlde istemci ayni
 *   `ActorUniqueID` ile iki varlik gorur ve birini sessizce dusurur.
 * - `pickup()` istemcinin gonderdigi miktara **guvenmez**: toplanan yigin
 *   daima sunucudaki kayittan okunur.
 * - Toplama mesafesi kare olarak onceden hesaplanir; karekök her istekte
 *   alinmaz.
 * - Envanterde yer yoksa esya **yerde kalir** (kismi toplama yok).
 */
final class ItemActorManager
{
    /** Toplama yaricapi (blok). */
    public const MAX_PICKUP_DISTANCE = 1.5;

    /** {@see MAX_PICKUP_DISTANCE}'in karesi; mesafe karsilastirmasi icin. */
    public const MAX_PICKUP_DISTANCE_SQUARED = self::MAX_PICKUP_DISTANCE * self::MAX_PICKUP_DISTANCE;

    /**
     * Yerdeki eşyanın yaşam süresi (saniye).
     *
     * Vanilla 300 saniyedir; süre dolunca varlık sunucudan silinir ve
     * `RemoveActor` yayınlanır.
     */
    public const LIFETIME_SECONDS = 300;

    /**
     * Yerçekimi ivmesi (blok/tick²).
     *
     * Vanilla Bedrock eşya fiziğiyle aynı değerler seçildi; istemci de
     * eşyayı yerel olarak aynı kuralla simüle eder. Farklı bir sabit
     * kullanmak, sunucu ile istemcinin gördüğü konumu ayırır.
     */
    public const GRAVITY_PER_TICK = 0.04;

    /** Her tick uygulanan hız sönümü (vanilla `0.98`). */
    public const DRAG_PER_TICK = 0.98;

    /** Konum değişimi bu eşikten küçükse paket üretilmez. */
    public const REST_EPSILON = 0.0001;

    /**
     * Düşerken taranacak en fazla blok sayısı.
     *
     * Terminal hız ~1.96 blok/tick olduğundan normalde 3 hücre yeter.
     * Sınır, bozuk/elle verilmiş dev bir hızın sonsuz döngü açmasını
     * engeller.
     */
    public const MAX_FALL_SCAN = 64;

    /** @var array<int, ItemActor> runtimeId => actor */
    private array $items = [];

    private readonly BlockRegistry $blocks;

    public function __construct(
        private readonly EntityManager $entities,
        private readonly ?ItemRegistry $registry,
        private readonly Logger $logger,
        ?BlockRegistry $blocks = null
    ) {
        $this->blocks = $blocks ?? BlockRegistry::default();
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function get(int $runtimeId): ?ItemActor
    {
        return $this->items[$runtimeId] ?? null;
    }

    /**
     * @return list<ItemActor>
     */
    public function all(): array
    {
        return array_values($this->items);
    }

    /**
     * Yere bir esya varligi birakir.
     *
     * @return AddItemActorPacket|null Esya tanimli degilse/bos ise `null`
     */
    public function spawn(
        World $world,
        ItemStack $stack,
        float $x,
        float $y,
        float $z,
        ?string $ownerSessionId = null,
        ?Vec3 $velocity = null
    ): ?AddItemActorPacket {
        if ($stack->isEmpty()) {
            return null;
        }

        $descriptor = $this->descriptorFor($stack);

        if ($descriptor === null) {
            $this->logger->warning('Esya varligi olusturulamadi: ag kimligi yok.', [
                'item' => $stack->type(),
            ]);

            return null;
        }

        $entity = $this->entities->create(Entity::TYPE_ITEM, $world, $x, $y, $z);
        $position = new Vec3($x, $y, $z);
        $velocity ??= Vec3::zero();

        $this->items[$entity->runtimeId()] = new ItemActor(
            $entity,
            $stack->copy(),
            $position,
            $ownerSessionId,
            time(),
            $velocity
        );

        return new AddItemActorPacket(
            targetActorId: $entity->entityId(),
            targetRuntimeId: $entity->runtimeId(),
            item: $descriptor,
            position: $position,
            velocity: $velocity
        );
    }

    /**
     * Varlığı sunucu tarafında kaldırır (istemciye paket göndermez).
     *
     * Geri alma (rollback) ve dünya değişimi temizliği için kullanılır.
     */
    public function despawn(int $runtimeId): bool
    {
        $actor = $this->items[$runtimeId] ?? null;

        if ($actor === null) {
            return false;
        }

        unset($this->items[$runtimeId]);
        $this->entities->remove($actor->entity->id());

        return true;
    }

    /**
     * Süresi dolan eşya varlıklarını siler.
     *
     * Çağıran, dönen paketleri istemcilere yayınlamakla yükümlüdür; bu sınıf
     * ağ katmanını bilmez. `RemoveActor` **ActorUniqueID** ile gider
     * (`AddItemActor` ile aynı kimlik), runtime kimlik değil.
     *
     * @param int|null $now Unix zamanı; `null` = gerçek saat (test enjekte eder)
     *
     * @return list<RemoveActorPacket>
     */
    public function tick(?int $now = null): array
    {
        $now ??= time();
        $packets = [];

        foreach ($this->items as $runtimeId => $actor) {
            if ($actor->isDespawned()) {
                unset($this->items[$runtimeId]);
                $this->entities->remove($actor->entity->id());

                continue;
            }

            if (($now - $actor->spawnedAt) < self::LIFETIME_SECONDS) {
                continue;
            }

            unset($this->items[$runtimeId]);
            $this->entities->remove($actor->entity->id());

            $packets[] = new RemoveActorPacket($actor->entityId());
            $this->logger->debug('Esya varligi suresi doldu.', [
                'runtime_id' => $runtimeId,
                'item' => $actor->stack->type(),
            ]);
        }

        return $packets;
    }

    /**
     * Yerçekimini uygular ve hareket eden eşyalar için `MoveActorDelta` üretir.
     *
     * ## Neden sunucu tarafında?
     *
     * İstemci düşen eşyayı yerel olarak simüle eder, ancak sunucu kendi
     * konumunu bilmezse toplama mesafesi havadaki ilk konuma göre
     * hesaplanır. Oyuncu yere düşen eşyanın yanına gittiğinde "menzil
     * dışı" reddi oluşur. Bu yüzden konum otoriter olarak burada ilerletilir.
     *
     * ## Basitleştirmeler (dürüst sınır)
     *
     * - Yalnızca **dikey** çarpışma modellenir. Yatay duvarlar eşyayı
     *   durdurmaz; eşya duvarın içinden geçebilir.
     * - Sıvı, yarı-saydam blok ve eğim yoktur; `BlockType::$solid` neyse o.
     * - Chunk yüklü değilse zemin bilinmez ve eşya düşmeye devam eder
     *   (`World::getBlock` `null` döner).
     *
     * @return list<MoveActorDeltaPacket>
     */
    public function tickMotion(): array
    {
        $packets = [];

        foreach ($this->items as $actor) {
            if ($actor->isDespawned()) {
                continue;
            }

            $world = $actor->entity->world();

            if ($world === null) {
                continue;
            }

            $move = $this->advance($actor, $world);

            if ($move === null) {
                continue;
            }

            $packets[] = $move;
        }

        return $packets;
    }

    /**
     * Tek bir eşyanın bir tick'lik hareketini hesaplar.
     *
     * @return MoveActorDeltaPacket|null Konum değişmediyse `null`
     */
    private function advance(ItemActor $actor, World $world): ?MoveActorDeltaPacket
    {
        $cellX = (int) floor($actor->position->x);
        $cellY = (int) floor($actor->position->y);
        $cellZ = (int) floor($actor->position->z);

        // Blok içine doğmuş/ışınlanmış eşya yukarı itilir; aksi hâlde
        // zemin taraması onu blok içinde "dinleniyor" sayardı.
        if ($this->solidAt($world, $cellX, $cellY, $cellZ)) {
            $next = new Vec3($actor->position->x, $cellY + 1.0, $actor->position->z);

            return $this->commit($actor, $next, Vec3::zero(), true);
        }

        $velocity = new Vec3(
            $actor->velocity->x * self::DRAG_PER_TICK,
            ($actor->velocity->y - self::GRAVITY_PER_TICK) * self::DRAG_PER_TICK,
            $actor->velocity->z * self::DRAG_PER_TICK
        );

        $nextX = $actor->position->x + $velocity->x;
        $nextY = $actor->position->y + $velocity->y;
        $nextZ = $actor->position->z + $velocity->z;

        $onGround = false;

        if ($nextY < $actor->position->y) {
            $floorY = $this->firstSolidBelow(
                $world,
                (int) floor($nextX),
                (int) floor($actor->position->y - self::REST_EPSILON),
                (int) floor($nextY),
                (int) floor($nextZ)
            );

            if ($floorY !== null) {
                $nextY = $floorY + 1.0;
                $velocity = new Vec3($velocity->x, 0.0, $velocity->z);
                $onGround = true;
            }
        }

        // Dünyanın altına düşen eşya kaybolmasın; en alt katmana oturur.
        if ($nextY < Chunk::MIN_Y) {
            $nextY = (float) Chunk::MIN_Y;
            $velocity = new Vec3($velocity->x, 0.0, $velocity->z);
            $onGround = true;
        }

        return $this->commit($actor, new Vec3($nextX, $nextY, $nextZ), $velocity, $onGround);
    }

    /**
     * Yeni durumu yazar; gerçekten değiştiyse paket döner.
     */
    private function commit(ItemActor $actor, Vec3 $position, Vec3 $velocity, bool $onGround): ?MoveActorDeltaPacket
    {
        $moved = abs($position->x - $actor->position->x) > self::REST_EPSILON
            || abs($position->y - $actor->position->y) > self::REST_EPSILON
            || abs($position->z - $actor->position->z) > self::REST_EPSILON
            || $onGround !== $actor->onGround;

        $actor->velocity = $velocity;
        $actor->onGround = $onGround;

        if (!$moved) {
            return null;
        }

        $actor->position = $position;
        $actor->entity->setPosition($position->x, $position->y, $position->z);

        return new MoveActorDeltaPacket(new MoveActorDeltaData(
            actorRuntimeId: $actor->runtimeId(),
            positionX: $position->x,
            positionY: $position->y,
            positionZ: $position->z,
            isOnGround: $onGround
        ));
    }

    /**
     * `startY`'den `endY`'ye (aşağı) ilk katı bloğun Y'sini bulur.
     */
    private function firstSolidBelow(World $world, int $x, int $startY, int $endY, int $z): ?int
    {
        $scanned = 0;

        for ($y = $startY; $y >= $endY && $scanned < self::MAX_FALL_SCAN; $y--, $scanned++) {
            if ($this->solidAt($world, $x, $y, $z)) {
                return $y;
            }
        }

        return null;
    }

    /**
     * Verilen blok konumu katı mı?
     *
     * Bilinmeyen ağ kimliği **katı sayılmaz**; "bilinmeyeni hava kabul et"
     * davranışı burada bilinçli: yüklü olmayan chunk zaten `null` döner ve
     * eşya düşmeye devam eder.
     */
    private function solidAt(World $world, int $x, int $y, int $z): bool
    {
        if (!Chunk::isYInRange($y)) {
            return false;
        }

        $networkId = $world->getBlock($x, $y, $z);

        if ($networkId === null) {
            return false;
        }

        $state = $this->blocks->fromNetworkId($networkId);

        if ($state === null || !$this->blocks->has($state->name)) {
            return false;
        }

        return $this->blocks->type($state->name)->solid;
    }

    /**
     * Oyuncunun yerdeki esyayi toplamasini dener.
     *
     * @return ItemPickupResult|null Mesafe/envanter uygun degilse `null`
     */
    public function pickup(Player $player, int $itemRuntimeId, World $world): ?ItemPickupResult
    {
        $actor = $this->items[$itemRuntimeId] ?? null;

        if ($actor === null) {
            return null;
        }

        if ($actor->isDespawned()) {
            unset($this->items[$itemRuntimeId]);

            return null;
        }

        // Farkli dunyadaki esya toplanamaz.
        if ($actor->entity->world() !== $world) {
            return null;
        }

        if (!$player->canInteract()) {
            return null;
        }

        if (!$this->withinReach($player, $actor)) {
            $this->logger->debug('Esya toplama mesafe disi, yok sayildi.', [
                'player' => $player->identity->displayName,
                'item' => $actor->stack->type(),
            ]);

            return null;
        }

        $updates = $this->insert($player->inventory(), $actor->stack);

        if ($updates === null) {
            // Envanter dolu: esya yerde kalir.
            return null;
        }

        unset($this->items[$itemRuntimeId]);
        $this->entities->remove($actor->entity->id());

        $this->logger->debug('Esya toplandi.', [
            'player' => $player->identity->displayName,
            'item' => $actor->stack->type(),
            'count' => $actor->stack->count(),
        ]);

        // `RemoveActor` alanı **ActorUniqueID**'dir (varlong), runtime kimlik
        // değil. `AddItemActor` ile aynı `entityId()` gönderilir; aksi hâlde
        // istemci eşyayı sahnede bırakır.
        return new ItemPickupResult(
            $actor->stack,
            new RemoveActorPacket($actor->entity->entityId()),
            $updates,
            $actor->position
        );
    }

    /**
     * Istemcinin "yere biraktim" dedigi yigini envanterden dusurur.
     *
     * Once **tum** istek karsilanabiliyor mu diye bakilir; aksi hâlde hicbir
     * yuva degismez (kismi mutasyon yok). Bu, istemcinin sahip olmadigi esyayi
     * "birakma" yoluyla cogaltmasini engeller.
     *
     * @return array<int, ItemStack|null>|null Yetersiz esya varsa `null`
     */
    public function consume(PlayerInventory $inventory, ItemStack $wanted): ?array
    {
        if ($wanted->isEmpty()) {
            return null;
        }

        $available = 0;

        foreach ($inventory->contents() as $existing) {
            if ($existing !== null && $existing->canMergeWith($wanted)) {
                $available += $existing->count();
            }
        }

        if ($available < $wanted->count()) {
            return null;
        }

        $remaining = $wanted->count();
        $updates = [];

        foreach ($inventory->contents() as $slot => $existing) {
            if ($remaining <= 0) {
                break;
            }

            if ($existing === null || !$existing->canMergeWith($wanted)) {
                continue;
            }

            $take = min($remaining, $existing->count());
            $existing->remove($take);
            $remaining -= $take;

            $new = $existing->isEmpty() ? null : $existing;
            $inventory->setSlot($slot, $new);
            $updates[$slot] = $new;
        }

        return $updates;
    }

    /**
     * Yigini envantere yerlestirir (varsa birlestirir).
     *
     * @return array<int, ItemStack|null>|null Yer yetmezse `null`
     */
    public function insert(PlayerInventory $inventory, ItemStack $stack): ?array
    {
        if ($stack->isEmpty()) {
            return null;
        }

        $capacity = 0;

        foreach ($inventory->contents() as $existing) {
            if ($existing === null) {
                $capacity += $stack->maxStackSize();
            } elseif ($existing->canMergeWith($stack)) {
                $capacity += $existing->remainingSpace();
            }
        }

        if ($capacity < $stack->count()) {
            return null;
        }

        $remaining = $stack->count();
        $updates = [];

        // 1) Mevcut yiginlara birlestir.
        foreach ($inventory->contents() as $slot => $existing) {
            if ($remaining <= 0) {
                break;
            }

            if ($existing === null || !$existing->canMergeWith($stack)) {
                continue;
            }

            $space = $existing->remainingSpace();

            if ($space <= 0) {
                continue;
            }

            $moved = min($space, $remaining);
            $existing->add($moved);
            $inventory->setSlot($slot, $existing);
            $remaining -= $moved;
            $updates[$slot] = $existing;
        }

        // 2) Bos yuvalari doldur.
        foreach ($inventory->contents() as $slot => $existing) {
            if ($remaining <= 0) {
                break;
            }

            if ($existing !== null) {
                continue;
            }

            $moved = min($stack->maxStackSize(), $remaining);
            $inventory->setSlot($slot, $stack->withCount($moved));
            $remaining -= $moved;
            $updates[$slot] = $inventory->getSlot($slot);
        }

        return $updates;
    }

    /**
     * Istemci tanimlayicisini sunucu yiginina cevirir.
     *
     * Ag kimligi bilinmiyorsa `null` doner; "bilinmeyen esya" sessizce
     * `minecraft:air`'a cevrilmez.
     */
    public function stackFromDescriptor(NetworkItemStackDescriptor $descriptor): ?ItemStack
    {
        if ($descriptor->isEmpty() || $descriptor->count <= 0) {
            return null;
        }

        if ($this->registry === null) {
            return null;
        }

        $item = $this->registry->byNetworkId($descriptor->id);

        if ($item === null) {
            return null;
        }

        return new ItemStack($item->identifier(), $descriptor->count, $item->maxStackSize());
    }

    /**
     * Bir yigini ag tanimlayicisina cevirir.
     */
    public function descriptorFor(ItemStack $stack): ?NetworkItemStackDescriptor
    {
        return ItemDescriptorFactory::forStack($stack, $this->registry);
    }

    private function withinReach(Player $player, ItemActor $actor): bool
    {
        $dx = $player->x() - $actor->position->x;
        $dy = $player->y() - $actor->position->y;
        $dz = $player->z() - $actor->position->z;

        return ($dx * $dx + $dy * $dy + $dz * $dz) <= self::MAX_PICKUP_DISTANCE_SQUARED;
    }
}
