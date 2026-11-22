<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini buat untuk membentuk tabel `audit_log` sebagai perekam jejak
 *             seluruh mutasi krusial pengadaan (nilai lama/baru, alasan, pelaku, dan alamat IP).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim pembuat tabel `audit_log`.
 *
 * Tujuan: menyediakan tabel pencatatan jejak audit permanen di database agar setiap aksi
 * persetujuan, penguncian MAK, kontrak, BAST, dan pencairan dapat ditelusuri kembali.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_10_07_000017_create_pengadaan_logs_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi pembuatan tabel `audit_log`.
     *
     * Tujuan: memilih `nullOnDelete` untuk `id_user` agar riwayat audit log tidak pernah
     * terhapus meskipun akun pegawai pembuat aksi suatu saat dihapus dari master pengguna.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id('id_log');
            $table->unsignedBigInteger('id_user')->nullable();
            $table->timestamp('waktu_log')->useCurrent();
            $table->string('jenis_aksi', 50);
            $table->string('nama_entitas', 100);
            $table->string('id_entitas', 100);
            $table->text('nilai_lama')->nullable();
            $table->text('nilai_baru')->nullable();
            $table->text('alasan')->nullable();
            $table->string('ip_address', 45)->nullable(); // Kapasitas 45 karakter cukup menampung alamat IPv6 penuh.
            $table->timestamps();

            // terapkan nullOnDelete agar jejak audit historis tetap utuh saat akun user dihapus.
            $table->foreign('id_user')
                ->references('id_user')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Membalikkan migrasi dengan menghapus tabel `audit_log`.
     *
     * Tujuan: menghapus tabel jejak audit ketika rollback skema dijalankan.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
