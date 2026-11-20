@echo off
rem PickaxeMP — kök sunucu başlatıcısı
rem Proje düzeyinde tools/php.ini ve tools/openssl.cnf kullanılır.
rem Sistem php.ini değiştirilmez.
setlocal
set "OPENSSL_CONF=%~dp0tools\openssl.cnf"
php -c "%~dp0tools\php.ini" "%~dp0start.php" %*
