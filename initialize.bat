@echo off
title Area Riservata - Inizializzazione
color 0B

echo.
echo  =====================================================
echo   Area Riservata - Francesco Ciappa
echo   Script di inizializzazione (prima installazione)
echo  =====================================================
echo.
echo  Prerequisiti richiesti sulla macchina:
echo    - PHP 8.1+   (https://www.php.net/downloads)
echo    - Composer   (https://getcomposer.org)
echo    - Node.js    (https://nodejs.org)
echo    - MySQL 8+   (https://dev.mysql.com/downloads)
echo.
pause

:: ── 1. Database ───────────────────────────────────────────────────────────────
echo.
echo  [1/4] Importazione schema database...
echo.
set /p DB_USER=Inserisci il tuo utente MySQL (es. root): 
set /p DB_NAME=Inserisci il nome del database da creare (es. areariservatafc_db): 

mysql -u %DB_USER% -p -e "CREATE DATABASE IF NOT EXISTS %DB_NAME% CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u %DB_USER% -p %DB_NAME% < database\schema.sql

if %errorlevel% neq 0 (
    echo.
    echo  [ERRORE] Importazione database fallita. Controlla le credenziali MySQL.
    pause
    exit /b 1
)
echo  [OK] Database importato.

:: ── 2. Backend Laravel - .env ─────────────────────────────────────────────────
echo.
echo  [2/4] Configurazione backend Laravel...
copy /Y backend-laravel\.env.development backend-laravel\.env >nul
echo.
echo  File .env creato da .env.development.
echo  IMPORTANTE: apri backend-laravel\.env e imposta:
echo    - DB_USERNAME e DB_PASSWORD con le tue credenziali MySQL
echo    - DB_DATABASE con: %DB_NAME%
echo.
pause

:: ── 3. Composer install ───────────────────────────────────────────────────────
echo.
echo  [3/4] Installazione dipendenze PHP (Composer)...
cd backend-laravel
composer install --no-interaction --prefer-dist
if %errorlevel% neq 0 (
    echo  [ERRORE] composer install fallito.
    cd ..
    pause
    exit /b 1
)
echo  [OK] Dipendenze PHP installate.
cd ..

:: ── 4. NPM install frontend ───────────────────────────────────────────────────
echo.
echo  [4/4] Installazione dipendenze frontend (npm)...
cd frontend
npm install
if %errorlevel% neq 0 (
    echo  [ERRORE] npm install fallito.
    cd ..
    pause
    exit /b 1
)
echo  [OK] Dipendenze frontend installate.
cd ..

:: ── Fine ──────────────────────────────────────────────────────────────────────
echo.
echo  =====================================================
echo   Inizializzazione completata!
echo.
echo   Credenziali di default:
echo     Admin:  admin / Admin@2024
echo     CAMBIA la password al primo accesso!
echo.
echo   Per avviare il progetto, lancia: start.bat
echo  =====================================================
echo.
pause
