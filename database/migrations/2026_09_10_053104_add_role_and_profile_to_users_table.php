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
        Schema::table('users', function (Blueprint $table) {
            $table->string('nip')->nullable()->after('name');
            $table->string('role', 30)->default('user')->after('email');
            $table->string('jabatan')->nullable()->after('role');
            $table->string('unit_kerja')->nullable()->after('jabatan');
            $table->string('phone')->nullable()->after('unit_kerja');
            $table->boolean('is_active')->default(true)->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nip', 'role', 'jabatan', 'unit_kerja', 'phone', 'is_active']);
        });
    }
};
