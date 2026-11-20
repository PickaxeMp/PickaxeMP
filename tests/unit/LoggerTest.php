<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit;

use PHPUnit\Framework\TestCase;
use PickaxeMP\utils\Logger;

final class LoggerTest extends TestCase
{
    /** @var resource */
    private $stream;

    private Logger $logger;

    protected function setUp(): void
    {
        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);

        $this->stream = $stream;
        $this->logger = new Logger('Test', $stream, Logger::DEBUG);
    }

    private function rendered(): string
    {
        rewind($this->stream);

        return (string) stream_get_contents($this->stream);
    }

    public function testWritesFormattedLine(): void
    {
        $this->logger->info('Merhaba dunya');

        $output = $this->rendered();

        self::assertStringContainsString('[INFO]', $output);
        self::assertStringContainsString('[Test]', $output);
        self::assertStringContainsString('Merhaba dunya', $output);
    }

    public function testRespectsMinimumLevel(): void
    {
        $this->logger->setMinLevel(Logger::WARNING);
        $this->logger->debug('gizli debug');
        $this->logger->info('gizli info');
        $this->logger->warning('gorunur uyari');

        $output = $this->rendered();

        self::assertStringNotContainsString('gizli debug', $output);
        self::assertStringNotContainsString('gizli info', $output);
        self::assertStringContainsString('gorunur uyari', $output);
    }

    public function testInterpolatesPlaceholders(): void
    {
        $this->logger->info('Sunucu {name} port {port}', ['name' => 'PickaxeMP', 'port' => 19132]);

        $output = $this->rendered();

        self::assertStringContainsString('Sunucu PickaxeMP port 19132', $output);
    }

    public function testRedactsSensitiveContext(): void
    {
        $this->logger->info('giris', [
            'user' => 'ali',
            'password' => 'cok-gizli',
            'api_token' => 'abc123',
        ]);

        $output = $this->rendered();

        self::assertStringNotContainsString('cok-gizli', $output);
        self::assertStringNotContainsString('abc123', $output);
        self::assertStringContainsString('[REDACTED]', $output);
        self::assertStringContainsString('ali', $output);
    }

    public function testRedactsSensitivePlaceholders(): void
    {
        $this->logger->info('token={token}', ['token' => 'super-secret']);

        $output = $this->rendered();

        self::assertStringNotContainsString('super-secret', $output);
    }

    public function testLevelFromName(): void
    {
        self::assertSame(Logger::DEBUG, Logger::levelFromName('debug'));
        self::assertSame(Logger::ERROR, Logger::levelFromName(' ERROR '));
    }

    public function testUnknownLevelThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Logger::levelFromName('trace');
    }

    public function testChannelAndMinLevelAccessors(): void
    {
        self::assertSame('Test', $this->logger->getChannel());
        self::assertSame(Logger::DEBUG, $this->logger->getMinLevel());
    }
}

