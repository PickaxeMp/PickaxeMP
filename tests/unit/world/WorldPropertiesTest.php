<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\world;

use PHPUnit\Framework\TestCase;
use PickaxeMP\world\WorldException;
use PickaxeMP\world\WorldProperties;

final class WorldPropertiesTest extends TestCase
{
    private function validArray(): array
    {
        return [
            'format_version' => WorldProperties::FORMAT_VERSION,
            'name' => 'world',
            'display_name' => 'Dunya',
            'seed' => 12345,
            'generator' => 'flat',
            'dimension' => 'overworld',
            'spawn' => ['x' => 8.5, 'y' => -57.0, 'z' => 8.5],
            'created_at' => 1000,
            'updated_at' => 2000,
        ];
    }

    public function testRoundTrip(): void
    {
        $properties = WorldProperties::fromArray($this->validArray());

        self::assertSame('world', $properties->name);
        self::assertSame(12345, $properties->seed);
        self::assertSame(-57.0, $properties->spawnY);

        $again = WorldProperties::fromArray($properties->toArray());

        self::assertSame($properties->name, $again->name);
        self::assertSame($properties->seed, $again->seed);
    }

    public function testRejectsMissingFormatVersion(): void
    {
        $data = $this->validArray();
        unset($data['format_version']);

        $this->expectException(WorldException::class);
        WorldProperties::fromArray($data);
    }

    public function testRejectsFutureFormatVersion(): void
    {
        $data = $this->validArray();
        $data['format_version'] = 99;

        $this->expectException(WorldException::class);
        WorldProperties::fromArray($data);
    }

    public function testRejectsMissingSpawn(): void
    {
        $data = $this->validArray();
        unset($data['spawn']);

        $this->expectException(WorldException::class);
        WorldProperties::fromArray($data);
    }

    public function testRejectsMissingSeed(): void
    {
        $data = $this->validArray();
        unset($data['seed']);

        $this->expectException(WorldException::class);
        WorldProperties::fromArray($data);
    }

    public function testRejectsEmptyName(): void
    {
        $data = $this->validArray();
        $data['name'] = '';

        $this->expectException(WorldException::class);
        WorldProperties::fromArray($data);
    }
}
