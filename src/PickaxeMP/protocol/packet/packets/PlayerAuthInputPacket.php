<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\types\PlayerAuthInputFlags;
use PickaxeMP\protocol\packet\types\PlayerBlockAction;
use PickaxeMP\protocol\packet\types\ItemUseInventoryTransaction;
use PickaxeMP\protocol\packet\types\ItemStackRequest;
use PickaxeMP\protocol\ProtocolException;

/**
 * `PlayerAuthInputPacket` (id **144**, C→S).
 *
 * Şema (Mojang v1.26.65, 2225) — 19 alan:
 *
 * ```
 *  0 Player Rotation      Vec2 (pitch, yaw)  float × 2
 *  1 Position             Vec3              float × 3
 *  2 Move Vector          Vec2              float × 2
 *  3 Player Head Rotation float
 *  4 Input Data           varint64 (bit seti)
 *  5 Input Mode           varuint32
 *  6 Play Mode            varuint32
 *  7 New Interaction Model int32
 *  8 Interact Rotation    Vec2
 *  9 Client Tick          varuint64
 * 10 Pos Delta            Vec3
 * 11 Item Use Transaction  KOŞULLU (bit 32)
 * 12 Item Stack Request    KOŞULLU (bit 34)
 * 13 Player Block Actions  KOŞULLU (bit 33)
 * 14 Vehicle Rotation      Vec2
 * 15 Client Predicted Vehicle KOŞULLU (bit 36)
 * 16 Analog Move Vector    Vec2
 * 17 Camera Orientation    Vec3
 * 18 Raw Move Vector       Vec2
 * ```
 *
 * ## Phase 5 kapsamı
 *
 * - Çekirdek alanlar (0-10, 14, 16-18) tam desteklenir.
 * - `Player Block Actions` (13) **desteklenir** — blok kırma için gerekli.
 * - `Item Use Transaction` (11) **desteklenir** (Phase 7) — blok yerleştirme
 *   girdisi budur.
 * - `Item Stack Request` (12) **desteklenir** (Phase 10.3) — envanterde eşya
 *   taşıma. Sunucu yanıtı `ConnectionPipeline` içinde üretilir.
 * - `Client Predicted Vehicle` (15) desteklenmez (araç Phase 8).
 */
final class PlayerAuthInputPacket implements PacketInterface
{
    /**
     * @param list<PlayerBlockAction> $blockActions
     */
    public function __construct(
        public readonly float $pitch,
        public readonly float $yaw,
        public readonly float $x,
        public readonly float $y,
        public readonly float $z,
        public readonly float $moveX,
        public readonly float $moveZ,
        public readonly float $headYaw,
        public readonly int $inputData,
        public readonly int $inputMode,
        public readonly int $playMode,
        public readonly int $interactionModel,
        public readonly float $interactPitch,
        public readonly float $interactYaw,
        public readonly int $clientTick,
        public readonly float $posDeltaX = 0.0,
        public readonly float $posDeltaY = 0.0,
        public readonly float $posDeltaZ = 0.0,
        public readonly array $blockActions = [],
        public readonly ?ItemUseInventoryTransaction $itemUseTransaction = null,
        public readonly float $vehiclePitch = 0.0,
        public readonly float $vehicleYaw = 0.0,
        public readonly float $analogMoveX = 0.0,
        public readonly float $analogMoveZ = 0.0,
        public readonly float $cameraX = 0.0,
        public readonly float $cameraY = 0.0,
        public readonly float $cameraZ = 0.0,
        public readonly float $rawMoveX = 0.0,
        public readonly float $rawMoveZ = 0.0,
        public readonly ?ItemStackRequest $itemStackRequest = null
    ) {
    }

    public function getId(): int
    {
        return PacketId::PLAYER_AUTH_INPUT;
    }

    /**
     * Bit setinde verilen bayrak var mı?
     */
    public function hasFlag(int $flag): bool
    {
        return PlayerAuthInputFlags::has($this->inputData, $flag);
    }

    /**
     * Bu pakette blok işlemi var mı?
     */
    public function hasBlockActions(): bool
    {
        return $this->blockActions !== [];
    }

