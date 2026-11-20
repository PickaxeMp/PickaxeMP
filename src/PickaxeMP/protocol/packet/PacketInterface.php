<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;

/**
 * Bedrock paketi sözleşmesi.
 *
 * Sorumluluk sınırı: paket yalnızca kendi alanlarını kodlar/çözer. Ağ, oturum
 * veya oyun mantığı bilmez.
 */
interface PacketInterface
{
    /**
     * Bedrock paket kimliği (varuint32 olarak yazılır).
     */
    public function getId(): int;

    /**
     * Paket gövdesini yazar (kimlik hariç).
     */
    public function encode(BinaryWriter $writer): void;

    /**
     * Gövdeyi okur (kimlik zaten tüketilmiş olarak gelir).
     */
    public static function decode(BinaryReader $reader): static;
}
