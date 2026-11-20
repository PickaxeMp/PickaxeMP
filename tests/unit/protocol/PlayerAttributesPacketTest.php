<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\protocol;

use PHPUnit\Framework\TestCase;
use PickaxeMP\player\PlayerAbilities;
use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketDirection;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketRegistry;
use PickaxeMP\protocol\packet\packets\UpdateAbilitiesPacket;
use PickaxeMP\protocol\packet\packets\UpdateAttributesPacket;
use PickaxeMP\protocol\packet\types\AbilityLayer;
use PickaxeMP\protocol\packet\types\Attribute;
use PickaxeMP\protocol\packet\types\AttributeModifier;
use PickaxeMP\protocol\packet\types\SerializedAbilitiesData;
use PickaxeMP\protocol\ProtocolException;

/**
 * Phase 9: `UpdateAttributes` (29) ve `UpdateAbilities` (187) tel biçimi.
 *
 * Altın baytlar `gophertunnel` `protocol/attribute.go` ve
 * `protocol/ability.go` sırasına göre elle hesaplandı. Alan sırası
 * değişirse altın bayt testi kırılır.
 */
final class PlayerAttributesPacketTest extends TestCase
{
    private function healthAttribute(): Attribute
    {
        return new Attribute(
            name: Attribute::HEALTH,
            min: 0.0,
            max: 20.0,
            current: 20.0,
            defaultMin: 0.0,
            defaultMax: 20.0,
            default: 20.0
        );
    }

    public function testPacketIdsMatchOfficialSchema(): void
    {
        self::assertSame(29, PacketId::UPDATE_ATTRIBUTES);
        self::assertSame(187, PacketId::UPDATE_ABILITIES);
        self::assertSame('UpdateAttributes', PacketId::name(29));
        self::assertSame('UpdateAbilities', PacketId::name(187));
    }

    public function testRegistryContainsPhase9PacketsWithDirections(): void
    {
        $registry = PacketRegistry::withSupportedPackets();

        self::assertSame(PacketDirection::ServerToClient, $registry->directionFor(PacketId::UPDATE_ATTRIBUTES));
        self::assertSame(PacketDirection::ServerToClient, $registry->directionFor(PacketId::UPDATE_ABILITIES));
        self::assertSame(UpdateAttributesPacket::class, $registry->classFor(PacketId::UPDATE_ATTRIBUTES));
        self::assertSame(UpdateAbilitiesPacket::class, $registry->classFor(PacketId::UPDATE_ABILITIES));
        self::assertSame(46, $registry->count());
    }

    public function testUpdateAttributesGoldenBytes(): void
    {
        $packet = new UpdateAttributesPacket(5, [$this->healthAttribute()]);

        $expected = '05'                     // runtime id varuint64
            . '01'                           // attribute sayısı varuint32
            . '00000000'                     // min 0.0
            . '0000a041'                     // max 20.0
            . '0000a041'                     // current 20.0
            . '00000000'                     // default min 0.0
            . '0000a041'                     // default max 20.0
            . '0000a041'                     // default 20.0
            . '10' . bin2hex('minecraft:health')
            . '00'                           // modifier sayısı
            . '00';                          // tick

        self::assertSame($expected, bin2hex($this->encode($packet)));
    }

