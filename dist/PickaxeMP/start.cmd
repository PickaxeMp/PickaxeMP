@echo off
rem PickaxeMP — tek sunucu başlatıcısı (tüm işlem burada)
rem Kullanım: start.cmd [--check] [--ticks=N]
rem - vendor/ yoksa composer install otomatik yapılır
rem - config.yml root'ta yoksa resources/config.yml'dan kopyalanır
rem - php.ini ve openssl.cnf root'tan kullanılır (sistem php.ini dokunulmaz)
setlocal

rem Ayarlar
set "OPENSSL_CONF=%~dp0openssl.cnf"
set "COMPOSER_PHAR=%~dp0composer.phar"
set "START_PHP=%~dp0start.php"
set "VENDOR_AUTOLOAD=%~dp0vendor\autoload.php"
set "CONFIG_YML=%~dp0config.yml"
set "BUNDLED_CONFIG=%~dp0resources\config.yml"

echo [PickaxeMP] Baslatiliyor...

rem 1. config.yml yoksa olustur
if not exist "%CONFIG_YML%" (
    echo [PickaxeMP] config.yml yok, resources/config.yml'dan kopyalaniyor...
    if exist "%BUNDLED_CONFIG%" (
        copy "%BUNDLED_CONFIG%" "%CONFIG_YML%" >nul
        echo [PickaxeMP] config.yml olusturuldu.
    ) else (
        echo [HATA] resources/config.yml bulunamadi.
        exit /b 1
    )
)

rem 2. vendor/autoload.php yoksa composer install yap
if not exist "%VENDOR_AUTOLOAD%" (
    echo [PickaxeMP] vendor/ yok, composer install calistiriliyor...
    php -c "%~dp0php.ini" "%COMPOSER_PHAR%" install
    if errorlevel 1 (
        echo [HATA] composer install basarisiz oldu.
        exit /b 1
    )
    echo [PickaxeMP] composer install tamamlandi.
)

rem 3. Sunucu baslat
echo [PickaxeMP] PHP calistiriliyor (php.ini: root, openssl.cnf: root)...

rem extension_dir'i mutlak yap (PHP'nin kurulu ext klasoru)
set "PHP_EXT="
for /f "usebackq tokens=*" %%i in (`powershell -Command "(Get-Command php).Source" 2^>nul`) do set "PHP_EXE=%%i"
if "%PHP_EXE%"=="" (
    echo [HATA] php.exe bulunamadi.
    exit /b 1
)
set "PHP_DIR=%PHP_EXE:php.exe=%"
set "PHP_EXT=%PHP_DIR%ext"

php -c "%~dp0php.ini" -d extension_dir="%PHP_EXT%" "%START_PHP%" %*

endlocal
