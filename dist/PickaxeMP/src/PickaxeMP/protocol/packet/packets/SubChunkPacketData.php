<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\types\SubChunkRequestResult;

/**
 * `SubChunkPacketData` (Mojang şeması, 2225).
 *
 * Sıra: Pos Offset (3 × int8) → Request Result (uint8) →
 *       Serialized Sub Chunk (string) → Height Map Data → Blob Id (opsiyonel)
 */
final class SubChunkPacketData
{
    public function __construct(
        public readonly int $offsetX,
        public readonly int $offsetY,
        public readonly int $offsetZ,
        public readonly SubChunkRequestResult $result,
        public readonly string $serializedSubChunk = '',
        /** Height map türü (0 = yok) */
        public readonly int $heightMapType = 0,
        /** @var array<int, int> */
        public readonly array $heightMapData = [],
        public readonly ?int $blobId = null
    ) {
    }

    public function encode(BinaryWriter $writer): void
    {
        // 0 Pos Offset — int8 × 3
        $writer->writeSignedByte($this->offsetX);
        $writer->writeSignedByte($this->offsetY);
        $writer->writeSignedByte($this->offsetZ);

        // 1 Request Result — uint8
        $writer->writeByte($this->result->value);

        // 2 Serialized Sub Chunk
        $writer->writeString($this->serializedSubChunk);

        // 3 Height Map Data — tür (uint8) + varuint32 sayım + int8 değerler
        $writer->writeByte($this->heightMapType);
        $writer->writeVarUInt(count($this->heightMapData));

        foreach ($this->heightMapData as $value) {
            $writer->writeSignedByte($value);
        }

        // 4 Blob Id — opsiyonel
        if ($this->blobId === null) {
            $writer->writeBool(false);
        } else {
            $writer->writeBool(true);
            $writer->writeUint64($this->blobId);
        }
    }

    public static function decode(BinaryReader $reader): self
    {
        $offsetX = $reader->readSignedByte();
        $offsetY = $reader->readSignedByte();
        $offsetZ = $reader->readSignedByte();

        $resultValue = $reader->readByte();
        $result = SubChunkRequestResult::tryFrom($resultValue);

        if ($result === null) {
            throw new \PickaxeMP\protocol\ProtocolException(
                "Gecersiz SubChunk request result: {$resultValue}"
            );
        }

        $serialized = $reader->readString();

        $heightMapType = $reader->readByte();
        $count = $reader->readVarUInt();
        $heightMap = [];

        for ($i = 0; $i < $count; $i++) {
            $heightMap[] = $reader->readSignedByte();
        }

        $blobId = null;

        if ($reader->readBool()) {
            $blobId = $reader->readUint64();
        }

        return new self(
            $offsetX,
            $offsetY,
            $offsetZ,
            $result,
            $serialized,
            $heightMapType,
            $heightMap,
            $blobId
        );
    }
}