    public function testUpdateAttributesRoundTripsWithModifiers(): void
    {
        $attribute = new Attribute(
            name: Attribute::MOVEMENT,
            min: 0.0,
            max: 1.0,
            current: 0.2,
            defaultMin: 0.0,
            defaultMax: 1.0,
            default: 0.1,
            modifiers: [
                new AttributeModifier(
                    id: 'speed-boost',
                    name: 'Speed Boost',
                    amount: 0.05,
                    operation: AttributeModifier::OPERATION_ADDITION,
                    operand: AttributeModifier::OPERAND_CURRENT,
                    serializable: true
                ),
            ]
        );

        $packet = new UpdateAttributesPacket(77, [$attribute], 1234);
        $decoded = UpdateAttributesPacket::decode($this->reader($packet));

        self::assertSame(77, $decoded->targetRuntimeId);
        self::assertSame(1234, $decoded->tick);
        self::assertCount(1, $decoded->attributes);

        $decodedAttribute = $decoded->attributes[0];
        self::assertSame(Attribute::MOVEMENT, $decodedAttribute->name);
        // Tel biçimi float32: 0.2 tam temsil edilemez, delta ile kıyaslanır.
        self::assertEqualsWithDelta(0.2, $decodedAttribute->current, 1e-6);
        self::assertCount(1, $decodedAttribute->modifiers);
        self::assertSame('speed-boost', $decodedAttribute->modifiers[0]->id);
        self::assertEqualsWithDelta(0.05, $decodedAttribute->modifiers[0]->amount, 1e-6);
        self::assertTrue($decodedAttribute->modifiers[0]->serializable);
    }

    public function testUpdateAttributesRejectsMoreThanLimit(): void
    {
        $attributes = array_fill(0, UpdateAttributesPacket::MAX_ATTRIBUTES + 1, $this->healthAttribute());

        $this->expectException(ProtocolException::class);

        new UpdateAttributesPacket(1, $attributes);
    }

    public function testSerializedAbilitiesLayersPrefixIsVarUInt32(): void
    {
        $data = PlayerAbilities::survival(1);
        $bytes = $this->encode(new UpdateAbilitiesPacket($data));

        $expected = '0100000000000000'   // target raw id int64 LE
            . '01'                       // player permissions int8
            . '00'                       // command permissions uint8
            . '01'                       // katman sayısı **varuint32**
            . '0100'                     // layer type uint16 (base)
            . '3f000000'                 // abilitiesSet uint32 (build..attackMobs)
            . '00600800'                 // abilityValues uint32 (hız bitleri)
            . 'cdcc4c3d'                 // fly speed 0.05
            . '0000803f'                 // vertical fly speed 1.0
            . 'cdcccc3d';                // walk speed 0.1

        self::assertSame($expected, bin2hex($bytes));
    }

    public function testUpdateAbilitiesRoundTripsMultipleLayers(): void
    {
        $data = new SerializedAbilitiesData(
            targetPlayerRawId: 42,
            playerPermissions: 1,
            commandPermissions: 0,
            layers: [
                new AbilityLayer(1, 63, 548864, 0.05, 1.0, 0.1),
                new AbilityLayer(2, 0, 0, 0.1, 1.0, 0.2),
            ]
        );

        $decoded = UpdateAbilitiesPacket::decode($this->reader(new UpdateAbilitiesPacket($data)));

        self::assertSame(42, $decoded->data->targetPlayerRawId);
        self::assertCount(2, $decoded->data->layers);
        self::assertSame(2, $decoded->data->layers[1]->layer);
        self::assertEqualsWithDelta(0.2, $decoded->data->layers[1]->walkSpeed, 1e-6);
    }

    public function testAbilityBitsMatchMojangConstants(): void
    {
        self::assertSame(1, PlayerAbilities::ABILITY_BUILD);
        self::assertSame(2, PlayerAbilities::ABILITY_MINE);
        self::assertSame(16, PlayerAbilities::ABILITY_ATTACK_PLAYERS);
        self::assertSame(512, PlayerAbilities::ABILITY_FLYING);
        self::assertSame(1024, PlayerAbilities::ABILITY_MAY_FLY);
        self::assertSame(1 << 19, PlayerAbilities::ABILITY_VERTICAL_FLY_SPEED);
        self::assertSame(63, PlayerAbilities::SURVIVAL_ABILITIES);
        self::assertSame(548864, PlayerAbilities::SURVIVAL_VALUES);
    }

    private function encode(\PickaxeMP\protocol\packet\PacketInterface $packet): string
    {
        $writer = new BinaryWriter();
        $packet->encode($writer);

        return $writer->bytes();
    }

    private function reader(\PickaxeMP\protocol\packet\PacketInterface $packet): BinaryReader
    {
        return new BinaryReader($this->encode($packet));
    }
}
