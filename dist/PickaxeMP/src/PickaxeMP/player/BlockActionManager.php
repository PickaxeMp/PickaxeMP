<?php

declare(strict_types=1);

namespace PickaxeMP\player;

use PickaxeMP\protocol\packet\types\ItemUseActionType;
use PickaxeMP\protocol\packet\types\ItemUseInventoryTransaction;
use PickaxeMP\protocol\packet\types\PlayerBlockAction;
use PickaxeMP\world\block\BlockRegistry;
use PickaxeMP\world\chunk\Chunk;
use PickaxeMP\world\World;
use PickaxeMP\utils\Logger;

/**
 * Blok kırma ve yerleştirme.
 *
 * Phase 5'teki `BlockBreakManager` yalnızca log basıyordu; bu sınıf gerçek
 * dünya mutasyonunu yapar ve **ne değiştiğini** döndürür. Yayın (broadcast)
 * çağıran katmanın işidir: bu sınıf taşıyıcıyı bilmez.
 *
 * ## Yetki ve güvenlik
 *
 * - Yalnızca `PlayerState::Spawned` ve canlı oyuncu mutasyon yapabilir.
 * - Yüklü olmayan chunk'a yazılmaz (`World::setBlock` `false` döner); bu,
 *   istemcinin rastgele koordinatlarla sunucu belleğini şişirmesini engeller.
 * - Oyuncu başına saniyede `MAX_MUTATIONS_PER_SECOND` sınırı vardır.
 */
final class BlockActionManager
{
    public const MAX_MUTATIONS_PER_SECOND = 30;

    /**
     * Blok etkileşim menzili (blok).
     *
     * Vanilla sunucu ~6 blok kabul eder. Bu sabit hem kırma hem yerleştirme
     * için tek kaynaktır; oyun modu menzili **değiştirmez** (yaratıcı modda da
     * aynı sınır geçerlidir).
     */
    public const MAX_REACH = 6.0;

    /** Oyuncunun göz yüksekliği (ayak konumuna eklenir). */
    public const EYE_HEIGHT = 1.62;

    /** @var array<string, int> sessionId => pencere başlangıç zamanı */
    private array $windowStart = [];

    /** @var array<string, int> sessionId => penceredeki mutasyon sayısı */
    private array $windowCount = [];

    public function __construct(
        private readonly Logger $logger,
        private readonly BlockRegistry $registry
    ) {
    }

    public function airNetworkId(): int
    {
        return $this->registry->defaultState('minecraft:air')->getNetworkId();
    }

    /**
     * `PlayerAuthInput` içindeki blok aksiyonlarını işler (kırma).
     *
     * @param list<PlayerBlockAction> $actions
     */
    public function handleActions(Player $player, array $actions, World $world): BlockActionResult
    {
        if (!$player->canInteract()) {
            return BlockActionResult::none();
        }

        $changes = [];
        $sounds = [];

        foreach ($actions as $action) {
            if (!$action->isBreak()) {
                continue;
            }

            $result = $this->breakBlock($player, $action->x, $action->y, $action->z, $world);

            foreach ($result->changes as $change) {
                $changes[] = $change;
            }

            foreach ($result->sounds as $sound) {
                $sounds[] = $sound;
            }
        }

        return new BlockActionResult($changes, sounds: $sounds);
    }

    /**
     * Tek bir blogu kirar.
     *
     * `PlayerAuthInput` blok aksiyonlari ve `PlayerAction` (CrackBlock /
     * PredictDestroyBlock / CreativeDestroyBlock) ayni yolu kullanir.
     */
    public function breakBlock(Player $player, int $x, int $y, int $z, World $world): BlockActionResult
    {
        if (!$player->canInteract()) {
            return BlockActionResult::none();
        }

        if (!Chunk::isYInRange($y)) {
            return BlockActionResult::none();
        }

        // Sunucu yetkisi: istemcinin gönderdiği koordinat menzil içinde olmalı.
        // Bu denetim olmadan istemci dünyanın herhangi bir yerindeki bloğu
        // kırabilirdi.
        if (!$this->withinReach($player, $x, $y, $z)) {
            $this->logger->warning('Blok kırma menzil dışı.', [
                'player' => $player->identity->displayName,
                'x' => $x,
                'y' => $y,
                'z' => $z,
            ]);

            return BlockActionResult::none();
        }

        if (!$this->admit($player)) {
            return BlockActionResult::none();
        }

        $air = $this->airNetworkId();
        $current = $world->getBlock($x, $y, $z);

        // `null` = chunk yüklü değil: kırma yok sayılır.
        if ($current === null || $current === $air) {
            return BlockActionResult::none();
        }

        if (!$world->setBlock($x, $y, $z, $air)) {
            return BlockActionResult::none();
        }

        $this->logger->debug('Blok kirildi.', [
            'player' => $player->identity->displayName,
            'x' => $x,
            'y' => $y,
            'z' => $z,
        ]);

        // Ses olayı için bloğun adı gerekir; kayıt defterinde yoksa (ör. özel
        // bir durum) boş ad gönderilir, ses yine çalar.
        $block = $this->registry->fromNetworkId($current)?->getName() ?? '';

        return new BlockActionResult(
            [new BlockChange($x, $y, $z, $air)],
            sounds: [BlockSound::broken($x, $y, $z, $block)]
        );
    }

