<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\compression;

use PickaxeMP\protocol\ProtocolException;

/**
 * Bedrock batch/yük sıkıştırmasını yönetir.
 *
 * Sorumluluk: "bu yük sıkıştırılmalı mı" kararı + güvenli sıkıştırma/çözme.
 * Paket ayrıştırma burada DEĞİL.
 *
 * DÜRÜST SINIR: Bedrock'ta sıkıştırma zarfının **tam** biçimi (batch başlığı
 * ile ilişkisi) NetherNet üzerinde gerçek istemciyle doğrulanmamıştır.
 * `ProtocolEngine` zarfı ayrı bir katmanda tutar ki doğrulama tek noktada
 * yapılabilsin.
 */
final class CompressionManager
{
    private CompressionSettings $settings;

    public function __construct(
        CompressionSettings $settings = new CompressionSettings(),
        private readonly ZlibCompressor $zlib = new ZlibCompressor()
    ) {
        $this->settings = $settings;
    }

    public function getSettings(): CompressionSettings
    {
        return $this->settings;
    }

    public function setSettings(CompressionSettings $settings): void
    {
        $this->settings = $settings;
    }

    public function shouldCompress(int $size): bool
    {
        return $this->settings->shouldCompress($size);
    }

    /**
     * Yükü gerekiyorsa sıkıştırır.
     *
     * Sıkıştırma devre dışıysa veya eşik altındaysa yük aynen döner; bu
     * durumda sıkıştırılmış olup olmadığı `isCompressedPrefix` ile ayırt
     * edilemez. Bu yüzden zarf katmanı her zaman açık bir "sıkıştırıldı mı"
     * bilgisi taşımalıdır.
     */
    public function compress(string $payload): string
    {
        if (!$this->shouldCompress(strlen($payload))) {
            return $payload;
        }

        return $this->compressUsing($this->settings->algorithm, $payload);
    }

    /**
     * Verilen algoritmayı açıkça kullanarak sıkıştırır.
     *
     * Batch telindeki 0x00/0x01/0xFF kimliği hangi algoritmanın kullanıldığını
     * tek başına bildirir; bu yüzden çözüm tarafı ayarlardan bağımsız olarak
     * teldeki kimliğe güvenmelidir.
     */
    public function compressUsing(CompressionAlgorithm $algorithm, string $payload): string
    {
        return match ($algorithm) {
            CompressionAlgorithm::Zlib => $this->zlib->compress($payload),
            CompressionAlgorithm::None => $payload,
            CompressionAlgorithm::Snappy => throw ProtocolException::unsupportedCompression(
                CompressionAlgorithm::Snappy->value
            ),
        };
    }

    /**
     * Sıkıştırılmış yükü açar.
     */
    public function decompress(string $payload, ?int $maxBytes = null): string
    {
        return $this->decompressUsing($this->settings->algorithm, $payload, $maxBytes);
    }

    /**
     * Verilen algoritmayı açıkça kullanarak sıkıştırılmış yükü açar.
     */
    public function decompressUsing(
        CompressionAlgorithm $algorithm,
        string $payload,
        ?int $maxBytes = null
    ): string {
        return match ($algorithm) {
            CompressionAlgorithm::Zlib => $this->zlib->decompress($payload, $maxBytes),
            CompressionAlgorithm::None => $payload,
            CompressionAlgorithm::Snappy => throw ProtocolException::unsupportedCompression(
                CompressionAlgorithm::Snappy->value
            ),
        };
    }

    /**
     * İstemcinin istediği algoritmayı kabul edilebilir bir algoritmaya indirger.
     *
     * Sunucu algoritmayı seçer; istemci yalnızca öneri sunar.
     */
    public function negotiate(CompressionAlgorithm $requested): CompressionAlgorithm
    {
        if ($requested->isSupported()) {
            return $requested;
        }

        return CompressionAlgorithm::Zlib;
    }
}
