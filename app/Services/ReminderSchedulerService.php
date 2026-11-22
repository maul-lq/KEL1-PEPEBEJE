<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis service ReminderSchedulerService ini untuk memproses jadwal reminder jatuh tempo secara atomik dengan cache lock, mengirim notifikasi/email RBAC, dan memajukan siklus pengingat berkala.
 */

namespace App\Services;

use App\Models\KontrakSPK;
use App\Models\NotifikasiUser;
use App\Models\Pembayaran;
use App\Models\Reminder;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Mesin Penjadwal & Pemroses Reminder Berkala (ReminderSchedulerService)
 *
 * membangun service ini untuk menjembatani pemicuan cron CLI maupun request web terautentikasi,
 * memastikan setiap jadwal jatuh tempo diproses sekali saja via lock terdistribusi, dan otomatis berhenti saat paket/tagihan tuntas.
 *
 * Parameter konstruktor: Tidak ada.
 * Nilai kembali         : Instance layanan penjadwal reminder.
 *
 * Contoh pemanggilan:
 * `$summary = app(ReminderSchedulerService::class)->processDueReminders();`
 */
class ReminderSchedulerService
{
    public const CACHE_THROTTLE_KEY = 'simpbj_reminder_scheduler_recent_tick';

    public const LOCK_KEY = 'simpbj_reminder_scheduler_lock';

    /**
     * menjalankan pemrosesan reminder dari request web dengan jeda throttle 2 menit agar beban kueri tetap ringan.
     *
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `app(ReminderSchedulerService::class)->runIfDueCached();`
     */
    public function runIfDueCached(): void
    {
        if (Cache::has(self::CACHE_THROTTLE_KEY)) {
            return;
        }

        // memeriksa keberadaan tabel/kolom terlebih dahulu supaya perintah migrasi awal atau boot sebelum migrate tidak melempar error SQL.
        if (! Schema::hasTable('reminder') || ! Schema::hasColumn('reminder', 'is_active')) {
            return;
        }

        Cache::put(self::CACHE_THROTTLE_KEY, now()->timestamp, now()->addMinutes(2));

        try {
            $this->processDueReminders();
        } catch (\Throwable $exception) {
            // menelan exception scheduler di jalur HTTP web sebagai warning log supaya gangguan penjadwal tidak memblokir pengguna membuka halaman.
            Log::warning('Pemrosesan otomatis reminder berkala gagal: '.$exception->getMessage());
        }
    }

    /**
     * melengkapi jadwal reminder yang belum tercatat untuk kontrak aktif maupun tagihan pembayaran belum lunas.
     *
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$scheduler->syncMissingSchedules();`
     */
    public function syncMissingSchedules(): void
    {
        if (! Schema::hasTable('reminder') || ! Schema::hasColumn('reminder', 'is_active')) {
            return;
        }

        if (Schema::hasTable('kontrak_spk')) {
            KontrakSPK::query()
                ->with('paket')
                ->whereIn('status_spk', ['terbit', 'aktif'])
                ->get()
                ->each(function (KontrakSPK $spk): void {
                    if ($spk->paket && ! in_array($spk->paket->status_paket, ['selesai', 'batal'], true)) {
                        PengadaanRoutingService::syncKontrakSchedules($spk, $spk->paket);
                    }
                });
        }

        if (Schema::hasTable('pembayaran')) {
            Pembayaran::query()
                ->with('paket')
                ->whereNotIn('status_pembayaran', [Pembayaran::STATUS_LUNAS, Pembayaran::STATUS_DITOLAK])
                ->whereNotNull('jatuh_tempo')
                ->get()
                ->each(function (Pembayaran $pembayaran): void {
                    PengadaanRoutingService::syncPembayaranSchedules($pembayaran);
                });
        }
    }

