<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\binary\Uuid;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\types\PackIdVersion;
use PickaxeMP\protocol\packet\types\PackInfoData;

/**
 * `ResourcePacksInfoPacket` (id 6, S→C).
 *
 * Şema (Mojang v1.26.65, 2225) — ordinal sırası:
 *   0 Resource Pack Required          bool
 *   1 Has Addon Packs                 bool
 *   2 Has Scripts                     bool
 *   3 Force Disable Vibrant Visuals   bool
 *   4 World Template Id And Version   PackIdVersion (UUID + SemVersion)
 *   5 Resource Packs                  array<PackInfoData>  (varuint32 sayım)
 *
 * Boş paket listesiyle gönderildiğinde istemci "indirilecek paket yok"
 * durumuna geçer. Bu, sade bir sunucu için doğru davranıştır.
 */
final class ResourcePacksInfoPacket implements PacketInterface
{
    /**
     * @param list<PackInfoData> $resourcePacks
     */
    public function __construct(
        public readonly bool $resourcePackRequired = false,
        public readonly bool $hasAddonPacks = false,
        public readonly bool $hasScripts = false,
        public readonly bool $forceDisableVibrantVisuals = false,
        public readonly ?PackIdVersion $worldTemplateIdAndVersion = null,
        public readonly array $resourcePacks = []
    ) {
    }

    public function getId(): int
    {
        return PacketId::RESOURCE_PACKS_INFO;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeBool($this->resourcePackRequired);
        $writer->writeBool($this->hasAddonPacks);
        $writer->writeBool($this->hasScripts);
        $writer->writeBool($this->forceDisableVibrantVisuals);

        // Şemada bu alan zorunlu; boş UUID + "0.0.0" gönderilir.
        $template = $this->worldTemplateIdAndVersion
            ?? new PackIdVersion(Uuid::empty(), '0.0.0');
        $template->encode($writer);

        $writer->writeVarUInt(count($this->resourcePacks));

        foreach ($this->resourcePacks as $pack) {
            $pack->encode($writer);
        }
    }

    public static function decode(BinaryReader $reader): static
    {
        $required = $reader->readBool();
        $addon = $reader->readBool();
        $scripts = $reader->readBool();
        $vibrant = $reader->readBool();
        $template = PackIdVersion::decode($reader);

        $count = $reader->readVarUInt();
        $packs = [];

        for ($i = 0; $i < $count; $i++) {
            $packs[] = PackInfoData::decode($reader);
        }

        return new static($required, $addon, $scripts, $vibrant, $template, $packs);
    }
}
