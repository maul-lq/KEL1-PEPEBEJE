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
        Schema::table('vendors', function (Blueprint $table) {
            $columnsToDrop = [];
            foreach (['nama_bank', 'nomor_rekening', 'atas_nama'] as $col) {
                if (Schema::hasColumn('vendors', $col)) {
                    $columnsToDrop[] = $col;
                }
            }
            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            if (! Schema::hasColumn('vendors', 'nama_bank')) {
                $table->string('nama_bank', 100)->nullable();
            }
            if (! Schema::hasColumn('vendors', 'nomor_rekening')) {
                $table->string('nomor_rekening', 50)->nullable();
            }
            if (! Schema::hasColumn('vendors', 'atas_nama')) {
                $table->string('atas_nama', 150)->nullable();
            }
        });
    }
};
