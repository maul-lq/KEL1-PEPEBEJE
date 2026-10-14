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
            $table->string('platform_pengadaan', 50)->default('spse'); // spse, inaproc, bela_pengadaan, e_katalog, toko_daring, non_spse
            $table->string('mekanisme_pengadaan', 50)->default('pengadaan_langsung'); // pengadaan_langsung, tender, seleksi, penunjukan_langsung, e_purchasing
            $table->string('jalur_routing', 50)->default('langsung_sd_50jt'); // langsung_sd_50jt, pp_50jt_sd_200jt, ppk_diatas_200jt
            $table->decimal('nilai_hps', 15, 2);
            $table->decimal('alokasi_pagu_paket', 15, 2);
            $table->string('file_dokumen_pengadaan', 255)->nullable();
            $table->string('status_paket', 50)->default('draft'); // draft, reviu_dokumen, spk_terbit, pengiriman, penerimaan, pembayaran, selesai, dibatalkan
            $table->string('id_paket_lkpp', 100)->nullable();
            $table->string('nomor_surat', 100);
            $table->timestamp('tanggal_registrasi_paket')->useCurrent();
            $table->string('nomor_memo_ppbj', 100)->nullable();
            $table->unsignedBigInteger('id_user_ppbj'); // Staf PPBJ registrasi
            $table->unsignedBigInteger('id_user_ppk')->nullable(); // PPK yang ditugaskan (1 dari 4 akun PPK)
            $table->timestamp('tanggal_penugasan_ppk')->nullable();
            $table->string('status_penugasan_ppk', 50)->nullable();
            $table->text('alasan_pengalihan_ppk')->nullable();
            $table->unsignedBigInteger('id_user_pp')->nullable(); // PP yang ditugaskan (jika 50-200jt)
            $table->timestamp('tanggal_penugasan_pp')->nullable();
            $table->decimal('nilai_hasil_negosiasi', 15, 2)->nullable();
            $table->text('catatan_verifikasi_pp')->nullable();
            $table->string('npwp_vendor', 30)->nullable();
            $table->timestamp('tanggal_penunjukan_vendor')->nullable();
            $table->timestamps();

            $table->foreign('nomor_surat')
                ->references('nomor_surat')
                ->on('permohonan_pengadaan')
                ->cascadeOnUpdate();

            $table->foreign('id_user_ppbj')
                ->references('id_user')
                ->on('users')
                ->cascadeOnUpdate();

            $table->foreign('id_user_ppk')
                ->references('id_user')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('id_user_pp')
                ->references('id_user')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('npwp_vendor')
                ->references('npwp')
                ->on('vendors')
                ->nullOnDelete()
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
