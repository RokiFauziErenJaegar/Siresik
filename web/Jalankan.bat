@echo off
REM Menjalankan Resik Collect (mode lokal) di browser.
REM Klik dua kali file ini.
cd /d "%~dp0"

REM Coba cari Python untuk server lokal (paling andal).
where python >nul 2>nul
if %errorlevel%==0 (
  echo Membuka Resik Collect di http://127.0.0.1:8000 ...
  start "" "http://127.0.0.1:8000/index.html"
  python -m http.server 8000 --bind 127.0.0.1
  goto :eof
)

REM Tanpa Python: buka langsung lewat file (localStorage tetap jalan).
echo Python tidak ditemukan. Membuka langsung file index.html ...
start "" "%~dp0index.html"
