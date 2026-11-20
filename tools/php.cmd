@echo off
rem PickaxeMP — proje duzeyi PHP calistirici (Windows)
rem Kullanim: tools\php.cmd script.php
setlocal
set "OPENSSL_CONF=%~dp0openssl.cnf"
php -c "%~dp0php.ini" %*
