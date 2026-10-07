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
        Schema::create('reminder', function (Blueprint $table) {
            $table->id('id_reminder');
            $table->string('nomor_paket', 100);
            $table->string('target_role', 50); // ppbj, pp, ppk, keuangan
            $table->string('tipe_reminder', 50); // pengiriman, h_minus_2_termin, bulanan_tahunan
            $table->string('judul_alert', 150);
            $table->text('pesan_alert');
            $table->timestamp('tanggal_pemicu');
            $table->boolean('is_read')->default(false);
            $table->boolean('is_dismissed')->default(false);
            $table->unsignedBigInteger('id_user')->nullable();
            $table->timestamps();

            $table->foreign('nomor_paket')
                ->references('nomor_paket')
                ->on('paket_pengadaan')
                ->cascadeOnDelete();

            $table->foreign('id_user')
                ->references('id_user')
                ->on('users')
                ->cascadeOnUpdate();

            $table->index(['id_reminder', 'nomor_paket']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reminder');
    }
};
