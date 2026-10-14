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
            $table->id();
            $table->unsignedBigInteger('id_user');
            $table->string('nomor_surat', 100);
            $table->string('role_verifikator', 50); // perencanaan, staff_bidang_2, wadir_2
            $table->string('status_keputusan', 50)->default('menunggu'); // menunggu, disetujui, ditolak, revisi, pending_anggaran
            $table->text('catatan_alasan')->nullable(); // Wajib diisi jika ditolak / revisi
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

            $table->unique(['id_user', 'nomor_surat', 'role_verifikator'], 'uq_verif_user_surat_role');
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
