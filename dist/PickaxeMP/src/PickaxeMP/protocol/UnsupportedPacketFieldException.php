<?php

declare(strict_types=1);

namespace PickaxeMP\protocol;

/**
 * Akışı bozan ama **bağlantıyı öldürmemesi gereken** paket alanı.
 *
 * `ProtocolException`'dan türetilir, ancak `ConnectionPipeline::onPayload`
 * bu istisnayı diğerlerinden ayırır: yalnızca söz konusu paket düşürülür,
 * bağlantı yaşamaya devam eder. Gerçek protokol bozulmaları (kısa veri,
 * geçersiz uzunluk) hâlâ fail-closed'a düşer.
 *
 * Kullanım nedeni: Phase 7'de `PlayerAuthInput` içindeki `Item Stack Request`
 * (bit 34) gibi henüz çözülmemiş alanlar her pakette gelebilir; bunları
 * fail-closed yapmak, istemciyi tamamen düşürür.
 */
class UnsupportedPacketFieldException extends ProtocolException
{
}
