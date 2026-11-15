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
        Schema::table('pembayaran', function (Blueprint $table) {
            $table->dropForeign(['id_user_reviu']);
            $table->dropColumn([
                'id_user_reviu',
                'status_reviu_akhir',
                'file_tagihan_vendor',
            ]);
        });

        Schema::table('memo_bayar', function (Blueprint $table) {
            $table->dropColumn([
                'urutan_level',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pembayaran', function (Blueprint $table) {
            $table->unsignedBigInteger('id_user_reviu')->nullable();
            $table->string('status_reviu_akhir', 50)->nullable();
            $table->string('file_tagihan_vendor', 255)->nullable();

            $table->foreign('id_user_reviu')
                ->references('id_user')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::table('memo_bayar', function (Blueprint $table) {
            $table->integer('urutan_level')->default(1);
        });
    }
};
