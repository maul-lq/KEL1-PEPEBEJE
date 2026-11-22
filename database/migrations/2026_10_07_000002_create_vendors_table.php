<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini buat untuk membentuk tabel `vendors` dan `vendor_file_legalitas`
 *             guna menyimpan profil penyedia barang/jasa beserta arsip dokumen legalitasnya.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim pembuat tabel `vendors` dan `vendor_file_legalitas`.
 *
 * Tujuan: mendefinisikan data rekanan penyedia berbasis NPWP sebagai primary key beserta
 * tabel anak untuk lampiran dokumen legalitas rekanan.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_10_07_000002_create_vendors_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi pembuatan tabel `vendors` dan `vendor_file_legalitas`.
     *
     * Tujuan: menggunakan `npwp` sebagai primary key alami agar seluruh paket dan kontrak
     * langsung merujuk identitas pajak rekanan secara konsisten.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->string('npwp', 30)->primary(); // jadikan NPWP sebagai kunci utama untuk mencegah registrasi perusahaan ganda.
            $table->string('nib', 30)->unique();
            $table->string('nama_perusahaan', 200);
            $table->text('alamat');
            $table->boolean('is_active')->default(true);
            $table->string('nama_pic', 100);
            $table->string('telepon_pic', 20);
            $table->string('email_pic', 100);
            $table->string('nama_bank', 100);
            $table->string('nomor_rekening', 50);
            $table->string('atas_nama', 150);
            $table->timestamps();
        });

        Schema::create('vendor_file_legalitas', function (Blueprint $table) {
            $table->id();
            $table->string('npwp', 30);
            $table->string('nama_dokumen', 150);
            $table->string('file_legalitas', 255);
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamps();

            // terapkan cascadeOnDelete karena dokumen legalitas hanya bermakna selama entitas vendor induk ada.
            $table->foreign('npwp')
                ->references('npwp')
                ->on('vendors')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Membalikkan migrasi dengan menghapus tabel anak legalitas lalu tabel induk `vendors`.
     *
     * Tujuan: menjaga urutan drop anak-lalu-induk supaya constraint foreign key di PostgreSQL tidak menolak rollback.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_file_legalitas');
        Schema::dropIfExists('vendors');
    }
};
