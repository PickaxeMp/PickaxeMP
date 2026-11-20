<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\types\ItemStackRequest;
use PickaxeMP\protocol\ProtocolException;

/**
 * `ItemStackRequestPacket` (id **147**, istemci → sunucu).
 *
 * Sema (`ItemStackRequestPacketPayload.json`, 2225): `Requests`, varuint32
 * sayili `ItemStackRequest` listesidir. Her istek bağımsız bir işlemdir ve
 * ayrı bir `ItemStackResponse` ile yanıtlanır.
 */
final class ItemStackRequestPacket implements PacketInterface
{
    public const MAX_REQUESTS = 100;

    /**
     * @param list<ItemStackRequest> $requests
     */
    public function __construct(
        public readonly array $requests
    ) {
        if (count($requests) > self::MAX_REQUESTS) {
            throw ProtocolException::lengthTooLarge(
                'item stack requests',
                count($requests),
                self::MAX_REQUESTS
            );
        }
    }

    public function getId(): int
    {
        return PacketId::ITEM_STACK_REQUEST;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeVarUInt(count($this->requests));

        foreach ($this->requests as $request) {
            $request->encode($writer);
        }
    }

    public static function decode(BinaryReader $reader): static
    {
        $count = $reader->readVarUInt();

        if ($count > self::MAX_REQUESTS) {
            throw ProtocolException::lengthTooLarge(
                'item stack requests',
                $count,
                self::MAX_REQUESTS
            );
        }

        $requests = [];

        for ($i = 0; $i < $count; $i++) {
            $requests[] = ItemStackRequest::decode($reader);
        }

        return new static($requests);
    }
}
