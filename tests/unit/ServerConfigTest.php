<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PickaxeMP\exception\ConfigException;
use PickaxeMP\utils\Config;
use PickaxeMP\utils\ServerConfig;

final class ServerConfigTest extends TestCase
{
    private function config(array $overrides = []): Config
    {
        $lines = [];

        foreach ($overrides as $key => $value) {
            $lines[] = sprintf('%s: %s', $key, $value);
        }

        return Config::fromString(implode("\n", $lines));
    }

    public function testDefaultsAreValid(): void
    {
        $settings = ServerConfig::fromConfig($this->config());

        self::assertSame('PickaxeMP Server', $settings->serverName);
        self::assertSame(19132, $settings->port);
        self::assertSame(20, $settings->maxPlayers);
        self::assertSame('survival', $settings->gamemode);
        self::assertSame('normal', $settings->difficulty);
        self::assertFalse($settings->onlineMode);
        self::assertSame(0, $settings->protocolVersion);
    }

    public function testRejectsEmptyName(): void
    {
        $this->expectException(ConfigException::class);
        ServerConfig::fromConfig($this->config(['server-name' => '""']));
    }

    public function testRejectsTooLongName(): void
    {
        $this->expectException(ConfigException::class);
        ServerConfig::fromConfig($this->config(['server-name' => '"' . str_repeat('a', 65) . '"']));
    }

    #[DataProvider('invalidPorts')]
    public function testRejectsInvalidPort(int $port): void
    {
        $this->expectException(ConfigException::class);
        ServerConfig::fromConfig($this->config(['port' => (string) $port]));
    }

    /**
     * @return array<string, array{int}>
     */
    public static function invalidPorts(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
            'too large' => [65536],
        ];
    }

    public function testRejectsInvalidGamemode(): void
    {
        $this->expectException(ConfigException::class);
        ServerConfig::fromConfig($this->config(['gamemode' => 'hardcore']));
    }

    public function testRejectsInvalidDifficulty(): void
    {
        $this->expectException(ConfigException::class);
        ServerConfig::fromConfig($this->config(['difficulty' => 'insane']));
    }

    public function testRejectsZeroMaxPlayers(): void
    {
        $this->expectException(ConfigException::class);
        ServerConfig::fromConfig($this->config(['max-players' => '0']));
    }

    public function testGamemodeIsCaseInsensitive(): void
    {
        $settings = ServerConfig::fromConfig($this->config(['gamemode' => 'Creative']));

        self::assertSame('creative', $settings->gamemode);
    }

    public function testAcceptsProtocolVersion(): void
    {
        $settings = ServerConfig::fromConfig($this->config(['protocol-version' => '2225']));

        self::assertSame(2225, $settings->protocolVersion);
    }

    public function testRejectsNegativeProtocolVersion(): void
    {
        $this->expectException(ConfigException::class);
        ServerConfig::fromConfig($this->config(['protocol-version' => '-1']));
    }
}
