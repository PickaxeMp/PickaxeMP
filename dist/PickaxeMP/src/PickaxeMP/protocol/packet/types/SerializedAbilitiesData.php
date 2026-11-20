<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\ProtocolException;

/**
 * `SerializedAbilitiesData` — oyuncunun izinleri ve yetenek katmanlari.
 *
 * Tel bicimi (Mojang `SerializedAbilitiesData.json` ordinal 0..3):
 * Target Player Raw Id int64 (sabit LE), Player Permissions int8,
 * Command Permissions uint8, Layers **varuint32** sayi + katmanlar.
 *
 * Katman sayisi **varuint32**'dir (gophertunnel `protocol/ability.go`
 * `Slice(r, &x.Layers)`); tek bayt olarak yazmak 256+ katman sinirinda
 * sessizce bozuk paket uretirdi.
 */
final class SerializedAbilitiesData
{
    public const MAX_LAYERS = 32;

    /**
     * @param list<AbilityLayer> $layers
     */
    public function __construct(
        public readonly int $targetPlayerRawId,
        public readonly int $playerPermissions,
        public readonly int $commandPermissions,
        public readonly array $layers
    ) {
        if ($playerPermissions < -128 || $playerPermissions > 127) {
            throw ProtocolException::varIntOverflow('player permissions (int8)');
        }

        if ($commandPermissions < 0 || $commandPermissions > 0xFF) {
            throw ProtocolException::varIntOverflow('command permissions (uint8)');
        }

        if (count($layers) > self::MAX_LAYERS) {
            throw ProtocolException::lengthTooLarge('ability layers', count($layers), self::MAX_LAYERS);
        }

        foreach ($layers as $layer) {
            if (!$layer instanceof AbilityLayer) {
                throw new ProtocolException('SerializedAbilitiesData: katmanlar AbilityLayer olmali');
            }
        }
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeInt64($this->targetPlayerRawId);
        $writer->writeSignedByte($this->playerPermissions);
        $writer->writeByte($this->commandPermissions);
        $writer->writeVarUInt(count($this->layers));

        foreach ($this->layers as $layer) {
            $layer->encode($writer);
        }
    }

    public static function decode(BinaryReader $reader): self
    {
        $targetPlayerRawId = $reader->readInt64();
        $playerPermissions = $reader->readSignedByte();
        $commandPermissions = $reader->readByte();
        $layerCount = $reader->readVarUInt();

        if ($layerCount > self::MAX_LAYERS) {
            throw ProtocolException::lengthTooLarge('ability layers', $layerCount, self::MAX_LAYERS);
        }

        $layers = [];

        for ($i = 0; $i < $layerCount; $i++) {
            $layers[] = AbilityLayer::decode($reader);
        }

        return new self($targetPlayerRawId, $playerPermissions, $commandPermissions, $layers);
    }
}
