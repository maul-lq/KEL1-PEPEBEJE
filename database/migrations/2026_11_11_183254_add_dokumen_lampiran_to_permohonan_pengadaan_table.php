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
        Schema::table('permohonan_pengadaan', function (Blueprint $table) {
            $table->json('dokumen_lampiran')->nullable()->after('file_spesifikasi_teknis');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permohonan_pengadaan', function (Blueprint $table) {
            $table->dropColumn('dokumen_lampiran');
        });
    }
};
