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
        Schema::create('pembayaran', function (Blueprint $table) {
            $table->string('nomor_pembayaran', 100);
            $table->string('nomor_paket', 100);
            $table->integer('tahap_termin')->default(1);
            $table->decimal('nominal_pengajuan', 15, 2);
            $table->string('status_pembayaran', 50); // draft, reviu_memo, perintah_bayar, lunas, ditolak
            $table->string('file_dokumen_penunjang', 255)->nullable();
            $table->string('file_tagihan_vendor', 255)->nullable();
            $table->timestamps();

            $table->primary(['nomor_pembayaran', 'nomor_paket']);

            $table->foreign('nomor_paket')
                ->references('nomor_paket')
                ->on('paket_pengadaan')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pembayaran');
    }
};
