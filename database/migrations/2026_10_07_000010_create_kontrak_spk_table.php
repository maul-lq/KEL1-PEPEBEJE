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
        Schema::create('kontrak_spk', function (Blueprint $table) {
            $table->string('nomor_spk', 100)->primary();
            $table->string('nomor_paket', 100);
            $table->unsignedBigInteger('id_user')->nullable(); // Pembuat SPK (PPBJ)
            $table->string('tipe_dokumen', 50)->default('spk'); // spk, surat_kontrak, surat_pesanan
            $table->string('sifat_kontrak', 50)->default('sekali_selesai'); // sekali_selesai, lumpsum, harga_satuan, termin_bulanan, kontrak_tahunan
            $table->string('status_spk', 50)->default('draft'); // draft, terbit, aktif, selesai
            $table->date('tanggal_spk');
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->date('jadwal_pengiriman');
            $table->decimal('nilai_kontrak', 15, 2);
            $table->string('file_spk', 255);
            $table->timestamps();

            $table->foreign('nomor_paket')
                ->references('nomor_paket')
                ->on('paket_pengadaan')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('id_user')
                ->references('id_user')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kontrak_spk');
    }
};