    /**
     * mengeksekusi seluruh jadwal induk aktif yang sudah memasuki waktu pemicu dan mengembalikan statistik hasil eksekusi.
     *
     * @return array{processed: int, dispatched_notifications: int, deactivated: int} mengembalikan jumlah jadwal diproses, notifikasi dikirim, dan jadwal yang dinonaktifkan.
     *
     * Contoh pemanggilan:
     * `$summary = app(ReminderSchedulerService::class)->processDueReminders();`
     */
    public function processDueReminders(): array
    {
        $summary = [
            'processed' => 0,
            'dispatched_notifications' => 0,
            'deactivated' => 0,
        ];

        // memakai cache lock 30 detik untuk mencegah race condition ketika dua request/worker memicu scheduler pada detik yang sama.
        $lock = Cache::lock(self::LOCK_KEY, 30);
        if (! $lock->get()) {
            return $summary;
        }

        try {
            $this->syncMissingSchedules();

            $now = Carbon::now();

            // hanya mengambil jadwal induk (parent_reminder_id IS NULL) agar record riwayat occurrence tidak pernah dianggap jadwal baru.
            $dueSchedules = Reminder::query()
                ->with(['paket.permohonan', 'paket.penerimaanBarang', 'pembayaran.transaksiPencairan'])
                ->whereNull('parent_reminder_id')
                ->where('is_active', true)
                ->whereIn('tipe_reminder', Reminder::SCHEDULED_TYPES)
                ->where('waktu_trigger', '<=', $now)
                ->orderBy('waktu_trigger')
                ->get();

            foreach ($dueSchedules as $schedule) {
                $summary['processed']++;

                if ($this->shouldDeactivateSchedule($schedule)) {
                    $schedule->is_active = false;
                    $schedule->save();
                    $summary['deactivated']++;

                    continue;
                }

                $recipients = $this->resolveRecipientsForSchedule($schedule);
                if ($recipients->isNotEmpty()) {
                    // mencetak record occurrence terpisah (is_active=false) supaya riwayat tiap siklus berkala tercatat tanpa merusak jadwal induknya.
                    $occurrence = Reminder::create([
                        'nomor_paket' => $schedule->nomor_paket,
                        'nomor_pembayaran' => $schedule->nomor_pembayaran,
                        'tipe_reminder' => $schedule->tipe_reminder,
                        'waktu_trigger' => $now,
                        'interval_bulan' => $schedule->interval_bulan,
                        'is_recurring' => false,
                        'is_active' => false,
                        'last_triggered_at' => $now,
                        'parent_reminder_id' => $schedule->id_reminder,
                        'target_permissions' => $schedule->target_permissions,
                        'judul_alert' => $schedule->judul_alert,
                        'pesan_alert' => $this->buildCycleMessage($schedule),
                    ]);

                    foreach ($recipients as $recipient) {
                        NotifikasiUser::create([
                            'id_user' => $recipient->id_user,
                            'id_reminder' => $occurrence->id_reminder,
                            'is_read' => false,
                            'is_dismissed' => false,
                        ]);
                        $summary['dispatched_notifications']++;
                    }
                }

                $schedule->last_triggered_at = $now;

                if ($schedule->is_recurring && (int) $schedule->interval_bulan > 0) {
                    // memakai addMonthsNoOverflow agar tanggal 31 (mis. Januari) tidak meloncat melewati Februari ke Maret.
                    $nextTrigger = $schedule->waktu_trigger
                        ? $schedule->waktu_trigger->copy()->addMonthsNoOverflow((int) $schedule->interval_bulan)
                        : $now->copy()->addMonthsNoOverflow((int) $schedule->interval_bulan);

                    // memajukan siklus sampai melampaui $now jika server lama mati, sehingga scheduler tidak meledak mengirim banyak tunggakan siklus sekaligus.
                    while ($nextTrigger->lte($now)) {
                        $nextTrigger->addMonthsNoOverflow((int) $schedule->interval_bulan);
                    }

                    $schedule->waktu_trigger = $nextTrigger;
                    $schedule->is_active = true;
                } else {
                    $schedule->is_active = false;
                    $summary['deactivated']++;
                }

                $schedule->save();
            }
        } finally {
            // memastikan lock selalu dilepas di blok finally meski salah satu pengiriman notifikasi melempar error.
            $lock->release();
        }

        return $summary;
    }

    /**
     * mengevaluasi apakah suatu jadwal pengingat sudah tidak relevan lagi karena paket/pembayaran selesai atau barang sudah diterima lengkap.
     *
     * @param  Reminder  $schedule  memeriksa relasi paket, pembayaran, transaksi pencairan, dan BAST pada jadwal ini.
     * @return bool mengembalikan true bila jadwal harus dinonaktifkan dan dilewati.
     *
     * Contoh pemanggilan:
     * `$stop = $this->shouldDeactivateSchedule($schedule);`
     */
    protected function shouldDeactivateSchedule(Reminder $schedule): bool
    {
        $paket = $schedule->paket;
        if ($paket && in_array($paket->status_paket, ['selesai', 'batal'], true)) {
            return true;
        }

        if (in_array($schedule->tipe_reminder, [Reminder::TIPE_JATUH_TEMPO_PEMBAYARAN_H2, Reminder::TIPE_JATUH_TEMPO_PEMBAYARAN], true)) {
            $pembayaran = $schedule->pembayaran;
            if (! $pembayaran) {
                return true;
            }

            if (
                in_array($pembayaran->status_pembayaran, [Pembayaran::STATUS_LUNAS, Pembayaran::STATUS_DITOLAK], true)
                || $pembayaran->transaksiPencairan !== null
            ) {
                return true;
            }

            return false;
        }

        // menghentikan pengingat pengiriman sekali jalan begitu barang tercatat diterima lengkap oleh Perlengkapan, namun mempertahankan pengingat kontrak berkala.
        if (
            ! $schedule->is_recurring
            && in_array($schedule->tipe_reminder, [Reminder::TIPE_PENGIRIMAN, Reminder::TIPE_H_MINUS_2_TERMIN], true)
            && $paket
        ) {
            return $paket->penerimaanBarang->contains(function ($bast): bool {
                return in_array($bast->status_fisik, ['terima', 'diterima_lengkap', 'sesuai'], true);
            });
        }

        return false;
    }

