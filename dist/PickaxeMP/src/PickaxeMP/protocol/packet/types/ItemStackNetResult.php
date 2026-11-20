<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

/**
 * `ItemStackNetResult` (Mojang v1.26.65, 2225) — `ItemStackResponse` durum kodu.
 *
 * Tel üzerinde **uint8** taşınır. Değerler şemadaki `Result` enum sırasıdır;
 * tahmin edilmemiştir.
 *
 * Yalnızca Phase 10.3'te üretilen kodlar sabitlendi; geri kalanı ileride
 * eklenecek. Bilinmeyen bir değer geldiğinde `name()` teşhis için sayı döner.
 */
final class ItemStackNetResult
{
    /** İstek kabul edildi; konteyner bilgisi geçerlidir. */
    public const SUCCESS = 0;
    /** Genel hata; istemci isteği geri alır. */
    public const ERROR = 1;
    /** Aksiyon türü bu protokol sürümünde tanınmadı. */
    public const INVALID_REQUEST_ACTION_TYPE = 2;
    /** Aksiyon bu oyuncu/durum için izinli değil. */
    public const ACTION_REQUEST_NOT_ALLOWED = 3;
    /** İçerik değişmedi: kaynak ve hedef aynı yuva. */
    public const DST_CONTAINER_AND_SLOT_EQUAL_TO_SRC_CONTAINER_AND_SLOT = 48;
    /** İstenen miktar hedefe sığmıyor. */
    public const INVALID_ADJUSTED_AMOUNT = 51;
    /** Transfer miktarı geçersiz (ör. kaynakta yeterli eşya yok). */
    public const INVALID_TRANSFER_AMOUNT = 53;
    /** İki yığın takas edilemiyor. */
    public const CANNOT_SWAP_ITEM = 54;
    /** Yığın hedef yuvaya konamıyor (tür uyuşmazlığı). */
    public const CANNOT_PLACE_ITEM = 55;
    /** Kaynak yuvada istenen miktar yok. */
    public const INVALID_REMOVED_AMOUNT = 57;
    /** Kaynak konteyner bu sunucuda desteklenmiyor. */
    public const INVALID_SOURCE_CONTAINER = 61;

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::SUCCESS => 'Success',
            self::ERROR => 'Error',
            self::INVALID_REQUEST_ACTION_TYPE => 'InvalidRequestActionType',
            self::ACTION_REQUEST_NOT_ALLOWED => 'ActionRequestNotAllowed',
            self::DST_CONTAINER_AND_SLOT_EQUAL_TO_SRC_CONTAINER_AND_SLOT
                => 'DstContainerAndSlotEqualToSrcContainerAndSlot',
            self::INVALID_ADJUSTED_AMOUNT => 'InvalidAdjustedAmount',
            self::INVALID_TRANSFER_AMOUNT => 'InvalidTransferAmount',
            self::CANNOT_SWAP_ITEM => 'CannotSwapItem',
            self::CANNOT_PLACE_ITEM => 'CannotPlaceItem',
            self::INVALID_REMOVED_AMOUNT => 'InvalidRemovedAmount',
            self::INVALID_SOURCE_CONTAINER => 'InvalidSourceContainer',
        ];
    }

    public static function name(int $result): string
    {
        return self::all()[$result] ?? sprintf('unknown(%d)', $result);
    }

    public static function isSuccess(int $result): bool
    {
        return $result === self::SUCCESS;
    }
}
