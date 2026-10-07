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
        Schema::create('alokasi_mak', function (Blueprint $table) {
            $table->string('nomor_surat', 100);
            $table->string('kode_mak', 100);
            $table->decimal('nominal_alokasi', 15, 2);
            $table->timestamp('tanggal_alokasi')->useCurrent();

            $table->primary(['nomor_surat', 'kode_mak']);

            $table->foreign('nomor_surat')
                ->references('nomor_surat')
                ->on('permohonan_pengadaan')
                ->cascadeOnUpdate();

            $table->foreign('kode_mak')
                ->references('kode_mak')
                ->on('anggaran_mak')
                ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alokasi_mak');
    }
};
