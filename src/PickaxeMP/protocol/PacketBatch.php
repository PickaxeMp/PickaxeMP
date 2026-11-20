<?php

declare(strict_types=1);

namespace PickaxeMP\protocol;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\compression\CompressionAlgorithm;
use PickaxeMP\protocol\compression\CompressionManager;

/**
 * Bedrock oyun paketi batch zarfı.
 *
 * Bir batch, bir veya daha fazla oyun paketini taşır. Her paketin başında
 * varuint32 uzunluk bulunur; uzunluk paket kimliğini ve gövdesini kapsar,
 * ancak kendi uzunluk önekini kapsamaz.
 *
 * ```text
 * uncompressed batch := varuint32 length | packet | varuint32 length | packet | ...
 * ```
 *
 * Taşıyıcı ve sıkıştırma katmanları ayrıdır:
 *
 * ```text
 * NetherNet := [compression-id]? batch
 * RakNet    := 0xFE | [compression-id]? batch
 * ```
 *
 * `compression-id` yalnızca NetworkSettings ile sıkıştırma görüşmesi
 * tamamlandıktan sonra eklenir:
 *
 * - 0x00: raw DEFLATE (zlib header/trailer yok)
 * - 0x01: Snappy
 * - 0xFF: sıkıştırılmamış batch
 *
 * Ağ üzerindeki tek bir mesajda birden fazla paket bulunabilir. Bu sınıf
 * paketleri birbirinden ayırır; paket kimliğini çözme sorumluluğu
 * `ProtocolEngine`'dedir.
 */
final class PacketBatch
{
    public const TRANSPORT_NETHERNET = 'nethernet';

    public const TRANSPORT_RAKNET = 'raknet';

    /** RakNet Minecraft game packet işareti. NetherNet'te bulunmaz. */
    public const RAKNET_GAME_PACKET_ID = 0xFE;

    public const COMPRESSION_ZLIB = 0x00;

    public const COMPRESSION_SNAPPY = 0x01;

    public const COMPRESSION_NONE = 0xFF;

    /** İstemciden gelen tek batch'te izin verilen en fazla paket. */
    public const DEFAULT_MAX_PACKETS = 1024;

    /** Sunucu batch'leri için üst sınır. */
    public const MAX_PACKETS_LIMIT = 65536;

    /** Ham veya sıkıştırılmış batch için güvenlik üst sınırı. */
    public const MAX_BATCH_SIZE = 16 * 1024 * 1024;

    public function __construct(
        private readonly CompressionManager $compression = new CompressionManager(),
        private readonly string $transport = self::TRANSPORT_NETHERNET,
        private readonly bool $compressionFraming = false,
        private readonly int $maxPackets = self::DEFAULT_MAX_PACKETS
    ) {
        if ($transport !== self::TRANSPORT_NETHERNET && $transport !== self::TRANSPORT_RAKNET) {
            throw new \InvalidArgumentException("Bilinmeyen batch tasima stratejisi: {$transport}");
        }

        if ($maxPackets < 1 || $maxPackets > self::MAX_PACKETS_LIMIT) {
            throw new \InvalidArgumentException(
                "Gecersiz batch paket siniri: {$maxPackets} (1.." . self::MAX_PACKETS_LIMIT . ')'
            );
        }
    }

    public function getTransportStrategy(): string
    {
        return $this->transport;
    }

    public function isCompressionFramingEnabled(): bool
    {
        return $this->compressionFraming;
    }

