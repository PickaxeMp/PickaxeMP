<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\compression\CompressionAlgorithm;
use PickaxeMP\protocol\compression\CompressionSettings;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;

/**
 * `NetworkSettingsPacket` (id 143, S→C).
 *
 * Şema (Mojang v1.26.65, 2225) — ordinal sırası:
 *   0 CompressionThreshold      uint16 (little-endian)
 *   1 CompressionAlgorithm      uint16 (little-endian enum)
 *   2 ClientThrottleEnabled     bool
 *   3 ClientThrottleThreshold   uint8
 *   4 ClientThrottleScalar      float  (little-endian)
 *
 * ÖNEMLİ SÜRÜM FARKI: 1.26.70-beta (protocol 2259) şemasında altıncı bir alan
 * (`SCTP Recovery Profile`, uint8) vardır. Hedefimiz 2225 olduğu için o alan
 * **yazılmaz**; eklenirse istemci paketi yanlış çözer.
 */
final class NetworkSettingsPacket implements PacketInterface
{
    public function __construct(
        public readonly int $compressionThreshold = CompressionSettings::DEFAULT_THRESHOLD,
        public readonly CompressionAlgorithm $compressionAlgorithm = CompressionAlgorithm::Zlib,
        public readonly bool $clientThrottleEnabled = false,
        public readonly int $clientThrottleThreshold = 0,
        public readonly float $clientThrottleScalar = 0.0
    ) {
    }

    public static function fromSettings(CompressionSettings $settings): self
    {
        return new self(
            compressionThreshold: $settings->threshold,
            compressionAlgorithm: $settings->algorithm
        );
    }

    public function getId(): int
    {
        return PacketId::NETWORK_SETTINGS;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeUint16($this->compressionThreshold);
        $writer->writeUint16($this->compressionAlgorithm->value);
        $writer->writeBool($this->clientThrottleEnabled);
        $writer->writeByte($this->clientThrottleThreshold);
        $writer->writeFloat($this->clientThrottleScalar);
    }

    public static function decode(BinaryReader $reader): static
    {
        return new static(
            compressionThreshold: $reader->readUint16(),
            compressionAlgorithm: CompressionAlgorithm::fromWire($reader->readUint16()),
            clientThrottleEnabled: $reader->readBool(),
            clientThrottleThreshold: $reader->readByte(),
            clientThrottleScalar: $reader->readFloat()
        );
    }

    public function toSettings(): CompressionSettings
    {
        return new CompressionSettings($this->compressionAlgorithm, $this->compressionThreshold);
    }
}
