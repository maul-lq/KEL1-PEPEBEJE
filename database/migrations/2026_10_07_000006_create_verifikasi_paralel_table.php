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
        Schema::create('verifikasi_paralel', function (Blueprint $table) {
            $table->unsignedBigInteger('id_user');
            $table->string('nomor_surat', 100);
            $table->string('role_verifikator', 50); // staff_bidang_2, wadir_2, perencanaan
            $table->string('status_keputusan', 50); // pending, disetujui, ditolak, hold_anggaran
            $table->text('catatan_alasan')->nullable();
            $table->timestamp('tanggal_keputusan')->useCurrent();

            $table->primary(['id_user', 'nomor_surat']);

            $table->foreign('id_user')
                ->references('id_user')
                ->on('users')
                ->cascadeOnUpdate();

            $table->foreign('nomor_surat')
                ->references('nomor_surat')
                ->on('permohonan_pengadaan')
                ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verifikasi_paralel');
    }
};
