<?php

declare(strict_types=1);

namespace PickaxeMP\world;

use PickaxeMP\protocol\packet\packets\LevelChunkPacket;
use PickaxeMP\world\chunk\ChunkPosition;
use PickaxeMP\world\chunk\ChunkSerializer;

/**
 * Oyuncunun çevresindeki chunk'ları **bir kez** gönderen akış servisi.
 *
 * ## Neden gerekli
 *
 * Girişte yalnızca spawn chunk'ı gönderiliyordu (Phase 4). İstemci spawn
 * noktasından birkaç blok uzaklaşınca boşluğa düşüyordu: görüş mesafesindeki
 * komşu chunk'lar hiç gönderilmemişti.
 *
 * ## Sözleşme
 *
 * - **Tekrar gönderim yok:** her oturum için gönderilen chunk anahtarları
 *   tutulur. Aynı chunk ikinci kez yayınlanmaz (bant genişliği + istemci
 *   tarafında gereksiz yeniden yükleme).
 * - **Yarıçap kırpılır:** istemcinin istediği `RequestChunkRadius` değeri
 *   `[1, viewDistance]` aralığına çekilir. İstemci otorite değildir.
 * - **Kare pencere:** dairesel değil, `(2r+1)²` kare. Dairesel maskelemek
 *   köşelerde "delik" bırakır; istemci bunları ayrıca ister ve gereksiz
 *   gidiş-dönüş olur.
 * - **Boş chunk da gönderilir:** aksi hâlde istemci orayı "yüklenmedi"
 *   sayar ve oyuncu düşer.
 *
 * ## Dürüst sınır
 *
 * `evictOutside()` **sunucu** defterini sınırlar; istemci tarafındaki
 * boşaltma `NetworkChunkPublisherUpdate` (`ConnectionPipeline::
 * publishChunkWindow()`) ile ayrıca yapılır. Bu sınıf o paketi üretmez,
 * çünkü paket chunk değil blok koordinatı ve yarıçap taşır; üretimi
 * bağlantı katmanının sorumluluğudur.
 */
final class ChunkStreamer
{
    /** Kabul edilen en küçük yarıçap (tek chunk bile çevresini ister). */
    public const MIN_RADIUS = 1;

    /** Kabul edilen en büyük yarıçap — bellek/bant genişliği tavanı. */
    public const MAX_RADIUS = 8;

    /** Varsayılan görüş mesafesi (5×5 = 25 chunk). */
    public const DEFAULT_RADIUS = 2;

    /**
     * Defter temizliğinde pencerenin dışında bırakılan ek chunk payı.
     *
     * Küçük ileri-geri hareketlerde chunk'ın yeniden gönderilmemesi için
     * pencere `radius + EVICT_MARGIN` kabul edilir. Pay olmasaydı sınırda
     * duran oyuncu her adımda aynı chunk'ı yeniden alırdı.
     */
    public const EVICT_MARGIN = 2;

    private readonly ChunkSerializer $serializer;

    /** @var array<string, array<string, true>> Oturum → gönderilen chunk anahtarları */
    private array $sent = [];

    public function __construct(
        private readonly int $viewDistance = self::DEFAULT_RADIUS,
        ?ChunkSerializer $serializer = null
    ) {
        if ($viewDistance < self::MIN_RADIUS || $viewDistance > self::MAX_RADIUS) {
            throw WorldException::invalidViewDistance($viewDistance, self::MIN_RADIUS, self::MAX_RADIUS);
        }

        $this->serializer = $serializer ?? new ChunkSerializer();
    }

    public function viewDistance(): int
    {
        return $this->viewDistance;
    }

    /**
     * İstemcinin istediği yarıçapı sunucu sınırlarına çeker.
     */
    public function clampRadius(int $requested): int
    {
        return max(self::MIN_RADIUS, min($requested, $this->viewDistance));
    }

