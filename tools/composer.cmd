@echo off
rem PickaxeMP — proje duzeyi Composer calistirici (Windows)
rem Kullanim: tools\composer.cmd install
setlocal
rem OpenSSL 3.x bu Windows derlemesinde sistem yapilandirmasini bulamiyor.
rem Composer altindaki testler de ayni proje duzeyi yapilandirmasini kullanmali.
set "OPENSSL_CONF=%~dp0openssl.cnf"
php -c "%~dp0php.ini" "%~dp0composer.phar" %*
