<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\types\ResourcePackResponse;

/**
 * `ResourcePackClientResponsePacket` (id 8, C→S).
 *
 * Şema (Mojang v1.26.65, 2225):
 *   Response — `oneOf` kontrol değeri, `x-control-value-type: uint32`
 *     1 Cancel
 *     2 Downloading          (+ Downloading Packs list<string>)
 *     3 DownloadingFinished
 *     4 ResourcePackStackFinished
 *
 * DÜRÜST SINIR: Şema kontrol değerini `uint32` olarak işaretler ve alt
 * yapıların kendi `Response Type` alanı `int8` + `const`'tır. Cereal'ın bu
 * birleşimi tam olarak nasıl serileştirdiği (kontrol değerinin bir kez mi
 * yoksa iki kez mi yazıldığı) resmî belgede açık değildir. Bu uygulama
 * **tek bir uint32 kontrol değeri** okur/yazar ve bu tercih dokümante
 * edilmiştir. Gerçek istemciyle doğrulanması gerekir.
 */
final class ResourcePackClientResponsePacket implements PacketInterface
{
    /**
     * @param list<string> $downloadingPacks Yalnızca `Downloading` için anlamlı
     */
    public function __construct(
        public readonly int $response,
        public readonly array $downloadingPacks = []
    ) {
    }

    public static function of(ResourcePackResponse $response, array $downloadingPacks = []): self
    {
        return new self($response->value, $downloadingPacks);
    }

    public function getId(): int
    {
        return PacketId::RESOURCE_PACK_CLIENT_RESPONSE;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeUint32($this->response);

        if ($this->response === ResourcePackResponse::Downloading->value) {
            $writer->writeVarUInt(count($this->downloadingPacks));

            foreach ($this->downloadingPacks as $packId) {
                $writer->writeString($packId);
            }
        }
    }

    public static function decode(BinaryReader $reader): static
    {
        $response = $reader->readUint32();
        $packs = [];

        if ($response === ResourcePackResponse::Downloading->value) {
            $count = $reader->readVarUInt();

            for ($i = 0; $i < $count; $i++) {
                $packs[] = $reader->readString();
            }
        }

        return new static($response, $packs);
    }

    public function responseEnum(): ?ResourcePackResponse
    {
        return ResourcePackResponse::fromWire($this->response);
    }
}
