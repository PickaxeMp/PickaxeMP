<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;

/**
 * `PackInfoData` (Mojang şeması, 2225) — ordinal sırası korunmuştur:
 *
 *   0 PackIdVersion (UUID + SemVersion)
 *   1 PackSize        uint64
 *   2 ContentKey      string
 *   3 SubpackName     string
 *   4 ContentIdentity string
 *   5 HasScripts      bool
 *   6 IsAddonPack     bool
 *   7 IsRayTracingCapable bool
 *   8 CDN URL         string
 */
final class PackInfoData
{
    public function __construct(
        public readonly PackIdVersion $packIdVersion,
        public readonly int $packSize,
        public readonly string $contentKey = '',
        public readonly string $subpackName = '',
        public readonly string $contentIdentity = '',
        public readonly bool $hasScripts = false,
        public readonly bool $isAddonPack = false,
        public readonly bool $isRayTracingCapable = false,
        public readonly string $cdnUrl = ''
    ) {
    }

    public static function decode(BinaryReader $reader): self
    {
        return new self(
            packIdVersion: PackIdVersion::decode($reader),
            packSize: $reader->readUint64(),
            contentKey: $reader->readString(),
            subpackName: $reader->readString(),
            contentIdentity: $reader->readString(),
            hasScripts: $reader->readBool(),
            isAddonPack: $reader->readBool(),
            isRayTracingCapable: $reader->readBool(),
            cdnUrl: $reader->readString()
        );
    }

    public function encode(BinaryWriter $writer): void
    {
        $this->packIdVersion->encode($writer);
        $writer->writeUint64($this->packSize);
        $writer->writeString($this->contentKey);
        $writer->writeString($this->subpackName);
        $writer->writeString($this->contentIdentity);
        $writer->writeBool($this->hasScripts);
        $writer->writeBool($this->isAddonPack);
        $writer->writeBool($this->isRayTracingCapable);
        $writer->writeString($this->cdnUrl);
    }
}
