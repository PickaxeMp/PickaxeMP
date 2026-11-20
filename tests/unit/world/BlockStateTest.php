<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\world;

use PHPUnit\Framework\TestCase;
use PickaxeMP\nbt\Fnv1a32;
use PickaxeMP\nbt\NbtWriter;
use PickaxeMP\world\block\BlockState;
use PickaxeMP\world\block\BlockRegistry;

final class BlockStateTest extends TestCase
{
    public function testFnv1aKnownVectors(): void
    {
        // FNV-1a 32 referans vektörleri (offset basis / prime standardi).
        self::assertSame(0x811C9DC5, Fnv1a32::hash(''));
        self::assertSame(0xE40C292C, Fnv1a32::hash('a'));
        self::assertSame(0xBF9CF968, Fnv1a32::hash('foobar'));
    }

    public function testFnv1aIsStable(): void
    {
        self::assertSame(Fnv1a32::hash('minecraft:stone'), Fnv1a32::hash('minecraft:stone'));
    }

    public function testNbtEmptyCompoundLayout(): void
    {
        $writer = new NbtWriter();
        $writer->beginCompound();
        $writer->endCompound();

        // 0x0A (compound) + varint 0 (ad) + 0x00 (end)
        self::assertSame("\x0A\x00\x00", $writer->bytes());
    }

    public function testNbtStringTagLayout(): void
    {
        $writer = new NbtWriter();
        $writer->beginCompound();
        $writer->writeStringTag('a', 'b');
        $writer->endCompound();

        // 0A 00 | 08 01 'a' 01 'b' | 00
        self::assertSame("\x0A\x00\x08\x01a\x01b\x00", $writer->bytes());
    }

    public function testBlockStateNbtShape(): void
    {
        $state = BlockState::of('minecraft:stone');
        $bytes = $state->toNbtBytes();

        // Kök compound + "name" + boş "states" compound
        self::assertStringContainsString('minecraft:stone', $bytes);
        self::assertStringContainsString('states', $bytes);
        self::assertSame(0x0A, ord($bytes[0]));
    }

    public function testBlockStateNetworkIdIsStable(): void
    {
        $a = BlockState::of('minecraft:stone');
        $b = BlockState::of('minecraft:stone');

        self::assertSame($a->getNetworkId(), $b->getNetworkId());
    }

    public function testDifferentBlocksHaveDifferentIds(): void
    {
        $ids = [];

        foreach (['minecraft:air', 'minecraft:stone', 'minecraft:dirt', 'minecraft:grass_block', 'minecraft:bedrock', 'minecraft:water'] as $name) {
            $ids[$name] = BlockState::of($name)->getNetworkId();
        }

        self::assertCount(6, $ids);
        self::assertCount(6, array_unique($ids), 'Her blok farkli ag kimligi uretmeli');
    }

    public function testPropertiesChangeNetworkId(): void
    {
        $plain = BlockState::of('minecraft:grass_block', ['snowy' => false]);
        $snowy = BlockState::of('minecraft:grass_block', ['snowy' => true]);

        self::assertNotSame($plain->getNetworkId(), $snowy->getNetworkId());
    }

    public function testPropertyOrderDoesNotChangeId(): void
    {
        $a = new BlockState('minecraft:test', ['a' => 1, 'b' => 2]);
        $b = new BlockState('minecraft:test', ['b' => 2, 'a' => 1]);

        self::assertSame($a->getNetworkId(), $b->getNetworkId(), 'Anahtar sirasi hash i degistirmemeli');
    }

    public function testEquality(): void
    {
        self::assertTrue(
            BlockState::of('minecraft:stone')->equals(BlockState::of('minecraft:stone'))
        );
        self::assertFalse(
            BlockState::of('minecraft:stone')->equals(BlockState::of('minecraft:dirt'))
        );
    }

    public function testKeyIncludesProperties(): void
    {
        self::assertSame('minecraft:stone', BlockState::of('minecraft:stone')->key());
        self::assertSame(
            'minecraft:grass_block[snowy=true]',
            BlockState::of('minecraft:grass_block', ['snowy' => true])->key()
        );
    }

    public function testRegistryHasSixBlocks(): void
    {
        $registry = BlockRegistry::withPhase4Blocks();

        self::assertSame(6, $registry->count());

        foreach ([
            'minecraft:air',
            'minecraft:stone',
            'minecraft:dirt',
            'minecraft:grass_block',
            'minecraft:bedrock',
            'minecraft:water',
        ] as $name) {
            self::assertTrue($registry->has($name), "{$name} kayitli olmali");
        }
    }

    public function testRegistryRejectsUnknownBlock(): void
    {
        $this->expectException(\PickaxeMP\world\WorldException::class);
        BlockRegistry::withPhase4Blocks()->type('minecraft:does_not_exist');
    }

    public function testRegistryCachesStates(): void
    {
        $registry = BlockRegistry::withPhase4Blocks();

        $a = $registry->state('minecraft:stone');
        $b = $registry->state('minecraft:stone');

        self::assertSame($a, $b);
    }

    public function testBedrockIsNotBreakable(): void
    {
        $registry = BlockRegistry::withPhase4Blocks();

        self::assertFalse($registry->type('minecraft:bedrock')->breakable);
        self::assertTrue($registry->type('minecraft:stone')->breakable);
    }

    public function testAirIsNotSolid(): void
    {
        $registry = BlockRegistry::withPhase4Blocks();

        self::assertFalse($registry->type('minecraft:air')->solid);
        self::assertTrue($registry->type('minecraft:stone')->solid);
    }
}
