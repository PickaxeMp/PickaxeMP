<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\types\ExperimentToggle;
use PickaxeMP\protocol\packet\types\PackInstanceId;

/**
 * `ResourcePackStackPacket` (id 7, S→C).
 *
 * Şema (Mojang v1.26.65, 2225) — ordinal sırası:
 *   0 Texture Pack Required   bool
 *   1 Texture Pack List       array<PackInstanceId> (varuint32 sayım)
 *   2 Base Game Version       string
 *   3 Experiments             { Toggles (sıkıştırmasız sayım), EverToggled bool }
 *   4 Include Editor Packs    bool
 *
 * ÖNEMLİ SÜRÜM FARKI: 1.26.70-beta (2259) sürümünde 4. alan `Editor Pack
 * Types` (uint8 bit bayrakları) olmuştur. Hedef 2225 olduğu için burada
 * **bool** kullanılır.
 *
 * `Experiments.Toggles` şemasında `"No size compression"` işaretlidir; yani
 * dizi sayımı varuint32 DEĞİL, sabit genişlikli tam sayıdır. Burada uint32
 * (little-endian) kullanılır. Bu ayrıntı gerçek istemciyle doğrulanmamıştır.
 */
final class ResourcePackStackPacket implements PacketInterface
{
    /**
     * @param list<PackInstanceId> $texturePackList
     * @param list<ExperimentToggle> $experimentToggles
     */
    public function __construct(
        public readonly bool $texturePackRequired = false,
        public readonly array $texturePackList = [],
        public readonly string $baseGameVersion = '',
        public readonly array $experimentToggles = [],
        public readonly bool $experimentsEverToggled = false,
        public readonly bool $includeEditorPacks = false
    ) {
    }

    public function getId(): int
    {
        return PacketId::RESOURCE_PACK_STACK;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeBool($this->texturePackRequired);

        $writer->writeVarUInt(count($this->texturePackList));

        foreach ($this->texturePackList as $pack) {
            $pack->encode($writer);
        }

        $writer->writeString($this->baseGameVersion);

        // "No size compression" → sabit uint32 sayım.
        $writer->writeUint32(count($this->experimentToggles));

        foreach ($this->experimentToggles as $toggle) {
            $toggle->encode($writer);
        }

        $writer->writeBool($this->experimentsEverToggled);
        $writer->writeBool($this->includeEditorPacks);
    }

    public static function decode(BinaryReader $reader): static
    {
        $required = $reader->readBool();

        $packCount = $reader->readVarUInt();
        $packs = [];

        for ($i = 0; $i < $packCount; $i++) {
            $packs[] = PackInstanceId::decode($reader);
        }

        $baseGameVersion = $reader->readString();

        $toggleCount = $reader->readUint32();
        $toggles = [];

        for ($i = 0; $i < $toggleCount; $i++) {
            $toggles[] = ExperimentToggle::decode($reader);
        }

        $everToggled = $reader->readBool();
        $includeEditor = $reader->readBool();

        return new static($required, $packs, $baseGameVersion, $toggles, $everToggled, $includeEditor);
    }
}
