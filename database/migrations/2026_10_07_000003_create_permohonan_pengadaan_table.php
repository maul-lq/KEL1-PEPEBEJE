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
        Schema::create('permohonan_pengadaan', function (Blueprint $table) {
            $table->string('nomor_surat', 100)->primary();
            $table->string('judul_pengadaan', 255);
            $table->string('asal_unit', 100);
            $table->text('tujuan_ringkas');
            $table->string('file_pdf_srikandi', 255);
            $table->string('status_permohonan', 50); // diajukan, reviu_paralel, pending_anggaran, disetujui, ditolak, diproses_ppbj
            $table->string('no_draft_srikandi', 100)->nullable();
            $table->unsignedBigInteger('id_user');
            $table->string('nomor_surat_induk', 100)->nullable();
            $table->timestamps();

            $table->foreign('id_user')
                ->references('id_user')
                ->on('users')
                ->cascadeOnUpdate();
        });

        Schema::table('permohonan_pengadaan', function (Blueprint $table) {
            $table->foreign('nomor_surat_induk')
                ->references('nomor_surat')
                ->on('permohonan_pengadaan')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permohonan_pengadaan');
    }
};
