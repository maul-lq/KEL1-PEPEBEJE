<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan kolom-kolom baru hasil Notulensi Pertemuan PPBJ terbaru:
     * 1. permohonan_pengadaan: Memo Cek MAK (Bidang 2 -> Perencanaan), Memo Ketersediaan MAK (Perencanaan -> Bidang 2 & PPK), dan id_user_ppk_awal.
     * 2. paket_pengadaan: nominal_harga_perkiraan, nominal_harga_akhir, dieksekusi_belanja_oleh, tanggal_eksekusi_belanja, serta output Kwitansi Belanja Langsung (<= 50jt).
     * 3. pembayaran: nomor_kwitansi, file_kwitansi, nomor_invoice, file_invoice untuk Informasi Dokumen Pembayaran.
     */
    public function up(): void
    {
        if (Schema::hasTable('permohonan_pengadaan')) {
            Schema::table('permohonan_pengadaan', function (Blueprint $table) {
                if (! Schema::hasColumn('permohonan_pengadaan', 'file_kak')) {
                    $table->string('file_kak', 255)->nullable()->after('file_permohonan');
                }
                if (! Schema::hasColumn('permohonan_pengadaan', 'file_spesifikasi_teknis')) {
                    $table->string('file_spesifikasi_teknis', 255)->nullable()->after('file_kak');
                }
                if (! Schema::hasColumn('permohonan_pengadaan', 'nomor_memo_cek_mak')) {
                    $table->string('nomor_memo_cek_mak', 100)->nullable()->after('kode_mak');
                }
                if (! Schema::hasColumn('permohonan_pengadaan', 'catatan_memo_cek_mak')) {
                    $table->text('catatan_memo_cek_mak')->nullable()->after('nomor_memo_cek_mak');
                }
                if (! Schema::hasColumn('permohonan_pengadaan', 'nomor_memo_ketersediaan_mak')) {
                    $table->string('nomor_memo_ketersediaan_mak', 100)->nullable()->after('catatan_memo_cek_mak');
                }
                if (! Schema::hasColumn('permohonan_pengadaan', 'catatan_memo_ketersediaan_mak')) {
                    $table->text('catatan_memo_ketersediaan_mak')->nullable()->after('nomor_memo_ketersediaan_mak');
                }
                if (! Schema::hasColumn('permohonan_pengadaan', 'id_user_ppk_awal')) {
                    $table->unsignedBigInteger('id_user_ppk_awal')->nullable()->after('catatan_memo_ketersediaan_mak');
                    $table->foreign('id_user_ppk_awal')
                        ->references('id_user')
                        ->on('users')
                        ->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('paket_pengadaan')) {
            Schema::table('paket_pengadaan', function (Blueprint $table) {
                if (! Schema::hasColumn('paket_pengadaan', 'nominal_harga_perkiraan')) {
                    $table->decimal('nominal_harga_perkiraan', 15, 2)->nullable()->after('nilai_hps');
                }
                if (! Schema::hasColumn('paket_pengadaan', 'nominal_harga_akhir')) {
                    $table->decimal('nominal_harga_akhir', 15, 2)->nullable()->after('nilai_hasil_negosiasi');
                }
                if (! Schema::hasColumn('paket_pengadaan', 'dieksekusi_belanja_oleh')) {
                    $table->unsignedBigInteger('dieksekusi_belanja_oleh')->nullable()->after('tanggal_penunjukan_vendor');
                    $table->foreign('dieksekusi_belanja_oleh')
                        ->references('id_user')
                        ->on('users')
                        ->nullOnDelete();
                }
                if (! Schema::hasColumn('paket_pengadaan', 'tanggal_eksekusi_belanja')) {
                    $table->timestamp('tanggal_eksekusi_belanja')->nullable()->after('dieksekusi_belanja_oleh');
                }
                if (! Schema::hasColumn('paket_pengadaan', 'nomor_kwitansi_belanja')) {
                    $table->string('nomor_kwitansi_belanja', 100)->nullable()->after('tanggal_eksekusi_belanja');
                }
                if (! Schema::hasColumn('paket_pengadaan', 'file_kwitansi_belanja')) {
                    $table->string('file_kwitansi_belanja', 255)->nullable()->after('nomor_kwitansi_belanja');
                }
            });
        }

        if (Schema::hasTable('pembayaran')) {
            Schema::table('pembayaran', function (Blueprint $table) {
                if (! Schema::hasColumn('pembayaran', 'nomor_kwitansi')) {
                    $table->string('nomor_kwitansi', 100)->nullable()->after('file_dokumen_penunjang');
                }
                if (! Schema::hasColumn('pembayaran', 'file_kwitansi')) {
                    $table->string('file_kwitansi', 255)->nullable()->after('nomor_kwitansi');
                }
                if (! Schema::hasColumn('pembayaran', 'nomor_invoice')) {
                    $table->string('nomor_invoice', 100)->nullable()->after('file_kwitansi');
                }
                if (! Schema::hasColumn('pembayaran', 'file_invoice')) {
                    $table->string('file_invoice', 255)->nullable()->after('nomor_invoice');
                }
            });
        }
    }

    /**
     * Membatalkan penambahan kolom-kolom alur baru.
     */
    public function down(): void
    {
        if (Schema::hasTable('pembayaran')) {
            Schema::table('pembayaran', function (Blueprint $table) {
                $cols = array_filter(
                    ['nomor_kwitansi', 'file_kwitansi', 'nomor_invoice', 'file_invoice'],
                    fn (string $c) => Schema::hasColumn('pembayaran', $c)
                );
                if (! empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('paket_pengadaan')) {
            Schema::table('paket_pengadaan', function (Blueprint $table) {
                if (Schema::hasColumn('paket_pengadaan', 'dieksekusi_belanja_oleh')) {
                    $table->dropForeign(['dieksekusi_belanja_oleh']);
                }
                $cols = array_filter([
                    'nominal_harga_perkiraan',
                    'nominal_harga_akhir',
                    'dieksekusi_belanja_oleh',
                    'tanggal_eksekusi_belanja',
                    'nomor_kwitansi_belanja',
                    'file_kwitansi_belanja',
                ], fn (string $c) => Schema::hasColumn('paket_pengadaan', $c));
                if (! empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('permohonan_pengadaan')) {
            Schema::table('permohonan_pengadaan', function (Blueprint $table) {
                if (Schema::hasColumn('permohonan_pengadaan', 'id_user_ppk_awal')) {
                    $table->dropForeign(['id_user_ppk_awal']);
                }
                $cols = array_filter([
                    'file_kak',
                    'file_spesifikasi_teknis',
                    'nomor_memo_cek_mak',
                    'catatan_memo_cek_mak',
                    'nomor_memo_ketersediaan_mak',
                    'catatan_memo_ketersediaan_mak',
                    'id_user_ppk_awal',
                ], fn (string $c) => Schema::hasColumn('permohonan_pengadaan', $c));
                if (! empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
