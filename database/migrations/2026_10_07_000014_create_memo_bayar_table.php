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
        Schema::create('memo_bayar', function (Blueprint $table) {
            $table->unsignedBigInteger('id_user');
            $table->string('nomor_pembayaran', 100);
            $table->string('nomor_paket', 100);
            $table->string('role_approver', 50); // ppk, wadir_2
            $table->string('status_memo', 50); // disetujui, ditolak
            $table->text('catatan_memo')->nullable();
            $table->timestamp('tanggal_acc')->useCurrent();

            $table->primary(['id_user', 'nomor_pembayaran', 'nomor_paket']);

            $table->foreign('id_user')
                ->references('id_user')
                ->on('users')
                ->cascadeOnUpdate();

            $table->foreign(['nomor_pembayaran', 'nomor_paket'])
                ->references(['nomor_pembayaran', 'nomor_paket'])
                ->on('pembayaran')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memo_bayar');
    }
};
