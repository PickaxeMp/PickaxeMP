<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\protocol;

use PHPUnit\Framework\TestCase;
use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketDirection;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\PacketRegistry;
use PickaxeMP\protocol\packet\packets\InteractPacket;
use PickaxeMP\protocol\packet\packets\PlayerActionPacket;
use PickaxeMP\protocol\packet\packets\RespawnPacket;
use PickaxeMP\protocol\packet\packets\SetHealthPacket;
use PickaxeMP\protocol\packet\packets\UpdateBlockPacket;
use PickaxeMP\protocol\packet\types\BlockPos;
use PickaxeMP\protocol\packet\types\InteractAction;
use PickaxeMP\protocol\packet\types\PlayerActionType;
use PickaxeMP\protocol\packet\types\RespawnState;
use PickaxeMP\protocol\packet\types\Vec3;
use PickaxeMP\protocol\ProtocolException;

/**
 * Phase 7 - oynanis dongusu paketlerinin tel bicimi.
 *
 * Altin baytlar (golden bytes) elle, Mojang `v1.26.65` semasindaki alan
 * sirasindan turetilmistir. Testler kodlayicinin kendi ciktisini degil,
 * **seamadaki sirayi** dogrular.
 */
final class GameplayPacketTest extends TestCase
{
    private function bytes(PacketInterface $packet): string
    {
        $writer = new BinaryWriter();
        $packet->encode($writer);

        return $writer->bytes();
    }

    // --- Kimlikler ve kayit -------------------------------------------------

    public function testPacketIdsMatchOfficialSchema(): void
    {
        self::assertSame(21, PacketId::UPDATE_BLOCK);
        self::assertSame(33, PacketId::INTERACT);
        self::assertSame(36, PacketId::PLAYER_ACTION);
        self::assertSame(42, PacketId::SET_HEALTH);
        self::assertSame(45, PacketId::RESPAWN);
    }

    public function testRegistryContainsGameplayPacketsWithDirections(): void
    {
        $registry = PacketRegistry::withSupportedPackets();

        // 29 (Phase 1-6) + 4 (Phase 7.1) = 33; +8 (Phase 8-10.2) = 41;
        // +2 (Phase 10.3: ItemStackRequest/Response) +1 (Phase 10.4: ItemRegistry)
        // +1 (Phase 10.5: NetworkChunkPublisherUpdate) = 45.
        self::assertSame(46, $registry->count());
        self::assertTrue($registry->directionFor(PacketId::UPDATE_BLOCK)->allows(PacketDirection::ServerToClient));
        self::assertTrue($registry->directionFor(PacketId::SET_HEALTH)->allows(PacketDirection::ServerToClient));
        self::assertTrue($registry->directionFor(PacketId::RESPAWN)->allows(PacketDirection::ServerToClient));
        self::assertTrue($registry->directionFor(PacketId::INTERACT)->allows(PacketDirection::ClientToServer));
        self::assertTrue($registry->directionFor(PacketId::PLAYER_ACTION)->allows(PacketDirection::ClientToServer));
    }

    public function testPacketIdNamesAreHumanReadable(): void
    {
        self::assertSame('UpdateBlock', PacketId::name(PacketId::UPDATE_BLOCK));
        self::assertSame('Interact', PacketId::name(PacketId::INTERACT));
        self::assertSame('PlayerAction', PacketId::name(PacketId::PLAYER_ACTION));
        self::assertSame('SetHealth', PacketId::name(PacketId::SET_HEALTH));
        self::assertSame('Respawn', PacketId::name(PacketId::RESPAWN));
    }

    // --- SetHealth ----------------------------------------------------------

    public function testSetHealthGoldenBytesZigZag(): void
    {
        // 20 -> zigzag 40 = 0x28 ; 0 -> 0x00
        self::assertSame("\x28", $this->bytes(new SetHealthPacket(20)));
        self::assertSame("\x00", $this->bytes(new SetHealthPacket(0)));
    }

    public function testSetHealthRoundTrip(): void
    {
        $decoded = SetHealthPacket::decode(new BinaryReader($this->bytes(new SetHealthPacket(17))));

        self::assertSame(17, $decoded->health);
    }

    // --- UpdateBlock --------------------------------------------------------

