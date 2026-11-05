# Resik Collect — versi web lokal

Versi aplikasi yang **berjalan sepenuhnya di browser**, tanpa Google / tanpa server backend.
Ini mode **Pratinjau (lokal)** bawaan aplikasi: login otomatis sebagai Admin/Kadis, data
disimpan di `localStorage` browser.

## Isi

| File | Keterangan |
|------|-----------|
| `index.html` | Aplikasi (sama dengan versi server, tapi `RESIK_MODE='lokal'`) |
| `seed.js` | Semua data (870 pelanggan, 531 pembayaran, dst.) sebagai `window.RESIK_SEED` |
| `Jalankan.bat` | Klik dua kali untuk menjalankan |

## Cara menjalankan

**Cara 1 — klik dua kali `Jalankan.bat`** (disarankan).
Akan membuka http://127.0.0.1:8000/index.html di browser. Biarkan jendela hitam (server) tetap
terbuka selama memakai aplikasi; tutup jendela itu untuk mematikan server.

**Cara 2 — jalur manual** (kalau punya Python):
```
cd web
python -m http.server 8000
```
lalu buka http://127.0.0.1:8000/index.html

**Cara 3 — tanpa server:** klik dua kali `index.html` langsung. Jalan juga (localStorage aktif),
hanya saja beberapa browser lebih ketat pada mode `file://`.

## Yang perlu diketahui

- **Mode lokal = pratinjau.** Tidak ada login PIN; otomatis masuk sebagai Admin/Kadis. Menu
  "Pengguna & PIN" dinonaktifkan (itu hanya ada di versi server Google Apps Script).
- **Data tersimpan per perangkat** di `localStorage` browser ini. Tidak tersinkron ke perangkat
  lain. Mengosongkan data browser akan menghapusnya.
- **Mengembalikan ke data awal:** hapus data situs untuk alamat ini di browser, atau buka menu
  dalam aplikasi untuk reset (jika tersedia). Data awal diambil ulang dari `seed.js`.
- Untuk versi multi-pengguna dengan login PIN & data terpusat, pakai versi Google Apps Script di
  folder `../src` (lihat `../README.md`).

> `seed.js` memuat data pribadi nyata (nama, alamat, no. WA). Simpan folder ini dengan hati-hati
> dan jangan diunggah ke tempat publik.
