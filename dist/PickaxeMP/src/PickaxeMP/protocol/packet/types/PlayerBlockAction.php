<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;

/**
 * `PlayerBlockActions` ogesi (Mojang semasi, 2225).
 *
 * Sira: Action Type (int32, sikistirmasiz) -> Block Position (BlockPos,
 * varint32 x 3) -> Face (int32, sikistirmasiz).
 *
 * Degerler `PlayerActionType` enum'undan **turetilir**; boylece sabit ile
 * sema arasinda kayma olmaz (Phase 7'de duzeltildi: eski sabitler
 * `STOP_BREAK`/`CRACK_BREAK` icin 2 ve 18'i karistiriyordu).
 */
final class PlayerBlockAction
{
    /** Kirma baslangici (`StartDestroyBlock`). */
    public const START_BREAK = 0;
    /** Kirma iptali (`AbortDestroyBlock`). */
    public const ABORT_BREAK = 1;
    /** Kirma durdu (`StopDestroyBlock`) - konum/yon tasimaz. */
    public const STOP_BREAK = 2;
    /** YaratIcI kirma (`CreativeDestroyBlock`). */
    public const CREATIVE_DESTROY_BLOCK = 13;
    /**
     * Istemci blogu kirdi (`CrackBlock`).
     *
     * Sunucu yetkili kirma **kapali** iken gonderilir; istemci kirma
     * animasyonunu yerel oynatmistir ve sunucudan yalnizca sonucu bekler.
     */
    public const CRACK_BREAK = 18;
    /** Istemci kirma tahminini tamamladi (`PredictDestroyBlock`). */
    public const PREDICT_DESTROY_BLOCK = 26;
    /** Kirma ilerlemesi (`ContinueDestroyBlock`) - sunucu yetkili kirma acikken. */
    public const CONTINUE_DESTROY_BLOCK = 27;

    public function __construct(
        public readonly int $actionType,
        public readonly int $x,
        public readonly int $y,
        public readonly int $z,
        public readonly int $face
    ) {
    }

    /**
     * Bu eylem blogun sunucuda gercekten yok edilmesini ister mi?
     */
    public function isBreak(): bool
    {
        return $this->actionType === self::CRACK_BREAK
            || $this->actionType === self::PREDICT_DESTROY_BLOCK
            || $this->actionType === self::CREATIVE_DESTROY_BLOCK;
    }

    public function isStartBreak(): bool
    {
        return $this->actionType === self::START_BREAK;
    }

    public function isAbortBreak(): bool
    {
        return $this->actionType === self::ABORT_BREAK;
    }

    public function action(): PlayerActionType
    {
        return PlayerActionType::fromWire($this->actionType);
    }

    public static function decode(BinaryReader $reader): self
    {
        return new self(
            actionType: $reader->readInt32(),
            x: $reader->readVarInt(),
            y: $reader->readVarInt(),
            z: $reader->readVarInt(),
            face: $reader->readInt32()
        );
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeInt32($this->actionType);
        $writer->writeVarInt($this->x);
        $writer->writeVarInt($this->y);
        $writer->writeVarInt($this->z);
        $writer->writeInt32($this->face);
    }
}
