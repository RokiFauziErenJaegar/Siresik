# Resik Collect — DLH Pringsewu

Aplikasi monitoring retribusi persampahan Dinas Lingkungan Hidup Kabupaten Pringsewu.
Dibangun ulang dari source code asli (3 file RTF) menjadi proyek **Google Apps Script** yang siap di-deploy.

Platform: Google Apps Script (Web App) + Google Spreadsheet sebagai basis data.
Front-end: satu halaman `index.html` (offline-first, memanggil backend lewat `google.script.run`).

## Isi proyek

```
Siresik/
├─ src/                      ← yang di-push ke Apps Script
│  ├─ index.html             Front-end aplikasi (HtmlService)
│  ├─ Code.gs                Backend: doGet, rpc, sesi, PIN, baca/tulis Spreadsheet
│  ├─ SeedData.gs            Data awal (RESIK_SEED v6: 17 petugas, 16 wilayah, 16 pos, 870 pelanggan, 531 bayar)
│  └─ appsscript.json        Manifest (zona waktu Asia/Jakarta, V8, Web App)
├─ Cadangan_Resik_2026-10-22.json   Cadangan data operasional (arsip)
├─ sumber-rtf/               Source asli (.rtf) — arsip
├─ .claspignore
└─ README.md
```

## Cara deploy — pilihan A: clasp (disarankan)

Butuh Node.js.

```bash
npm install -g @google/clasp
clasp login

# Buat proyek Apps Script baru yang terikat ke Spreadsheet baru:
clasp create --type sheets --title "Resik Collect" --rootDir ./src
# (perintah di atas membuat .clasp.json berisi scriptId)

clasp push        # kirim index.html, Code.gs, SeedData.gs, appsscript.json
clasp open        # buka editor Apps Script
```

Lalu di editor Apps Script:
1. **Deploy ▸ New deployment ▸ Web app**.
2. *Execute as*: **Me** · *Who has access*: **Anyone**.
3. Salin URL `/exec` — itulah alamat aplikasinya.

## Cara deploy — pilihan B: manual (tanpa clasp)

1. Buat Spreadsheet baru di Google Drive → menu **Extensions ▸ Apps Script**.
2. Buat file dan tempel isinya persis:
   - File **HTML** bernama `index` ← isi dari `src/index.html`
     (doGet memanggil `createHtmlOutputFromFile('index')`, jadi namanya harus `index`)
   - File **Script** `Code.gs` ← isi dari `src/Code.gs`
   - File **Script** `SeedData.gs` ← isi dari `src/SeedData.gs`
   - Manifest: **Project Settings ▸ Show "appsscript.json"**, samakan dengan `src/appsscript.json`
     (atau cukup set *Time zone* = Asia/Jakarta).
3. **Deploy ▸ New deployment ▸ Web app** (Execute as: Me, Access: Anyone), salin URL `/exec`.

## Saat pertama dibuka

- Fungsi `pasang()` berjalan otomatis (dipicu `doGet`): membuat lembar `_meta`, `petugas`,
  `wilayah`, `pos`, `pelanggan`, `bayar`, `mutasi`, `cover`, `notes`, `settings`, `pengguna`,
  lalu mengisi data awal dari `RESIK_SEED`.
- `buatPengguna_()` membuat 19 akun (Admin/Kadis, Bendahara, Monev + 16 petugas) dengan
  **PIN 6-digit acak**, PIN di-hash (SHA-256 + salt). Daftar PIN plaintext ditulis ke lembar
  **"PIN awal"** di Spreadsheet — buka lembar itu untuk melihat/membagikan PIN.

> Catatan keamanan: deployment baru menghasilkan PIN acak baru. Bagikan PIN satu per satu
> (jangan di grup), minta tiap petugas mengganti PIN sendiri lewat menu **Akun**. Admin bisa
> mereset/membuat PIN dari menu pengguna.

## Peran

| Peran       | Hak |
|-------------|-----|
| `kadis`     | Admin/Kadis — akses penuh, kelola pengguna & wilayah |
| `bendahara` | Keuangan, pembayaran, cadangan data |
| `monev`     | Hanya melihat (read-only) |
| `petugas`   | Pelanggan & setoran wilayah tugasnya |

## Mengembalikan data operasional (opsional)

`Cadangan_Resik_2026-10-22.json` berisi data nyata (870 pelanggan, 531 pembayaran). Untuk
memulihkannya ke instance baru, gunakan fitur **pulihkan/impor cadangan** di aplikasi (peran
Kadis/Bendahara), bukan dengan mengedit Spreadsheet manual. Data ini memuat informasi pribadi
(nama, alamat, no. WA) — simpan hati-hati dan jangan diunggah ke tempat publik.
