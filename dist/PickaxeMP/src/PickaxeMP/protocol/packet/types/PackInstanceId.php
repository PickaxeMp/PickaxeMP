<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;

/**
 * `PackInstanceId` (Mojang şeması, 2225).
 *
 * Sıra: Pack ID (string) → Version (string) → Sub Pack Name (string).
 */
final class PackInstanceId
{
    public function __construct(
        public readonly string $packId,
        public readonly string $version,
        public readonly string $subPackName = ''
    ) {
    }

    public static function decode(BinaryReader $reader): self
    {
        return new self(
            packId: $reader->readString(),
            version: $reader->readString(),
            subPackName: $reader->readString()
        );
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeString($this->packId);
        $writer->writeString($this->version);
        $writer->writeString($this->subPackName);
    }
}
