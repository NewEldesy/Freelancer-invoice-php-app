@echo off
echo ============================================
echo  Installation — Facture Proforma B'Tech
echo ============================================
echo.

REM Check PHP
php -v >nul 2>&1
if %errorlevel% neq 0 (
    echo [!] PHP non trouve. Telechargement en cours...
    echo     Allez sur https://windows.php.net/download/
    echo     Téléchargez "PHP 8.3 VS16 x64 Non Thread Safe"
    echo     Extrayez dans C:\php et ajoutez C:\php au PATH.
    echo.
    echo  Ou utilisez Laragon (tout-en-un) : https://laragon.org/download/
    pause
    exit /b 1
)

echo [OK] PHP detecte.

REM Check Composer
composer -v >nul 2>&1
if %errorlevel% neq 0 (
    echo [!] Composer non trouve. Installation...
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php --install-dir=. --filename=composer.phar
    php composer.phar install
    del composer-setup.php
) else (
    echo [OK] Composer detecte.
    composer install
)

echo.
echo ============================================
echo  Demarrage du serveur sur http://localhost:8080
echo ============================================
php -S localhost:8080 -t public