    /**
     * `ItemUseInventoryTransaction` içindeki `Place` eylemini işler.
     */
    public function place(Player $player, ItemUseInventoryTransaction $use, World $world): BlockActionResult
    {
        if (!$player->canInteract()) {
            return BlockActionResult::none();
        }

        if ($use->actionType !== ItemUseActionType::Place) {
            return BlockActionResult::none();
        }

        if (!$this->admit($player)) {
            return BlockActionResult::none();
        }

        [$targetX, $targetY, $targetZ] = $this->placementPosition($use);

        if (!$this->withinReach($player, $use->position->x, $use->position->y, $use->position->z)
            || !$this->withinReach($player, $targetX, $targetY, $targetZ)
        ) {
            $this->logger->warning('Blok yerleştirme menzil dışı.', [
                'player' => $player->identity->displayName,
                'x' => $targetX,
                'y' => $targetY,
                'z' => $targetZ,
            ]);

            return BlockActionResult::none();
        }

        if (!Chunk::isYInRange($targetY) || !$world->blockPositionLoaded($targetX, $targetY, $targetZ)) {
            return BlockActionResult::none();
        }

        $air = $this->airNetworkId();

        if ($world->getBlock($targetX, $targetY, $targetZ) !== $air) {
            // Hedef dolu: üstüne yazma yok.
            return BlockActionResult::none();
        }

        $slot = $this->resolveSlot($player, $use->slot);
        $stack = $slot !== null ? $player->inventory()->getSlot($slot) : null;

        if ($slot === null || $stack === null || $stack->isEmpty()) {
            return BlockActionResult::none();
        }

        // Kullanılan eşya bir blok olmalı; aksi hâlde yerleştirme yok.
        if (!$this->registry->has($stack->type())) {
            return BlockActionResult::none();
        }

        $state = $this->registry->defaultState($stack->type());

        if (!$world->setBlock($targetX, $targetY, $targetZ, $state->getNetworkId())) {
            return BlockActionResult::none();
        }

        $inventory = $player->inventory();
        $inventory->removeFromSlot($slot, 1);

        $this->logger->debug('Blok yerlestirildi.', [
            'player' => $player->identity->displayName,
            'x' => $targetX,
            'y' => $targetY,
            'z' => $targetZ,
            'block' => $state->getName(),
        ]);

        return new BlockActionResult(
            [new BlockChange($targetX, $targetY, $targetZ, $state->getNetworkId())],
            $slot,
            $inventory->getSlot($slot),
            [BlockSound::placed($targetX, $targetY, $targetZ, $state->getName())]
        );
    }

    /**
     * Tıklanan bloğun yüzüne göre yerleştirme konumu.
     *
     * Bedrock yüz sırası: 0 = aşağı, 1 = yukarı, 2 = kuzey (-Z),
     * 3 = güney (+Z), 4 = batı (-X), 5 = doğu (+X).
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function placementPosition(ItemUseInventoryTransaction $use): array
    {
        $x = $use->position->x;
        $y = $use->position->y;
        $z = $use->position->z;

        return match ($use->face) {
            0 => [$x, $y - 1, $z],
            1 => [$x, $y + 1, $z],
            2 => [$x, $y, $z - 1],
            3 => [$x, $y, $z + 1],
            4 => [$x - 1, $y, $z],
            5 => [$x + 1, $y, $z],
            default => [$x, $y, $z],
        };
    }

    /**
     * Oyuncunun gözü ile bloğun **sınırlayıcı kutusu** arasındaki mesafe
     * menzil içinde mi?
     *
     * Vanilla sunucu da bloğun AABB'sine en yakın noktayı ölçer; merkez
     * ölçmek ayakta duran bir oyuncunun yanındaki alçak bloğu yanlışlıkla
     * menzil dışı sayardı. Eksen başına kontrol ise **yanlıştır**: köşegen
     * mesafe daha büyüktür.
     */
    private function withinReach(Player $player, int $x, int $y, int $z): bool
    {
        $eyeX = $player->x();
        $eyeY = $player->y() + self::EYE_HEIGHT;
        $eyeZ = $player->z();

        // Gözü, bloğun [x, x+1] × [y, y+1] × [z, z+1] kutusuna kırp.
        $nearestX = max((float) $x, min($eyeX, $x + 1.0));
        $nearestY = max((float) $y, min($eyeY, $y + 1.0));
        $nearestZ = max((float) $z, min($eyeZ, $z + 1.0));

        $dx = $eyeX - $nearestX;
        $dy = $eyeY - $nearestY;
        $dz = $eyeZ - $nearestZ;

        return ($dx * $dx + $dy * $dy + $dz * $dz) <= (self::MAX_REACH * self::MAX_REACH);
    }

    private function resolveSlot(Player $player, int $slot): ?int
    {
        $size = $player->inventory()->size();

        if ($slot >= 0 && $slot < $size) {
            return $slot;
        }

        $fallback = $player->selectedHotbarSlot();

        return $fallback >= 0 && $fallback < $size ? $fallback : null;
    }

    /**
     * Hız sınırı: oyuncu başına saniyede `MAX_MUTATIONS_PER_SECOND`.
     */
    private function admit(Player $player): bool
    {
        $now = time();
        $session = $player->sessionId;

        if (($this->windowStart[$session] ?? -1) !== $now) {
            $this->windowStart[$session] = $now;
            $this->windowCount[$session] = 0;
        }

        if (($this->windowCount[$session] ?? 0) >= self::MAX_MUTATIONS_PER_SECOND) {
            $this->logger->warning('Blok mutasyon hizi siniri asildi.', [
                'player' => $player->identity->displayName,
            ]);

            return false;
        }

        $this->windowCount[$session]++;

        return true;
    }
}