    public function testUpdateBlockGoldenBytes(): void
    {
        $packet = new UpdateBlockPacket(
            position: new BlockPos(1, -64, 2),
            blockRuntimeId: 1234
        );

        // x=1 -> 0x02, y=-64 -> zigzag 127 = 0x7F, z=2 -> 0x04
        // runtime 1234 = 0xD2 0x09 (varuint), flags 3, layer 0
        self::assertSame("\x02\x7F\x04\xD2\x09\x03\x00", $this->bytes($packet));
    }

    public function testUpdateBlockRoundTrip(): void
    {
        $packet = new UpdateBlockPacket(
            position: new BlockPos(-100, 319, 200000),
            blockRuntimeId: 0xFFFFFFFF,
            flags: UpdateBlockPacket::FLAG_NEIGHBORS,
            layer: 1
        );

        $decoded = UpdateBlockPacket::decode(new BinaryReader($this->bytes($packet)));

        self::assertSame(-100, $decoded->position->x);
        self::assertSame(319, $decoded->position->y);
        self::assertSame(200000, $decoded->position->z);
        self::assertSame(0xFFFFFFFF, $decoded->blockRuntimeId);
        self::assertSame(UpdateBlockPacket::FLAG_NEIGHBORS, $decoded->flags);
        self::assertSame(1, $decoded->layer);
    }

    public function testUpdateBlockDefaultFlagsIncludeNetwork(): void
    {
        $packet = new UpdateBlockPacket(new BlockPos(0, 0, 0), 1);

        self::assertSame(
            UpdateBlockPacket::FLAG_NEIGHBORS | UpdateBlockPacket::FLAG_NETWORK,
            $packet->flags
        );
    }

    // --- Interact -----------------------------------------------------------

    public function testInteractWithoutPositionIsTwoBytes(): void
    {
        $packet = new InteractPacket(InteractAction::OpenInventory, 1);

        self::assertSame("\x06\x01", $this->bytes($packet));
    }

    public function testInteractUpdateCarriesPosition(): void
    {
        $packet = new InteractPacket(
            InteractAction::InteractUpdate,
            9,
            new Vec3(1.5, 2.5, 3.5)
        );

        $bytes = $this->bytes($packet);
        self::assertSame(1 + 1 + 12, strlen($bytes));

        $decoded = InteractPacket::decode(new BinaryReader($bytes));
        self::assertSame(InteractAction::InteractUpdate, $decoded->action);
        self::assertSame(9, $decoded->targetRuntimeId);
        self::assertNotNull($decoded->position);
        self::assertSame(1.5, $decoded->position->x);
        self::assertSame(3.5, $decoded->position->z);
    }

    public function testInteractUpdateWithoutExplicitPositionWritesZeroVector(): void
    {
        $packet = new InteractPacket(InteractAction::InteractUpdate, 9);

        self::assertSame(1 + 1 + 12, strlen($this->bytes($packet)));
    }

    public function testInteractUnknownActionMapsToInvalid(): void
    {
        $decoded = InteractPacket::decode(new BinaryReader("\x09\x01"));

        self::assertSame(InteractAction::Invalid, $decoded->action);
        self::assertNull($decoded->position);
    }

    // --- PlayerAction -------------------------------------------------------

    public function testPlayerActionGoldenBytesFieldOrder(): void
    {
        $packet = new PlayerActionPacket(
            playerRuntimeId: 5,
            action: PlayerActionType::Respawn,
            blockPosition: new BlockPos(0, 0, 0),
            resultPosition: new BlockPos(0, 0, 0),
            face: 0
        );

        // runtimeId 5 -> 0x05, action 7 -> zigzag 14 = 0x0E, 3+3 sifir, face 0
        self::assertSame("\x05\x0E\x00\x00\x00\x00\x00\x00\x00", $this->bytes($packet));
    }

    public function testPlayerActionRoundTrip(): void
    {
        $packet = new PlayerActionPacket(
            playerRuntimeId: 123456,
            action: PlayerActionType::PredictDestroyBlock,
            blockPosition: new BlockPos(10, 64, -10),
            resultPosition: new BlockPos(-1, -64, 1),
            face: 5
        );

        $decoded = PlayerActionPacket::decode(new BinaryReader($this->bytes($packet)));

        self::assertSame(123456, $decoded->playerRuntimeId);
        self::assertSame(PlayerActionType::PredictDestroyBlock, $decoded->action);
        self::assertSame(10, $decoded->blockPosition->x);
        self::assertSame(64, $decoded->blockPosition->y);
        self::assertSame(-10, $decoded->blockPosition->z);
        self::assertSame(-1, $decoded->resultPosition->x);
        self::assertSame(-64, $decoded->resultPosition->y);
        self::assertSame(5, $decoded->face);
    }

