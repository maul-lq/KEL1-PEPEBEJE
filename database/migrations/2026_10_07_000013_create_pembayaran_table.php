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
            $table->string('nomor_pembayaran', 100)->primary();
            $table->string('nomor_paket', 100);
            $table->unsignedBigInteger('id_user_pengaju'); // PPBJ
            $table->integer('tahap_termin')->default(1);
            $table->decimal('nominal_pengajuan', 15, 2);
            $table->date('tanggal_pengajuan');
            $table->date('jatuh_tempo');
            $table->string('status_pembayaran', 50)->default('diajukan'); // diajukan, reviu_memo, disetujui_memo, perintah_bayar, lunas, ditolak
            $table->string('file_tagihan_vendor', 255)->nullable();
            $table->string('file_dokumen_penunjang', 255)->nullable();
            $table->string('nomor_memo', 100)->nullable();
            $table->string('file_memo', 255)->nullable();
            $table->unsignedBigInteger('id_user_reviu')->nullable(); // PPBJ reviu akhir
            $table->timestamp('tanggal_perintah_bayar')->nullable();
            $table->string('status_reviu_akhir', 50)->nullable();
            $table->text('catatan_reviu_akhir')->nullable();
            $table->timestamps();

            $table->foreign('nomor_paket')
                ->references('nomor_paket')
                ->on('paket_pengadaan')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('id_user_pengaju')
                ->references('id_user')
                ->on('users')
                ->cascadeOnUpdate();

            $table->foreign('id_user_reviu')
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
        Schema::dropIfExists('pembayaran');
    }
};