    public function encode(BinaryWriter $writer): void
    {
        // 0 Player Rotation
        $writer->writeFloat($this->pitch);
        $writer->writeFloat($this->yaw);

        // 1 Position
        $writer->writeFloat($this->x);
        $writer->writeFloat($this->y);
        $writer->writeFloat($this->z);

        // 2 Move Vector
        $writer->writeFloat($this->moveX);
        $writer->writeFloat($this->moveZ);

        // 3 Head Rotation
        $writer->writeFloat($this->headYaw);

        // 4 Input Data (varint64 — sıkıştırmalı)
        $writer->writeVarLong($this->inputData);

        // 5-6
        $writer->writeVarUInt($this->inputMode);
        $writer->writeVarUInt($this->playMode);

        // 7 New Interaction Model (int32 düz)
        $writer->writeInt32($this->interactionModel);

        // 8 Interact Rotation
        $writer->writeFloat($this->interactPitch);
        $writer->writeFloat($this->interactYaw);

        // 9 Client Tick (varuint64)
        $writer->writeVarULong($this->clientTick);

        // 10 Pos Delta
        $writer->writeFloat($this->posDeltaX);
        $writer->writeFloat($this->posDeltaY);
        $writer->writeFloat($this->posDeltaZ);

        // 11 Item Use Transaction - KOŞULLU
        if (PlayerAuthInputFlags::has($this->inputData, PlayerAuthInputFlags::PERFORM_ITEM_INTERACTION)) {
            if ($this->itemUseTransaction === null) {
                throw new ProtocolException(
                    'PlayerAuthInput: Item Use Transaction bayragi acik ama veri yok'
                );
            }

            $this->itemUseTransaction->encode($writer);
        }

        // 12 Item Stack Request - KOŞULLU
        if (PlayerAuthInputFlags::has($this->inputData, PlayerAuthInputFlags::PERFORM_ITEM_STACK_REQUEST)) {
            if ($this->itemStackRequest === null) {
                throw new ProtocolException(
                    'PlayerAuthInput: Item Stack Request bayragi acik ama veri yok'
                );
            }

            $this->itemStackRequest->encode($writer);
        }

        // 13 Player Block Actions — KOŞULLU
        if (PlayerAuthInputFlags::has($this->inputData, PlayerAuthInputFlags::PERFORM_BLOCK_ACTIONS)) {
            $writer->writeVarUInt(count($this->blockActions));

            foreach ($this->blockActions as $action) {
                $action->encode($writer);
            }
        }

        // 14 Vehicle Rotation
        $writer->writeFloat($this->vehiclePitch);
        $writer->writeFloat($this->vehicleYaw);

        // 15 Client Predicted Vehicle — desteklenmiyor
        if (PlayerAuthInputFlags::has($this->inputData, PlayerAuthInputFlags::IS_IN_CLIENT_PREDICTED_VEHICLE)) {
            throw new ProtocolException(
                'PlayerAuthInput: Client Predicted Vehicle Phase 5 kapsaminda degil'
            );
        }

        // 16 Analog Move Vector
        $writer->writeFloat($this->analogMoveX);
        $writer->writeFloat($this->analogMoveZ);

        // 17 Camera Orientation
        $writer->writeFloat($this->cameraX);
        $writer->writeFloat($this->cameraY);
        $writer->writeFloat($this->cameraZ);

        // 18 Raw Move Vector
        $writer->writeFloat($this->rawMoveX);
        $writer->writeFloat($this->rawMoveZ);
    }

    public static function decode(BinaryReader $reader): static
    {
        $pitch = $reader->readFloat();
        $yaw = $reader->readFloat();

        $x = $reader->readFloat();
        $y = $reader->readFloat();
        $z = $reader->readFloat();

        $moveX = $reader->readFloat();
        $moveZ = $reader->readFloat();

        $headYaw = $reader->readFloat();

        $inputData = $reader->readVarLong();

        $inputMode = $reader->readVarUInt();
        $playMode = $reader->readVarUInt();
        $interactionModel = $reader->readInt32();

        $interactPitch = $reader->readFloat();
        $interactYaw = $reader->readFloat();

        $clientTick = $reader->readVarULong();

        $posDeltaX = $reader->readFloat();
        $posDeltaY = $reader->readFloat();
        $posDeltaZ = $reader->readFloat();

        // 11 Item Use Transaction
        $itemUseTransaction = null;

        if (PlayerAuthInputFlags::has($inputData, PlayerAuthInputFlags::PERFORM_ITEM_INTERACTION)) {
            $itemUseTransaction = ItemUseInventoryTransaction::decode($reader);
        }

        // 12 Item Stack Request
        $itemStackRequest = null;

        if (PlayerAuthInputFlags::has($inputData, PlayerAuthInputFlags::PERFORM_ITEM_STACK_REQUEST)) {
            $itemStackRequest = ItemStackRequest::decode($reader);
        }

        // 13 Player Block Actions
        $blockActions = [];

        if (PlayerAuthInputFlags::has($inputData, PlayerAuthInputFlags::PERFORM_BLOCK_ACTIONS)) {
            $count = $reader->readVarUInt();

            for ($i = 0; $i < $count; $i++) {
                $blockActions[] = PlayerBlockAction::decode($reader);
            }
        }

        $vehiclePitch = $reader->readFloat();
        $vehicleYaw = $reader->readFloat();

        if (PlayerAuthInputFlags::has($inputData, PlayerAuthInputFlags::IS_IN_CLIENT_PREDICTED_VEHICLE)) {
            throw new ProtocolException(
                'PlayerAuthInput: Client Predicted Vehicle Phase 5 kapsaminda degil'
            );
        }

        $analogMoveX = $reader->readFloat();
        $analogMoveZ = $reader->readFloat();

        $cameraX = $reader->readFloat();
        $cameraY = $reader->readFloat();
        $cameraZ = $reader->readFloat();

        $rawMoveX = $reader->readFloat();
        $rawMoveZ = $reader->readFloat();

        return new static(
            pitch: $pitch,
            yaw: $yaw,
            x: $x,
            y: $y,
            z: $z,
            moveX: $moveX,
            moveZ: $moveZ,
            headYaw: $headYaw,
            inputData: $inputData,
            inputMode: $inputMode,
            playMode: $playMode,
            interactionModel: $interactionModel,
            interactPitch: $interactPitch,
            interactYaw: $interactYaw,
            clientTick: $clientTick,
            posDeltaX: $posDeltaX,
            posDeltaY: $posDeltaY,
            posDeltaZ: $posDeltaZ,
            blockActions: $blockActions,
            itemUseTransaction: $itemUseTransaction,
            vehiclePitch: $vehiclePitch,
            vehicleYaw: $vehicleYaw,
            analogMoveX: $analogMoveX,
            analogMoveZ: $analogMoveZ,
            cameraX: $cameraX,
            cameraY: $cameraY,
            cameraZ: $cameraZ,
            rawMoveX: $rawMoveX,
            rawMoveZ: $rawMoveZ,
            itemStackRequest: $itemStackRequest
        );
    }
}
