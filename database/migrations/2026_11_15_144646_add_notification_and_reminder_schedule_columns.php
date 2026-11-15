<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan preferensi reminder email di tabel users serta metadata penjadwalan
     * di tabel reminder agar reminder berkala dan sekali jalan dapat diproses otomatis.
     */
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'reminder_email_aktif')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('reminder_email_aktif')->default(true);
            });
        }

        if (Schema::hasTable('reminder')) {
            Schema::table('reminder', function (Blueprint $table): void {
                if (! Schema::hasColumn('reminder', 'is_recurring')) {
                    $table->boolean('is_recurring')->default(false);
                }

                if (! Schema::hasColumn('reminder', 'nomor_pembayaran')) {
                    $table->string('nomor_pembayaran')->nullable()->index();
                }

                if (! Schema::hasColumn('reminder', 'target_permissions')) {
                    $table->json('target_permissions')->nullable();
                }

                if (! Schema::hasColumn('reminder', 'is_active')) {
                    $table->boolean('is_active')->default(true)->index();
                }

                if (! Schema::hasColumn('reminder', 'last_triggered_at')) {
                    $table->timestamp('last_triggered_at')->nullable();
                }

                if (! Schema::hasColumn('reminder', 'parent_reminder_id')) {
                    $table->unsignedBigInteger('parent_reminder_id')->nullable()->index();
                }
            });

            // Tandai histori reminder instan yang sudah memiliki notifikasi_user sebagai non-aktif (sudah terkirim)
            $dispatchedIds = DB::table('notifikasi_user')->distinct()->pluck('id_reminder');
            if ($dispatchedIds->isNotEmpty()) {
                DB::table('reminder')
                    ->whereIn('id_reminder', $dispatchedIds)
                    ->where('is_recurring', false)
                    ->update([
                        'is_active' => false,
                        'last_triggered_at' => DB::raw('COALESCE(waktu_trigger, created_at)'),
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('reminder')) {
            $existingIndexes = collect(Schema::getIndexes('reminder'))
                ->pluck('name')
                ->all();

            Schema::table('reminder', function (Blueprint $table) use ($existingIndexes): void {
                foreach ($existingIndexes as $indexName) {
                    if (
                        str_contains($indexName, 'nomor_pembayaran')
                        || str_contains($indexName, 'is_active')
                        || str_contains($indexName, 'parent_reminder_id')
                        || str_contains($indexName, 'active_trigger')
                    ) {
                        $table->dropIndex($indexName);
                    }
                }

                $columns = array_filter([
                    Schema::hasColumn('reminder', 'nomor_pembayaran') ? 'nomor_pembayaran' : null,
                    Schema::hasColumn('reminder', 'target_permissions') ? 'target_permissions' : null,
                    Schema::hasColumn('reminder', 'is_active') ? 'is_active' : null,
                    Schema::hasColumn('reminder', 'is_recurring') ? 'is_recurring' : null,
                    Schema::hasColumn('reminder', 'last_triggered_at') ? 'last_triggered_at' : null,
                    Schema::hasColumn('reminder', 'parent_reminder_id') ? 'parent_reminder_id' : null,
                ]);

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'reminder_email_aktif')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('reminder_email_aktif');
            });
        }
    }
};
