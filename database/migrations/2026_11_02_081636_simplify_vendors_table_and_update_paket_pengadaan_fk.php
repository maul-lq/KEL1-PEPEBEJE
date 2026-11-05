<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            // 1. Drop foreign key constraints on referencing tables
            Schema::table('vendor_file_legalitas', function (Blueprint $table) {
                $table->dropForeign(['npwp']);
            });

            Schema::table('paket_pengadaan', function (Blueprint $table) {
                $table->dropForeign(['npwp_vendor']);
            });

            // 2. Drop primary key and unique constraints on vendors
            DB::statement('ALTER TABLE vendors DROP CONSTRAINT IF EXISTS vendors_pkey CASCADE');
            DB::statement('ALTER TABLE vendors DROP CONSTRAINT IF EXISTS vendors_nib_unique CASCADE');

            // 3. Add id as primary key and new minimal columns
            if (! Schema::hasColumn('vendors', 'id')) {
                DB::statement('ALTER TABLE vendors ADD COLUMN id BIGSERIAL PRIMARY KEY');
            }

            if (! Schema::hasColumn('vendors', 'nama_vendor')) {
                DB::statement('ALTER TABLE vendors ADD COLUMN nama_vendor VARCHAR(255) NULL');
            }

            if (! Schema::hasColumn('vendors', 'file_dokumen_legalitas')) {
                DB::statement('ALTER TABLE vendors ADD COLUMN file_dokumen_legalitas VARCHAR(255) NULL');
            }

            // 4. Make previous mandatory columns nullable to support lightweight vendor creation
            $columnsToNullable = ['npwp', 'nib', 'nama_perusahaan', 'alamat', 'nama_pic', 'telepon_pic', 'email_pic', 'nama_bank', 'nomor_rekening', 'atas_nama'];
            foreach ($columnsToNullable as $col) {
                DB::statement("ALTER TABLE vendors ALTER COLUMN {$col} DROP NOT NULL");
            }

            // Sync existing nama_perusahaan to nama_vendor
            DB::statement('UPDATE vendors SET nama_vendor = nama_perusahaan WHERE nama_vendor IS NULL AND nama_perusahaan IS NOT NULL');

            // 5. Add id_vendor to paket_pengadaan
            if (! Schema::hasColumn('paket_pengadaan', 'id_vendor')) {
                Schema::table('paket_pengadaan', function (Blueprint $table) {
                    $table->unsignedBigInteger('id_vendor')->nullable()->after('npwp_vendor');
                    $table->foreign('id_vendor')->references('id')->on('vendors')->nullOnDelete()->cascadeOnUpdate();
                });

                DB::statement('UPDATE paket_pengadaan SET id_vendor = vendors.id FROM vendors WHERE paket_pengadaan.npwp_vendor = vendors.npwp AND paket_pengadaan.id_vendor IS NULL');
            }
        } else {
            // SQLite (Testing Environment)
            Schema::disableForeignKeyConstraints();

            Schema::create('vendors_new', function (Blueprint $table) {
                $table->id();
                $table->string('nama_vendor', 255)->nullable();
                $table->string('file_dokumen_legalitas', 255)->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('npwp', 30)->nullable()->unique();
                $table->string('nib', 30)->nullable();
                $table->string('nama_perusahaan', 200)->nullable();
                $table->text('alamat')->nullable();
                $table->string('nama_pic', 100)->nullable();
                $table->string('telepon_pic', 20)->nullable();
                $table->string('email_pic', 100)->nullable();
                $table->string('nama_bank', 100)->nullable();
                $table->string('nomor_rekening', 50)->nullable();
                $table->string('atas_nama', 150)->nullable();
                $table->timestamps();
            });

            if (Schema::hasTable('vendors')) {
                DB::statement('INSERT INTO vendors_new (npwp, nib, nama_perusahaan, nama_vendor, alamat, is_active, nama_pic, telepon_pic, email_pic, nama_bank, nomor_rekening, atas_nama, created_at, updated_at) SELECT npwp, nib, nama_perusahaan, nama_perusahaan, alamat, is_active, nama_pic, telepon_pic, email_pic, nama_bank, nomor_rekening, atas_nama, created_at, updated_at FROM vendors');
                Schema::drop('vendors');
            }
            Schema::rename('vendors_new', 'vendors');

            if (! Schema::hasColumn('paket_pengadaan', 'id_vendor')) {
                Schema::table('paket_pengadaan', function (Blueprint $table) {
                    $table->unsignedBigInteger('id_vendor')->nullable()->after('npwp_vendor');
                });
            }

            if (Schema::hasColumn('paket_pengadaan', 'npwp_vendor')) {
                DB::statement('UPDATE paket_pengadaan SET id_vendor = (SELECT id FROM vendors WHERE vendors.npwp = paket_pengadaan.npwp_vendor) WHERE id_vendor IS NULL AND npwp_vendor IS NOT NULL');
            }

            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            if (Schema::hasColumn('paket_pengadaan', 'id_vendor')) {
                Schema::table('paket_pengadaan', function (Blueprint $table) {
                    $table->dropForeign(['id_vendor']);
                    $table->dropColumn('id_vendor');
                });
            }

            if (Schema::hasColumn('vendors', 'id')) {
                DB::statement('ALTER TABLE vendors DROP CONSTRAINT IF EXISTS vendors_pkey CASCADE');
                Schema::table('vendors', function (Blueprint $table) {
                    $table->dropColumn(['id', 'nama_vendor', 'file_dokumen_legalitas']);
                });
                DB::statement('ALTER TABLE vendors ADD CONSTRAINT vendors_pkey PRIMARY KEY (npwp)');
            }
        }
    }
};
