<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Port setia dari Code.gs (Apps Script) ke Laravel.
 * Penyimpanan key-value-ub + sinkronisasi delta, PIN hash, dan sesi token.
 */
class ResikStore
{
    /** Urutan tabel domain — harus sama dengan Apps Script. */
    public const TBL = ['petugas', 'wilayah', 'pos', 'pelanggan', 'bayar', 'mutasi', 'cover', 'notes', 'audit', 'settings'];

    /** Kolom kunci per tabel (selain itu 'id'). */
    public const KEYF = ['pos' => 'kode', 'pelanggan' => 'kode'];

    public const SESI_HARI = 30;
    public const TZ = 'Asia/Jakarta';

    public function keyOf(string $t, array $row): string
    {
        $f = self::KEYF[$t] ?? 'id';
        return (string) ($row[$f] ?? '');
    }

    /** SHA-256(salt|pin) — identik dengan hash_() Apps Script. */
    public function hash(string $pin, string $salt): string
    {
        return hash('sha256', $salt . '|' . $pin);
    }

    public function pin6(): string
    {
        return (string) random_int(100000, 999999);
    }

    public function hariIni(): string
    {
        return now(self::TZ)->format('Y-m-d');
    }

    /* ---------- penghitung perubahan ---------- */

    public function ubNow(): int
    {
        $v = DB::table('resik_meta')->where('kunci', 'ub')->value('nilai');
        return (int) ($v ?? 0);
    }

    public function ubNext(int $n = 1): int
    {
        $next = $this->ubNow() + $n;
        DB::table('resik_meta')->updateOrInsert(['kunci' => 'ub'], ['nilai' => (string) $next]);
        return $next;
    }

    /* ---------- baca / tulis tabel domain ---------- */

    /** Baca semua baris tabel; jika $since>0 hanya yang ub > since (delta). */
    public function baca(string $t, int $since = 0): array
    {
        $q = DB::table('records')->where('tabel', $t);
        if ($since > 0) {
            $q->where('ub', '>', $since);
        }
        $out = [];
        foreach ($q->get(['data']) as $r) {
            $row = json_decode($r->data, true);
            if (is_array($row)) {
                $out[] = $row;
            }
        }
        return $out;
    }

    /** Upsert sekumpulan baris ke tabel dengan nilai ub tertentu. */
    public function tulis(string $t, array $rows, int $ub): void
    {
        foreach ($rows as $row) {
            $k = $this->keyOf($t, $row);
            DB::table('records')->updateOrInsert(
                ['tabel' => $t, 'kunci' => $k],
                ['data' => json_encode($row, JSON_UNESCAPED_UNICODE), 'ub' => $ub, 'updated_at' => now()]
            );
        }
    }

    /** Hapus satu baris domain berdasarkan kuncinya (dipakai hapus pelanggan). */
    public function hapus(string $t, string $kunci): void
    {
        DB::table('records')->where('tabel', $t)->where('kunci', $kunci)->delete();
    }

    public function terpasang(): bool
    {
        return DB::table('resik_meta')->where('kunci', 'terpasang')->exists();
    }

    /* ---------- pengguna ---------- */

    public function users(): array
    {
        return DB::table('resik_users')->get()->map(fn ($u) => (array) $u)->all();
    }

    public function userById(?string $id): ?array
    {
        if (!$id) return null;
        $u = DB::table('resik_users')->where('id', $id)->first();
        return $u ? (array) $u : null;
    }

    public function simpanUser(array $u): void
    {
        $u['ub'] = $this->ubNext();
        $u['aktif'] = !empty($u['aktif']) ? 1 : 0;
        $u['updated_at'] = now();
        DB::table('resik_users')->updateOrInsert(['id' => $u['id']], $u);
    }

    /* ---------- sesi ---------- */

    public function buatSesi(string $id): string
    {
        $tok = str_replace('-', '', (string) Str::uuid());
        $exp = (int) (microtime(true) * 1000) + self::SESI_HARI * 86400000;
        DB::table('resik_sessions')->insert(['token' => $tok, 'user_id' => $id, 'expires' => $exp]);
        return $tok;
    }

    public function sesi(?string $tok): ?array
    {
        if (!$tok) return null;
        $s = DB::table('resik_sessions')->where('token', $tok)->first();
        if (!$s) return null;
        if ((int) $s->expires < (int) (microtime(true) * 1000)) {
            DB::table('resik_sessions')->where('token', $tok)->delete();
            return null;
        }
        return $this->userById($s->user_id);
    }

    public function hapusSesi(?string $tok): void
    {
        if ($tok) {
            DB::table('resik_sessions')->where('token', $tok)->delete();
        }
    }
}
