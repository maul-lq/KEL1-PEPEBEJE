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
            $table->unsignedBigInteger('id_user'); // Staf Keuangan
            $table->timestamp('tanggal_transfer')->useCurrent();
            $table->decimal('nominal_transfer', 15, 2);
            $table->string('file_bukti_transfer', 255);
            $table->text('catatan_keuangan')->nullable();
            $table->timestamps();

            $table->foreign('nomor_pembayaran')
                ->references('nomor_pembayaran')
                ->on('pembayaran')
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
        Schema::dropIfExists('transaksi_pencairan');
    }
};
