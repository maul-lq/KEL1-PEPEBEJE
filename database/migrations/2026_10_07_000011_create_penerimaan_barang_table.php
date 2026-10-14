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
            $table->id('id_penerimaan');
            $table->string('nomor_bast', 100)->unique()->nullable();
            $table->string('nomor_paket', 100);
            $table->unsignedBigInteger('id_user'); // Petugas Perlengkapan
            $table->date('tanggal_penerimaan');
            $table->string('status_fisik', 50)->default('terima'); // terima, pending, tidak_sesuai
            $table->text('ttd_digital_bast')->nullable();
            $table->string('file_bast_signed', 255)->nullable();
            $table->string('file_penerimaan_pihak3', 255)->nullable();
            $table->text('catatan_pemeriksaan')->nullable();
            $table->timestamp('waktu_selesai_inspeksi')->nullable();
            $table->timestamps();

            $table->foreign('nomor_paket')
                ->references('nomor_paket')
                ->on('paket_pengadaan')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

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