    public function testPlayerActionTypeWireValuesMatchSchema(): void
    {
        self::assertSame(0, PlayerActionType::StartDestroyBlock->value);
        self::assertSame(2, PlayerActionType::StopDestroyBlock->value);
        self::assertSame(7, PlayerActionType::Respawn->value);
        self::assertSame(13, PlayerActionType::CreativeDestroyBlock->value);
        self::assertSame(18, PlayerActionType::CrackBlock->value);
        self::assertSame(26, PlayerActionType::PredictDestroyBlock->value);
        self::assertSame(27, PlayerActionType::ContinueDestroyBlock->value);
        self::assertSame(-1, PlayerActionType::Unknown->value);
    }

    public function testPlayerActionTypeDestroysBlock(): void
    {
        self::assertTrue(PlayerActionType::CrackBlock->destroysBlock());
        self::assertTrue(PlayerActionType::PredictDestroyBlock->destroysBlock());
        self::assertTrue(PlayerActionType::CreativeDestroyBlock->destroysBlock());
        self::assertFalse(PlayerActionType::ContinueDestroyBlock->destroysBlock());
        self::assertFalse(PlayerActionType::StartDestroyBlock->destroysBlock());
        self::assertFalse(PlayerActionType::AbortDestroyBlock->destroysBlock());
    }

    // --- Respawn ------------------------------------------------------------

    public function testRespawnGoldenBytes(): void
    {
        $packet = new RespawnPacket(
            position: new Vec3(0.0, 64.0, 0.0),
            state: RespawnState::ReadyToSpawn,
            playerRuntimeId: 7
        );

        self::assertSame(
            "\x00\x00\x00\x00\x00\x00\x80\x42\x00\x00\x00\x00\x01\x07",
            $this->bytes($packet)
        );
    }

    public function testRespawnRoundTrip(): void
    {
        $packet = new RespawnPacket(
            position: new Vec3(-12.5, 70.25, 3.0),
            state: RespawnState::ReadyToSpawn,
            playerRuntimeId: 42
        );

        $decoded = RespawnPacket::decode(new BinaryReader($this->bytes($packet)));

        self::assertSame(-12.5, $decoded->position->x);
        self::assertSame(70.25, $decoded->position->y);
        self::assertSame(3.0, $decoded->position->z);
        self::assertSame(RespawnState::ReadyToSpawn, $decoded->state);
        self::assertSame(42, $decoded->playerRuntimeId);
    }

    public function testRespawnStateWireValuesMatchSchema(): void
    {
        self::assertSame(0, RespawnState::SearchingForSpawn->value);
        self::assertSame(1, RespawnState::ReadyToSpawn->value);
        self::assertSame(2, RespawnState::ClientReadyToSpawn->value);
    }

    // --- Katilik ------------------------------------------------------------

    public function testGameplayPacketsRejectTrailingBytes(): void
    {
        $cases = [
            [new SetHealthPacket(20)],
            [new UpdateBlockPacket(new BlockPos(1, 2, 3), 4)],
            [new InteractPacket(InteractAction::OpenInventory, 1)],
            [
                new PlayerActionPacket(
                    playerRuntimeId: 1,
                    action: PlayerActionType::Respawn,
                    blockPosition: new BlockPos(0, 0, 0),
                    resultPosition: new BlockPos(0, 0, 0),
                    face: 0
                ),
            ],
            [
                new RespawnPacket(
                    position: Vec3::zero(),
                    state: RespawnState::ReadyToSpawn,
                    playerRuntimeId: 1
                ),
            ],
        ];

        foreach ($cases as [$packet]) {
            $class = $packet::class;
            $reader = new BinaryReader($this->bytes($packet) . "\x00");

            $class::decode($reader);

            $threw = false;

            try {
                $reader->assertFullyConsumed($class);
            } catch (ProtocolException) {
                $threw = true;
            }

            self::assertTrue($threw, 'Artan bayt kabul edildi: ' . $class);
        }
    }
}
