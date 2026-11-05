<?php

namespace App\Http\Controllers;

use App\Services\ResikStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Port setia dari fungsi rpc(a, body, q, tok) Apps Script.
 * Bentuk JSON permintaan & jawaban dibuat identik agar front-end asli tidak perlu diubah logikanya.
 */
class RpcController extends Controller
{
    public function __construct(private ResikStore $store) {}

    public function handle(Request $req)
    {
        $a = (string) $req->query('a', '');
        $q = (string) $req->getQueryString();
        $tok = $req->header('X-Token') ?: $req->query('tok', '');
        $body = $req->isJson() ? ($req->json()->all() ?? null) : $req->all();
        if (empty($body)) {
            $raw = $req->getContent();
            $body = $raw ? json_decode($raw, true) : null;
        }

        try {
            $s = $this->store;
            $since = 0;
            if (preg_match('/since=(\d+)/', $q, $m)) {
                $since = (int) $m[1];
            }

            if ($a === 'pengguna') {
                $daftar = array_map(
                    fn ($u) => ['id' => $u['id'], 'nama' => $u['nama'], 'peran' => $u['peran'], 'ptg' => $u['ptg'] ?? ''],
                    array_filter($s->users(), fn ($u) => (int) ($u['aktif'] ?? 1) !== 0)
                );
                return $this->ok(['pengguna' => array_values($daftar)]);
            }

            if ($a === 'masuk') {
                $u = $s->userById($body['id'] ?? null);
                if (!$u || (int) ($u['aktif'] ?? 1) === 0) {
                    return $this->tolak('Pengguna tidak ditemukan atau tidak aktif.', 400);
                }
                if (empty($u['pin'])) {
                    return $this->tolak('PIN belum dibuat. Minta Admin/Kadis membuat PIN.', 400);
                }
                if ($s->hash((string) ($body['pin'] ?? ''), (string) $u['salt']) !== $u['pin']) {
                    return $this->tolak('PIN salah.', 400);
                }
                return $this->ok(['token' => $s->buatSesi($u['id'])]);
            }

            if ($a === 'keluar') {
                $s->hapusSesi($tok);
                return $this->ok();
            }

            $me = $s->sesi($tok);
            if (!$me) {
                return $this->tolak('Sesi berakhir. Silakan masuk lagi.', 401, true);
            }

            if ($a === 'data') {
                $rows = [];
                foreach (ResikStore::TBL as $t) {
                    $rows[$t] = $s->baca($t, $since);
                }
                return $this->ok([
                    'me' => ['id' => $me['id'], 'nama' => $me['nama'], 'peran' => $me['peran'], 'ptg' => $me['ptg'] ?? ''],
                    'ub' => $s->ubNow(),
                    'today' => $s->hariIni(),
                    'rows' => $rows,
                ]);
            }

            if ($a === 'cadangan') {
                if ($me['peran'] !== 'kadis' && $me['peran'] !== 'bendahara') {
                    return $this->tolak('Tidak punya hak.', 403);
                }
                $all = [];
                foreach (ResikStore::TBL as $t) {
                    $all[$t] = $s->baca($t);
                }
                $all['diambil'] = now()->toIso8601String();
                return $this->ok(['data' => $all]);
            }

            if ($a === 'simpan') {
                if ($me['peran'] === 'monev') {
                    return $this->tolak('Peran Monev hanya boleh melihat.', 403);
                }
                $ops = $body['ops'] ?? [];
                if (!$ops) {
                    return $this->ok(['rows' => []]);
                }
                $hasil = [];
                DB::transaction(function () use ($s, $ops, &$hasil) {
                    $ub = $s->ubNext();
                    $byT = [];
                    foreach ($ops as $o) {
                        if (!in_array($o['t'] ?? '', ResikStore::TBL, true)) {
                            continue;
                        }
                        // Op hapus: buang baris, jangan dikembalikan di rows (agar tidak di-upsert ulang di klien).
                        if (!empty($o['hapus'])) {
                            $s->hapus($o['t'], $s->keyOf($o['t'], $o['row']));
                            continue;
                        }
                        $byT[$o['t']][] = $o['row'];
                        $hasil[] = ['t' => $o['t'], 'row' => $o['row'], 'asal' => $s->keyOf($o['t'], $o['row'])];
                    }
                    foreach ($byT as $t => $rows) {
                        $s->tulis($t, $rows, $ub);
                    }
                });
                return $this->ok(['rows' => $hasil]);
            }

            if ($a === 'ganti_pin') {
                $me2 = $s->userById($me['id']);
                if (empty($me2['pin']) || $s->hash((string) ($body['lama'] ?? ''), (string) $me2['salt']) !== $me2['pin']) {
                    return $this->tolak('PIN lama salah.', 400);
                }
                if (!preg_match('/^\d{6}$/', (string) ($body['baru'] ?? ''))) {
                    return $this->tolak('PIN baru harus 6 angka.', 400);
                }
                $me2['salt'] = (string) \Illuminate\Support\Str::uuid();
                $me2['pin'] = $s->hash((string) $body['baru'], $me2['salt']);
                $s->simpanUser($me2);
                return $this->ok();
            }

            if ($a === 'kelola' || $a === 'kelola_simpan') {
                if ($me['peran'] !== 'kadis') {
                    return $this->tolak('Hanya Admin/Kadis.', 403);
                }
                if ($a === 'kelola') {
                    $ls = array_map(fn ($u) => [
                        'id' => $u['id'], 'nama' => $u['nama'], 'peran' => $u['peran'],
                        'ptg' => $u['ptg'] ?? '', 'aktif' => (int) ($u['aktif'] ?? 1) !== 0, 'adaPin' => !empty($u['pin']),
                    ], $s->users());
                    return $this->ok(['pengguna' => array_values($ls)]);
                }
                $id = $body['id'] ?? ('U' . strtoupper(base_convert((string) (int) (microtime(true) * 1000), 10, 36)));
                $ex = $s->userById($id) ?? ['id' => $id, 'salt' => (string) \Illuminate\Support\Str::uuid(), 'pin' => ''];
                $ex['nama'] = trim((string) ($body['nama'] ?? ''));
                if ($ex['nama'] === '') {
                    return $this->tolak('Isi nama pengguna.', 400);
                }
                $ex['peran'] = $body['peran'] ?? 'petugas';
                $ex['ptg'] = ($ex['peran'] === 'petugas') ? ($body['ptg'] ?? '') : '';
                $ex['aktif'] = !empty($body['aktif']);
                if (!empty($body['pin'])) {
                    if (!preg_match('/^\d{6}$/', (string) $body['pin'])) {
                        return $this->tolak('PIN harus 6 angka.', 400);
                    }
                    $ex['salt'] = (string) \Illuminate\Support\Str::uuid();
                    $ex['pin'] = $s->hash((string) $body['pin'], $ex['salt']);
                }
                $s->simpanUser($ex);
                return $this->ok();
            }

            return $this->tolak('Perintah tidak dikenal.', 404);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'status' => 500, 'pesan' => 'Server: ' . $e->getMessage()]);
        }
    }

    private function ok(array $extra = [])
    {
        return $this->json(['ok' => true] + $extra);
    }

    private function tolak(string $pesan, int $status = 400, bool $keluar = false)
    {
        return $this->json(['ok' => false, 'pesan' => $pesan, 'status' => $status, 'keluar' => $keluar]);
    }

    private function json(array $data)
    {
        // Front-end mem-parse TEKS jawaban (seperti google.script.run), jadi kirim JSON mentah.
        return response(json_encode($data, JSON_UNESCAPED_UNICODE), 200)
            ->header('Content-Type', 'application/json; charset=utf-8');
    }
}
