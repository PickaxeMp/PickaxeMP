<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\types\BlockPos;
use PickaxeMP\protocol\packet\types\ChunkPos;
use PickaxeMP\protocol\ProtocolException;

/**
 * `NetworkChunkPublisherUpdate` (121) - istemcinin hangi chunk'ları
 * **bellekte tutacağını** belirler.
 *
 * ## Neden gerekli
 *
 * `LevelChunk` göndermek istemciye chunk'ı yükletir, ama **boşaltmaz**.
 * Oyuncu sürekli yürüdüğünde istemci belleği sınırsız büyür. Bu paket,
 * "bu merkez çevresinde şu yarıçapta chunk tut" der; merkez kaydıkça
 * istemci pencere dışındaki chunk'ları bırakır.
 *
 * ## Alanlar (gophertunnel `NetworkChunkPublisherUpdate`)
 *
 * ```
 * 0 Position     BlockPos (varint32 x3, ZigZag)
 * 1 Radius       varuint32 - **blok** cinsinden (chunk * 16)
 * 2 SavedChunks  uint32 sayı + ChunkPos (sabit int32 x2)
 * ```
 *
 * ## Dürüst sınır
 *
 * `SavedChunks` sunucu tarafından hesaplanmaz (boş liste gönderilir).
 * Vanilla sunucu da çoğunlukla boş gönderir; alan istemcinin önceden
 * çözdüğü chunk'ları hatırlaması için vardır.
 */
final class NetworkChunkPublisherUpdatePacket implements PacketInterface
{
    /** Protokol üst sınırı (gophertunnel ile aynı). */
    public const MAX_SAVED_CHUNKS = 9216;

    /**
     * @param list<ChunkPos> $savedChunks
     */
    public function __construct(
        public readonly BlockPos $position,
        public readonly int $radius,
        public readonly array $savedChunks = []
    ) {
        if ($radius < 0 || $radius > 0xFFFFFFFF) {
            throw ProtocolException::varIntOverflow('network chunk publisher radius (uint32)');
        }

        if (count($savedChunks) > self::MAX_SAVED_CHUNKS) {
            throw ProtocolException::lengthTooLarge(
                'network chunk publisher saved chunks',
                count($savedChunks),
                self::MAX_SAVED_CHUNKS
            );
        }

        foreach ($savedChunks as $chunk) {
            if (!$chunk instanceof ChunkPos) {
                throw new ProtocolException('network chunk publisher saved chunks: ChunkPos bekleniyordu');
            }
        }
    }

    public function getId(): int
    {
        return PacketId::NETWORK_CHUNK_PUBLISHER_UPDATE;
    }

    public function encode(BinaryWriter $writer): void
    {
        $this->position->encode($writer);
        $writer->writeVarUInt($this->radius);
        $writer->writeUint32(count($this->savedChunks));

        foreach ($this->savedChunks as $chunk) {
            $chunk->encode($writer);
        }
    }

    public static function decode(BinaryReader $reader): static
    {
        $position = BlockPos::decode($reader);
        $radius = $reader->readVarUInt();
        $count = $reader->readUint32();

        if ($count > self::MAX_SAVED_CHUNKS) {
            throw ProtocolException::lengthTooLarge(
                'network chunk publisher saved chunks',
                $count,
                self::MAX_SAVED_CHUNKS
            );
        }

        $savedChunks = [];

        for ($i = 0; $i < $count; $i++) {
            $savedChunks[] = ChunkPos::decode($reader);
        }

        return new static($position, $radius, $savedChunks);
    }
}
