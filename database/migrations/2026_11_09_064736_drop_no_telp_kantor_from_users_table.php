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
        if (Schema::hasColumn('users', 'no_telp_kantor')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('no_telp_kantor');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'no_telp_kantor')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('no_telp_kantor', 20)->nullable();
            });
        }
    }
};
