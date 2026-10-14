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
            $table->text('tujuan_pengadaan_ringkas');
            $table->string('file_permohonan', 255);
            $table->string('status_permohonan', 50)->default('diajukan'); // diajukan, reviu_paralel, pending_anggaran, disetujui, ditolak, diproses_ppbj, selesai
            $table->string('status_anggaran', 50)->default('pending'); // pending, hold, disetujui, ditolak
            $table->integer('revisi_ke')->default(0);
            $table->decimal('estimasi_total_pagu', 15, 2)->nullable();
            $table->timestamp('tanggal_pengajuan')->useCurrent();
            $table->string('kode_unit', 20);
            $table->unsignedBigInteger('id_user');
            $table->string('nomor_surat_induk_split', 100)->nullable();
            $table->timestamp('tanggal_pemecahan')->nullable();
            $table->text('alasan_pemecahan')->nullable();
            $table->string('nomor_surat_revisi', 100)->nullable();
            $table->timestamp('tanggal_revisi')->nullable();
            $table->text('alasan_revisi')->nullable();
            $table->string('kode_mak', 50)->nullable();
            $table->timestamps();

            $table->foreign('kode_unit')
                ->references('kode_unit')
                ->on('units')
                ->cascadeOnUpdate();

            $table->foreign('id_user')
                ->references('id_user')
                ->on('users')
                ->cascadeOnUpdate();

            $table->foreign('kode_mak')
                ->references('kode_mak')
                ->on('anggaran_mak')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::table('permohonan_pengadaan', function (Blueprint $table) {
            $table->foreign('nomor_surat_induk_split')
                ->references('nomor_surat')
                ->on('permohonan_pengadaan')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('nomor_surat_revisi')
                ->references('nomor_surat')
                ->on('permohonan_pengadaan')
                ->nullOnDelete()
                ->cascadeOnUpdate();
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
