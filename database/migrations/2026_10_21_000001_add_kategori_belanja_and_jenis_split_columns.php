<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan kolom kategori_belanja pada penerimaan_barang (Belanja Barang / Belanja Modal)
     * serta kolom jenis_pengadaan_split pada permohonan_pengadaan (Split Berkas PPBJ berdasarkan Jenis Pengadaan - FR 7.2).
     */
    public function up(): void
    {
        if (Schema::hasTable('penerimaan_barang') && ! Schema::hasColumn('penerimaan_barang', 'kategori_belanja')) {
            Schema::table('penerimaan_barang', function (Blueprint $table) {
                $table->string('kategori_belanja', 50)->default('belanja_barang')->after('status_fisik'); // belanja_barang, belanja_modal
            });
        }

        if (Schema::hasTable('permohonan_pengadaan') && ! Schema::hasColumn('permohonan_pengadaan', 'jenis_pengadaan_split')) {
            Schema::table('permohonan_pengadaan', function (Blueprint $table) {
                $table->string('jenis_pengadaan_split', 50)->nullable()->after('kode_mak'); // barang, jasa, konstruksi
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('penerimaan_barang') && Schema::hasColumn('penerimaan_barang', 'kategori_belanja')) {
            Schema::table('penerimaan_barang', function (Blueprint $table) {
                $table->dropColumn('kategori_belanja');
            });
        }

        if (Schema::hasTable('permohonan_pengadaan') && Schema::hasColumn('permohonan_pengadaan', 'jenis_pengadaan_split')) {
            Schema::table('permohonan_pengadaan', function (Blueprint $table) {
                $table->dropColumn('jenis_pengadaan_split');
            });
        }
    }
};
