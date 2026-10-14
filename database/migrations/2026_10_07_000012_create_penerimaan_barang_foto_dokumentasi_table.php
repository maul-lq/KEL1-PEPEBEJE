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
        Schema::create('penerimaan_barang_foto_dokumentasi', function (Blueprint $table) {
            $table->id('id_foto');
            $table->unsignedBigInteger('id_penerimaan');
            $table->string('foto_dokumentasi', 255);
            $table->string('keterangan_foto', 255)->nullable();
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamps();

            $table->foreign('id_penerimaan')
                ->references('id_penerimaan')
                ->on('penerimaan_barang')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penerimaan_barang_foto_dokumentasi');
    }
};
