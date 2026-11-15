<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menghapus kolom duplikat users.role (digantikan penuh oleh users.id_role)
     * serta menghapus kolom sisa vendors.npwp.
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
     * Memulihkan kolom users.role dan vendors.npwp saat rollback migrasi.
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
