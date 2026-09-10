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
        Schema::create('pengadaans', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_pengadaan')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('nama_pengadaan');
            $table->string('jenis_pengadaan')->default('Barang');
            $table->string('jenis_belanja')->nullable(); // Belanja Barang / Belanja Modal
            $table->text('latar_belakang')->nullable();
            $table->decimal('estimasi_anggaran', 15, 2)->default(0);
            $table->string('nomor_surat_user')->nullable();
            $table->date('tanggal_surat_user')->nullable();
            $table->string('no_draft_srikandi')->nullable();
            $table->string('file_surat_permohonan')->nullable();

            // Status & jalur routing
            $table->string('status', 40)->default('diajukan');
            $table->string('jalur_pengadaan', 40)->nullable(); // pengadaan_langsung, pejabat_pengadaan, pejabat_pembuat_komitmen

            // Wadir 2 Persetujuan
            $table->foreignId('wadir2_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan_wadir2')->nullable();
            $table->dateTime('tanggal_persetujuan_wadir2')->nullable();

            // Perencanaan (MAK)
            $table->foreignId('perencanaan_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nomor_mak')->nullable();
            $table->decimal('pagu_anggaran', 15, 2)->nullable();
            $table->string('sumber_dana', 50)->nullable();
            $table->date('tanggal_mak')->nullable();
            $table->text('catatan_perencanaan')->nullable();

            // PPBJ & Vendor / SPK
            $table->foreignId('ppbj_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nomor_memo_ppbj')->nullable();
            $table->dateTime('tanggal_registrasi_ppbj')->nullable();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('metode_pengadaan')->nullable();
            $table->string('id_paket_lkpp')->nullable(); // ID Paket / Link Transaksi LKPP-Inaproc (katalog.inaproc.id)
            $table->string('nomor_spk')->nullable();
            $table->date('tanggal_spk')->nullable();
            $table->decimal('nilai_kontrak', 15, 2)->nullable();
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai_jadwal')->nullable();
            $table->string('file_spk')->nullable();
            $table->boolean('tembusan_perlengkapan')->default(false);
            $table->boolean('tembusan_keuangan')->default(false);
            $table->string('klasifikasi_alarm')->nullable();

            // Review PPK/PP
            $table->foreignId('ppk_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status_reviu_ppk', 30)->nullable(); // disetujui / perbaikan
            $table->text('catatan_ppk')->nullable();
            $table->dateTime('tanggal_reviu_ppk')->nullable();

            // Perlengkapan (Penerimaan Barang)
            $table->foreignId('perlengkapan_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status_penerimaan', 30)->nullable(); // Terima / Pending
            $table->string('nama_penerima')->nullable();
            $table->string('unit_penerima')->nullable();
            $table->dateTime('tanggal_penerimaan')->nullable();
            $table->string('foto_dokumentasi_barang')->nullable();
            $table->longText('ttd_digital')->nullable();
            $table->text('catatan_penerimaan')->nullable();

            // Pembayaran & Keuangan
            $table->string('file_dokumen_pembayaran')->nullable();
            $table->text('catatan_pengajuan_pembayaran')->nullable();
            $table->dateTime('tanggal_pengajuan_pembayaran')->nullable();
            $table->boolean('acc_pembayaran_ppk')->default(false);
            $table->dateTime('tanggal_acc_pembayaran_ppk')->nullable();
            $table->text('catatan_pembayaran_ppk')->nullable();
            $table->boolean('acc_pembayaran_wadir2')->default(false);
            $table->dateTime('tanggal_acc_pembayaran_wadir2')->nullable();
            $table->text('catatan_pembayaran_wadir2')->nullable();
            $table->string('nomor_spp')->nullable();
            $table->date('tanggal_spp')->nullable();

            $table->foreignId('keuangan_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nomor_bukti_bayar')->nullable();
            $table->date('tanggal_bayar')->nullable();
            $table->string('file_bukti_bayar')->nullable();
            $table->text('catatan_keuangan')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengadaans');
    }
};
