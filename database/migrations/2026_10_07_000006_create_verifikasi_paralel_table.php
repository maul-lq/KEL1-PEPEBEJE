<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini buat untuk membentuk tabel `verifikasi_paralel` guna merekam
 *             keputusan individu setiap verifikator (Bidang II, Perencanaan, Wadir II, PPK).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim pembuat tabel `verifikasi_paralel`.
 *
 * Tujuan: merancang tabel log keputusan per peran verifikator beserta indeks unik gabungan
 * agar satu akun verifikator hanya memiliki satu rekaman status per surat dan peran.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_10_07_000006_create_verifikasi_paralel_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi pembentukan tabel `verifikasi_paralel`.
     *
     * Tujuan: mendefinisikan foreign key cascade ke surat permohonan dan constraint unik
     * `uq_verif_user_surat_role` demi mencegah duplikasi suara persetujuan.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        Schema::create('verifikasi_paralel', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_user');
            $table->string('nomor_surat', 100);
            $table->string('role_verifikator', 50);
            $table->string('status_keputusan', 50)->default('menunggu');
            $table->text('catatan_alasan')->nullable();
            $table->timestamp('tanggal_keputusan')->nullable();
            $table->timestamps();

            $table->foreign('id_user')
                ->references('id_user')
                ->on('users')
                ->cascadeOnUpdate();

            $table->foreign('nomor_surat')
                ->references('nomor_surat')
                ->on('permohonan_pengadaan')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            // batasi keunikan (id_user, nomor_surat, role_verifikator) agar pembaruan keputusan selalu idempotent.
            $table->unique(['id_user', 'nomor_surat', 'role_verifikator'], 'uq_verif_user_surat_role');
        });
    }

    /**
     * Membalikkan migrasi dengan menghapus tabel `verifikasi_paralel`.
     *
     * Tujuan: menghapus tabel verifikasi paralel saat rollback skema.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        Schema::dropIfExists('verifikasi_paralel');
    }
};
