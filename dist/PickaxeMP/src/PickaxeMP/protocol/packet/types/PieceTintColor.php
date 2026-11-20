<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;

/**
 * `PieceTintColors` haritasının tek bir girdisi.
 *
 * Şema `SerializedSkin.json` içinde `PieceTintColors` bir
 * `map<persona::PieceType, TintMapColor>` olarak tanımlanır ve anahtar
 * `persona__PieceType.json` ile `uint32` görünür. **Ancak** gerçek tel
 * biçiminde anahtar bir **string**'dir ("hands", "body", ...): referans
 * uygulama `PersonaPieceTintColour.Marshal` önce `r.String(&pieceType)`
 * yazar. Şema üreteci enum'u sayı sanmıştır; string yazılmazsa istemci
 * haritayı çözemez.
 *
 * Girdi biçimi: `string pieceType` + `TintMapColor` (4 renk, uzunluk öneki
 * yok).
 */
final class PieceTintColor
{
    public function __construct(
        public readonly string $pieceType,
        public readonly TintMapColor $tint
    ) {
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeString($this->pieceType, 64);
        $this->tint->encode($writer);
    }

    public static function decode(BinaryReader $reader): self
    {
        return new self(
            pieceType: $reader->readString(64),
            tint: TintMapColor::decode($reader)
        );
    }
}
