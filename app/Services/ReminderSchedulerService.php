<?php

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
 * Mesin Pemroses Reminder Berkala & Jatuh Tempo (ReminderSchedulerService)
 *
 * Memproses seluruh jadwal pengingat otomatis yang telah memasuki waktu pemicu (`waktu_trigger <= now()`),
 * membangkitkan kemunculan notifikasi personal + email untuk setiap penerima berbasis RBAC,
 * memajukan jadwal berkala (`termin_bulanan` setiap 1 bulan / `kontrak_tahunan` setiap 12 bulan),
 * dan menonaktifkan jadwal secara otomatis saat paket atau tagihan sudah selesai/lunas.
 */
class ReminderSchedulerService
{
    public const CACHE_THROTTLE_KEY = 'simpbj_reminder_scheduler_recent_tick';

    public const LOCK_KEY = 'simpbj_reminder_scheduler_lock';

    /**
     * Eksekusi cepat terjadwal untuk dipanggil dari request web terautentikasi dengan jeda cache.
     */
    public function runIfDueCached(): void
    {
        if (Cache::has(self::CACHE_THROTTLE_KEY)) {
            return;
        }

        if (! Schema::hasTable('reminder') || ! Schema::hasColumn('reminder', 'is_active')) {
            return;
        }

        Cache::put(self::CACHE_THROTTLE_KEY, now()->timestamp, now()->addMinutes(2));

        try {
            $this->processDueReminders();
        } catch (\Throwable $exception) {
            Log::warning('Pemrosesan otomatis reminder berkala gagal: '.$exception->getMessage());
        }
    }

    /**
     * Memastikan seluruh kontrak/tagihan aktif memiliki jadwal pengingat lengkap.
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
     * Memproses seluruh jadwal reminder yang telah jatuh tempo (`waktu_trigger <= now()`).
     *
     * @return array{processed: int, dispatched_notifications: int, deactivated: int}
     */
    public function processDueReminders(): array
    {
        $summary = [
            'processed' => 0,
            'dispatched_notifications' => 0,
            'deactivated' => 0,
        ];

        $lock = Cache::lock(self::LOCK_KEY, 30);
        if (! $lock->get()) {
            return $summary;
        }

        try {
            $this->syncMissingSchedules();

            $now = Carbon::now();

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
                    $nextTrigger = $schedule->waktu_trigger
                        ? $schedule->waktu_trigger->copy()->addMonthsNoOverflow((int) $schedule->interval_bulan)
                        : $now->copy()->addMonthsNoOverflow((int) $schedule->interval_bulan);

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
            $lock->release();
        }

        return $summary;
    }

    /**
     * Memeriksa apakah jadwal pengingat harus dihentikan karena paket/pembayaran telah selesai.
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
     * Menentukan penerima notifikasi untuk suatu jadwal pengingat berbasis RBAC & penugasan paket.
     *
     * @return Collection<int, User>
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

        // Filter khusus pemegang izin terisolasi paket (`reviu.ppk` / `reviu.pp`): hanya jika ditugaskan ke paket ini
        $paket = $schedule->paket;
        $assignedIds = array_filter([
            $paket?->id_user_ppk,
            $paket?->id_user_pp,
            $paket?->id_user_ppbj,
            $schedule->pembayaran?->id_user_pengaju,
        ]);

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

        // Tambahkan secara eksplisit PPK/PP pengawal paket jika ada dan aktif
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
     * Menyusun pesan pengingat pada setiap kemunculan siklus berkala.
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
     * Memetakan daftar permission ke slug `nama_role` pada tabel `roles` saat permission spesifik belum terhubung ke role.
     *
     * @param  array<int, string>  $permissions
     * @return array<int, string>
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
