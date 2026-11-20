<?php

declare(strict_types=1);

namespace PickaxeMP\protocol;

/**
 * Hedef Bedrock protokol sürümü — TEK merkezi kaynak.
 *
 * Değerler Mojang'ın sürümlendirilmiş şemasından alınmıştır:
 *   https://github.com/Mojang/bedrock-protocol-docs/tree/v1.26.65
 *
 * Doğrulanan gerçekler (v1.26.65 etiketi, x-protocol-version = 2225):
 *   - 1.26.60 / 1.26.61 / 1.26.62 / 1.26.65 → protocol 2225
 *   - RequestNetworkSettings = 193, NetworkSettings = 143
 *
 * Bu sabit değişmeden hiçbir paket şeması değişmemelidir. Farklı bir sürüm
 * hedeflenirse önce şemalar yeniden çekilip doğrulanmalıdır.
 */
final class ProtocolVersion
{
    /** Ağ protokol sürümü (ClientNetworkVersion alanında kullanılır). */
    public const PROTOCOL = 2225;

    /** İstemciye bildirilen oyun sürümü metni. */
    public const GAME_VERSION = '1.26.65';

    /** Desteklenen en düşük protokol (şu an tek sürüm destekliyoruz). */
    public const MIN_PROTOCOL = 2225;

    /** Desteklenen en yüksek protokol. */
    public const MAX_PROTOCOL = 2225;

    /**
     * İstemcinin bildirdiği sürüm kabul edilebilir mi?
     */
    public static function isCompatible(int $clientProtocol): bool
    {
        return $clientProtocol >= self::MIN_PROTOCOL && $clientProtocol <= self::MAX_PROTOCOL;
    }

    /**
     * Sürüm metninden protokol numarası (yalnızca bilinen sürümler).
     *
     * @return array<string, int>
     */
    public static function known(): array
    {
        return [
            '1.26.60' => 2225,
            '1.26.61' => 2225,
            '1.26.62' => 2225,
            '1.26.65' => 2225,
            '1.26.51' => 2193,
            '1.26.50' => 2193,
        ];
    }
}
