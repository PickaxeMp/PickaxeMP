<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\ProtocolException;

/**
 * Tek bir `ItemStackRequest` (paket 147'nin ve `PlayerAuthInput` bit 34'ün
 * gövdesi).
 *
 * Tel biçimi (Mojang `ItemStackRequest.json`, 2225):
 *
 * ```
 *  0 Client Request Id      varint32 (zigzag) — negatif tek sayi olmali
 *  1 Actions                varuint32 sayi (1..100) + aksiyonlar
 *  2 Strings To Filter      varuint32 sayi + string (her biri <= 1000 bayt)
 *  3 StringsToFilterOrigin  int32 (sabit LE)
 * ```
 */
final class ItemStackRequest
{
    public const MAX_ACTIONS = 100;
    public const MAX_FILTER_STRINGS = 64;
    public const MAX_FILTER_STRING_BYTES = 1000;

    /**
     * @param list<ItemStackRequestAction> $actions
     * @param list<string> $filterStrings
     */
    public function __construct(
        public readonly int $requestId,
        public readonly array $actions,
        public readonly array $filterStrings = [],
        public readonly int $filterStringOrigin = 0
    ) {
    }

    /**
     * Şemanın çalışma zamanı kuralı: istemci istek kimliği **negatif ve tek**
     * olmalıdır. Aksi hâlde kimlik çakışması riski doğar; istek reddedilir.
     */
    public function hasValidRequestId(): bool
    {
        return $this->requestId < 0 && $this->requestId % 2 !== 0;
    }

    public function encode(BinaryWriter $writer): void
    {
        if (count($this->actions) < 1 || count($this->actions) > self::MAX_ACTIONS) {
            throw ProtocolException::lengthTooLarge(
                'item stack request actions',
                count($this->actions),
                self::MAX_ACTIONS
            );
        }

        $writer->writeVarInt($this->requestId);
        $writer->writeVarUInt(count($this->actions));

        foreach ($this->actions as $action) {
            $action->encode($writer);
        }

        $writer->writeVarUInt(count($this->filterStrings));

        foreach ($this->filterStrings as $string) {
            $writer->writeString($string, self::MAX_FILTER_STRING_BYTES);
        }

        $writer->writeInt32($this->filterStringOrigin);
    }

    public static function decode(BinaryReader $reader): self
    {
        $requestId = $reader->readVarInt();

        $actionCount = $reader->readVarUInt();

        if ($actionCount < 1 || $actionCount > self::MAX_ACTIONS) {
            throw ProtocolException::lengthTooLarge(
                'item stack request actions',
                $actionCount,
                self::MAX_ACTIONS
            );
        }

        $actions = [];

        for ($i = 0; $i < $actionCount; $i++) {
            $actions[] = ItemStackRequestAction::decode($reader);
        }

        $stringCount = $reader->readVarUInt();

        if ($stringCount > self::MAX_FILTER_STRINGS) {
            throw ProtocolException::lengthTooLarge(
                'item stack request filter strings',
                $stringCount,
                self::MAX_FILTER_STRINGS
            );
        }

        $filterStrings = [];

        for ($i = 0; $i < $stringCount; $i++) {
            $filterStrings[] = $reader->readString(self::MAX_FILTER_STRING_BYTES);
        }

        return new self(
            requestId: $requestId,
            actions: $actions,
            filterStrings: $filterStrings,
            filterStringOrigin: $reader->readInt32()
        );
    }
}
