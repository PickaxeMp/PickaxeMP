<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\types\BlockPos;
use PickaxeMP\protocol\packet\types\SpawnPositionType;

/**
 * `SetSpawnPositionPacket` (id **43**, sunucu -> istemci).
 *
 * Şema (Mojang v1.26.65, 2225) - `SetSpawnPositionPacketPayload`, ordinal
 * sırası:
 *
 * ```
 * 0 Spawn Position Type  int32 (Compression + Enum-as-Value: 0,1)
 * 1 Block Position       BlockPos (ZigZag varint32 x3)
 * 2 Dimension type       int32 (Compression) -> DimensionType.value
 * 3 Spawn Block Pos      BlockPos (ZigZag varint32 x3)
 * ```
 *
 * ## Neden gerekli
 *
 * Mojang açıklaması: "When a player logs in or the SetWorldSpawnCommand is
 * used this is sent from the server to the client." Paket gönderilmezse
 * istemcinin yeniden doğma noktası bilinmez: ölüm sonrası `Respawn`
 * sonrası istemci `{0,0,0}` çevresine düşebilir. Vanilla sunucu bunu
 * girişte ve yeniden doğmada yollar.
 *
 * ## Alan anlamları
 *
 * - `Block Position`: yatağın/çapanın konumu (1.16+ itibarıyla her zaman
 *   oyuncunun konumu).
 * - `Spawn Block Pos`: dünyanın gerçek spawn noktası; varsayılan için
 *   `{-2147483648, -2147483648, -2147483648}`.
 * - `Dimension`: spawn'ın güncellendiği boyut kimliği (`Overworld` = 0).
 */
final class SetSpawnPositionPacket implements PacketInterface
{
    /** "Ayarlanmamış" spawn için kullanılan sentinel (vanilla). */
    public const UNSET_SPAWN = -2147483648;

    public function __construct(
        public readonly SpawnPositionType $spawnType,
        public readonly BlockPos $blockPosition,
        public readonly int $dimension,
        public readonly BlockPos $spawnBlockPos
    ) {
    }

    /**
     * Dünya spawn'ı için kısayol: pusula ve yeniden doğma aynı noktayı
     * gösterir.
     */
    public static function worldSpawn(BlockPos $position, int $dimension = 0): self
    {
        return new self(SpawnPositionType::WorldSpawn, $position, $dimension, $position);
    }

    /**
     * Oyuncunun kişisel yeniden doğma noktası (yatak/çapa).
     *
     * `$worldSpawn` verilmezse sentinel yazılır: istemci dünya spawn'ını
     * değiştirmez, yalnız oyuncunun noktasını öğrenir.
     */
    public static function playerRespawn(
        BlockPos $position,
        int $dimension = 0,
        ?BlockPos $worldSpawn = null
    ): self {
        return new self(
            SpawnPositionType::PlayerRespawn,
            $position,
            $dimension,
            $worldSpawn ?? new BlockPos(
                self::UNSET_SPAWN,
                self::UNSET_SPAWN,
                self::UNSET_SPAWN
            )
        );
    }

    public function getId(): int
    {
        return PacketId::SET_SPAWN_POSITION;
    }

    public function encode(BinaryWriter $writer): void
    {
        // `Compression` = ZigZag varint32; `writeVarInt` bu kod tabanında
        // ZigZag'dır (bkz. BinaryWriter).
        $writer->writeVarInt($this->spawnType->value);
        $this->blockPosition->encode($writer);
        $writer->writeVarInt($this->dimension);
        $this->spawnBlockPos->encode($writer);
    }

    public static function decode(BinaryReader $reader): static
    {
        $spawnType = SpawnPositionType::tryFrom($reader->readVarInt())
            ?? SpawnPositionType::WorldSpawn;

        return new static(
            spawnType: $spawnType,
            blockPosition: BlockPos::decode($reader),
            dimension: $reader->readVarInt(),
            spawnBlockPos: BlockPos::decode($reader)
        );
    }
}
