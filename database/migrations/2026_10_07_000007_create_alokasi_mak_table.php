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
            $table->id();
            $table->string('nomor_surat', 100);
            $table->string('kode_mak', 50);
            $table->decimal('nominal_alokasi', 15, 2);
            $table->timestamp('tanggal_alokasi')->useCurrent();
            $table->timestamps();

            $table->foreign('nomor_surat')
                ->references('nomor_surat')
                ->on('permohonan_pengadaan')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('kode_mak')
                ->references('kode_mak')
                ->on('anggaran_mak')
                ->cascadeOnDelete()
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
