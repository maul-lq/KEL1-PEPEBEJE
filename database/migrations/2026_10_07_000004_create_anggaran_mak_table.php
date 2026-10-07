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
        Schema::create('anggaran_mak', function (Blueprint $table) {
            $table->string('kode_mak', 100)->primary();
            $table->string('uraian_mak', 255);
            $table->decimal('pagu_anggaran', 15, 2);
            $table->string('sumber_dana', 100);
            $table->integer('tahun_anggaran');
            $table->boolean('is_locked')->default(false);
            $table->timestamp('tanggal_kunci')->nullable();
            $table->unsignedBigInteger('id_user');
            $table->timestamps();

            $table->foreign('id_user')
                ->references('id_user')
                ->on('users')
                ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anggaran_mak');
    }
};
