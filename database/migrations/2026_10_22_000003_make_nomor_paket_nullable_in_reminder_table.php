<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Mengubah kolom nomor_paket pada tabel reminder menjadi nullable
     * dan menyetel foreign key constraint ke ON DELETE SET NULL.
     */
    public function up(): void
    {
        if (! Schema::hasTable('reminder')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE reminder DROP CONSTRAINT IF EXISTS reminder_nomor_paket_foreign;');
            DB::statement('ALTER TABLE reminder ALTER COLUMN nomor_paket DROP NOT NULL;');
            DB::statement('ALTER TABLE reminder ADD CONSTRAINT reminder_nomor_paket_foreign FOREIGN KEY (nomor_paket) REFERENCES paket_pengadaan(nomor_paket) ON DELETE SET NULL ON UPDATE CASCADE;');
        } elseif ($driver === 'sqlite') {
            // Pada SQLite in-memory, kolom nomor_paket sudah nullable sejak migrasi awal create_reminder_table.
            // Tidak diperlukan alter table agar tidak merusak schema in-memory SQLite.
        } else {
            Schema::table('reminder', function (Blueprint $table) {
                $table->dropForeign(['nomor_paket']);
                $table->string('nomor_paket', 100)->nullable()->change();
                $table->foreign('nomor_paket')
                    ->references('nomor_paket')
                    ->on('paket_pengadaan')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('reminder')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE reminder DROP CONSTRAINT IF EXISTS reminder_nomor_paket_foreign;');
            DB::statement('ALTER TABLE reminder ALTER COLUMN nomor_paket SET NOT NULL;');
            DB::statement('ALTER TABLE reminder ADD CONSTRAINT reminder_nomor_paket_foreign FOREIGN KEY (nomor_paket) REFERENCES paket_pengadaan(nomor_paket) ON DELETE CASCADE ON UPDATE CASCADE;');
        } elseif ($driver === 'sqlite') {
            // No-op for SQLite
        } else {
            Schema::table('reminder', function (Blueprint $table) {
                $table->dropForeign(['nomor_paket']);
                $table->string('nomor_paket', 100)->nullable(false)->change();
                $table->foreign('nomor_paket')
                    ->references('nomor_paket')
                    ->on('paket_pengadaan')
                    ->cascadeOnDelete()
                    ->cascadeOnUpdate();
            });
        }
    }
};
