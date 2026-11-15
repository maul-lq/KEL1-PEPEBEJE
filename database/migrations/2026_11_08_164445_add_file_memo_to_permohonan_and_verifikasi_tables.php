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
        if (Schema::hasTable('permohonan_pengadaan')) {
            Schema::table('permohonan_pengadaan', function (Blueprint $table) {
                if (! Schema::hasColumn('permohonan_pengadaan', 'file_memo_cek_mak')) {
                    $table->string('file_memo_cek_mak', 255)->nullable()->after('catatan_memo_cek_mak');
                }
                if (! Schema::hasColumn('permohonan_pengadaan', 'file_memo_ketersediaan_mak')) {
                    $table->string('file_memo_ketersediaan_mak', 255)->nullable()->after('catatan_memo_ketersediaan_mak');
                }
            });
        }

        if (Schema::hasTable('verifikasi_paralel')) {
            Schema::table('verifikasi_paralel', function (Blueprint $table) {
                if (! Schema::hasColumn('verifikasi_paralel', 'nomor_memo')) {
                    $table->string('nomor_memo', 100)->nullable()->after('catatan_alasan');
                }
                if (! Schema::hasColumn('verifikasi_paralel', 'file_memo')) {
                    $table->string('file_memo', 255)->nullable()->after('nomor_memo');
                }
            });
        }

        if (Schema::hasTable('memo_bayar')) {
            Schema::table('memo_bayar', function (Blueprint $table) {
                if (! Schema::hasColumn('memo_bayar', 'nomor_memo')) {
                    $table->string('nomor_memo', 100)->nullable()->after('catatan_memo');
                }
                if (! Schema::hasColumn('memo_bayar', 'file_memo')) {
                    $table->string('file_memo', 255)->nullable()->after('nomor_memo');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('permohonan_pengadaan')) {
            Schema::table('permohonan_pengadaan', function (Blueprint $table) {
                if (Schema::hasColumn('permohonan_pengadaan', 'file_memo_cek_mak')) {
                    $table->dropColumn('file_memo_cek_mak');
                }
                if (Schema::hasColumn('permohonan_pengadaan', 'file_memo_ketersediaan_mak')) {
                    $table->dropColumn('file_memo_ketersediaan_mak');
                }
            });
        }

        if (Schema::hasTable('verifikasi_paralel')) {
            Schema::table('verifikasi_paralel', function (Blueprint $table) {
                if (Schema::hasColumn('verifikasi_paralel', 'file_memo')) {
                    $table->dropColumn('file_memo');
                }
                if (Schema::hasColumn('verifikasi_paralel', 'nomor_memo')) {
                    $table->dropColumn('nomor_memo');
                }
            });
        }

        if (Schema::hasTable('memo_bayar')) {
            Schema::table('memo_bayar', function (Blueprint $table) {
                if (Schema::hasColumn('memo_bayar', 'file_memo')) {
                    $table->dropColumn('file_memo');
                }
                if (Schema::hasColumn('memo_bayar', 'nomor_memo')) {
                    $table->dropColumn('nomor_memo');
                }
            });
        }
    }
};
