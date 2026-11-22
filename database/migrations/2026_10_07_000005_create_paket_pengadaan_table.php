<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini buat untuk membentuk tabel `paket_pengadaan` yang mencatat paket
 *             pekerjaan, nilai HPS, jalur routing otomatis, penugasan PPK/PP, dan vendor terpilih.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim pembuat tabel `paket_pengadaan`.
 *
 * Tujuan: menetapkan skema inti paket pengadaan yang menautkan surat permohonan disetujui ke
 * pelaku pengadaan (PPBJ, PP, PPK) dan vendor pemenang.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_10_07_000005_create_paket_pengadaan_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi pembentukan tabel `paket_pengadaan`.
     *
     * Tujuan: menyiapkan seluruh kolom routing nilai HPS (`langsung_sd_50jt`, `pp_50jt_sd_200jt`,
     * `ppk_diatas_200jt`) serta relasi opsional ke PPK, PP, dan vendor agar tahap eksekusi tercatat utuh.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        Schema::create('paket_pengadaan', function (Blueprint $table) {
            $table->string('nomor_paket', 100)->primary();
            $table->string('nama_paket', 255);
            $table->string('jenis_pengadaan', 50);
            $table->string('platform_pengadaan', 50)->default('spse');
            $table->string('mekanisme_pengadaan', 50)->default('pengadaan_langsung');
            $table->string('jalur_routing', 50)->default('langsung_sd_50jt'); // Menentukan penanggung jawab otomatis berdasar ambang HPS Perpres.
            $table->decimal('nilai_hps', 15, 2);
            $table->decimal('alokasi_pagu_paket', 15, 2);
            $table->string('file_dokumen_pengadaan', 255)->nullable();
            $table->string('status_paket', 50)->default('draft');
            $table->string('id_paket_lkpp', 100)->nullable();
            $table->string('nomor_surat', 100);
            $table->timestamp('tanggal_registrasi_paket')->useCurrent();
            $table->string('nomor_memo_ppbj', 100)->nullable();
            $table->unsignedBigInteger('id_user_ppbj');
            $table->unsignedBigInteger('id_user_ppk')->nullable();
            $table->timestamp('tanggal_penugasan_ppk')->nullable();
            $table->string('status_penugasan_ppk', 50)->nullable();
            $table->text('alasan_pengalihan_ppk')->nullable();
            $table->unsignedBigInteger('id_user_pp')->nullable();
            $table->timestamp('tanggal_penugasan_pp')->nullable();
            $table->decimal('nilai_hasil_negosiasi', 15, 2)->nullable();
            $table->text('catatan_verifikasi_pp')->nullable();
            $table->string('npwp_vendor', 30)->nullable();
            $table->timestamp('tanggal_penunjukan_vendor')->nullable();
            $table->timestamps();

            $table->foreign('nomor_surat')
                ->references('nomor_surat')
                ->on('permohonan_pengadaan')
                ->cascadeOnUpdate();

            $table->foreign('id_user_ppbj')
                ->references('id_user')
                ->on('users')
                ->cascadeOnUpdate();

            // pakai nullOnDelete untuk PPK, PP, dan vendor agar data paket tidak ikut terhapus jika akun/vendor dinonaktifkan.
            $table->foreign('id_user_ppk')
                ->references('id_user')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('id_user_pp')
                ->references('id_user')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('npwp_vendor')
                ->references('npwp')
                ->on('vendors')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Membalikkan migrasi dengan menghapus tabel `paket_pengadaan`.
     *
     * Tujuan: membersihkan tabel paket pengadaan apabila rollback migrasi diperlukan.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        Schema::dropIfExists('paket_pengadaan');
    }
};
