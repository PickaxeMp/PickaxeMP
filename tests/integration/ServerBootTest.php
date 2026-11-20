<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\integration;

use PHPUnit\Framework\TestCase;
use PickaxeMP\exception\ConfigException;
use PickaxeMP\Server;

/**
 * Uçtan uca iskelet doğrulaması.
 *
 * Bu testler ağ, dünya veya oyuncu davranışını KANITLAMAZ; yalnızca sunucu
 * sürecinin gerçekten başlayıp temiz kapandığını gösterir.
 */
final class ServerBootTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCheckModeExitsCleanly(): void
    {
        [$exitCode, $output] = $this->runPhp(['start.php', '--check']);

        self::assertSame(0, $exitCode, "Beklenmeyen cikis kodu. Cikti:\n{$output}");
        self::assertStringContainsString('Cekirdek baslatildi', $output);
        self::assertStringContainsString('Cekirdek kapatildi', $output);
    }

    public function testTickLoopStopsAfterRequestedTicks(): void
    {
        [$exitCode, $output] = $this->runPhp(['start.php', '--ticks=2']);

        self::assertSame(0, $exitCode, "Beklenmeyen cikis kodu. Cikti:\n{$output}");
        self::assertStringContainsString('Phase 0', $output);
        self::assertStringContainsString('Cekirdek kapatildi', $output);
    }

    public function testBootLoadsBundledConfig(): void
    {
        $server = new Server($this->root, $this->memoryLogger());
        $kernel = $server->boot();

        self::assertSame(19132, $kernel->getSettings()->port);
        self::assertSame('PickaxeMP Server', $kernel->getSettings()->serverName);
        self::assertTrue($kernel->isBooted());

        $server->shutdown();

        self::assertFalse($kernel->isBooted());
    }

    public function testMissingConfigThrowsConfigException(): void
    {
        $emptyDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pickaxe-empty-' . bin2hex(random_bytes(6));
        mkdir($emptyDir);

        try {
            $server = new Server($emptyDir, $this->memoryLogger());

            $this->expectException(ConfigException::class);
            $server->boot();
        } finally {
            @rmdir($emptyDir);
        }
    }

    public function testRuntimeConfigOverridesBundledConfig(): void
    {
        $dataDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pickaxe-runtime-' . bin2hex(random_bytes(6));
        mkdir($dataDir);
        mkdir($dataDir . DIRECTORY_SEPARATOR . 'resources');
        file_put_contents(
            $dataDir . DIRECTORY_SEPARATOR . 'config.yml',
            "server-name: \"Runtime Override\"\nport: 20000\n"
        );

        try {
            $server = new Server($dataDir, $this->memoryLogger());
            $kernel = $server->boot();

            self::assertSame('Runtime Override', $kernel->getSettings()->serverName);
            self::assertSame(20000, $kernel->getSettings()->port);

            $server->shutdown();
        } finally {
            @unlink($dataDir . DIRECTORY_SEPARATOR . 'config.yml');
            @rmdir($dataDir . DIRECTORY_SEPARATOR . 'resources');
            @rmdir($dataDir);
        }
    }

    private function memoryLogger(): \PickaxeMP\utils\Logger
    {
        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);

        return new \PickaxeMP\utils\Logger('Test', $stream, \PickaxeMP\utils\Logger::DEBUG);
    }

    /**
     * @param list<string> $arguments
     * @return array{0: int, 1: string}
     */
    private function runPhp(array $arguments): array
    {
        $command = array_merge([PHP_BINARY, '-c', $this->root . '/tools/php.ini'], $arguments);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, $this->root);

        self::assertIsResource($process, 'Alt surec baslatilamadi.');

        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [$exitCode, $stdout . $stderr];
    }
}