    /**
     * mengumpulkan daftar pengguna aktif penerima pengingat berdasarkan izin RBAC serta isolasi penugasan PPK/PP pada paket.
     *
     * @param  Reminder  $schedule  membaca `target_permissions` serta relasi paket/pembayaran untuk menyaring penerima sah.
     * @return Collection<int, User> mengembalikan koleksi unik model User aktif yang berhak menerima notifikasi.
     *
     * Contoh pemanggilan:
     * `$recipients = $this->resolveRecipientsForSchedule($schedule);`
     */
    protected function resolveRecipientsForSchedule(Reminder $schedule): Collection
    {
        $permissions = $schedule->target_permissions;

        if (! is_array($permissions) || $permissions === []) {
            $permissions = match ($schedule->tipe_reminder) {
                Reminder::TIPE_JATUH_TEMPO_PEMBAYARAN_H2, Reminder::TIPE_JATUH_TEMPO_PEMBAYARAN => [
                    'pembayaran.pencairan_sp2d',
                    'pembayaran.spp_terbitkan',
                    'pembayaran.pengajuan',
                ],
                default => [
                    'penerimaan.bast_create',
                    'spk.create',
                ],
            };
        }

        $fallbackRoles = $this->mapPermissionsToFallbackRoles($permissions);

        $roleMatchedUsers = User::query()
            ->where('status_aktif', true)
            ->whereHas('roleEntity', function ($roleQuery) use ($permissions, $fallbackRoles): void {
                $roleQuery->whereHas('permissions', function ($pq) use ($permissions): void {
                    $pq->whereIn('nama_permission', $permissions);
                });

                if ($fallbackRoles !== []) {
                    $roleQuery->orWhereIn('nama_role', $fallbackRoles);
                }
            })
            ->get();

        $paket = $schedule->paket;
        $assignedIds = array_filter([
            $paket?->id_user_ppk,
            $paket?->id_user_pp,
            $paket?->id_user_ppbj,
            $schedule->pembayaran?->id_user_pengaju,
        ]);

        // menegakkan isolasi NFR 4 akun PPK di sini: pengguna berizin 'paket.view_assigned' tanpa izin lintas paket hanya menerima pengingat untuk paket miliknya.
        $filtered = $roleMatchedUsers->filter(function (User $u) use ($paket, $assignedIds): bool {
            if (! $paket) {
                return true;
            }

            $isScopedOnly = $u->hasPermission('paket.view_assigned') && ! $u->hasAnyPermission([
                'paket.view_all',
                'penerimaan.bast_create',
                'pembayaran.pencairan_sp2d',
                'pembayaran.spp_terbitkan',
                'spk.create',
            ]);

            if ($isScopedOnly) {
                return in_array($u->id_user, $assignedIds, true);
            }

            return true;
        });

        // menambahkan pejabat pelaksana spesifik paket secara eksplisit agar PPK/PP/PPBJ tetap terinfokan meski matriks izin role-nya baru disesuaikan.
        if ($assignedIds !== []) {
            $assignedUsers = User::query()
                ->where('status_aktif', true)
                ->whereIn('id_user', $assignedIds)
                ->get();

            $filtered = $filtered->merge($assignedUsers);
        }

        return $filtered->unique('id_user')->values();
    }

    /**
     * menyusun teks pesan alert dan menambahkan stempel tanggal siklus apabila jadwal bersifat berkala.
     *
     * @param  Reminder  $schedule  membaca `pesan_alert`, `is_recurring`, dan `waktu_trigger` dari jadwal induk.
     * @return string mengembalikan teks pesan siap tampil di notifikasi web dan email.
     *
     * Contoh pemanggilan:
     * `$pesan = $this->buildCycleMessage($schedule);`
     */
    protected function buildCycleMessage(Reminder $schedule): string
    {
        $base = (string) $schedule->pesan_alert;

        if ($schedule->is_recurring && $schedule->waktu_trigger) {
            return $base.' (Siklus pengingat otomatis: '.$schedule->waktu_trigger->format('d M Y').').';
        }

        return $base;
    }

    /**
     * memetakan daftar permission ke slug peran standar sebagai cadangan jika seeding permission belum lengkap.
     *
     * @param  array<int, string>  $permissions  menerima daftar slug permission sasaran jadwal.
     * @return array<int, string> mengembalikan daftar unik slug `nama_role` yang beririsan dengan permission tersebut.
     *
     * Contoh pemanggilan:
     * `$roles = $this->mapPermissionsToFallbackRoles(['spk.create']);`
     */
    protected function mapPermissionsToFallbackRoles(array $permissions): array
    {
        $roles = [];
        foreach (PengadaanNotificationService::ROLE_PERMISSION_MAP as $roleSlug => $rolePerms) {
            if (array_intersect($permissions, $rolePerms) !== []) {
                $roles[] = $roleSlug;
            }
        }

        return array_values(array_unique($roles));
    }
}
