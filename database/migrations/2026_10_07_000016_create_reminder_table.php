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
        Schema::create('reminder', function (Blueprint $table) {
            $table->id('id_reminder');
            $table->string('nomor_paket', 100);
            $table->string('tipe_reminder', 50); // pengiriman, h_minus_2_termin, bulanan_tahunan
            $table->timestamp('waktu_trigger');
            $table->boolean('is_recurring')->default(false);
            $table->integer('interval_bulan')->nullable();
            $table->string('judul_alert', 150);
            $table->text('pesan_alert');
            $table->timestamps();

            $table->foreign('nomor_paket')
                ->references('nomor_paket')
                ->on('paket_pengadaan')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::create('notifikasi_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_user');
            $table->unsignedBigInteger('id_reminder');
            $table->boolean('is_read')->default(false);
            $table->boolean('is_dismissed')->default(false);
            $table->timestamp('waktu_dibaca')->nullable();
            $table->timestamps();

            $table->foreign('id_user')
                ->references('id_user')
                ->on('users')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('id_reminder')
                ->references('id_reminder')
                ->on('reminder')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->unique(['id_user', 'id_reminder']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifikasi_user');
        Schema::dropIfExists('reminder');
    }
};
