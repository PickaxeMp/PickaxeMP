<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\types\ItemStackResponseInfo;
use PickaxeMP\protocol\ProtocolException;

/**
 * `ItemStackResponsePacket` (id **148**, sunucu → istemci).
 *
 * Sema (`ItemStackResponsePacketPayload.json`, 2225): `Responses`, varuint32
 * sayili `ItemStackResponse` listesidir. Her istek için **tek** yanıt
 * gönderilir; Phase 10.3 kapsamında sunucu istekleri tek tek yanıtlar.
 */
final class ItemStackResponsePacket implements PacketInterface
{
    public const MAX_RESPONSES = 100;

    /**
     * @param list<ItemStackResponseInfo> $responses
     */
    public function __construct(
        public readonly array $responses
    ) {
        if (count($responses) > self::MAX_RESPONSES) {
            throw ProtocolException::lengthTooLarge(
                'item stack responses',
                count($responses),
                self::MAX_RESPONSES
            );
        }
    }

    public function getId(): int
    {
        return PacketId::ITEM_STACK_RESPONSE;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeVarUInt(count($this->responses));

        foreach ($this->responses as $response) {
            $response->encode($writer);
        }
    }

    public static function decode(BinaryReader $reader): static
    {
        $count = $reader->readVarUInt();

        if ($count > self::MAX_RESPONSES) {
            throw ProtocolException::lengthTooLarge(
                'item stack responses',
                $count,
                self::MAX_RESPONSES
            );
        }

        $responses = [];

        for ($i = 0; $i < $count; $i++) {
            $responses[] = ItemStackResponseInfo::decode($reader);
        }

        return new static($responses);
    }
}
