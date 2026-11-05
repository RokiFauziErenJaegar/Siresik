<?php

namespace App\Console\Commands;

use App\Services\ResikStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * `php artisan resik:install` — padanan pasang() + buatPengguna_() Apps Script.
 * Mengisi data awal dari database/data/resik_seed.json dan membuat akun + PIN acak.
 */
class InstallResik extends Command
{
    protected $signature = 'resik:install {--fresh : Kosongkan data dulu sebelum mengisi}';
    protected $description = 'Pasang data awal Resik Collect dan buat akun + PIN awal';

    public function handle(ResikStore $store): int
    {
        if ($this->option('fresh')) {
            DB::table('records')->delete();
            DB::table('resik_users')->delete();
            DB::table('resik_sessions')->delete();
            DB::table('resik_meta')->delete();
            $this->warn('Data lama dikosongkan (--fresh).');
        }

        $path = database_path('data/resik_seed.json');
        if (!is_file($path)) {
            $this->error("Berkas benih tidak ditemukan: $path");
            return self::FAILURE;
        }
        $seed = json_decode(file_get_contents($path), true);
        if (!is_array($seed)) {
            $this->error('resik_seed.json tidak valid.');
            return self::FAILURE;
        }

        // ub awal = 1 (sama seperti pasang())
        DB::table('resik_meta')->updateOrInsert(['kunci' => 'ub'], ['nilai' => '1']);

        foreach (ResikStore::TBL as $t) {
            if (count($store->baca($t)) > 0) {
                continue; // jangan timpa data yang sudah ada
            }
            $rows = $seed[$t] ?? [];
            if ($rows) {
                $store->tulis($t, $rows, 1);
                $this->line(sprintf('  %-10s %d baris', $t, count($rows)));
            }
        }

        if (count($store->users()) === 0) {
            $this->buatPengguna($store, $seed);
        } else {
            $this->info('Pengguna sudah ada — PIN tidak dibuat ulang.');
        }

        DB::table('resik_meta')->updateOrInsert(['kunci' => 'terpasang'], ['nilai' => now()->toIso8601String()]);
        $this->info('Pemasangan selesai.');
        return self::SUCCESS;
    }

    private function buatPengguna(ResikStore $store, array $seed): void
    {
        $dasar = [
            ['id' => 'ADM', 'nama' => 'Admin / Kadis', 'peran' => 'kadis'],
            ['id' => 'BDH', 'nama' => 'Bendahara', 'peran' => 'bendahara'],
            ['id' => 'MNV', 'nama' => 'Monev', 'peran' => 'monev'],
        ];
        foreach (($seed['petugas'] ?? []) as $k) {
            if (($k['jenis'] ?? '') === 'langsung') {
                continue;
            }
            $dasar[] = [
                'id' => 'U' . $k['id'],
                'nama' => $k['lengkap'] ?? $k['nama'],
                'peran' => 'petugas',
                'ptg' => $k['id'],
            ];
        }

        $catat = [];
        foreach ($dasar as $d) {
            $pin = $store->pin6();
            $salt = (string) Str::uuid();
            $store->simpanUser([
                'id' => $d['id'],
                'nama' => $d['nama'],
                'peran' => $d['peran'],
                'ptg' => $d['ptg'] ?? '',
                'aktif' => true,
                'salt' => $salt,
                'pin' => $store->hash($pin, $salt),
            ]);
            $catat[] = [$d['id'], $d['nama'], $d['peran'], $pin];
        }

        // Tulis daftar "PIN awal" ke berkas (padanan lembar "PIN awal" di Spreadsheet)
        $file = storage_path('app/pin-awal.csv');
        $fh = fopen($file, 'w');
        fputcsv($fh, ['ID', 'Nama', 'Peran', 'PIN awal (ganti setelah dibagikan)']);
        foreach ($catat as $row) {
            fputcsv($fh, $row);
        }
        fclose($fh);

        $this->newLine();
        $this->info('=== PIN AWAL (bagikan satu per satu, lalu minta diganti) ===');
        $this->table(['ID', 'Nama', 'Peran', 'PIN'], $catat);
        $this->line("Tersimpan juga di: $file");
    }
}
