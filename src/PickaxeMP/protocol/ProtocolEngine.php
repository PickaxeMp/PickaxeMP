<?php

declare(strict_types=1);

namespace PickaxeMP\protocol;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\compression\CompressionManager;
use PickaxeMP\protocol\packet\PacketContext;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\PacketRegistry;

/**
 * Bedrock paket motoru.
 *
 * Sorumluluk: bayt ↔ paket nesnesi. Ağ, oturum, dünya veya oyuncu bilgisi
 * burada YOKTUR.
 *
 * Sınır: `TransportInterface` ham bayt taşır; `ProtocolEngine` onu paketlere
 * çevirir. İkisi birbirine bağımlı değildir — bu sayede `FakeTransport` ile de
 * protokol testleri yapılabilir.
 *
 * Tel biçimi (paket düzeyi): `varuint32 kimlik` + paket gövdesi. Zarf/batch
 * katmanı ayrıdır (bkz. `PacketBatch`).
 */
final class ProtocolEngine
{
    public function __construct(
        private readonly PacketRegistry $registry,
        private readonly CompressionManager $compression = new CompressionManager(),
        /** Fazla baytları hata sayar mı? */
        private readonly bool $strict = true
    ) {
    }

    public function getRegistry(): PacketRegistry
    {
        return $this->registry;
    }

    public function getCompression(): CompressionManager
    {
        return $this->compression;
    }

    /**
     * Baytlardan tek bir paket çözer.
     *
     * @throws ProtocolException paket bilinmiyorsa, yön yanlışsa, veri
     *         bozuksa veya (strict modda) artık bayt varsa
     */
    public function decode(string $bytes, PacketContext $context): PacketInterface
    {
        $context->assertCompatible();

        if ($bytes === '') {
            throw ProtocolException::unexpectedEnd('packet', 1, 0);
        }

        $reader = new BinaryReader($bytes);
        $id = $reader->readVarUInt();

        $this->registry->classFor($id); // bilinmeyen kimlik → açık hata

        $declaredDirection = $this->registry->directionFor($id);

        if (!$declaredDirection->allows($context->direction)) {
            throw new ProtocolException(sprintf(
                'Paket yonu uyumsuz: %s (0x%02X) %s, beklenen %s',
                \PickaxeMP\protocol\packet\PacketId::name($id),
                $id,
                $context->direction->value,
                $declaredDirection->value
            ));
        }

        $maxSize = $this->registry->maxSizeFor($id);

        if (strlen($bytes) > $maxSize) {
            throw ProtocolException::lengthTooLarge('packet', strlen($bytes), $maxSize);
        }

        /** @var class-string<PacketInterface> $class */
        $class = $this->registry->classFor($id);
        $packet = $class::decode($reader);

        if ($context->strict && $this->strict) {
            $reader->assertFullyConsumed(\PickaxeMP\protocol\packet\PacketId::name($id));
        }

        return $packet;
    }

    /**
     * Paketi baytlara çevirir (kimlik dahil, zarf hariç).
     */
    public function encode(PacketInterface $packet): string
    {
        $writer = new BinaryWriter();
        $writer->writeVarUInt($packet->getId());
        $packet->encode($writer);

        return $writer->bytes();
    }

    /**
     * Paketi sıkıştırma ayarlarına göre sıkıştırır (zarf hariç).
     */
    public function encodeCompressed(PacketInterface $packet): string
    {
        return $this->compression->compress($this->encode($packet));
    }

    /**
     * Sıkıştırılmış paket yükünü açar ve çözer.
     */
    public function decodeCompressed(string $bytes, PacketContext $context): PacketInterface
    {
        return $this->decode($this->compression->decompress($bytes), $context);
    }
}
