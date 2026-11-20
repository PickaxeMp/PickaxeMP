<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\ProtocolException;

/**
 * Tek bir `ItemStackResponse` (paket 148'in gövdesi).
 *
 * Tel biçimi (Mojang `ItemStackResponseInfo.json`, 2225):
 *
 * ```
 *  0 Result           uint8 (ItemStackNetResult)
 *  1 Client Request Id varint32 (zigzag)
 *  2 Containers       istege bagli: varlik bayti + varuint32 sayi + konteynerler
 * ```
 *
 * `Containers` **yalnızca** `Result == Success` iken dolu olur; hata
 * durumunda istemci yalnızca kimliği eşleyip tahminini geri alır.
 */
final class ItemStackResponseInfo
{
    public const MAX_CONTAINERS = 64;

    /**
     * @param list<ItemStackResponseContainerInfo> $containers
     */
    public function __construct(
        public readonly int $result,
        public readonly int $requestId,
        public readonly array $containers = []
    ) {
        if ($result < 0 || $result > 0xFF) {
            throw ProtocolException::varIntOverflow('item stack net result (uint8)');
        }

        if (count($containers) > self::MAX_CONTAINERS) {
            throw ProtocolException::lengthTooLarge(
                'item stack response containers',
                count($containers),
                self::MAX_CONTAINERS
            );
        }
    }

    public static function success(int $requestId, array $containers = []): self
    {
        return new self(ItemStackNetResult::SUCCESS, $requestId, $containers);
    }

    public static function error(int $requestId, int $result): self
    {
        return new self($result, $requestId);
    }

    public function isSuccess(): bool
    {
        return ItemStackNetResult::isSuccess($this->result);
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeByte($this->result);
        $writer->writeVarInt($this->requestId);

        $writer->writeOptional($this->containers !== [], function (BinaryWriter $w): void {
            $w->writeVarUInt(count($this->containers));

            foreach ($this->containers as $container) {
                $container->encode($w);
            }
        });
    }

    public static function decode(BinaryReader $reader): self
    {
        $result = $reader->readByte();
        $requestId = $reader->readVarInt();

        $containers = [];

        if ($reader->readBool()) {
            $count = $reader->readVarUInt();

            if ($count > self::MAX_CONTAINERS) {
                throw ProtocolException::lengthTooLarge(
                    'item stack response containers',
                    $count,
                    self::MAX_CONTAINERS
                );
            }

            for ($i = 0; $i < $count; $i++) {
                $containers[] = ItemStackResponseContainerInfo::decode($reader);
            }
        }

        return new self($result, $requestId, $containers);
    }
}
