<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\ProtocolException;

/**
 * `FullContainerName` — konteyner adi + istege bagli dinamik kimlik.
 *
 * Tel bicimi (Mojang `FullContainerName.json`):
 *  - `Container Name` uint8 (`ContainerEnumName`)
 *  - `Dynamic ID`   istege bagli: varlik bayti + **sabit** uint32 (LE)
 *
 * `Dynamic ID` "Compression" secenegi tasimadigi icin ZigZag varint degil,
 * 4 bayt sabit uint32'dir.
 */
final class FullContainerName
{
    public function __construct(
        public readonly int $containerName,
        public readonly ?int $dynamicId = null
    ) {
        if ($containerName < 0 || $containerName > 0xFF) {
            throw ProtocolException::varIntOverflow('container name (uint8)');
        }

        if ($dynamicId !== null && ($dynamicId < 0 || $dynamicId > 0xFFFFFFFF)) {
            throw ProtocolException::varIntOverflow('container dynamic id (uint32)');
        }
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeByte($this->containerName);
        $writer->writeOptional($this->dynamicId !== null, function (BinaryWriter $w): void {
            $w->writeUint32($this->dynamicId ?? 0);
        });
    }

    public static function decode(BinaryReader $reader): self
    {
        $containerName = $reader->readByte();
        $dynamicId = $reader->readBool() ? $reader->readUint32() : null;

        return new self($containerName, $dynamicId);
    }
}
