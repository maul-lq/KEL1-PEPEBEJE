<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis service PengadaanRoutingService ini untuk menetapkan jalur pengadaan otomatis berbasis nilai HPS/pagu dan menyinkronkan jadwal reminder kontrak maupun pembayaran secara idempoten.
 */

namespace App\Services;

use App\Models\KontrakSPK;
use App\Models\NotifikasiUser;
use App\Models\PaketPengadaan;
use App\Models\Pembayaran;
use App\Models\Reminder;
use Carbon\Carbon;

/**
 * Service Penentuan Jalur & Penjadwalan Reminder (PengadaanRoutingService)
 *
 * memusatkan logika auto-routing batas nominal (<=50jt, >50-200jt, >200jt) serta pembentukan
 * jadwal pengingat H-2/Hari-H dan kontrak berkala di service ini agar controller dan model hook tidak menduplikasi jadwal.
 *
 * Parameter konstruktor: Tidak ada (seluruh utilitas bersifat statis).
 * Nilai kembali         : Kumpulan metode statis mutasi routing dan sinkronisasi tabel reminder.
 *
 * Contoh pemanggilan:
 * `PengadaanRoutingService::assignJalur($paket);`
 */
class PengadaanRoutingService
{
    /**
     * menentukan jalur pengadaan paket otomatis berdasarkan batas nilai HPS atau alokasi pagu paket.
     *
     * @param  PaketPengadaan  $paket  membaca nilai HPS/alokasi paket lalu mengisi `jalur_routing` dan membersihkan `nomor_memo_ppbj` jika jalur PPK.
     * @return void Tidak mengembalikan nilai (memutasi atribut model $paket secara langsung).
     *
     * Contoh pemanggilan:
     * `PengadaanRoutingService::assignJalur($paket);`
     */
    public static function assignJalur(PaketPengadaan $paket): void
    {
        // memprioritaskan nilai_hps terbaru lalu jatuh ke alokasi_pagu_paket agar routing tetap konsisten bila salah satu bernilai 0.
        $nominal = (float) ($paket->nilai_hps ?: $paket->alokasi_pagu_paket);
        $paket->jalur_routing = PaketPengadaan::tentukanRouting($nominal);

        // mengosongkan nomor memo PPBJ pada jalur >200jt karena proses penawaran/lelang dikawal langsung oleh PPK tanpa memo pengadaan PPBJ.
        if ($paket->jalur_routing === PaketPengadaan::ROUTING_PPK) {
            $paket->nomor_memo_ppbj = null;
        }
    }

