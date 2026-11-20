<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\ProtocolException;

/**
 * `InventorySource` — bir envanter isleminin kaynagi.
 *
 * Mojang `InventorySource.json` yalnizca `Source Type` alanini zorunlu
 * isaretler; diger iki alan kosulludur. Tel bicimi her kosullu alan icin bir
 * varlik bayti tasir (pmmp `NetworkInventoryAction` ile dogrulandi):
 *
 * ```
 *  Source Type   varuint32
 *  Container ID  istege bagli: varlik bayti + int8
 *  Bit Flags     istege bagli: varlik bayti + varuint32
 * ```
 */
final class InventorySource
{
    public const TYPE_CONTAINER = 0;
    public const TYPE_GLOBAL = 1;
    public const TYPE_WORLD = 2;
    public const TYPE_CREATIVE = 3;
    public const TYPE_TODO = 99999;

    public const FLAG_NONE = 0;
    public const FLAG_WORLD_INTERACTION_RANDOM = 1;

    public function __construct(
        public readonly int $type,
        public readonly ?int $containerId = null,
        public readonly ?int $flags = null
    ) {
        if (!in_array($type, [
            self::TYPE_CONTAINER,
            self::TYPE_GLOBAL,
            self::TYPE_WORLD,
            self::TYPE_CREATIVE,
            self::TYPE_TODO,
        ], true)) {
            throw ProtocolException::invalidEnum('inventory source type', $type);
        }

        if ($containerId !== null && ($containerId < -128 || $containerId > 127)) {
            throw ProtocolException::varIntOverflow('inventory source container id (int8)');
        }

        if ($flags !== null && ($flags < 0 || $flags > 0xFFFFFFFF)) {
            throw ProtocolException::varIntOverflow('inventory source flags (uint32)');
        }

        if ($containerId !== null && $type !== self::TYPE_CONTAINER && $type !== self::TYPE_TODO) {
            throw new ProtocolException(
                "InventorySource: container id yalnizca Container/TODO icin gecerlidir (tur: {$type})"
            );
        }

        if ($flags !== null && $type !== self::TYPE_WORLD) {
            throw new ProtocolException(
                "InventorySource: bit flags yalnizca WorldInteraction icin gecerlidir (tur: {$type})"
            );
        }
    }

    public static function container(int $containerId): self
    {
        return new self(self::TYPE_CONTAINER, containerId: $containerId);
    }

    public static function creative(): self
    {
        return new self(self::TYPE_CREATIVE);
    }

    public static function world(int $flags = self::FLAG_NONE): self
    {
        return new self(self::TYPE_WORLD, flags: $flags);
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeVarUInt($this->type);

        $writer->writeOptional($this->containerId !== null, function (BinaryWriter $w): void {
            $w->writeSignedByte($this->containerId ?? 0);
        });

        $writer->writeOptional($this->flags !== null, function (BinaryWriter $w): void {
            $w->writeVarUInt($this->flags ?? 0);
        });
    }

    public static function decode(BinaryReader $reader): self
    {
        $type = $reader->readVarUInt();

        $containerId = $reader->readBool() ? $reader->readSignedByte() : null;
        $flags = $reader->readBool() ? $reader->readVarUInt() : null;

        return new self($type, $containerId, $flags);
    }
}
