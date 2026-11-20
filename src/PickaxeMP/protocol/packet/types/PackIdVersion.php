<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\binary\Uuid;

/**
 * `PackIdVersion` (Mojang şeması, 2225).
 *
 * Sıra: UUID (mce::UUID) → SemVersion (string).
 */
final class PackIdVersion
{
    public function __construct(
        public readonly Uuid $uuid,
        public readonly string $version
    ) {
    }

    public static function decode(BinaryReader $reader): self
    {
        return new self($reader->readUuid(), $reader->readString());
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeUuid($this->uuid);
        $writer->writeString($this->version);
    }
}
