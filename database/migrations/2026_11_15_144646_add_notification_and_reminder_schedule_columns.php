<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini susun untuk menambahkan kolom preferensi reminder email pada `users`
 *             serta kolom penjadwalan otomatis pada tabel `reminder` untuk pengingat jatuh tempo.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas migrasi anonim penambah kolom preferensi notifikasi email dan jadwal reminder otomatis.
 *
 * Tujuan: melengkapi tabel `reminder` dengan target permissions JSON, flag aktif, serta
 * jejak waktu trigger agar penjadwal berkala tidak mengirim pengingat yang sama berulang kali.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_11_15_144646_add_notification_and_reminder_schedule_columns.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi penambahan kolom preferensi email pengguna dan metadata jadwal reminder.
     *
     * Tujuan: menandai reminder satu-kali yang sudah masuk ke `notifikasi_user` sebagai
     * nonaktif (`is_active = false`) agar tidak ikut diproses ulang saat scheduler baru aktif.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
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

            // tandai nonaktif histori reminder sekali-jalan yang sudah terkirim agar scheduler tidak mengirim ganda.
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
     * Membalikkan migrasi dengan menjatuhkan indeks terkait dan kolom penjadwalan reminder.
     *
     * Tujuan: memeriksa daftar indeks aktif via `Schema::getIndexes` sebelum drop kolom agar
     * SQLite dan PostgreSQL tidak melempar error ketergantungan indeks.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
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
