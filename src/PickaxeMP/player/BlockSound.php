<?php

declare(strict_types=1);

namespace PickaxeMP\player;

use PickaxeMP\protocol\packet\packets\LevelSoundEventPacket;
use PickaxeMP\protocol\packet\types\Vec3;

/**
 * Bir blok mutasyonunun **istemciye duyurulacak ses geri bildirimi**.
 *
 * ## Neden ayrı taşıyıcı?
 *
 * `UpdateBlock` yalnızca bloğu değiştirir; istemci kırma/yerleştirme sesini
 * sunucudan beklemez, **tahmin** eder. Sunucu otoriter olduğu için sonuç
 * istemcinin tahmininden farklı olabilir (gecikme, reddedilen istek, chunk
 * sınırı). Bu durumda istemcinin kendi sesi çalar ama dünya değişmez, ya da
 * tam tersi olur. Ses olayını sunucudan yayınlamak iki tarafı eşitler.
 *
 * `BlockChange` ile aynı desen: sınıf ağ bilmez gibi görünse de pakete
 * çevrim tek yerde (`toPacket`) tutulur; böylece yayın kararı çağıranın.
 */
final class BlockSound
{
    /** Blok kırıldığında çalan olay adı. */
    public const BREAK = 'break';

    /** Blok yerleştirildiğinde çalan olay adı. */
    public const PLACE = 'place';

    public function __construct(
        public readonly string $event,
        public readonly int $x,
        public readonly int $y,
        public readonly int $z,
        public readonly string $block = ''
    ) {
    }

    public static function broken(int $x, int $y, int $z, string $block): self
    {
        return new self(self::BREAK, $x, $y, $z, $block);
    }

    public static function placed(int $x, int $y, int $z, string $block): self
    {
        return new self(self::PLACE, $x, $y, $z, $block);
    }

    /**
     * Ses paketi.
     *
     * `isGlobal` **false**: blok sesleri görüş mesafesindeki oyunculara
     * gider, tüm sunucuya değil. Konum yarım blok ortasına alınır; Bedrock
     * blok seslerini blok merkezinden çalar.
     */
    public function toPacket(): LevelSoundEventPacket
    {
        return new LevelSoundEventPacket(
            $this->event,
            new Vec3($this->x + 0.5, $this->y + 0.5, $this->z + 0.5),
            actorIdentifier: $this->block,
            isGlobal: false
        );
    }
}
