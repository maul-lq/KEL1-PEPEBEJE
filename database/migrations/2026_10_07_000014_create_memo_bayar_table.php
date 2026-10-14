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
            $table->id();
            $table->unsignedBigInteger('id_user');
            $table->string('nomor_pembayaran', 100);
            $table->string('role_approver', 50); // ppk, wadir_2
            $table->integer('urutan_level')->default(1); // 1: PPK, 2: Wadir 2
            $table->string('status_memo', 50)->default('menunggu'); // menunggu, disetujui, ditolak
            $table->text('catatan_memo')->nullable();
            $table->timestamp('tanggal_acc')->nullable();
            $table->timestamps();

            $table->foreign('id_user')
                ->references('id_user')
                ->on('users')
                ->cascadeOnUpdate();

            $table->foreign('nomor_pembayaran')
                ->references('nomor_pembayaran')
                ->on('pembayaran')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
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
