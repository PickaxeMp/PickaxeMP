<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\binary\Uuid;

/**
 * `SerializedPersonaPieceHandle` - tek bir persona parçası.
 *
 * Mojang şeması: `SerializedPersonaPieceHandle.json`, ordinal sırası:
 *
 * ```
 * 0 PieceId        string
 * 1 PieceType      uint32 (Enum-as-Value; Compression YOK -> sabit uint32 LE)
 * 2 PackId         mce::UUID
 * 3 IsDefaultPiece bool
 * 4 ProductId      string
 * ```
 *
 * Dikkat: `PieceType` burada `"Compression"` taşımaz; bu yüzden **varuint32
 * değil**, sabit genişlikli `uint32` yazılır. Şemadaki tek fark budur ve
 * atlanırsa paket kayar.
 */
final class SerializedPersonaPieceHandle
{
    public function __construct(
        public readonly string $pieceId,
        public readonly PieceType $pieceType,
        public readonly Uuid $packId,
        public readonly bool $isDefaultPiece,
        public readonly string $productId
    ) {
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeString($this->pieceId, 256);
        $writer->writeUint32($this->pieceType->value);
        $writer->writeUuid($this->packId);
        $writer->writeBool($this->isDefaultPiece);
        $writer->writeString($this->productId, 256);
    }

    public static function decode(BinaryReader $reader): self
    {
        return new self(
            pieceId: $reader->readString(256),
            pieceType: PieceType::fromWire($reader->readUint32()),
            packId: $reader->readUuid(),
            isDefaultPiece: $reader->readBool(),
            productId: $reader->readString(256)
        );
    }
}
