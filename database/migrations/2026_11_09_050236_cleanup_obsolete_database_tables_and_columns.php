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
        // 1. Hapus tabel usang vendor_file_legalitas (sudah digantikan kolom file_dokumen_legalitas pada tabel vendors)
        Schema::dropIfExists('vendor_file_legalitas');

        // 2. Hapus kolom usang npwp_vendor pada tabel paket_pengadaan (sudah digantikan oleh id_vendor dengan relasi resmi)
        if (Schema::hasColumn('paket_pengadaan', 'npwp_vendor')) {
            Schema::table('paket_pengadaan', function (Blueprint $table) {
                $table->dropColumn('npwp_vendor');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('vendor_file_legalitas')) {
            Schema::create('vendor_file_legalitas', function (Blueprint $table) {
                $table->id();
                $table->string('npwp', 30)->nullable();
                $table->string('nama_dokumen', 150);
                $table->string('file_legalitas', 255);
                $table->timestamp('uploaded_at')->useCurrent();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('paket_pengadaan', 'npwp_vendor')) {
            Schema::table('paket_pengadaan', function (Blueprint $table) {
                $table->string('npwp_vendor', 30)->nullable();
            });
        }
    }
};
