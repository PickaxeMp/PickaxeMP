<?php

declare(strict_types=1);

namespace PickaxeMP\utils;

/**
 * Seviyeli, akışa yazan basit logger.
 *
 * Neden PSR-3 değil? Phase 0'da harici bağımlılık eklememek için. Arayüz
 * bilinçli olarak PSR-3'e benzer tutuldu; ileride adaptör yazılabilir.
 *
 * Hassas veri maskeleme: bağlam (context) içindeki parola/token benzeri
 * anahtarların değerleri log'a yazılmaz.
 */
final class Logger
{
    public const DEBUG = 10;
    public const INFO = 20;
    public const WARNING = 30;
    public const ERROR = 40;

    private const LEVEL_NAMES = [
        self::DEBUG => 'DEBUG',
        self::INFO => 'INFO',
        self::WARNING => 'WARNING',
        self::ERROR => 'ERROR',
    ];

    /** Log'a yazılmaması gereken anahtar parçaları (küçük harf). */
    private const REDACTED_KEY_FRAGMENTS = [
        'password',
        'passwd',
        'secret',
        'token',
        'apikey',
        'api_key',
        'privatekey',
        'private_key',
        'authorization',
        'cookie',
        'sessionkey',
    ];

    /** @var resource */
    private $stream;

    /**
     * @param resource|null $stream Varsayılan: STDOUT
     */
    public function __construct(
        private readonly string $channel = 'Server',
        $stream = null,
        private int $minLevel = self::INFO
    ) {
        if ($stream === null) {
            $stream = defined('STDOUT') ? STDOUT : fopen('php://stdout', 'wb');
        }

        if (!is_resource($stream)) {
            throw new \InvalidArgumentException('Logger akisi gecerli bir kaynak olmali.');
        }

        $this->stream = $stream;
    }

    /**
     * @param resource $stream
     */
    public static function toStream(string $channel, $stream, int $minLevel = self::INFO): self
    {
        return new self($channel, $stream, $minLevel);
    }

    public static function levelFromName(string $name): int
    {
        $needle = strtoupper(trim($name));

        foreach (self::LEVEL_NAMES as $level => $label) {
            if ($label === $needle) {
                return $level;
            }
        }

        throw new \InvalidArgumentException("Bilinmeyen log seviyesi: {$name}");
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function getMinLevel(): int
    {
        return $this->minLevel;
    }

    public function setMinLevel(int $level): void
    {
        if (!isset(self::LEVEL_NAMES[$level])) {
            throw new \InvalidArgumentException("Gecersiz log seviyesi: {$level}");
        }

        $this->minLevel = $level;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log(int $level, string $message, array $context = []): void
    {
        if (!isset(self::LEVEL_NAMES[$level])) {
            throw new \InvalidArgumentException("Gecersiz log seviyesi: {$level}");
        }

        if ($level < $this->minLevel) {
            return;
        }

        $line = sprintf(
            '[%s] [%s] [%s] %s',
            gmdate('Y-m-d H:i:s'),
            self::LEVEL_NAMES[$level],
            $this->channel,
            $this->interpolate($message, $context)
        );

        if ($context !== []) {
            $extra = $this->redact($context);

            if ($extra !== []) {
                $line .= ' ' . json_encode($extra, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }

        fwrite($this->stream, $line . PHP_EOL);
    }

    /**
     * "Merhaba {name}" biçimindeki yer tutucuları bağlamdan doldurur.
     *
     * @param array<string, mixed> $context
     */
    private function interpolate(string $message, array $context): string
    {
        if (!str_contains($message, '{')) {
            return $message;
        }

        $replacements = [];

        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $replacements['{' . $key . '}'] = $this->isSensitive((string) $key)
                    ? '[REDACTED]'
                    : (string) $value;
            }
        }

        return strtr($message, $replacements);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function redact(array $context): array
    {
        $clean = [];

        foreach ($context as $key => $value) {
            if ($this->isSensitive((string) $key)) {
                $clean[$key] = '[REDACTED]';
                continue;
            }

            $clean[$key] = is_scalar($value) || $value === null
                ? $value
                : '[' . get_debug_type($value) . ']';
        }

        return $clean;
    }

    private function isSensitive(string $key): bool
    {
        $normalized = strtolower($key);

        foreach (self::REDACTED_KEY_FRAGMENTS as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
