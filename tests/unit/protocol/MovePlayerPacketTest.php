<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\protocol;

use PHPUnit\Framework\TestCase;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\compression\CompressionManager;
use PickaxeMP\protocol\packet\PacketContext;
use PickaxeMP\protocol\packet\PacketDirection;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketRegistry;
use PickaxeMP\protocol\packet\packets\MovePlayerPacket;
use PickaxeMP\protocol\ProtocolEngine;
use PickaxeMP\protocol\ProtocolException;

/**
 * `MovePlayerPacket` (id 19, S→C) kodlama sözleşmesi.
 *
 * Şema: `docs/protocol/schemas/v1.26.65/MovePlayerPacket*.json`
 * (`[cereal:packet]` = 19).
 */
final class MovePlayerPacketTest extends TestCase
{
    private function engine(): ProtocolEngine
    {
        return new ProtocolEngine(PacketRegistry::withSupportedPackets(), new CompressionManager());
    }

    private function s2c(): PacketContext
    {
        return new PacketContext('test', PacketDirection::ServerToClient);
    }

    public function testPacketIdMatchesOfficialSchema(): void
    {
        self::assertSame(19, PacketId::MOVE_PLAYER);
        self::assertSame('MovePlayer', PacketId::name(19));
    }

    public function testRegistryKnowsMovePlayerAsServerBoundPacket(): void
    {
        $registry = PacketRegistry::withSupportedPackets();

        self::assertTrue($registry->has(PacketId::MOVE_PLAYER));
        self::assertSame(
            PacketDirection::ServerToClient,
            $registry->directionFor(PacketId::MOVE_PLAYER)
        );
    }

    public function testTeleportRoundTrip(): void
    {
        $packet = new MovePlayerPacket(
            runtimeId: 4242,
            x: 8.5,
            y: -57.25,
            z: -8.75,
            pitch: 12.5,
            yaw: 181.0,
            headYaw: 179.5,
            mode: MovePlayerPacket::MODE_TELEPORT,
            onGround: true,
            ridingRuntimeId: 7,
            teleportCause: 3,
            teleportSource: 5,
            tick: 9001
        );

        $engine = $this->engine();
        $decoded = $engine->decode($engine->encode($packet), $this->s2c());

        self::assertInstanceOf(MovePlayerPacket::class, $decoded);
        self::assertSame(4242, $decoded->runtimeId);
        self::assertEqualsWithDelta(8.5, $decoded->x, 1.0e-6);
        self::assertEqualsWithDelta(-57.25, $decoded->y, 1.0e-6);
        self::assertEqualsWithDelta(-8.75, $decoded->z, 1.0e-6);
        self::assertEqualsWithDelta(12.5, $decoded->pitch, 1.0e-6);
        self::assertEqualsWithDelta(181.0, $decoded->yaw, 1.0e-6);
        self::assertEqualsWithDelta(179.5, $decoded->headYaw, 1.0e-6);
        self::assertSame(MovePlayerPacket::MODE_TELEPORT, $decoded->mode);
        self::assertTrue($decoded->onGround);
        self::assertSame(7, $decoded->ridingRuntimeId);
        self::assertSame(3, $decoded->teleportCause);
        self::assertSame(5, $decoded->teleportSource);
        self::assertSame(9001, $decoded->tick);
    }

    /**
     * Teleport olmayan modlarda şema teleport verisini **yazmaz**.
     * Yanlışlıkla yazılırsa istemci tick alanını teleport verisi sanır.
     */
    public function testNonTeleportModeOmitsTeleportData(): void
    {
        $packet = new MovePlayerPacket(
            runtimeId: 1,
            x: 0.0,
            y: 0.0,
            z: 0.0,
            pitch: 0.0,
            yaw: 0.0,
            headYaw: 0.0,
            mode: MovePlayerPacket::MODE_NORMAL,
            teleportCause: 9,
            teleportSource: 9,
            tick: 123
        );

        $engine = $this->engine();
        $decoded = $engine->decode($engine->encode($packet), $this->s2c());

        self::assertInstanceOf(MovePlayerPacket::class, $decoded);
        self::assertSame(MovePlayerPacket::MODE_NORMAL, $decoded->mode);
        self::assertSame(0, $decoded->teleportCause, 'Normal modda teleport verisi yazilmamali');
        self::assertSame(0, $decoded->teleportSource);
        self::assertSame(123, $decoded->tick);
    }

    public function testEncodedFrameStartsWithPacketId19(): void
    {
        $bytes = $this->engine()->encode(new MovePlayerPacket(
            runtimeId: 0,
            x: 0.0,
            y: 0.0,
            z: 0.0,
            pitch: 0.0,
            yaw: 0.0,
            headYaw: 0.0
        ));

        self::assertSame(19, ord($bytes[0]), 'VarUInt 19 tek bayttir');
    }

    public function testConstructorRejectsUnknownMode(): void
    {
        $this->expectException(ProtocolException::class);

        new MovePlayerPacket(
            runtimeId: 0,
            x: 0.0,
            y: 0.0,
            z: 0.0,
            pitch: 0.0,
            yaw: 0.0,
            headYaw: 0.0,
            mode: 4
        );
    }

    public function testDecodeRejectsUnknownModeByte(): void
    {
        $writer = new BinaryWriter();
        $writer->writeVarUInt(PacketId::MOVE_PLAYER);
        $writer->writeVarULong(1);   // runtimeId
        $writer->writeFloat(0.0);    // x
        $writer->writeFloat(0.0);    // y
        $writer->writeFloat(0.0);    // z
        $writer->writeFloat(0.0);    // pitch
        $writer->writeFloat(0.0);    // yaw
        $writer->writeFloat(0.0);    // headYaw
        $writer->writeByte(200);     // GEÇERSİZ mod

        $this->expectException(ProtocolException::class);
        $this->engine()->decode($writer->bytes(), $this->s2c());
    }
}
