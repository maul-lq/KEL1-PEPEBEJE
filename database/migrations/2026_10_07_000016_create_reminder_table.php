<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini susun untuk membuat tabel `reminder` (jadwal pengingat kontrak)
 *             dan tabel `notifikasi_user` (status baca & dismiss notifikasi per akun pengguna).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim pembuat tabel `reminder` dan `notifikasi_user`.
 *
 * Tujuan: memisahkan definisi jadwal pengingat dengan status keterbacaan tiap penerima agar
 * satu alert pengadaan dapat dikirim ke banyak pengguna secara independen.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_10_07_000016_create_reminder_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi pembentukan tabel `reminder` dan `notifikasi_user`.
     *
     * Tujuan: menambahkan constraint unik `(id_user, id_reminder)` di tabel `notifikasi_user`
     * guna menjamin satu pengguna tidak menerima duplikasi entri untuk reminder yang sama.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        Schema::create('reminder', function (Blueprint $table) {
            $table->id('id_reminder');
            $table->string('nomor_paket', 100)->nullable();
            $table->string('tipe_reminder', 50);
            $table->timestamp('waktu_trigger');
            $table->boolean('is_recurring')->default(false);
            $table->integer('interval_bulan')->nullable();
            $table->string('judul_alert', 150);
            $table->text('pesan_alert');
            $table->timestamps();

            $table->foreign('nomor_paket')
                ->references('nomor_paket')
                ->on('paket_pengadaan')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::create('notifikasi_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_user');
            $table->unsignedBigInteger('id_reminder');
            $table->boolean('is_read')->default(false);
            $table->boolean('is_dismissed')->default(false);
            $table->timestamp('waktu_dibaca')->nullable();
            $table->timestamps();

            $table->foreign('id_user')
                ->references('id_user')
                ->on('users')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('id_reminder')
                ->references('id_reminder')
                ->on('reminder')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            // cegah duplikasi notifikasi bagi pasangan pengguna dan reminder yang sama.
            $table->unique(['id_user', 'id_reminder']);
        });
    }

    /**
     * Membalikkan migrasi dengan menghapus `notifikasi_user` lalu `reminder`.
     *
     * Tujuan: menjatuhkan tabel anak lebih dahulu supaya foreign key constraint tidak menghambat rollback.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        Schema::dropIfExists('notifikasi_user');
        Schema::dropIfExists('reminder');
    }
};
