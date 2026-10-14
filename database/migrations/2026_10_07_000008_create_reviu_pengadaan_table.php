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
        Schema::create('reviu_dokumen', function (Blueprint $table) {
            $table->id('id_reviu');
            $table->string('nomor_paket', 100);
            $table->unsignedBigInteger('id_user');
            $table->integer('putaran_ke')->default(1);
            $table->string('role_reviewer', 50); // pp, ppk
            $table->string('status_reviu', 50); // disetujui, perlu_perbaikan, ditolak
            $table->text('catatan_perbaikan')->nullable();
            $table->timestamp('tanggal_reviu')->useCurrent();
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
        Schema::dropIfExists('reviu_dokumen');
    }
};
