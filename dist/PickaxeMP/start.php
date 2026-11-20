<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use PickaxeMP\Server;

$arguments = $argv ?? [];
$checkOnly = in_array('--check', $arguments, true);
$maxTicks = null;

foreach ($arguments as $argument) {
    if (str_starts_with($argument, '--ticks=')) {
        $maxTicks = (int) substr($argument, strlen('--ticks='));
    }
}

$server = new Server(__DIR__);

try {
    $kernel = $server->boot();
} catch (Throwable $throwable) {
    fwrite(STDERR, '[FATAL] Sunucu baslatilamadi: ' . $throwable->getMessage() . PHP_EOL);
    exit(64);
}

$settings = $kernel->getSettings();
$logger = $kernel->getLogger();

if ($checkOnly) {
    $logger->info('Boot kontrolu tamamlandi (--check).');
    $server->shutdown();
    exit(0);
}

/**
 * CTRL+C / CTRL+BREAK ile nazik kapanış.
 *
 * PHP CLI'da varsayılan davranış süreci anında sonlandırmaktır; bu da kayıt
 * adımlarını atlar. Burada yalnızca bir bayrak kaldırıyoruz ve döngü kendisi
 * düzgün biçimde çıkıyor.
 */
$control = new class {
    public bool $stopRequested = false;
};

if (function_exists('sapi_windows_set_ctrl_handler')) {
    sapi_windows_set_ctrl_handler(
        static function (int $event) use ($control): bool {
            $interrupt = defined('PHP_WINDOWS_EVENT_CTRL_C') ? PHP_WINDOWS_EVENT_CTRL_C : 0;

            if ($event === $interrupt) {
                $control->stopRequested = true;

                return true;
            }

            return false;
        }
    );
}

try {
    $server->run($maxTicks, static fn (): bool => $control->stopRequested || $server->getConsoleManager()->isStopRequested());
} finally {
    $server->shutdown();
}

exit(0);
