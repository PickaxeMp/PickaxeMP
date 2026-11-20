<?php

declare(strict_types=1);

namespace PickaxeMP\world\block;

/**
 * Bir blok **türünün** sabit özellikleri.
 *
 * Bu sınıf blok **davranışını** uygulamaz (kırma, etkileşim). Yalnızca veri
 * modeli: ad, durumlar, sertlik, katılık, opaklık.
 *
 * DÜRÜST NOT: Sertlik ve katılık değerleri Minecraft'ın bilinen oyun
 * değerleridir; ağ protokolünün parçası **değildir** ve istemciye
 * gönderilmez. Phase 5'te kırma süresi hesabı için kullanılacaktır.
 */
final class BlockType
{
    /**
     * @param array<string, mixed> $defaultState
     */
    public function __construct(
        public readonly string $name,
        public readonly array $defaultState,
        public readonly float $hardness,
        public readonly bool $solid,
        public readonly bool $transparent,
        /** Kırılabilir mi? (bedrock kırılamaz) */
        public readonly bool $breakable = true
    ) {
    }

    public function defaultBlockState(): BlockState
    {
        return new BlockState($this->name, $this->defaultState);
    }
}
