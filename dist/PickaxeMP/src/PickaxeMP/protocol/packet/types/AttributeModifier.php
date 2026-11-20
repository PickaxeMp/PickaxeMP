<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\ProtocolException;

/**
 * `AttributeModifier` — bir özniteliğe uygulanan geçici etki.
 *
 * Tel biçimi (Mojang `AttributeModifier.json` ordinal 0..5):
 * `Id` string, `Name` string, `Amount` float, `Operation` int32,
 * `Operand` int32, `Is Serializable?` bool.
 *
 * `gophertunnel` (`protocol/attribute.go`) aynı sırayı kullanır:
 * `r.String(ID); r.String(Name); r.Float32(Amount); r.Int32(Operation);
 * r.Int32(Operand); r.Bool(Serializable)`.
 */
final class AttributeModifier
{
    public const OPERATION_ADDITION = 0;

    public const OPERATION_MULTIPLY_BASE = 1;

    public const OPERATION_MULTIPLY_TOTAL = 2;

    public const OPERATION_CAP = 3;

    public const OPERAND_MIN = 0;

    public const OPERAND_MAX = 1;

    public const OPERAND_CURRENT = 2;

    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly float $amount,
        public readonly int $operation = self::OPERATION_ADDITION,
        public readonly int $operand = self::OPERAND_CURRENT,
        public readonly bool $serializable = false
    ) {
        if ($id === '') {
            throw new ProtocolException('AttributeModifier: id bos olamaz');
        }

        if (!is_finite($amount)) {
            throw new ProtocolException('AttributeModifier: amount sonlu olmali');
        }
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeString($this->id, 256);
        $writer->writeString($this->name, 256);
        $writer->writeFloat($this->amount);
        $writer->writeInt32($this->operation);
        $writer->writeInt32($this->operand);
        $writer->writeBool($this->serializable);
    }

    public static function decode(BinaryReader $reader): self
    {
        return new self(
            id: $reader->readString(256),
            name: $reader->readString(256),
            amount: $reader->readFloat(),
            operation: $reader->readInt32(),
            operand: $reader->readInt32(),
            serializable: $reader->readBool()
        );
    }
}
