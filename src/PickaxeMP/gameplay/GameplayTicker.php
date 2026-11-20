<?php

declare(strict_types=1);

namespace PickaxeMP\gameplay;

use PickaxeMP\entity\ItemActorManager;
use PickaxeMP\player\Player;
use PickaxeMP\player\PlayerSurvival;
use PickaxeMP\world\World;

/**
 * Oyun döngüsünün tick başına yaptığı işler (ağdan bağımsız).
 *
 * ## Kapsam
 *
 * - **Dünya saati:** her tick bir ilerler; her `TIME_SYNC_INTERVAL` tick'te
 *   `SetTime` yayınlanır. İstemci saati yerel ilerletir, sunucu periyodik
 *   referans gönderir (yalnızca girişte bir kez göndermek uzun oturumlarda
 *   kayma biriktirir).
 * - **Eşya ömrü:** süresi dolan eşya varlıkları silinir; dönen `RemoveActor`
 *   paketleri çağıran tarafından yayınlanır.
 * - **Eşya fiziği:** düşen eşyaların konumu sunucuda ilerletilir; hareket
 *   edenler için `MoveActorDelta` üretilir.
 * - **Açlık zinciri:** oyuncu **hareket ettiyse** tükenme artar. Hareket
 *   tespiti, tick'ler arası yatay mesafeyle yapılır; istemcinin "yürüyorum"
 *   iddiasına güvenilmez.
 *
 * ## Neden hareket mesafesi?
 *
 * `PlayerAuthInput` her tick gelmeyebilir. Pozisyon değişimi, paket sıklığından
 * bağımsız olarak gerçek yer değiştirmeyi ölçer. Eşik, kayan nokta
 * gürültüsünü eler.
 */
final class GameplayTicker
{
    /** `SetTime` yayın aralığı (tick): 20 TPS'te 10 saniye. */
    public const TIME_SYNC_INTERVAL = 200;

    /** Hareket sayılan en küçük yatay yer değiştirme (blok). */
    public const MOVE_EPSILON = 0.001;

    private const MOVE_EPSILON_SQUARED = self::MOVE_EPSILON * self::MOVE_EPSILON;

    /** @var array<string, array{x: float, z: float}> Oturum → son tick konumu */
    private array $lastPositions = [];

    /** @var array<string, PlayerSurvival> Oturum → hayatta kalma zamanlayıcıları */
    private array $survival = [];

    public function __construct(
        private readonly World $world,
        private readonly ?ItemActorManager $items = null,
        private readonly int $timeSyncInterval = self::TIME_SYNC_INTERVAL
    ) {
        if ($timeSyncInterval < 1) {
            throw new \InvalidArgumentException('timeSyncInterval en az 1 olmali');
        }
    }

    /**
     * Bir tick işler.
     *
     * @param int $tickNumber 1'den başlayan monoton tick sayacı
     * @param iterable<Player> $players
     */
    public function tick(int $tickNumber, iterable $players): GameplayTickResult
    {
        if ($tickNumber < 0) {
            throw new \InvalidArgumentException('tickNumber negatif olamaz');
        }

        $time = $this->world->advanceTime(1);
        $timeToBroadcast = $tickNumber > 0 && $tickNumber % $this->timeSyncInterval === 0
            ? $time
            : null;

        $removed = $this->items?->tick() ?? [];
        $moved = $this->items?->tickMotion() ?? [];
        $updates = [];
        $healthUpdates = [];
        $deaths = [];
        $seen = [];

        foreach ($players as $player) {
            $sessionId = $player->sessionId;
            $seen[$sessionId] = true;

            if ($player->state() === \PickaxeMP\player\PlayerState::Disconnected) {
                unset($this->lastPositions[$sessionId], $this->survival[$sessionId]);

                continue;
            }

            $x = $player->x();
            $z = $player->z();
            $previous = $this->lastPositions[$sessionId] ?? null;
            $this->lastPositions[$sessionId] = ['x' => $x, 'z' => $z];

            if ($previous === null) {
                continue;
            }

            $dx = $x - $previous['x'];
            $dz = $z - $previous['z'];
            $moving = ($dx * $dx + $dz * $dz) > self::MOVE_EPSILON_SQUARED;

            // Ölü veya henüz spawn olmamış oyuncuda açlık zinciri ilerlemez.
            if ($player->state()->canInteract() && $player->tickAttributes($moving)) {
                $updates[$sessionId] = $player->attributeList();
            }

            $healthDelta = ($this->survival[$sessionId] ??= new PlayerSurvival())->tick($player);

            if ($healthDelta !== 0) {
                $healthUpdates[$sessionId] = $player->health();

                // Olum yalnizca hasar sonrasi saglik 0'a dustugunde bildirilir.
                // `PlayerSurvival` olu oyuncuyu islemedigi icin olay bir kez
                // uretilir; yeniden dogus sagligi sifirlar.
                if ($player->health() === 0) {
                    $deaths[$sessionId] = $player->entityId;
                }
            }
        }

        // Ayrılan oyuncuların konum/zamanlayıcı kaydı birikmesin.
        foreach (array_keys($this->lastPositions) as $sessionId) {
            if (!isset($seen[$sessionId])) {
                unset($this->lastPositions[$sessionId]);
            }
        }

        foreach (array_keys($this->survival) as $sessionId) {
            if (!isset($seen[$sessionId])) {
                unset($this->survival[$sessionId]);
            }
        }

        return new GameplayTickResult($time, $timeToBroadcast, $removed, $updates, $moved, $healthUpdates, $deaths);
    }

    public function forget(string $sessionId): void
    {
        unset($this->lastPositions[$sessionId], $this->survival[$sessionId]);
    }
}
