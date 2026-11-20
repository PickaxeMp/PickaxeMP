Sen PickaxeMP (PHP 8.2, Bedrock protokol 2225 / 1.26.65) için kıdemli bir kod inceleme uzmanısın.

Phase 10.1 (hayatta kalma sonuçları) yeni tamamlandı. Amaç: açlık 0 iken 80 tick'te 1 hasar (ölüm dahil), açlık >= 18 ve can dolu değilken 80 tick'te 1 can yenilenmesi. Zamanlayıcılar oyuncu başınadır; ağ katmanı `ConnectionPipeline` yalnızca ilgili oyuncuya `SetHealth` (42) gönderir. Yeni paket yok.

Yalnızca aşağıdaki kodu ve sözleşmeyi değerlendir. Gerçek dosyalara erişimin yok; verilen kod kesitleri esastır.

## PlayerSurvival.php

```php
final class PlayerSurvival
{
    public const REGEN_INTERVAL = 80;
    public const STARVATION_INTERVAL = 80;
    public const REGEN_MIN_HUNGER = 18;

    private int $regenTicks = 0;
    private int $starvationTicks = 0;

    public function tick(Player $player): int
    {
        if ($player->state() !== PlayerState::Spawned || $player->isDead()) {
            $this->regenTicks = 0;
            $this->starvationTicks = 0;
            return 0;
        }

        if ($player->attributes()->hunger() <= 0) {
            $this->regenTicks = 0;
            if (++$this->starvationTicks < self::STARVATION_INTERVAL) {
                return 0;
            }
            $this->starvationTicks = 0;
            return -$player->damage(1);
        }

        $this->starvationTicks = 0;

        if ($player->attributes()->hunger() >= self::REGEN_MIN_HUNGER
            && $player->health() < Player::MAX_HEALTH
        ) {
            if (++$this->regenTicks < self::REGEN_INTERVAL) {
                return 0;
            }
            $this->regenTicks = 0;
            return $player->heal(1);
        }

        $this->regenTicks = 0;
        return 0;
    }
}
```

## GameplayTicker.php (ilgili bölüm)

```php
$removed = $this->items?->tick() ?? [];
$moved = $this->items?->tickMotion() ?? [];
$updates = [];
$healthUpdates = [];
$seen = [];

foreach ($players as $player) {
    $sessionId = $player->sessionId;
    $seen[$sessionId] = true;

    if ($player->state() === PlayerState::Disconnected) {
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
    }
}

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

return new GameplayTickResult($time, $timeToBroadcast, $removed, $updates, $moved, $healthUpdates);
```

`Player::MAX_HEALTH = 20`; `damage(int)` hasar miktarını, `heal(int)` eklenen canı döndürür; ikisi de ölü/etkileşimsiz oyuncuda 0 döndürür. `PlayerState::canInteract()` yalnızca `Spawned` için true. `Player::damage()` can 0'a inince durumu `Dead` yapar.

## GameplayTickResult.php

```php
public function __construct(
    public readonly int $time,
    public readonly ?int $timeToBroadcast,
    public readonly array $removedItems,
    public readonly array $attributeUpdates,
    public readonly array $movedItems = [],
    public readonly array $healthUpdates = []
) {}

public function hasWork(): bool
{
    return $this->timeToBroadcast !== null
        || $this->removedItems !== []
        || $this->attributeUpdates !== []
        || $this->movedItems !== []
        || $this->healthUpdates !== [];
}
```

## ConnectionPipeline::tick (ilgili bölüm)

```php
foreach ($result->attributeUpdates as $sessionId => $attributes) {
    $player = $this->players->bySession($sessionId);
    if ($player === null) {
        continue;
    }
    $this->sendTo($sessionId, new UpdateAttributesPacket($player->runtimeId, $attributes));
}

foreach ($result->healthUpdates as $sessionId => $health) {
    if ($this->players->bySession($sessionId) === null) {
        continue;
    }
    $this->sendTo($sessionId, new SetHealthPacket($health));
}
```

## Test sözleşmeleri

- 79 uygun tick sonrası hasar yok, 80. tick'te 1 hasar.
- Açlıktan ölüm mümkün; ölü oyuncuya sonraki tick'lerde hasar yok.
- Açlık 1'e çıkınca açlık sayacı sıfırlanır; açlık 18'in altına inince yenilenme sayacı sıfırlanır.
- Dolu canda yenilenme yok; ölü ve `Joining` oyuncu işlenmez.
- Ölü oyuncu 400 tick hareket etse bile tükenme/doygunluk değişmez.
- `GameplayTicker` ağdan bağımsız; entegrasyon testi gerçek pipeline'da `SetHealth` görür.

Kısıtlar: gerçek Bedrock istemcisi yok; envanterden yemek tüketimi yok; ölümde `ActorEvent(Death)` yayınlanmıyor; yenilenme hızı doygunluğa göre değişmiyor; zorluk kavramı yok.

Görev: En fazla 5 maddelik, önceliklendirilmiş bir inceleme yaz. Her maddede (1) dosya/yöntem veya satır, (2) somut risk, (3) en küçük düzeltme olsun. Övgü ve özet yazma; yalnızca bulgular. Emin olmadığın noktayı "doğrulanamadı" olarak işaretle. Yeni paket alanı veya protokol ayrıntısı uydurma.
