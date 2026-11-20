<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap.
 *
 * vendor/autoload.php yoksa testler anlaşılır bir mesajla durur; sessizce
 * "0 test çalıştı" gibi yanıltıcı bir sonuç üretmeyiz.
 */

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (!is_file($autoload)) {
    fwrite(STDERR, "vendor/autoload.php bulunamadi.\n");
    fwrite(STDERR, "Calistir: tools/composer.cmd install\n");
    exit(1);
}

require_once $autoload;

/*
 * OpenSSL 3.x, provider'lari etkinlestirmek icin bir config dosyasi arar.
 * Windows'taki bu PHP derlemesinde varsayilan config yok; `openssl_pkey_new`
 * "configuration file routines::no such file" ile basarisiz olur.
 *
 * Proje duzeyindeki `tools/openssl.cnf` bu sorunu cozer. Testler `phpunit`
 * dogrudan cagrilarak da calistirilabildigi icin burada ayarliyoruz.
 */
if (getenv('OPENSSL_CONF') === false) {
    $opensslConfig = dirname(__DIR__) . '/tools/openssl.cnf';

    if (is_file($opensslConfig)) {
        putenv('OPENSSL_CONF=' . $opensslConfig);
    }
}
