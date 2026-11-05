@echo off
REM Menjalankan Resik Collect versi Laravel (offline, SQLite).
REM Klik dua kali file ini.
cd /d "%~dp0"

where php >nul 2>nul
if not %errorlevel%==0 (
  echo PHP tidak ditemukan di PATH. Pasang PHP/XAMPP lebih dulu.
  pause
  exit /b 1
)

echo Menyiapkan database (sekali jalan, aman diulang)...
php artisan migrate --force
php artisan resik:install

echo.
echo Membuka http://127.0.0.1:8010 ...
start "" "http://127.0.0.1:8010/"
php artisan serve --host=127.0.0.1 --port=8010
