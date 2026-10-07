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
        Schema::create('paket_pengadaan', function (Blueprint $table) {
            $table->string('nomor_paket', 100)->primary();
            $table->string('nama_paket', 255);
            $table->string('jenis_pengadaan', 50); // barang, jasa_lainnya, konstruksi, jasa_konsultansi
            $table->string('metode_pengadaan', 50); // pengadaan_langsung, e_purchasing, tender, non_tender_spse, inaproc
            $table->decimal('nilai_hps', 15, 2);
            $table->string('status_paket', 50); // registrasi_ppbj, reviu_dokumen, spk_terbit, pengiriman, penerimaan, pembayaran, selesai
            $table->string('jalur_routing', 50); // langsung_sd_50jt, pp_50jt_sd_200jt, ppk_diatas_200jt
            $table->string('id_paket_lkpp', 100)->nullable();
            $table->string('nomor_surat', 100);
            $table->string('kode_mak', 100);
            $table->unsignedBigInteger('ppbj_user_id');
            $table->unsignedBigInteger('pp_user_id')->nullable();
            $table->unsignedBigInteger('ppk_user_id')->nullable();
            $table->string('npwp_vendor', 30)->nullable();
            $table->timestamps();

            $table->foreign('nomor_surat')
                ->references('nomor_surat')
                ->on('permohonan_pengadaan')
                ->cascadeOnUpdate();

            $table->foreign('kode_mak')
                ->references('kode_mak')
                ->on('anggaran_mak')
                ->cascadeOnUpdate();

            $table->foreign('ppbj_user_id')
                ->references('id_user')
                ->on('users')
                ->cascadeOnUpdate();

            $table->foreign('pp_user_id')
                ->references('id_user')
                ->on('users')
                ->cascadeOnUpdate();

            $table->foreign('ppk_user_id')
                ->references('id_user')
                ->on('users')
                ->cascadeOnUpdate();

            $table->foreign('npwp_vendor')
                ->references('npwp')
                ->on('vendors')
                ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paket_pengadaan');
    }
};
