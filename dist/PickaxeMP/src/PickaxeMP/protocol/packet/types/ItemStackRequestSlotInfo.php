<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\ProtocolException;

/**
 * `ItemStackRequestSlotInfo` — istekteki bir yuva referansı.
 *
 * Tel biçimi (Mojang `ItemStackRequestSlotInfo.json`, 2225):
 *
 * ```
 *  0 Full Container Name  uint8 + istege bagli uint32
 *  1 Slot                 uint8
 *  2 Net Id Variant       int32 (sabit LE, sikistirmasiz)
 * ```
 *
 * `Net Id Variant`, `ItemStackNetId` / `ItemStackRequestId` /
 * `ItemStackLegacyRequestId` birleşimidir. Şemada **"Compression" yoktur**;
 * gophertunnel `master` (protocol 2225) da bu alanı `r.Int32` ile sabit
 * genişlikte okur. `ItemStackRequestId` (zigzag) ile karıştırılmamalıdır.
 *
 * Sunucu ağ kimliği üretmediği için gelen değer doğrulanmaz; yalnızca
 * yuvarlanır (round-trip) ve teşhis için tutulur.
 */
final class ItemStackRequestSlotInfo
{
    public function __construct(
        public readonly FullContainerName $container,
        public readonly int $slot,
        public readonly int $netId = 0
    ) {
        if ($slot < 0 || $slot > 0xFF) {
            throw ProtocolException::varIntOverflow('item stack request slot (uint8)');
        }
    }

    public function encode(BinaryWriter $writer): void
    {
        $this->container->encode($writer);
        $writer->writeByte($this->slot);
        $writer->writeInt32($this->netId);
    }

    public static function decode(BinaryReader $reader): self
    {
        return new self(
            container: FullContainerName::decode($reader),
            slot: $reader->readByte(),
            netId: $reader->readInt32()
        );
    }

    public function isSameSlotAs(self $other): bool
    {
        return $this->slot === $other->slot
            && $this->container->containerName === $other->container->containerName
            && $this->container->dynamicId === $other->container->dynamicId;
    }
}
