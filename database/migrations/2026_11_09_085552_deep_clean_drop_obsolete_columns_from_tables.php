<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('paket_pengadaan', function (Blueprint $table) {
            $table->dropColumn([
                'id_paket_lkpp',
                'tanggal_penugasan_ppk',
                'status_penugasan_ppk',
                'tanggal_penugasan_pp',
                'tanggal_penunjukan_vendor',
            ]);
        });

        Schema::table('pembayaran', function (Blueprint $table) {
            $table->dropColumn([
                'nomor_memo',
                'file_memo',
            ]);
        });

        Schema::table('reminder', function (Blueprint $table) {
            $table->dropColumn([
                'is_recurring',
            ]);
        });

        Schema::table('penerimaan_barang_foto_dokumentasi', function (Blueprint $table) {
            $table->dropColumn([
                'uploaded_at',
            ]);
        });

        Schema::table('audit_log', function (Blueprint $table) {
            $table->dropColumn([
                'ip_address',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('paket_pengadaan', function (Blueprint $table) {
            $table->string('id_paket_lkpp')->nullable();
            $table->dateTime('tanggal_penugasan_ppk')->nullable();
            $table->string('status_penugasan_ppk')->nullable();
            $table->dateTime('tanggal_penugasan_pp')->nullable();
            $table->dateTime('tanggal_penunjukan_vendor')->nullable();
        });

        Schema::table('pembayaran', function (Blueprint $table) {
            $table->string('nomor_memo', 100)->nullable();
            $table->string('file_memo')->nullable();
        });

        Schema::table('reminder', function (Blueprint $table) {
            $table->boolean('is_recurring')->default(false);
        });

        Schema::table('penerimaan_barang_foto_dokumentasi', function (Blueprint $table) {
            $table->dateTime('uploaded_at')->nullable();
        });

        Schema::table('audit_log', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable();
        });
    }
};
