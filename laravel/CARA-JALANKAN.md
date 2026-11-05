# Resik Collect — versi Laravel (offline)

Aplikasi retribusi persampahan DLH Pringsewu, ditulis ulang sebagai **aplikasi web Laravel asli**
dengan database **SQLite**. Setelah dipasang, **berjalan 100% offline** (tanpa internet, tanpa
Google). Front-end asli dipakai utuh; hanya lapisan jaringannya diarahkan ke API Laravel
(`/rpc`) menggantikan `google.script.run`.

## Arsitektur

- **Backend**: Laravel 13 (PHP 8.3). Port setia dari `Code.gs`:
  - penyimpanan key-value-`ub` + sinkronisasi delta (`?since=`) — tabel `records`
  - PIN di-hash SHA-256(`salt|pin`), sesi token 30 hari — `resik_users`, `resik_sessions`
  - semua aksi RPC: `pengguna, masuk, keluar, data, cadangan, simpan, ganti_pin, kelola, kelola_simpan`
- **Database**: SQLite (`database/database.sqlite`) — satu berkas, tanpa server DB.
- **Front-end**: `resources/resik/index.html` (sama dengan aslinya; `api()` memakai `fetch('/rpc')`).

## Prasyarat (sekali saja, butuh internet)

- PHP 8.2+ dan Composer (sudah ada via XAMPP di mesin ini).
- Dependensi sudah ter-install saat proyek dibuat (`vendor/` sudah ada). Jika `vendor/` hilang:
  ```
  composer install
  ```

## Menjalankan

**Cara 1 — klik dua kali `Jalankan-Laravel.bat`** (disarankan).
Menyiapkan DB (aman diulang) lalu membuka http://127.0.0.1:8010.

**Cara 2 — manual:**
```
cd laravel
php artisan migrate --force      # buat tabel (sekali)
php artisan resik:install        # isi data awal + cetak PIN (sekali)
php artisan serve --host=127.0.0.1 --port=8010
```
Buka http://127.0.0.1:8010

> Setelah langkah pasang selesai, mematikan Wi-Fi/internet tidak berpengaruh — semuanya lokal.

## Login & PIN

`php artisan resik:install` membuat 19 akun (Admin/Kadis, Bendahara, Monev + 16 petugas) dengan
**PIN 6-angka acak**. Daftar PIN:
- dicetak di layar saat `resik:install`, dan
- tersimpan di `storage/app/pin-awal.csv`.

Ganti PIN lewat menu **Akun** di aplikasi. Admin/Kadis mengelola pengguna & PIN di menu Pengguna.

## Perintah berguna

| Perintah | Guna |
|----------|------|
| `php artisan resik:install` | Pasang data awal + PIN (tidak menimpa data yang sudah ada) |
| `php artisan resik:install --fresh` | **Kosongkan** lalu pasang ulang dari awal (PIN baru) |

## Mengganti/mengembalikan data

Data awal diambil dari `database/data/resik_seed.json`. Untuk memuat data operasional terbaru,
ganti isi berkas itu dengan cadangan JSON (bentuk sama: `petugas, wilayah, pos, pelanggan, bayar, ...`)
lalu jalankan `php artisan resik:install --fresh`.

## Catatan keamanan

- `database/database.sqlite`, `database/data/resik_seed.json`, dan `storage/app/pin-awal.csv`
  memuat data pribadi nyata (nama, alamat, no. WA) dan PIN. Simpan hati-hati; jangan unggah ke
  tempat publik dan jangan commit ke repo publik.
- `.env` berisi `APP_KEY` — rahasia aplikasi.

## Deploy ke hosting (opsional, bukan "offline")

Arahkan web server ke folder `public/`, set `.env` (`APP_ENV=production`, `APP_DEBUG=false`),
jalankan `php artisan migrate && php artisan resik:install`. Untuk MySQL/Postgres, ubah
`DB_CONNECTION` di `.env` lalu migrasi ulang.