    /**
     * menerbitkan notifikasi selesai Belanja Langsung (<=50jt) dan membuat jadwal pengingat pengiriman jika tanggal target tersedia.
     *
     * @param  PaketPengadaan  $paket  memakai nomor paket, nama paket, dan nomor kwitansi untuk membentuk alert pengingat.
     * @param  string|null  $jadwalPengiriman  mengonversi tanggal target kedatangan barang menjadi jadwal Hari-H dan H-2 (opsional).
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `PengadaanRoutingService::generateBelanjaLangsungReminder($paket, '2026-11-30');`
     */
    public static function generateBelanjaLangsungReminder(PaketPengadaan $paket, ?string $jadwalPengiriman = null): void
    {
        $nominal = (float) ($paket->nominal_harga_akhir ?: $paket->nilai_hps ?: $paket->alokasi_pagu_paket);

        // memakai firstOrCreate pada master reminder agar pemanggilan ulang tidak membanjiri pengguna dengan alert kwitansi ganda.
        $reminderBelanja = Reminder::firstOrCreate(
            [
                'nomor_paket' => $paket->nomor_paket,
                'tipe_reminder' => 'belanja_langsung_kwitansi',
                'parent_reminder_id' => null,
            ],
            [
                'waktu_trigger' => now(),
                'interval_bulan' => null,
                'is_recurring' => false,
                'is_active' => false,
                'last_triggered_at' => now(),
                'target_permissions' => ['penerimaan.bast_create', 'pembayaran.pencairan_sp2d'],
                'judul_alert' => 'Belanja Langsung Selesai: '.$paket->nama_paket,
                'pesan_alert' => 'Belanja Langsung (Kwitansi '.($paket->nomor_kwitansi_belanja ?? '-').') senilai Rp '.number_format($nominal, 0, ',', '.').' telah dicatat tanpa kontrak. Menunggu pemeriksaan barang Perlengkapan.',
            ]
        );

        $users = PengadaanNotificationService::resolveActiveUsersByLogicalRoles(['perlengkapan', 'keuangan']);

        foreach ($users as $targetUser) {
            NotifikasiUser::firstOrCreate(
                [
                    'id_user' => $targetUser->id_user,
                    'id_reminder' => $reminderBelanja->id_reminder,
                ],
                [
                    'is_read' => false,
                    'is_dismissed' => false,
                ]
            );
        }

        if ($jadwalPengiriman) {
            $jadwalCarbon = Carbon::parse($jadwalPengiriman);

            self::upsertSchedule(
                [
                    'nomor_paket' => $paket->nomor_paket,
                    'tipe_reminder' => Reminder::TIPE_PENGIRIMAN,
                    'parent_reminder_id' => null,
                ],
                [
                    'waktu_trigger' => $jadwalCarbon->copy()->startOfDay(),
                    'interval_bulan' => null,
                    'is_recurring' => false,
                    'is_active' => true,
                    'target_permissions' => ['penerimaan.bast_create', 'spk.create', 'reviu.ppk'],
                    'judul_alert' => 'Jadwal Pengiriman Belanja Langsung: '.$paket->nama_paket,
                    'pesan_alert' => 'Target kedatangan barang Belanja Langsung pada '.$jadwalCarbon->format('d M Y').'.',
                ]
            );

            // menambahkan jadwal H-2 terpisah agar unit Perlengkapan dan pengawal paket punya jeda persiapan gudang sebelum barang tiba.
            self::upsertSchedule(
                [
                    'nomor_paket' => $paket->nomor_paket,
                    'tipe_reminder' => Reminder::TIPE_H_MINUS_2_TERMIN,
                    'parent_reminder_id' => null,
                ],
                [
                    'waktu_trigger' => $jadwalCarbon->copy()->subDays(2)->startOfDay(),
                    'interval_bulan' => null,
                    'is_recurring' => false,
                    'is_active' => true,
                    'target_permissions' => ['penerimaan.bast_create', 'spk.create', 'reviu.ppk'],
                    'judul_alert' => 'H-2 Pengiriman Belanja Langsung: '.$paket->nama_paket,
                    'pesan_alert' => 'Memasuki H-2 target kedatangan barang Belanja Langsung ('.$jadwalCarbon->format('d M Y').').',
                ]
            );
        }
    }

    /**
     * menyinkronkan jadwal reminder pengiriman (sekali jalan maupun berkala bulanan/tahunan) serta H-2 dari dokumen Kontrak/SPK.
     *
     * @param  KontrakSPK  $spk  membaca status, sifat kontrak, dan tanggal target pengiriman dari entitas kontrak ini.
     * @param  PaketPengadaan|null  $paket  mengizinkan passing paket secara eksplisit untuk menghindari query relasi tambahan bila sudah dimuat.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `PengadaanRoutingService::syncKontrakSchedules($kontrakSpk);`
     */
    public static function syncKontrakSchedules(KontrakSPK $spk, ?PaketPengadaan $paket = null): void
    {
        $paket = $paket ?? $spk->paket;
        // melewati pembuatan jadwal jika paket sudah final/batal atau SPK masih draft agar pengingat tidak memicu alarm palsu.
        if (! $paket || in_array($paket->status_paket, ['selesai', 'batal'], true)) {
            return;
        }

        if (! in_array($spk->status_spk, ['terbit', 'aktif'], true)) {
            return;
        }

        $jadwal = $spk->jadwal_pengiriman ?? $spk->tanggal_selesai;
        if (! $jadwal) {
            return;
        }

        $jadwalCarbon = Carbon::parse($jadwal);
        // memetakan sifat kontrak ke interval bulan agar kontrak rutin otomatis memperpanjang jadwal pengingatnya setelah jatuh tempo.
        $intervalBulan = match ($spk->sifat_kontrak) {
            'termin_bulanan' => 1,
            'kontrak_tahunan' => 12,
            default => null,
        };
        $isRecurring = $intervalBulan !== null;
        $labelSifat = match ($spk->sifat_kontrak) {
            'termin_bulanan' => ' (Kontrak Termin Bulanan)',
            'kontrak_tahunan' => ' (Kontrak Tahunan)',
            default => '',
        };

        self::upsertSchedule(
            [
                'nomor_paket' => $paket->nomor_paket,
                'tipe_reminder' => Reminder::TIPE_PENGIRIMAN,
                'parent_reminder_id' => null,
            ],
            [
                'waktu_trigger' => $jadwalCarbon->copy()->startOfDay(),
                'interval_bulan' => $intervalBulan,
                'is_recurring' => $isRecurring,
                'is_active' => true,
                'target_permissions' => ['penerimaan.bast_create', 'spk.create', 'reviu.ppk'],
                'judul_alert' => ($isRecurring ? 'Pengingat Berkala Kontrak: ' : 'Jadwal Pengiriman Barang: ').$paket->nama_paket,
                'pesan_alert' => "Jadwal pengiriman / termin{$labelSifat} kontrak {$spk->nomor_spk} pada tanggal ".$jadwalCarbon->format('d M Y').'.',
            ]
        );

        self::upsertSchedule(
            [
                'nomor_paket' => $paket->nomor_paket,
                'tipe_reminder' => Reminder::TIPE_H_MINUS_2_TERMIN,
                'parent_reminder_id' => null,
            ],
            [
                'waktu_trigger' => $jadwalCarbon->copy()->subDays(2)->startOfDay(),
                'interval_bulan' => null,
                'is_recurring' => false,
                'is_active' => true,
                'target_permissions' => ['penerimaan.bast_create', 'spk.create', 'reviu.ppk'],
                'judul_alert' => 'H-2 Pengiriman Barang: '.$paket->nama_paket,
                'pesan_alert' => 'Pengadaan memasuki H-2 batas waktu pengiriman barang sesuai kontrak '.$spk->nomor_spk.' ('.$jadwalCarbon->format('d M Y').'). Mohon koordinasi dengan vendor.',
            ]
        );
    }

