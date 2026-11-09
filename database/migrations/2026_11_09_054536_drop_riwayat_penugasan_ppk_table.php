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
        Schema::dropIfExists('riwayat_penugasan_ppk');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('riwayat_penugasan_ppk')) {
            Schema::create('riwayat_penugasan_ppk', function (Blueprint $table) {
                $table->bigIncrements('id_riwayat');
                $table->string('kode_paket', 50)->nullable();
                $table->string('nomor_paket', 50)->nullable();
                $table->unsignedBigInteger('ppk_lama_user_id')->nullable();
                $table->unsignedBigInteger('ppk_baru_user_id')->nullable();
                $table->unsignedBigInteger('diubah_oleh_user_id')->nullable();
                $table->text('alasan_perubahan')->nullable();
                $table->timestamp('tanggal_penugasan')->useCurrent();
                $table->timestamps();
            });
        }
    }
};
