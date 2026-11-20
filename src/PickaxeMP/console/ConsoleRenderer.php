<?php

declare(strict_types=1);

namespace PickaxeMP\console;

/**
 * Konsol çıkışını düzenler. Renk desteği varsa kullanır, yoksa renksiz geri döner.
 */
final class ConsoleRenderer
{
    private const COLOR_RESET = "\033[0m";
    private const COLOR_INFO = "\033[37m";     // white
    private const COLOR_WARN = "\033[33m";     // yellow
    private const COLOR_ERROR = "\033[31m";    // red
    private const COLOR_SUCCESS = "\033[32m";  // green

    private bool $colorEnabled;

    public function __construct(bool $colorEnabled = true)
    {
        $this->colorEnabled = $colorEnabled && $this->supportsColor();
    }

    /** Satır yazdırır. */
    public function writeLine(string $line): void
    {
        fwrite(STDOUT, $line . PHP_EOL);
    }

    /** Bilgi mesajı yazdırır. */
    public function writeInfo(string $message): void
    {
        $this->writeLine($this->color('INFO', self::COLOR_INFO) . ' ' . $message);
    }

    /** Uyarı mesajı yazdırır. */
    public function writeWarn(string $message): void
    {
        $this->writeLine($this->color('WARN', self::COLOR_WARN) . ' ' . $message);
    }

    /** Hata mesajı yazdırır. */
    public function writeError(string $message): void
    {
        $this->writeLine($this->color('ERROR', self::COLOR_ERROR) . ' ' . $message);
    }

    /** Başarı mesajı yazdırır. */
    public function writeSuccess(string $message): void
    {
        $this->writeLine($this->color('OK', self::COLOR_SUCCESS) . ' ' . $message);
    }

    /** Komut istemini gösterir. */
    public function writePrompt(string $prompt): void
    {
        fwrite(STDOUT, $this->color($prompt, self::COLOR_INFO));
    }

    /** Renkli metin döndürür. Renk desteklenmiyorsa düz metin döner. */
    private function color(string $text, string $code): string
    {
        if (!$this->colorEnabled) {
            return $text;
        }

        return $code . $text . self::COLOR_RESET;
    }

    /**
     * Terminalin renk desteği olup olmadığını tahmin eder.
     * Windows CMD ve PowerShell'de ANSI desteği sürüme bağlıdır.
     */
    private function supportsColor(): bool
    {
        if (!function_exists('posix_isatty')) {
            return false;
        }

        if (!@posix_isatty(STDOUT)) {
            return false;
        }

        return true;
    }
}
