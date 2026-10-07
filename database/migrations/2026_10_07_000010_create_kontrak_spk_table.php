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
            $table->string('nomor_spk', 100);
            $table->string('nomor_paket', 100);
            $table->string('tipe_kontrak', 50); // sekali_selesai, kontrak_tahunan, termin_bulanan
            $table->date('tanggal_spk');
            $table->date('jadwal_pengiriman');
            $table->decimal('nilai_kontrak', 15, 2);
            $table->string('file_spk', 255);
            $table->timestamps();

            $table->primary(['nomor_spk', 'nomor_paket']);

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
        Schema::dropIfExists('kontrak_spk');
    }
};
