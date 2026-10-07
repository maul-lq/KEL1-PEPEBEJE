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
        Schema::create('pengadaan_logs', function (Blueprint $table) {
            $table->id('id_log');
            $table->string('nomor_surat', 100)->nullable();
            $table->string('nomor_paket', 100)->nullable();
            $table->unsignedBigInteger('id_user');
            $table->string('aksi', 100); // SPLIT_MAK, ASSIGN_PPK, LOCK_MAK, ACC_WADIR2, dll.
            $table->text('keterangan');
            $table->string('ip_address', 45);
            $table->timestamp('created_at')->useCurrent();

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
        Schema::dropIfExists('pengadaan_logs');
    }
};
