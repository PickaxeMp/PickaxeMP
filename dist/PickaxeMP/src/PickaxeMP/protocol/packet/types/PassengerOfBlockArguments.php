<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\ProtocolException;

/**
 * `PassengerOfBlockArguments` — oyuncunun/varligin blok ustundeki konumu.
 *
 * Tel bicimi (Mojang `PassengerOfBlockArguments.json` ordinal 0..4):
 * BlockPos, Offset Vec3, Rotation float, RotationLimit float, EmoteType uint8.
 */
final class PassengerOfBlockArguments
{
    public const EMOTE_STANDING = 0;
    public const EMOTE_RIDING = 1;
    public const EMOTE_LAYING = 2;

    public function __construct(
        public readonly BlockPos $blockPos,
        public readonly Vec3 $offset,
        public readonly float $rotation,
        public readonly float $rotationLimit,
        public readonly int $emoteType
    ) {
        if ($emoteType < 0 || $emoteType > 0xFF) {
            throw ProtocolException::varIntOverflow('passenger emote type (uint8)');
        }

        if ($rotation < 0.0 || $rotation > 360.0) {
            throw new ProtocolException("PassengerOfBlockArguments: rotation araligi 0..360: {$rotation}");
        }

        if ($rotationLimit < 0.0 || $rotationLimit > 360.0) {
            throw new ProtocolException(
                "PassengerOfBlockArguments: rotation limit araligi 0..360: {$rotationLimit}"
            );
        }
    }

    public function encode(BinaryWriter $writer): void
    {
        $this->blockPos->encode($writer);
        $this->offset->encode($writer);
        $writer->writeFloat($this->rotation);
        $writer->writeFloat($this->rotationLimit);
        $writer->writeByte($this->emoteType);
    }

    public static function decode(BinaryReader $reader): self
    {
        return new self(
            BlockPos::decode($reader),
            Vec3::decode($reader),
            $reader->readFloat(),
            $reader->readFloat(),
            $reader->readByte()
        );
    }
}
