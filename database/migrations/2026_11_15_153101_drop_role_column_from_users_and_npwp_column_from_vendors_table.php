<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini susun untuk menghapus kolom legacy `users.role` (digantikan penuh
 *             oleh relasi `users.id_role`) dan kolom sisa `vendors.npwp`.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim penghapus kolom duplikat `users.role` dan `vendors.npwp`.
 *
 * Tujuan: menuntaskan transisi ke sistem RBAC relasional berbasis `id_role` dan membersihkan
 * sisa indeks kolom sebelum melakukan penghapusan kolom di SQLite maupun PostgreSQL.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_11_15_153101_drop_role_column_from_users_and_npwp_column_from_vendors_table.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi backfilling `id_role` lalu menghapus kolom `users.role` dan `vendors.npwp`.
     *
     * Tujuan: memetakan nilai string `role` lama ke `id_role` lebih dulu bagi baris yang
     * masih null dan melepaskan indeks non-primary kolom terkait agar dropColumn tidak gagal.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'role')) {
            if (Schema::hasTable('roles') && Schema::hasColumn('users', 'id_role')) {
                $rolesMap = DB::table('roles')->pluck('id', 'nama_role');
                $defaultRoleId = $rolesMap['user'] ?? null;

                DB::table('users')
                    ->whereNull('id_role')
                    ->orderBy('id_user')
                    ->get(['id_user', 'role'])
                    ->each(function (object $row) use ($rolesMap, $defaultRoleId): void {
                        $resolvedRoleId = $rolesMap[$row->role] ?? $defaultRoleId;
                        if ($resolvedRoleId !== null) {
                            DB::table('users')
                                ->where('id_user', $row->id_user)
                                ->update(['id_role' => $resolvedRoleId]);
                        }
                    });
            }

            $userIndexes = collect(Schema::getIndexes('users'))
                ->filter(fn (array $index): bool => ! ($index['primary'] ?? false) && in_array('role', $index['columns'] ?? [], true))
                ->values();

            Schema::table('users', function (Blueprint $table) use ($userIndexes): void {
                foreach ($userIndexes as $index) {
                    if ($index['unique'] ?? false) {
                        $table->dropUnique($index['name']);
                    } else {
                        $table->dropIndex($index['name']);
                    }
                }

                $table->dropColumn('role');
            });
        }

        if (Schema::hasTable('vendors') && Schema::hasColumn('vendors', 'npwp')) {
            $vendorIndexes = collect(Schema::getIndexes('vendors'))
                ->filter(fn (array $index): bool => ! ($index['primary'] ?? false) && in_array('npwp', $index['columns'] ?? [], true))
                ->values();

            Schema::table('vendors', function (Blueprint $table) use ($vendorIndexes): void {
                foreach ($vendorIndexes as $index) {
                    if ($index['unique'] ?? false) {
                        $table->dropUnique($index['name']);
                    } else {
                        $table->dropIndex($index['name']);
                    }
                }

                $table->dropColumn('npwp');
            });
        }
    }

    /**
     * Membalikkan migrasi dengan memulihkan kolom `users.role` dan `vendors.npwp`.
     *
     * Tujuan: mengisi kembali kolom string `role` dari tabel `roles` berdasar `id_role` agar
     * sistem kembali kompatibel apabila rollback diperlukan.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('role', 50)->default('user');
            });

            if (Schema::hasTable('roles') && Schema::hasColumn('users', 'id_role')) {
                $rolesMap = DB::table('roles')->pluck('nama_role', 'id');

                DB::table('users')
                    ->whereNotNull('id_role')
                    ->orderBy('id_user')
                    ->get(['id_user', 'id_role'])
                    ->each(function (object $row) use ($rolesMap): void {
                        $roleName = $rolesMap[$row->id_role] ?? null;
                        if ($roleName !== null) {
                            DB::table('users')
                                ->where('id_user', $row->id_user)
                                ->update(['role' => $roleName]);
                        }
                    });
            }
        }

        if (Schema::hasTable('vendors') && ! Schema::hasColumn('vendors', 'npwp')) {
            Schema::table('vendors', function (Blueprint $table): void {
                $table->string('npwp', 30)->nullable();
            });
        }
    }
};
