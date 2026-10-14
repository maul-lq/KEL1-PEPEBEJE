<?php

namespace App\Services;

use App\Models\KontrakSPK;
use App\Models\NotifikasiUser;
use App\Models\Pembayaran;
use App\Models\Reminder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Layanan Sinkronisasi Pengingat & Alarm Pengadaan (ReminderService)
 *
 * Mengotomatisasi pemantauan jadwal kritis pengadaan sesuai FR 12 dan NFR 5:
 * - Pengingat tanggal jadwal pengiriman barang fisik oleh rekanan (FR 12.2).
 * - Pengingat H-2 sebelum batas jatuh tempo pembayaran termin (FR 12.2).
 * - Pengingat berkala per bulan khusus untuk kontrak tahunan atau termin berkala (FR 12.3).
 * - Menjamin ketepatan kemunculan notifikasi pada feed pengguna terkait (PPBJ, PP, PPK, Perlengkapan, Keuangan).
 */
class ReminderService
{
    /**
     * Menjalankan sinkronisasi jadwal secara menyeluruh untuk memicu reminder dan notifikasi baru.
     *
     * @return array Rangkuman jumlah reminder dan notifikasi yang berhasil diproses
     */
    public function sinkronkanJadwal(): array
    {
        $today = Carbon::today();
        $totalReminders = 0;
        $totalNotifikasi = 0;

        // =========================================================================
        // 1. PENGINGAT JADWAL PENGIRIMAN BARANG (HARI H PENGIRIMAN SPK - FR 12.2)
        // =========================================================================
        // Menelusuri seluruh kontrak SPK aktif yang jadwal pengirimannya jatuh tempo hari ini atau mendekati
        $kontrakPengiriman = KontrakSPK::with('paket')
            ->whereIn('status_spk', ['aktif', 'terbit'])
            ->whereNotNull('jadwal_pengiriman')
            ->get();

        foreach ($kontrakPengiriman as $spk) {
            $tglKirim = Carbon::parse($spk->jadwal_pengiriman)->startOfDay();

            // Pemicu aktif jika hari ini adalah tanggal pengiriman atau H-1 pengiriman
            if ($today->diffInDays($tglKirim, false) <= 1 && $today->diffInDays($tglKirim, false) >= 0) {
                $judul = "Jadwal Pengiriman Barang: Paket {$spk->nomor_paket}";
                $pesan = "Batas akhir pengiriman barang untuk Kontrak SPK {$spk->nomor_spk} dijadwalkan pada {$spk->jadwal_pengiriman->format('d/m/Y')}. Unit Perlengkapan dan PPBJ harap bersiap menerima fisik barang.";

                // Buat atau temukan reminder pengiriman
                $reminder = Reminder::firstOrCreate(
                    [
                        'nomor_paket' => $spk->nomor_paket,
                        'tipe_reminder' => 'pengiriman',
                    ],
                    [
                        'waktu_trigger' => now(),
                        'is_recurring' => false,
                        'interval_bulan' => null,
                        'judul_alert' => $judul,
                        'pesan_alert' => $pesan,
                    ]
                );
                $totalReminders++;

                // Sasaran penerima notifikasi pengiriman: PPBJ, PP, PPK penanggung jawab, dan Unit Perlengkapan
                $targetRoles = ['ppbj', 'pp', 'perlengkapan'];
                $users = User::whereIn('role', $targetRoles)->get();

                // Tambahkan PPK spesifik yang menangani paket
                if ($spk->paket && $spk->paket->id_user_ppk) {
                    $ppkUser = User::find($spk->paket->id_user_ppk);
                    if ($ppkUser && ! $users->contains('id_user', $ppkUser->id_user)) {
                        $users->push($ppkUser);
                    }
                }

                // Sebarkan notifikasi ke feed masing-masing pengguna
                foreach ($users as $u) {
                    $notif = NotifikasiUser::firstOrCreate(
                        [
                            'id_user' => $u->id_user,
                            'id_reminder' => $reminder->id_reminder,
                        ],
                        [
                            'is_read' => false,
                            'is_dismissed' => false,
                        ]
                    );
                    if ($notif->wasRecentlyCreated) {
                        $totalNotifikasi++;
                    }
                }
            }
        }

        // =========================================================================
        // 2. PENGINGAT H-2 JATUH TEMPO PEMBAYARAN TERMIN (FR 12.2)
        // =========================================================================
        // Mencari tagihan pembayaran yang belum lunas dan jatuh temponya berada dalam rentang H-2
        $pembayaranPending = Pembayaran::with('paket')
            ->whereNotIn('status_pembayaran', ['lunas', 'ditolak'])
            ->whereNotNull('jatuh_tempo')
            ->get();

        foreach ($pembayaranPending as $bayar) {
            $tglJatuhTempo = Carbon::parse($bayar->jatuh_tempo)->startOfDay();
            $selisihHari = $today->diffInDays($tglJatuhTempo, false);

            // Memicu alarm jika tersisa 2 hari atau kurang menjelang jatuh tempo termin
            if ($selisihHari <= 2 && $selisihHari >= 0) {
                $judul = "Peringatan H-2 Pembayaran Termin: {$bayar->nomor_pembayaran}";
                $pesan = "Pembayaran termin ke-{$bayar->tahap_termin} untuk paket {$bayar->nomor_paket} senilai Rp ".number_format($bayar->nominal_pengajuan)." jatuh tempo pada {$tglJatuhTempo->format('d/m/Y')}. Segera proses memo dan SP2D.";

                $reminder = Reminder::firstOrCreate(
                    [
                        'nomor_paket' => $bayar->nomor_paket,
                        'tipe_reminder' => 'h_minus_2_termin',
                    ],
                    [
                        'waktu_trigger' => now(),
                        'is_recurring' => false,
                        'interval_bulan' => null,
                        'judul_alert' => $judul,
                        'pesan_alert' => $pesan,
                    ]
                );
                $totalReminders++;

                // Sasaran penerima notifikasi pembayaran termin: PPBJ, PPK, dan Bagian Keuangan
                $targetUsers = User::whereIn('role', ['ppbj', 'keuangan'])->get();
                if ($bayar->paket && $bayar->paket->id_user_ppk) {
                    $ppkUser = User::find($bayar->paket->id_user_ppk);
                    if ($ppkUser && ! $targetUsers->contains('id_user', $ppkUser->id_user)) {
                        $targetUsers->push($ppkUser);
                    }
                }

                foreach ($targetUsers as $u) {
                    $notif = NotifikasiUser::firstOrCreate(
                        [
                            'id_user' => $u->id_user,
                            'id_reminder' => $reminder->id_reminder,
                        ],
                        [
                            'is_read' => false,
                            'is_dismissed' => false,
                        ]
                    );
                    if ($notif->wasRecentlyCreated) {
                        $totalNotifikasi++;
                    }
                }
            }
        }

        // =========================================================================
        // 3. PENGINGAT BERKALA BULANAN (MONTHLY RECURRING REMINDER - FR 12.3)
        // =========================================================================
        // Menelusuri pengingat bertipe bulanan/berulang
        $recurringReminders = Reminder::where('is_recurring', true)->get();
        foreach ($recurringReminders as $r) {
            $waktuTrigger = Carbon::parse($r->waktu_trigger);
            // Jika sudah lewat sebulan sejak waktu pemicu terakhir, perbarui waktu trigger ke bulan berikutnya
            if ($today->greaterThanOrEqualTo($waktuTrigger)) {
                $r->waktu_trigger = $waktuTrigger->addMonths($r->interval_bulan ?: 1);
                $r->save();

                // Aktifkan kembali notifikasi untuk seluruh staf pengadaan
                $staf = User::whereIn('role', ['ppbj', 'ppk', 'keuangan'])->get();
                foreach ($staf as $s) {
                    NotifikasiUser::updateOrCreate(
                        [
                            'id_user' => $s->id_user,
                            'id_reminder' => $r->id_reminder,
                        ],
                        [
                            'is_read' => false,
                            'is_dismissed' => false,
                        ]
                    );
                    $totalNotifikasi++;
                }
            }
        }

        return [
            'status' => 'success',
            'reminders_processed' => $totalReminders,
            'notifications_dispatched' => $totalNotifikasi,
            'waktu_sinkronisasi' => now()->toIso8601String(),
        ];
    }

    /**
     * Mengambil daftar notifikasi dan pengingat aktif yang belum di-dismiss untuk pengguna tertentu.
     *
     * @param  User  $user  Pengguna yang sedang login
     * @return Collection
     */
    public function getActiveRemindersForUser(User $user)
    {
        return NotifikasiUser::with(['reminder.paket'])
            ->where('id_user', $user->id_user)
            ->where('is_dismissed', false)
            ->latest('id')
            ->get();
    }
}
