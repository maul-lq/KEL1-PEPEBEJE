<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menjalankan migrasi penambahan kolom identitas Google OAuth pada tabel pengguna (users).
     *
     * Kolom ini digunakan untuk menyimpan identitas akun Google yang ditautkan oleh pengguna:
     * - google_id: ID unik pengguna dari Google OAuth (wajib unik untuk mencegah duplikasi akun).
     * - google_email: Alamat email akun Google yang ditautkan.
     * - google_avatar: URL foto profil pengguna dari Google.
     * - google_linked_at: Waktu penautan akun Google dilakukan oleh pengguna.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->string('google_email')->nullable()->after('google_id');
            $table->string('google_avatar')->nullable()->after('google_email');
            $table->timestamp('google_linked_at')->nullable()->after('google_avatar');
        });
    }

    /**
     * Membatalkan migrasi dan menghapus seluruh kolom terkait Google OAuth.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'google_id',
                'google_email',
                'google_avatar',
                'google_linked_at',
            ]);
        });
    }
};
