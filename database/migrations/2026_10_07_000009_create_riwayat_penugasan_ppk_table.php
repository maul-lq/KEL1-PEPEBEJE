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
        Schema::create('riwayat_penugasan_ppk', function (Blueprint $table) {
            $table->id('id_riwayat');
            $table->string('nomor_paket', 100);
            $table->unsignedBigInteger('ppk_lama_user_id')->nullable();
            $table->unsignedBigInteger('ppk_baru_user_id');
            $table->unsignedBigInteger('diubah_oleh_user_id');
            $table->text('alasan_perubahan')->nullable();
            $table->timestamp('tanggal_penugasan')->useCurrent();

            $table->foreign('nomor_paket')
                ->references('nomor_paket')
                ->on('paket_pengadaan')
                ->cascadeOnUpdate();

            $table->foreign('ppk_lama_user_id')
                ->references('id_user')
                ->on('users')
                ->cascadeOnUpdate();

            $table->foreign('ppk_baru_user_id')
                ->references('id_user')
                ->on('users')
                ->cascadeOnUpdate();

            $table->foreign('diubah_oleh_user_id')
                ->references('id_user')
                ->on('users')
                ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('riwayat_penugasan_ppk');
    }
};
