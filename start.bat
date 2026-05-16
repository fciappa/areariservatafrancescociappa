@echo off
title Area Riservata - Dev Server
color 0A

echo.
echo  =====================================================
echo   Area Riservata - Francesco Ciappa
echo   Avvio ambiente di sviluppo locale
echo  =====================================================
echo.

:: ── Backend Laravel ───────────────────────────────────
echo  [1/2] Avvio backend Laravel su http://localhost:8000
start "Laravel Backend" cmd /k "cd /d T:\francescociappa\areariservata\backend-laravel && php -S localhost:8000 index.php"

:: Attendi un momento prima di avviare il frontend
timeout /t 2 /nobreak >nul

:: ── Frontend Vite ──────────────────────────────────────
echo  [2/2] Avvio frontend Vite su http://localhost:5173
start "Vite Frontend" cmd /k "cd /d T:\francescociappa\areariservata\frontend && npm run dev"

echo.
echo  Apertura browser tra 4 secondi...
timeout /t 4 /nobreak >nul
start http://localhost:5173

echo.
echo  Tutto avviato! Chiudi le due finestre dei server per fermare.
echo.