    /**
     * menyinkronkan jadwal pengingat H-2 dan Hari-H jatuh tempo pembayaran tagihan, atau menonaktifkannya jika tagihan sudah lunas/ditolak.
     *
     * @param  Pembayaran  $pembayaran  memeriksa status pembayaran dan tanggal `jatuh_tempo` untuk membuat atau mematikan jadwal terkait.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `PengadaanRoutingService::syncPembayaranSchedules($pembayaran);`
     */
    public static function syncPembayaranSchedules(Pembayaran $pembayaran): void
    {
        if (in_array($pembayaran->status_pembayaran, [Pembayaran::STATUS_LUNAS, Pembayaran::STATUS_DITOLAK], true)) {
            self::deactivateForPembayaran($pembayaran->nomor_pembayaran);

            return;
        }

        if (empty($pembayaran->jatuh_tempo)) {
            return;
        }

        $jatuhTempo = Carbon::parse($pembayaran->jatuh_tempo);
        $namaPaket = $pembayaran->paket?->nama_paket ?? $pembayaran->nomor_paket;
        $nominal = number_format((float) ($pembayaran->nominal_pengajuan ?? 0), 0, ',', '.');
        $targetPermissions = ['pembayaran.pencairan_sp2d', 'pembayaran.spp_terbitkan', 'pembayaran.pengajuan'];

        self::upsertSchedule(
            [
                'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
                'tipe_reminder' => Reminder::TIPE_JATUH_TEMPO_PEMBAYARAN_H2,
                'parent_reminder_id' => null,
            ],
            [
                'nomor_paket' => $pembayaran->nomor_paket,
                'waktu_trigger' => $jatuhTempo->copy()->subDays(2)->startOfDay(),
                'interval_bulan' => null,
                'is_recurring' => false,
                'is_active' => true,
                'target_permissions' => $targetPermissions,
                'judul_alert' => "H-2 Jatuh Tempo Tagihan: {$pembayaran->nomor_pembayaran}",
                'pesan_alert' => "Tagihan pembayaran termin {$pembayaran->tahap_termin} paket '{$namaPaket}' senilai Rp {$nominal} memasuki H-2 jatuh tempo ({$jatuhTempo->format('d M Y')}).",
            ]
        );

        self::upsertSchedule(
            [
                'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
                'tipe_reminder' => Reminder::TIPE_JATUH_TEMPO_PEMBAYARAN,
                'parent_reminder_id' => null,
            ],
            [
                'nomor_paket' => $pembayaran->nomor_paket,
                'waktu_trigger' => $jatuhTempo->copy()->startOfDay(),
                'interval_bulan' => null,
                'is_recurring' => false,
                'is_active' => true,
                'target_permissions' => $targetPermissions,
                'judul_alert' => "Jatuh Tempo Pembayaran Hari Ini: {$pembayaran->nomor_pembayaran}",
                'pesan_alert' => "Tagihan pembayaran '{$namaPaket}' nomor {$pembayaran->nomor_pembayaran} senilai Rp {$nominal} jatuh tempo pada {$jatuhTempo->format('d M Y')}. Segera proses penyelesaian SP2D.",
            ]
        );
    }

