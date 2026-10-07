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
        Schema::create('vendors', function (Blueprint $table) {
            $table->string('npwp', 30)->primary();
            $table->string('nib', 30)->unique();
            $table->string('nama_perusahaan', 200);
            $table->text('alamat');
            $table->string('file_legalitas', 255);
            $table->boolean('is_active')->default(true);
            $table->string('nama_pic', 100);
            $table->string('telepon_pic', 20);
            $table->string('email_pic', 100);
            $table->string('nama_bank', 100);
            $table->string('nomor_rekening', 50);
            $table->string('atas_nama', 150);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