    /**
     * Paketleri batch'e kodlar.
     *
     * @param list<string> $packsets Her öğe, varuint32 paket kimliği dahil
     *        tek bir Bedrock paketinin baytlarıdır.
     */
    public function encode(array $packets): string
    {
        if ($packets === []) {
            throw new ProtocolException('Batch en az bir paket icermelidir.');
        }

        $writer = new BinaryWriter();

        foreach ($packets as $packet) {
            $length = strlen($packet);

            if ($length === 0) {
                throw new ProtocolException('Batch icinde bos paket olamaz.');
            }

            $writer->writeVarUInt($length);
            $writer->write($packet);
        }

        $batch = $writer->bytes();

        if (strlen($batch) > self::MAX_BATCH_SIZE) {
            throw ProtocolException::lengthTooLarge('batch', strlen($batch), self::MAX_BATCH_SIZE);
        }

        $body = $batch;
        $compressionId = null;

        if ($this->compressionFraming) {
            $settings = $this->compression->getSettings();

            if ($this->compression->shouldCompress(strlen($batch))) {
                $body = $this->compression->compressUsing($settings->algorithm, $batch);
                $compressionId = match ($settings->algorithm) {
                    CompressionAlgorithm::Zlib => self::COMPRESSION_ZLIB,
                    CompressionAlgorithm::Snappy => self::COMPRESSION_SNAPPY,
                    CompressionAlgorithm::None => self::COMPRESSION_NONE,
                };
            } else {
                $compressionId = self::COMPRESSION_NONE;
            }
        }

        if ($compressionId !== null) {
            $body = chr($compressionId) . $body;
        }

        if ($this->transport === self::TRANSPORT_RAKNET) {
            $body = chr(self::RAKNET_GAME_PACKET_ID) . $body;
        }

        return $body;
    }

    /**
     * Batch'teki paketleri ayırır.
     *
     * @return list<string> Varuint32 paket kimliği dahil paket baytları
     */
    public function decode(string $frame): array
    {
        if (strlen($frame) > self::MAX_BATCH_SIZE + 2) {
            throw ProtocolException::lengthTooLarge(
                'batch frame',
                strlen($frame),
                self::MAX_BATCH_SIZE + 2
            );
        }

        $body = $frame;

        if ($this->transport === self::TRANSPORT_RAKNET) {
            if ($body === '') {
                throw ProtocolException::unexpectedEnd('RakNet game packet', 1, 0);
            }

            if (ord($body[0]) !== self::RAKNET_GAME_PACKET_ID) {
                throw ProtocolException::badMagic(
                    'RakNet game packet',
                    sprintf('0x%02X', self::RAKNET_GAME_PACKET_ID),
                    sprintf('0x%02X', ord($body[0]))
                );
            }

            $body = substr($body, 1);
        }

        if ($this->compressionFraming) {
            if ($body === '') {
                throw ProtocolException::unexpectedEnd('compression identifier', 1, 0);
            }

            $compressionId = ord($body[0]);
            $body = substr($body, 1);

            $body = match ($compressionId) {
                self::COMPRESSION_NONE => $body,
                self::COMPRESSION_ZLIB => $this->compression->decompressUsing(
                    CompressionAlgorithm::Zlib,
                    $body
                ),
                self::COMPRESSION_SNAPPY => throw ProtocolException::unsupportedCompression(
                    $compressionId
                ),
                default => throw ProtocolException::unsupportedCompression($compressionId),
            };
        }

        if ($body === '') {
            throw ProtocolException::unexpectedEnd('batch', 1, 0);
        }

        if (strlen($body) > self::MAX_BATCH_SIZE) {
            throw ProtocolException::lengthTooLarge('batch', strlen($body), self::MAX_BATCH_SIZE);
        }

        $reader = new BinaryReader($body);
        $packets = [];

        while (!$reader->isAtEnd()) {
            if (count($packets) >= $this->maxPackets) {
                throw ProtocolException::lengthTooLarge(
                    'batch packet count',
                    count($packets) + 1,
                    $this->maxPackets
                );
            }

            $length = $reader->readVarUInt();

            if ($length === 0) {
                throw new ProtocolException('Batch icinde sifir uzunluklu paket olamaz.');
            }

            if ($length > $reader->remaining()) {
                throw ProtocolException::unexpectedEnd('batch packet', $length, $reader->remaining());
            }

            $packets[] = $reader->read($length);
        }

        return $packets;
    }
}