    public function reset(string $sessionId): void
    {
        unset($this->sent[$sessionId]);
    }

    /**
     * Pencere dışında kalan defter kayıtlarını unutur.
     *
     * ## Neden gerekli
     *
     * Oyuncu sürekli yürürse deftere eklenen anahtar sayısı sınırsız büyür.
     * Bu, oturum başına küçük ama uzun oturumlarda fark edilir bir bellek
     * sızıntısıdır. Kayıt dışı kalan chunk'lar zaten görüş alanında değildir.
     *
     * ## Dürüst sınır
     *
     * Bu **istemci tarafında chunk boşaltmaz**; bunu `NetworkChunkPublisherUpdate`
     * yapar (bkz. `ConnectionPipeline::publishChunkWindow()`). Buradaki
     * temizlik yalnızca **sunucu** defterini sınırlar; pencereye geri
     * dönülürse chunk yeniden gönderilir (istemci aynı chunk'ı yeniden
     * yükler, hata değildir).
     *
     * @param int $margin Yarıçapa eklenen pay; negatif değer 0 kabul edilir
     *
     * @return int Silinen kayıt sayısı
     */
    public function evictOutside(
        string $sessionId,
        float $x,
        float $z,
        ?int $radius = null,
        int $margin = self::EVICT_MARGIN
    ): int {
        if (!isset($this->sent[$sessionId])) {
            return 0;
        }

        $radius = $this->clampRadius($radius ?? $this->viewDistance) + max(0, $margin);
        $center = ChunkPosition::fromBlock((int) floor($x), (int) floor($z));
        $removed = 0;

        foreach (array_keys($this->sent[$sessionId]) as $key) {
            $parts = explode(',', $key, 2);

            if (count($parts) !== 2) {
                unset($this->sent[$sessionId][$key]);
                $removed++;

                continue;
            }

            $chunkX = (int) $parts[0];
            $chunkZ = (int) $parts[1];

            if (abs($chunkX - $center->x) > $radius || abs($chunkZ - $center->z) > $radius) {
                unset($this->sent[$sessionId][$key]);
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Girişte gönderilen spawn chunk'ını kaydeder.
     *
     * Bu çağrı olmadan `sync()` aynı chunk'ı ikinci kez gönderir.
     */
    public function markSent(string $sessionId, ChunkPosition $position): void
    {
        $this->sent[$sessionId][$position->key()] = true;
    }

    public function hasSent(string $sessionId, ChunkPosition $position): bool
    {
        return isset($this->sent[$sessionId][$position->key()]);
    }

    public function sentCount(string $sessionId): int
    {
        return count($this->sent[$sessionId] ?? []);
    }

    /**
     * Oyuncunun bulunduğu noktanın çevresinde **henüz gönderilmemiş**
     * chunk'ları paket olarak döner ve gönderilmiş sayar.
     *
     * @return list<LevelChunkPacket>
     */
    public function sync(World $world, string $sessionId, float $x, float $z, ?int $radius = null): array
    {
        $radius ??= $this->viewDistance;
        $radius = $this->clampRadius($radius);

        $center = ChunkPosition::fromBlock((int) floor($x), (int) floor($z));

        $packets = [];

        for ($dx = -$radius; $dx <= $radius; $dx++) {
            for ($dz = -$radius; $dz <= $radius; $dz++) {
                $position = new ChunkPosition($center->x + $dx, $center->z + $dz);

                if ($this->hasSent($sessionId, $position)) {
                    continue;
                }

                $packets[] = $this->serializer->packet($world->getChunk($position), $position);
                $this->markSent($sessionId, $position);
            }
        }

        return $packets;
    }

    /**
     * Tek bir chunk'ı (girişteki spawn chunk'ı gibi) pakete çevirir.
     */
    public function packetFor(World $world, ChunkPosition $position): LevelChunkPacket
    {
        return $this->serializer->packet($world->getChunk($position), $position);
    }
}
