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
        Schema::create('penerimaan_barang', function (Blueprint $table) {
            $table->string('nomor_bast', 100);
            $table->string('nomor_paket', 100);
            $table->date('tanggal_penerimaan');
            $table->string('status_fisik', 50); // diterima_lengkap, diterima_sebagian, pending_rusak, ditolak
            $table->text('ttd_digital_bast');
            $table->string('file_bast_signed', 255);
            $table->string('file_penerimaan_pihak3', 255)->nullable();
            $table->text('catatan_pemeriksaan')->nullable();
            $table->unsignedBigInteger('id_user'); // Petugas Perlengkapan
            $table->timestamps();

            $table->primary(['nomor_bast', 'nomor_paket']);

            $table->foreign('nomor_paket')
                ->references('nomor_paket')
                ->on('paket_pengadaan')
                ->cascadeOnDelete();

            $table->foreign('id_user')
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
        Schema::dropIfExists('penerimaan_barang');
    }
};
