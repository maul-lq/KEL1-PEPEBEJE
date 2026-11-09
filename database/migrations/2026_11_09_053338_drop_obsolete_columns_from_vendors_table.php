<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Pastikan nama_vendor terisi dari nama_perusahaan jika masih ada yang bernilai null
        if (Schema::hasColumn('vendors', 'nama_perusahaan') && Schema::hasColumn('vendors', 'nama_vendor')) {
            DB::statement('UPDATE vendors SET nama_vendor = nama_perusahaan WHERE (nama_vendor IS NULL OR nama_vendor = \'\') AND nama_perusahaan IS NOT NULL');
        }

        // 2. Drop kolom-kolom kontak legacy dan redundan
        $columnsToDrop = ['nib', 'alamat', 'nama_pic', 'telepon_pic', 'email_pic', 'nama_perusahaan'];

        foreach ($columnsToDrop as $col) {
            if (Schema::hasColumn('vendors', $col)) {
                Schema::table('vendors', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('nib', 30)->nullable();
            $table->text('alamat')->nullable();
            $table->string('nama_pic', 100)->nullable();
            $table->string('telepon_pic', 20)->nullable();
            $table->string('email_pic', 100)->nullable();
            $table->string('nama_perusahaan', 200)->nullable();
        });

        if (Schema::hasColumn('vendors', 'nama_perusahaan') && Schema::hasColumn('vendors', 'nama_vendor')) {
            DB::statement('UPDATE vendors SET nama_perusahaan = nama_vendor WHERE nama_perusahaan IS NULL AND nama_vendor IS NOT NULL');
        }
    }
};
