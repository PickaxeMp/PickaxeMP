<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit;

use PHPUnit\Framework\TestCase;
use PickaxeMP\exception\ConfigException;
use PickaxeMP\utils\Config;

final class ConfigTest extends TestCase
{
    public function testParsesFlatScalars(): void
    {
        $config = Config::fromString(<<<'YAML'
        name: "PickaxeMP Server"
        port: 19132
        ratio: 0.75
        enabled: true
        disabled: false
        empty: null
        tilde: ~
        bracket: [a, b, c]
        YAML);

        self::assertSame('PickaxeMP Server', $config->getString('name'));
        self::assertSame(19132, $config->getInt('port'));
        self::assertSame(0.75, $config->getFloat('ratio'));
        self::assertTrue($config->getBool('enabled'));
        self::assertFalse($config->getBool('disabled'));
        self::assertNull($config->get('empty'));
        self::assertNull($config->get('tilde'));
        self::assertSame(['a', 'b', 'c'], $config->getArray('bracket'));
    }

    public function testParsesNestedMaps(): void
    {
        $config = Config::fromString(<<<'YAML'
        reload:
          enabled: true
          timeout-ms: 5000
        restart:
          schedule:
            - "daily 04:00"
            - "weekly sun 04:30"
        YAML);

        self::assertTrue($config->getBool('reload.enabled'));
        self::assertSame(5000, $config->getInt('reload.timeout-ms'));
        self::assertSame(['daily 04:00', 'weekly sun 04:30'], $config->getArray('restart.schedule'));
        self::assertTrue($config->has('restart.schedule'));
        self::assertFalse($config->has('restart.missing'));
    }

    public function testParsesSequenceOfMaps(): void
    {
        $config = Config::fromString(<<<'YAML'
        worlds:
          - name: "world"
            generator: flat
          - name: "nether"
            generator: flat
        YAML);

        $worlds = $config->getArray('worlds');

        self::assertCount(2, $worlds);
        self::assertSame('world', $worlds[0]['name']);
        self::assertSame('flat', $worlds[0]['generator']);
        self::assertSame('nether', $worlds[1]['name']);
    }

    public function testStripsCommentsButNotInsideQuotes(): void
    {
        $config = Config::fromString(<<<'YAML'
        # tam satir yorum
        motd: "Renk #123 korunur" # satir sonu yorum
        plain: value # yorum
        YAML);

        self::assertSame('Renk #123 korunur', $config->getString('motd'));
        self::assertSame('value', $config->getString('plain'));
    }

    public function testHandlesCrlfAndBlankLines(): void
    {
        $config = Config::fromString("a: 1\r\n\r\nb: 2\r\n");

        self::assertSame(1, $config->getInt('a'));
        self::assertSame(2, $config->getInt('b'));
    }

    public function testEscapesInDoubleQuotes(): void
    {
        $config = Config::fromString(<<<'YAML'
        a: "line1\nline2"
        b: "tab\there"
        c: "quote\"inside"
        YAML);

        self::assertSame("line1\nline2", $config->getString('a'));
        self::assertSame("tab\there", $config->getString('b'));
        self::assertSame('quote"inside', $config->getString('c'));
    }

    public function testSingleQuotesAreLiteral(): void
    {
        $config = Config::fromString("a: 'it''s fine'");

        self::assertSame("it's fine", $config->getString('a'));
    }

    public function testDefaultsWhenMissing(): void
    {
        $config = Config::fromString('a: 1');

        self::assertSame('fallback', $config->getString('missing', 'fallback'));
        self::assertSame(7, $config->getInt('missing', 7));
        self::assertNull($config->get('missing'));
    }

    public function testTypeMismatchThrows(): void
    {
        $config = Config::fromString('port: "19132"');

        $this->expectException(ConfigException::class);
        $config->getInt('port');
    }

    public function testRejectsTabIndentation(): void
    {
        $this->expectException(ConfigException::class);
        Config::fromString("a:\n\tb: 1");
    }

    public function testRejectsDuplicateKeys(): void
    {
        $this->expectException(ConfigException::class);
        Config::fromString("a: 1\na: 2");
    }

    public function testRejectsFlowMap(): void
    {
        $this->expectException(ConfigException::class);
        Config::fromString('a: {b: c}');
    }

    public function testRejectsBlockScalar(): void
    {
        $this->expectException(ConfigException::class);
        Config::fromString("a: |\n  metin");
    }

    public function testRejectsAnchors(): void
    {
        $this->expectException(ConfigException::class);
        Config::fromString('a: &anchor value');
    }

    public function testRejectsUnclosedQuote(): void
    {
        $this->expectException(ConfigException::class);
        Config::fromString('a: "kapanmadi');
    }

    public function testRejectsMissingSeparator(): void
    {
        $this->expectException(ConfigException::class);
        Config::fromString('sadece-bir-kelime');
    }

    public function testRejectsRootSequence(): void
    {
        $this->expectException(ConfigException::class);
        Config::fromString("- a\n- b");
    }

    public function testRejectsUnknownEscape(): void
    {
        $this->expectException(ConfigException::class);
        Config::fromString('a: "bad\q"');
    }

    public function testEmptyDocumentIsEmptyArray(): void
    {
        $config = Config::fromString("\n# sadece yorum\n");

        self::assertSame([], $config->all());
    }

    public function testReadsBundledConfigFile(): void
    {
        $path = dirname(__DIR__, 2) . '/resources/config.yml';

        self::assertFileExists($path);

        $config = Config::fromFile($path);

        self::assertGreaterThan(0, $config->getInt('port'));
        self::assertIsBool($config->getBool('online-mode'));
    }

    public function testMissingFileThrows(): void
    {
        $this->expectException(ConfigException::class);
        Config::fromFile(dirname(__DIR__, 2) . '/resources/does-not-exist.yml');
    }
}
