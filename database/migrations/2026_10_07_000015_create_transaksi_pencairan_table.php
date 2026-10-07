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
        Schema::create('transaksi_pencairan', function (Blueprint $table) {
            $table->string('nomor_transaksi_bank', 100)->primary();
            $table->string('nomor_pembayaran', 100);
            $table->string('nomor_paket', 100);
            $table->timestamp('tanggal_transfer')->useCurrent();
            $table->decimal('nominal_transfer', 15, 2);
            $table->string('file_bukti_transfer', 255);
            $table->text('catatan_keuangan')->nullable();
            $table->unsignedBigInteger('id_user'); // Staf Keuangan
            $table->timestamps();

            $table->foreign(['nomor_pembayaran', 'nomor_paket'])
                ->references(['nomor_pembayaran', 'nomor_paket'])
                ->on('pembayaran')
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
        Schema::dropIfExists('transaksi_pencairan');
    }
};
