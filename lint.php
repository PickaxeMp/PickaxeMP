<?php

declare(strict_types=1);

/**
 * Platformdan bağımsız PHP sözdizimi denetleyicisi.
 *
 * CI'da `find | xargs` yerine kullanılır; Windows ve Linux'ta aynı çalışır.
 * Kullanım: php tools/lint.php [dizin...]
 * Çıkış kodu: 0 = temiz, 1 = sözdizimi hatası, 2 = kullanım hatası.
 */

$targets = array_slice($argv, 1);

if ($targets === []) {
    $targets = ['src', 'tests', 'tools', 'start.php'];
}

$phpBinary = PHP_BINARY;
$files = [];
$skipped = 0;

foreach ($targets as $target) {
    if (is_file($target)) {
        if (str_ends_with($target, '.php')) {
            $files[] = $target;
        }
        continue;
    }

    if (!is_dir($target)) {
        fwrite(STDERR, "UYARI: bulunamadi: {$target}\n");
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $path = $file->getPathname();

        // Üretilmiş ve üçüncü taraf kodlarını denetleme.
        if (str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)
            || str_contains($path, DIRECTORY_SEPARATOR . '.phpunit.cache' . DIRECTORY_SEPARATOR)
        ) {
            $skipped++;
            continue;
        }

        $files[] = $path;
    }
}

sort($files);

if ($files === []) {
    fwrite(STDERR, "Denetlenecek PHP dosyasi bulunamadi.\n");
    exit(2);
}

$failures = [];

foreach ($files as $file) {
    $command = escapeshellarg($phpBinary) . ' -l ' . escapeshellarg($file) . ' 2>&1';
    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    if ($exitCode !== 0) {
        $failures[$file] = implode("\n", $output);
    }
}

printf("Denetlenen dosya: %d (atlanan: %d)\n", count($files), $skipped);

if ($failures === []) {
    echo "Sozdizimi temiz.\n";
    exit(0);
}

foreach ($failures as $file => $message) {
    fwrite(STDERR, "\n--- {$file} ---\n{$message}\n");
}

printf("\n%d dosyada sozdizimi hatasi var.\n", count($failures));
exit(1);
