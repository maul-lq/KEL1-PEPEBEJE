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
        Schema::create('users', function (Blueprint $table) {
            $table->id('id_user');
            $table->string('nip', 30)->unique();
            $table->string('email', 100)->unique();
            $table->string('nama', 150);
            $table->string('password', 255);
            $table->string('jabatan', 100);
            $table->string('unit_kerja', 100);
            $table->string('role', 50);
            $table->boolean('status_aktif')->default(true);
            $table->string('no_hp', 20);
            $table->string('no_telp_kantor', 20)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