    /**
     * menonaktifkan jadwal pengingat aktif milik paket tertentu ketika BAST diterima atau paket resmi ditutup.
     *
     * @param  string  $nomorPaket  mencari jadwal induk aktif berdasarkan kode/nomor paket ini.
     * @param  bool  $onlyNonRecurringDelivery  memakai flag ini agar BAST pertama hanya mematikan pengingat pengiriman sekali jalan tanpa memutus kontrak berkala.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `PengadaanRoutingService::deactivateForPaket($paket->nomor_paket, true);`
     */
    public static function deactivateForPaket(string $nomorPaket, bool $onlyNonRecurringDelivery = false): void
    {
        $query = Reminder::query()
            ->where('nomor_paket', $nomorPaket)
            ->whereNull('parent_reminder_id')
            ->where('is_active', true);

        // menjaga jadwal berkala (termin_bulanan / kontrak_tahunan) tetap hidup setelah BAST pertama agar siklus bulan berikutnya tetap teringat.
        if ($onlyNonRecurringDelivery) {
            $query->where('is_recurring', false)
                ->whereIn('tipe_reminder', [Reminder::TIPE_PENGIRIMAN, Reminder::TIPE_H_MINUS_2_TERMIN]);
        }

        $query->update(['is_active' => false]);
    }

    /**
     * mematikan seluruh jadwal jatuh tempo aktif milik nomor pembayaran tertentu saat lunas atau ditolak.
     *
     * @param  string  $nomorPembayaran  menargetkan jadwal induk terkait tagihan pembayaran ini.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `PengadaanRoutingService::deactivateForPembayaran($pembayaran->nomor_pembayaran);`
     */
    public static function deactivateForPembayaran(string $nomorPembayaran): void
    {
        Reminder::query()
            ->where('nomor_pembayaran', $nomorPembayaran)
            ->whereNull('parent_reminder_id')
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    /**
     * menyediakan entry point kompatibilitas untuk membangkitkan pengingat jadwal SPK paket secara idempoten.
     *
     * @param  PaketPengadaan  $paket  memakai paket sebagai konteks jika SPK belum dilewatkan langsung.
     * @param  KontrakSPK|null  $spk  memprioritaskan objek kontrak SPK yang diberikan atau mengambil relasi kontrakSpk paket.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `PengadaanRoutingService::generateReminders($paket, $kontrak);`
     */
    public static function generateReminders(PaketPengadaan $paket, ?KontrakSPK $spk = null): void
    {
        $spk = $spk ?? $paket->kontrakSpk;
        if ($spk) {
            self::syncKontrakSchedules($spk, $paket);
        }
    }

    /**
     * membuat atau memperbarui jadwal pengingat induk tanpa menimpa tanggal trigger jadwal berkala yang sudah pernah berjalan.
     *
     * @param  array<string, mixed>  $attributes  menggunakan kombinasi atribut ini sebagai kunci keunikan jadwal induk.
     * @param  array<string, mixed>  $values  mengisi waktu trigger, flag berkala, permission sasaran, serta pesan alert.
     * @return Reminder mengembalikan instance master jadwal Reminder yang dibuat atau diperbarui.
     *
     * Contoh pemanggilan:
     * `self::upsertSchedule(['nomor_paket' => $no, 'tipe_reminder' => 'pengiriman', 'parent_reminder_id' => null], $values);`
     */
    protected static function upsertSchedule(array $attributes, array $values): Reminder
    {
        $existing = Reminder::query()->where($attributes)->first();

        if (! $existing) {
            return Reminder::create(array_merge($attributes, $values));
        }

        $existing->interval_bulan = $values['interval_bulan'] ?? $existing->interval_bulan;
        $existing->is_recurring = $values['is_recurring'] ?? $existing->is_recurring;
        $existing->target_permissions = $values['target_permissions'] ?? $existing->target_permissions;

        // hanya mengizinkan pembaruan waktu_trigger apabila jadwal belum pernah terpicu agar siklus berkala yang sedang berjalan tidak mundur kembali ke tanggal awal kontrak.
        if ($existing->last_triggered_at === null) {
            $existing->waktu_trigger = $values['waktu_trigger'];
            $existing->judul_alert = $values['judul_alert'];
            $existing->pesan_alert = $values['pesan_alert'];
            if (array_key_exists('nomor_paket', $values)) {
                $existing->nomor_paket = $values['nomor_paket'];
            }
        }

        $existing->save();

        return $existing;
    }
}
