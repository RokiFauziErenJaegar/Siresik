<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Skema Resik Collect — meniru model penyimpanan Apps Script:
 * tiap "tabel" domain disimpan sebagai baris (tabel, kunci, data-JSON, ub)
 * dengan penghitung perubahan global `ub` untuk sinkronisasi delta (?since=).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Penyimpanan key-value per tabel domain (petugas, wilayah, pos, pelanggan, ...)
        Schema::create('records', function (Blueprint $t) {
            $t->id();
            $t->string('tabel', 32);
            $t->string('kunci');
            $t->longText('data');      // JSON satu baris domain
            $t->unsignedBigInteger('ub')->default(0);
            $t->timestamps();
            $t->unique(['tabel', 'kunci']);
            $t->index(['tabel', 'ub']);
        });

        // Penghitung perubahan global + meta kecil
        Schema::create('resik_meta', function (Blueprint $t) {
            $t->string('kunci')->primary();
            $t->string('nilai')->nullable();
        });

        // Pengguna aplikasi (login PIN) — terpisah dari tabel users bawaan Laravel
        Schema::create('resik_users', function (Blueprint $t) {
            $t->string('id', 32)->primary();
            $t->string('nama');
            $t->string('peran', 16);               // kadis | bendahara | monev | petugas
            $t->string('ptg', 16)->nullable();     // id petugas (untuk peran petugas)
            $t->boolean('aktif')->default(true);
            $t->string('salt')->nullable();
            $t->string('pin')->nullable();         // hash SHA-256(salt|pin)
            $t->unsignedBigInteger('ub')->default(0);
            $t->timestamps();
        });

        // Sesi login (token -> user, kedaluwarsa)
        Schema::create('resik_sessions', function (Blueprint $t) {
            $t->string('token', 64)->primary();
            $t->string('user_id', 32);
            $t->unsignedBigInteger('expires');     // epoch ms, seperti Apps Script
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resik_sessions');
        Schema::dropIfExists('resik_users');
        Schema::dropIfExists('resik_meta');
        Schema::dropIfExists('records');
    }
};
