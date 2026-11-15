<?php

namespace App\Services;

use App\Models\AnggaranMAK;
use App\Models\KontrakSPK;
use App\Models\MemoBayar;
use App\Models\NotifikasiUser;
use App\Models\PaketPengadaan;
use App\Models\Pembayaran;
use App\Models\PenerimaanBarang;
use App\Models\PermohonanPengadaan;
use App\Models\Reminder;
use App\Models\Role;
use App\Models\TransaksiPencairan;
use App\Models\User;
use App\Models\VerifikasiParalel;
use Illuminate\Support\Collection;

/**
 * Layanan Notifikasi Siklus Pengadaan (PengadaanNotificationService)
 *
 * Mengorkestrasi pembuatan pengingat (Reminder) dan distribusi notifikasi personal (NotifikasiUser)
 * untuk seluruh titik transisi siklus pengadaan barang dan jasa SIM-PBJ Politeknik Negeri Jakarta.
 * Setiap rekaman NotifikasiUser yang dibuat akan otomatis memicu pengiriman email resmi
 * via NotifikasiUserObserver, serta dapat dikonsumsi oleh API polling Web Notification OS Desktop.
 */
class PengadaanNotificationService
{
    /**
     * Pemetaan peran logis ke daftar permission RBAC untuk menentukan penerima notifikasi secara dinamis.
     *
     * @var array<string, array<int, string>>
     */
    public const ROLE_PERMISSION_MAP = [
        'admin' => ['admin.approval_registrasi'],
        'user' => ['permohonan.create'],
        'staff_bidang_2' => ['verifikasi.bidang_2_view'],
        'wadir_2' => ['verifikasi.wadir_2_decide', 'pembayaran.memo_wadir2'],
        'perencanaan' => ['anggaran.mak_alokasi', 'anggaran.mak_lock', 'anggaran.mak_manage'],
        'ppbj' => ['paket.create', 'spk.create', 'pembayaran.pengajuan', 'pembayaran.spp_terbitkan'],
        'pp' => ['reviu.pp'],
        'ppk' => ['reviu.ppk', 'pembayaran.memo_ppk'],
        'perlengkapan' => ['penerimaan.bast_create', 'penerimaan.bast_sign'],
        'keuangan' => ['pembayaran.pencairan_sp2d'],
    ];

    /**
     * Mengambil pengguna aktif berdasarkan hak akses (RBAC permission) dari satu atau beberapa peran logis,
     * atau relasi nama peran di tabel `roles` apabila izin spesifik belum dipetakan.
     *
     * @param  string|array<int, string>  $roles
     * @return Collection<int, User>
     */
    public static function resolveActiveUsersByLogicalRoles(string|array $roles): Collection
    {
        $rolesList = array_values(array_unique((array) $roles));
        if ($rolesList === []) {
            return collect();
        }

        $permissions = [];
        foreach ($rolesList as $roleKey) {
            foreach (self::ROLE_PERMISSION_MAP[$roleKey] ?? [] as $perm) {
                $permissions[] = $perm;
            }
        }
        $permissions = array_values(array_unique($permissions));

        return User::query()
            ->where('status_aktif', true)
            ->whereHas('roleEntity', function ($roleQuery) use ($permissions, $rolesList): void {
                $roleQuery->whereIn('nama_role', $rolesList);

                if ($permissions !== []) {
                    $roleQuery->orWhereHas('permissions', function ($pq) use ($permissions): void {
                        $pq->whereIn('nama_permission', $permissions);
                    });
                }
            })
            ->get();
    }

    /**
     * Mengirimkan notifikasi kepada satu pengguna tertentu.
     *
     * @param  User|int  $user  Model User penerima atau ID pengguna
     * @param  string  $judul  Judul notifikasi / peringatan
     * @param  string  $pesan  Isi pesan lengkap
     * @param  string  $tipe  Kategori reminder (pengingat, verifikasi, pembayaran, pengiriman, spk)
     * @param  string|null  $nomorPaket  Kode/nomor paket atau nomor surat referensi
     * @return NotifikasiUser Rekaman notifikasi yang berhasil dibuat
     */
    public function kirimKeUser(User|int $user, string $judul, string $pesan, string $tipe = 'pengingat', ?string $nomorPaket = null): NotifikasiUser
    {
        $userId = $user instanceof User ? $user->id_user : $user;

        // Validasi keberadaan nomor paket agar mematuhi integritas foreign key basis data
        $validNomorPaket = null;
        if (! empty($nomorPaket) && PaketPengadaan::where('nomor_paket', $nomorPaket)->exists()) {
            $validNomorPaket = $nomorPaket;
        }

        // 1. Buat master reminder instan (sudah terpicu, bukan jadwal pending)
        $reminder = Reminder::create([
            'nomor_paket' => $validNomorPaket,
            'tipe_reminder' => $tipe,
            'waktu_trigger' => now(),
            'is_recurring' => false,
            'is_active' => false,
            'last_triggered_at' => now(),
            'judul_alert' => $judul,
            'pesan_alert' => $pesan,
        ]);

        // 2. Buat notifikasi personal penerima (Otomatis memicu NotifikasiUserObserver::created -> email)
        return NotifikasiUser::create([
            'id_user' => $userId,
            'id_reminder' => $reminder->id_reminder,
            'is_read' => false,
            'is_dismissed' => false,
        ]);
    }

    /**
     * Mengirimkan notifikasi ke seluruh pengguna aktif yang memiliki izin sesuai sasaran.
     *
     * @param  string|array<string>  $roles  Satu atau beberapa nama role sasaran
     * @param  string  $judul  Judul notifikasi
     * @param  string  $pesan  Isi pesan lengkap
     * @param  string  $tipe  Kategori reminder
     * @param  string|null  $nomorPaket  Nomor paket atau nomor surat referensi
     * @return Collection<int, NotifikasiUser> Koleksi notifikasi yang diterbitkan
     */
    public function kirimKePeran(string|array $roles, string $judul, string $pesan, string $tipe = 'pengingat', ?string $nomorPaket = null): Collection
    {
        $rolesList = is_array($roles) ? $roles : [$roles];
        $targetUsers = self::resolveActiveUsersByLogicalRoles($rolesList);

        if ($targetUsers->isEmpty()) {
            return collect();
        }

        // Validasi keberadaan nomor paket agar mematuhi integritas foreign key basis data
        $validNomorPaket = null;
        if (! empty($nomorPaket) && PaketPengadaan::where('nomor_paket', $nomorPaket)->exists()) {
            $validNomorPaket = $nomorPaket;
        }

        $targetPermissions = [];
        foreach ($rolesList as $r) {
            foreach (self::ROLE_PERMISSION_MAP[$r] ?? [] as $perm) {
                $targetPermissions[] = $perm;
            }
        }

        // Buat 1 master reminder instan (sudah terpicu)
        $reminder = Reminder::create([
            'nomor_paket' => $validNomorPaket,
            'tipe_reminder' => $tipe,
            'waktu_trigger' => now(),
            'is_recurring' => false,
            'is_active' => false,
            'last_triggered_at' => now(),
            'target_permissions' => array_values(array_unique($targetPermissions)),
            'judul_alert' => $judul,
            'pesan_alert' => $pesan,
        ]);

        $createdNotifs = collect();

        foreach ($targetUsers as $u) {
            $createdNotifs->push(NotifikasiUser::create([
                'id_user' => $u->id_user,
                'id_reminder' => $reminder->id_reminder,
                'is_read' => false,
                'is_dismissed' => false,
            ]));
        }

        return $createdNotifs;
    }

    // =========================================================================
    // LIFECYCLE EVENT NOTIFICATIONS
    // =========================================================================

    /**
     * Event 1: Pengajuan Usulan Permohonan Pengadaan Baru (Alur Berantai).
     * Surat dari User masuk terlebih dahulu ke Bidang 2 (Staf Bidang 2 & Wadir 2).
     */
    public function notifyPengajuanBaru(PermohonanPengadaan $permohonan): void
    {
        $judul = "Usulan Pengadaan Baru ke Bidang 2: {$permohonan->nomor_surat}";
        $estimasi = number_format($permohonan->estimasi_total_pagu ?? $permohonan->anggaran_diajukan ?? 0, 0, ',', '.');
        $unitName = $permohonan->unit->nama_unit ?? $permohonan->kode_unit;

        $pesan = "Unit {$unitName} mengajukan permohonan baru ke Bidang 2: '{$permohonan->perihal}' (Estimasi Rp {$estimasi}). Menunggu pemeriksaan berkas & pembuatan Memo Cek Anggaran oleh Staf Bidang 2 ke Perencanaan.";

        $this->kirimKePeran(['staff_bidang_2', 'wadir_2'], $judul, $pesan, 'verifikasi', $permohonan->nomor_surat);
    }

    /**
     * Event 2: Pengajuan Revisi Usulan Permohonan Pengadaan.
     * Surat revisi masuk kembali ke antrean Bidang 2.
     */
    public function notifyPengajuanRevisi(PermohonanPengadaan $permohonan): void
    {
        $judul = "Revisi Usulan Pengadaan: {$permohonan->nomor_surat}";
        $unitName = $permohonan->unit->nama_unit ?? $permohonan->kode_unit;

        $pesan = "Unit {$unitName} memperbarui dokumen usulan pengadaan ke Bidang 2: '{$permohonan->perihal}' (Revisi ke-{$permohonan->revisi_ke}). Silakan periksa dan kirim Memo Cek Anggaran ke Perencanaan.";

        $this->kirimKePeran(['staff_bidang_2', 'wadir_2'], $judul, $pesan, 'verifikasi', $permohonan->nomor_surat);
    }

    /**
     * Notifikasi saat usulan yang sudah memiliki MAK direvisi oleh pengaju dan dikirim kembali ke Bagian Perencanaan.
     */
    public function notifyRevisiKePerencanaan(PermohonanPengadaan $permohonan): void
    {
        $judul = "Revisi Usulan Pengadaan (Pasca-Anggaran): {$permohonan->nomor_surat}";
        $unitName = $permohonan->unit->nama_unit ?? $permohonan->kode_unit;

        $pesan = "Unit {$unitName} telah memperbarui dokumen usulan pengadaan '{$permohonan->perihal}' (Revisi ke-{$permohonan->revisi_ke}). Silakan periksa ketersediaan pos anggaran dan teruskan ke pemeriksa terkait.";

        $this->kirimKePeran('perencanaan', $judul, $pesan, 'verifikasi', $permohonan->nomor_surat);
    }

    /**
     * Event 3: Staf Bidang 2 Mengirimkan Memo Cek MAK ke Bagian Perencanaan.
     * Notifikasi dikirimkan ke: Perencanaan.
     */
    public function notifyMemoCekMak(PermohonanPengadaan $permohonan, ?string $nomorMemo = null, ?string $catatan = null): void
    {
        $memoRef = $nomorMemo ?: ($permohonan->nomor_memo_cek_mak ?: '-');
        $judul = "Memo Cek Anggaran dari Bidang 2: {$permohonan->nomor_surat}";
        $pesan = "Bidang 2 mengirimkan Memo Cek Anggaran ({$memoRef}) untuk usulan '{$permohonan->perihal}'. Catatan: ".($catatan ?: 'Mohon periksa/siapkan pos anggaran dan pilih PPK').'.';

        $this->kirimKePeran('perencanaan', $judul, $pesan, 'verifikasi', $permohonan->nomor_surat);
    }

    /**
     * Alias kompatibilitas penerusan berkas Staf Bidang 2 -> Memo Cek MAK ke Perencanaan.
     */
    public function notifyDiteruskanStaffB2(PermohonanPengadaan $permohonan, ?string $catatan = null): void
    {
        $this->notifyMemoCekMak($permohonan, $permohonan->nomor_memo_cek_mak, $catatan);
    }

    /**
     * Event 4: Keputusan Wadir 2 (ACC Kegiatan / Tolak).
     * Jika Ditolak: Notifikasi ke Pengusul (User) dengan alasan.
     * Jika Disetujui Penuh (PPK terpilih juga sudah ACC): Notifikasi ke Pengusul dan Seluruh PPBJ.
     * Jika Disetujui tapi PPK terpilih belum ACC: Notifikasi ke PPK terpilih.
     */
    public function notifyKeputusanWadir2(PermohonanPengadaan $permohonan, string $keputusan, ?string $catatan = null): void
    {
        if (in_array($keputusan, ['ditolak', 'revisi'])) {
            if ($permohonan->id_user) {
                $statusText = $keputusan === 'revisi' ? 'Diminta Revisi' : 'Ditolak';
                $judul = "Usulan Pengadaan {$statusText} Wadir 2: {$permohonan->nomor_surat}";
                $pesan = "Usulan permohonan '{$permohonan->perihal}' {$statusText} oleh Wakil Direktur II. Alasan: ".($catatan ?: 'Perlu perbaikan usulan').'. Silakan periksa dan ajukan revisi.';

                $this->kirimKeUser($permohonan->id_user, $judul, $pesan, 'verifikasi', $permohonan->nomor_surat);
            }

            return;
        }

        if ($permohonan->status_permohonan === PermohonanPengadaan::STATUS_DISETUJUI) {
            $this->notifyPersetujuanPenuh($permohonan, $permohonan->kode_mak);
        } elseif ($permohonan->id_user_ppk_awal) {
            $judul = "Persetujuan Wadir 2 Dicatat: {$permohonan->nomor_surat}";
            $pesan = "Wakil Direktur II telah menyetujui kegiatan '{$permohonan->perihal}'. Menunggu persetujuan usulan awal oleh PPK untuk diteruskan ke Unit Pengadaan.";

            $this->kirimKeUser($permohonan->id_user_ppk_awal, $judul, $pesan, 'verifikasi', $permohonan->nomor_surat);
        } else {
            $judul = "Persetujuan Wadir 2 Dicatat: {$permohonan->nomor_surat}";
            $pesan = "Wakil Direktur II telah menyetujui usulan '{$permohonan->perihal}'. Menunggu penyelesaian verifikasi anggaran oleh Bagian Perencanaan.";

            $this->kirimKePeran('perencanaan', $judul, $pesan, 'verifikasi', $permohonan->nomor_surat);
        }
    }

    /**
     * Event 4B: Keputusan Persetujuan Awal oleh PPK Terpilih (id_user_ppk_awal).
     */
    public function notifyKeputusanPpkAwal(PermohonanPengadaan $permohonan, string $keputusan, ?string $catatan = null): void
    {
        if ($keputusan === 'ditolak') {
            if ($permohonan->id_user) {
                $judul = "Usulan Pengadaan Ditolak PPK: {$permohonan->nomor_surat}";
                $pesan = "Usulan permohonan '{$permohonan->perihal}' ditolak oleh Pejabat Pembuat Komitmen (PPK). Alasan: ".($catatan ?: 'Perlu perbaikan spesifikasi/Kerangka Acuan').'.';

                $this->kirimKeUser($permohonan->id_user, $judul, $pesan, 'verifikasi', $permohonan->nomor_surat);
            }

            return;
        }

        if ($permohonan->status_permohonan === PermohonanPengadaan::STATUS_DISETUJUI) {
            $this->notifyPersetujuanPenuh($permohonan, $permohonan->kode_mak);
        } else {
            $judul = "Persetujuan Awal PPK Dicatat: {$permohonan->nomor_surat}";
            $pesan = "PPK terpilih telah menyetujui usulan '{$permohonan->perihal}'. Menunggu persetujuan kegiatan oleh Wakil Direktur II.";

            $this->kirimKePeran('wadir_2', $judul, $pesan, 'verifikasi', $permohonan->nomor_surat);
        }
    }

    /**
     * Event 5: Memo Ketersediaan MAK oleh Bagian Perencanaan ke Bidang 2 (Wadir 2) dan PPK Terpilih.
     */
    public function notifyAlokasiMak(PermohonanPengadaan $permohonan, AnggaranMAK $mak, string $keputusan, ?string $catatan = null): void
    {
        if ($keputusan === 'tahan') {
            if ($permohonan->id_user) {
                $judul = "Anggaran Usulan Ditahan: {$permohonan->nomor_surat}";
                $pesan = "Bagian Perencanaan menandai usulan '{$permohonan->perihal}' sebagai menunggu anggaran. Catatan: ".($catatan ?: 'Anggaran belum tersedia').'.';

                $this->kirimKeUser($permohonan->id_user, $judul, $pesan, 'verifikasi', $permohonan->nomor_surat);
            }

            return;
        }

        if ($permohonan->status_permohonan === PermohonanPengadaan::STATUS_DISETUJUI) {
            $this->notifyPersetujuanPenuh($permohonan, $mak->kode_mak);

            return;
        }

        $memoRef = $permohonan->nomor_memo_ketersediaan_mak ?: '-';
        $judul = "Memo Ketersediaan Anggaran ({$mak->kode_mak}): {$permohonan->nomor_surat}";
        $pesan = "Tim Perencanaan memastikan ketersediaan Pos Anggaran {$mak->kode_mak} (Memo: {$memoRef}) untuk usulan '{$permohonan->perihal}'. Mohon persetujuan agar berkas diteruskan ke Unit Pengadaan.";

        // Cek status keputusan Wadir 2 & PPK di verifikasi_paralel
        $wadirAcc = VerifikasiParalel::where('nomor_surat', $permohonan->nomor_surat)
            ->where('role_verifikator', VerifikasiParalel::ROLE_WADIR_2)
            ->where('status_keputusan', 'disetujui')
            ->exists();

        $ppkAcc = VerifikasiParalel::where('nomor_surat', $permohonan->nomor_surat)
            ->where('role_verifikator', VerifikasiParalel::ROLE_PPK)
            ->where('status_keputusan', 'disetujui')
            ->exists();

        // Hanya kirim notifikasi ke verifikator yang belum menyetujui (status menunggu)
        if (! $wadirAcc) {
            $this->kirimKePeran('wadir_2', $judul, $pesan, 'verifikasi', $permohonan->nomor_surat);
        }

        if (! $ppkAcc && $permohonan->id_user_ppk_awal) {
            $this->kirimKeUser($permohonan->id_user_ppk_awal, $judul, $pesan, 'verifikasi', $permohonan->nomor_surat);
        }
    }

    /**
     * Helper: Notifikasi persetujuan penuh (Perencanaan MAK + Wadir 2 + PPK).
     * Dikirimkan ke: Pengaju (User) dan Seluruh Petugas PPBJ.
     */
    public function notifyPersetujuanPenuh(PermohonanPengadaan $permohonan, ?string $kodeMak = null): void
    {
        $makInfo = $kodeMak ? " pada Pos Anggaran {$kodeMak}" : '';

        // 1. Notifikasi ke Pengusul (User)
        if ($permohonan->id_user) {
            $judulUser = "Permohonan Pengadaan Disetujui: {$permohonan->nomor_surat}";
            $pesanUser = "Usulan pengadaan '{$permohonan->perihal}' telah disetujui lengkap oleh Bidang 2 (Wadir 2) dan PPK{$makInfo}. Dokumen otomatis diteruskan ke Unit Pengadaan.";

            $this->kirimKeUser($permohonan->id_user, $judulUser, $pesanUser, 'verifikasi', $permohonan->nomor_surat);
        }

        // 2. Notifikasi ke seluruh petugas PPBJ
        $judulPpbj = "Paket Siap Didaftarkan Unit Pengadaan: {$permohonan->nomor_surat}";
        $pesanPpbj = "Usulan '{$permohonan->perihal}' nomor {$permohonan->nomor_surat} telah disetujui penuh oleh Wadir 2 dan PPK. Silakan daftarkan paket dan tentukan metode pengadaan (Belanja Langsung / Non-Tender / Tender Terbuka).";

        $this->kirimKePeran('ppbj', $judulPpbj, $pesanPpbj, 'verifikasi', $permohonan->nomor_surat);
    }

    /**
     * Event 6: Penugasan / Registrasi Paket Pengadaan oleh PPBJ.
     * Notifikasi dikirimkan ke: Pejabat PPK dan/atau Pejabat Pengadaan (PP) yang ditugaskan.
     */
    public function notifyPendaftaranPaket(PaketPengadaan $paket): void
    {
        $judul = "Penugasan Paket Pengadaan Baru: {$paket->kode_paket}";
        $hps = number_format($paket->nilai_hps ?? 0, 0, ',', '.');
        $routing = strtoupper(str_replace('_', ' ', $paket->jalur_routing ?? ''));

        $pesan = "Anda ditugaskan mengawal paket '{$paket->nama_paket}' (Perkiraan Harga Rp {$hps}, Jalur: {$routing}). Harap lakukan penelaahan Kerangka Acuan dan persiapan pengadaan.";

        if ($paket->id_user_ppk) {
            $this->kirimKeUser($paket->id_user_ppk, $judul, $pesan, 'verifikasi', $paket->nomor_paket);
        }

        if ($paket->id_user_pp) {
            $this->kirimKeUser($paket->id_user_pp, $judul, $pesan, 'verifikasi', $paket->nomor_paket);
        }
    }

    /**
     * Event 7: Pengalihan Tugas (Reassign) PPK pada Paket.
     * Notifikasi dikirimkan ke: PPK Baru yang menerima pelimpahan.
     */
    public function notifyReassignPpk(PaketPengadaan $paket, User $ppkBaru, ?string $alasan = null): void
    {
        $judul = "Pelimpahan Paket Pengadaan: {$paket->kode_paket}";
        $pesan = "Tugas pengawalan paket '{$paket->nama_paket}' dialihkan kepada Anda. Alasan: ".($alasan ?: 'Penyesuaian beban kerja PPK').'. Harap segera tindak lanjuti.';

        $this->kirimKeUser($ppkBaru->id_user, $judul, $pesan, 'verifikasi', $paket->nomor_paket);
    }

    /**
     * Event 8: Reviu Dokumen Pengadaan oleh PP / PPK.
     * Jika Perlu Perbaikan: Notifikasi ke PPBJ dan Pengusul.
     */
    public function notifyReviuDokumen(PaketPengadaan $paket, string $statusReviu, ?string $catatan = null): void
    {
        if ($statusReviu === 'perlu_perbaikan') {
            $judul = "Revisi Dokumen Diperlukan: {$paket->kode_paket}";
            $pesan = "Pejabat penelaah meminta perbaikan berkas untuk paket '{$paket->nama_paket}'. Catatan: ".($catatan ?: 'Perbaiki spesifikasi teknis / perkiraan harga').'.';

            // Kirim ke PPBJ
            $this->kirimKePeran('ppbj', $judul, $pesan, 'pengingat', $paket->nomor_paket);

            // Kirim ke Pengusul asli jika ada
            if ($paket->permohonan && $paket->permohonan->id_user) {
                $this->kirimKeUser($paket->permohonan->id_user, $judul, $pesan, 'pengingat', $paket->nomor_paket);
            }
        }
    }

    /**
     * Event 8B: PP Selesai Mereview & Menginput Harga Perkiraan, Harga Akhir, dan Vendor (50-200jt).
     * Berkoordinasi mengirimkan permintaan Approval ke PPK penanggung jawab paket.
     */
    public function notifyReviewPpKePpk(PaketPengadaan $paket, ?string $catatan = null): void
    {
        if (! $paket->id_user_ppk) {
            return;
        }

        $hargaAkhir = number_format((float) ($paket->nominal_harga_akhir ?? $paket->nilai_hasil_negosiasi ?? 0), 0, ',', '.');
        $judul = "Permintaan Persetujuan PPK atas Reviu Pejabat Pengadaan: {$paket->nomor_paket}";
        $pesan = "Pejabat Pengadaan telah memverifikasi kelengkapan paket '{$paket->nama_paket}' (Harga Akhir Rp {$hargaAkhir}). Catatan: ".($catatan ?: 'Berkas lengkap, mohon persetujuan PPK').'.';

        $this->kirimKeUser($paket->id_user_ppk, $judul, $pesan, 'verifikasi', $paket->nomor_paket);
    }

    /**
     * Event 8C: PPK Memberikan Approval pada Paket > 50jt s.d. 200jt -> Memberitahu PP untuk Eksekusi Belanja.
     */
    public function notifyApprovalPpkKePp(PaketPengadaan $paket): void
    {
        $judul = "Persetujuan PPK Diterbitkan (Siap Belanja): {$paket->nomor_paket}";
        $pesan = "PPK telah memberikan persetujuan pada paket '{$paket->nama_paket}'. Silakan Pejabat Pengadaan melanjutkan proses belanja.";

        if ($paket->id_user_pp) {
            $this->kirimKeUser($paket->id_user_pp, $judul, $pesan, 'verifikasi', $paket->nomor_paket);
        } else {
            $this->kirimKePeran('pp', $judul, $pesan, 'verifikasi', $paket->nomor_paket);
        }
    }

    /**
     * Event 8D: Eksekusi Belanja oleh PP (50-200jt) atau PPK (>200jt) Selesai -> PPBJ Siap Upload 1 Dokumen Perikatan.
     */
    public function notifySiapUploadDokumenPpbj(PaketPengadaan $paket, string $rolePelaksana): void
    {
        $judul = "Eksekusi Belanja Selesai (Unggah Dokumen): {$paket->nomor_paket}";
        $pesan = strtoupper($rolePelaksana)." telah melaksanakan belanja paket '{$paket->nama_paket}'. Silakan Unit Pengadaan mengunggah dokumen kontrak (Surat Pesanan / Kontrak Kerja / Surat Kontrak) dan mengatur Pengingat.";

        $this->kirimKePeran('ppbj', $judul, $pesan, 'spk', $paket->nomor_paket);
    }

    /**
     * Event 9: Penerbitan Kontrak / Surat Perintah Kerja (SPK).
     * Sesuai TRUTH: Tembusan SPK dikirim ke Bagian Perlengkapan dan Bagian Keuangan.
     * Serta informasi terbit ke Pengusul.
     */
    public function notifyPenerbitanKontrak(KontrakSPK $kontrak): void
    {
        $namaPaket = $kontrak->paket?->nama_paket ?? 'Pengadaan Barang/Jasa';
        $nilai = number_format($kontrak->nilai_kontrak ?? 0, 0, ',', '.');

        // 1. Tembusan ke Unit Perlengkapan (Persiapan pemeriksaan barang)
        $judulPerlengkapan = "Tembusan Dokumen Kontrak Terbit: {$kontrak->nomor_spk}";
        $pesanPerlengkapan = "Dokumen Kontrak nomor {$kontrak->nomor_spk} untuk paket '{$namaPaket}' telah terbit. Harap pantau jadwal pengiriman penyedia dan persiapkan pemeriksaan barang.";
        $this->kirimKePeran('perlengkapan', $judulPerlengkapan, $pesanPerlengkapan, 'spk', $kontrak->nomor_paket);

        // 2. Tembusan ke Bagian Keuangan (Persiapan anggaran dan pembayaran)
        $judulKeuangan = "Tembusan Dokumen Kontrak Baru: {$kontrak->nomor_spk}";
        $pesanKeuangan = "Dokumen Kontrak nomor {$kontrak->nomor_spk} untuk paket '{$namaPaket}' senilai Rp {$nilai} telah terbit. Harap catat anggaran pada pos anggaran terkait.";
        $this->kirimKePeran('keuangan', $judulKeuangan, $pesanKeuangan, 'spk', $kontrak->nomor_paket);

        // 3. Notifikasi ke Pengusul (User)
        $idUserPengaju = $kontrak->paket?->permohonan?->id_user;
        if ($idUserPengaju) {
            $judulPengaju = "Dokumen Kontrak Diterbitkan: {$kontrak->nomor_spk}";
            $pesanPengaju = "Kontrak pengadaan '{$namaPaket}' telah diterbitkan ke pihak penyedia ({$kontrak->nomor_spk}). Penyedia akan segera melakukan pengiriman/pengerjaan.";
            $this->kirimKeUser($idUserPengaju, $judulPengaju, $pesanPengaju, 'spk', $kontrak->nomor_paket);
        }
    }

    /**
     * Event 10: Penerimaan Fisik Barang dan BAST Terbit.
     * Sesuai TRUTH: Barang/jasa sudah sampai/selesai ->
     * 1. PPBJ diberitahu agar membuat pengajuan pembayaran ke PPK.
     * 2. Pengusul (User) diberitahu barang telah sampai dan diterima lengkap.
     */
    public function notifyPenerimaanBarang(PenerimaanBarang $bast): void
    {
        $nomorBast = $bast->nomor_bast;
        $namaPaket = $bast->paket?->nama_paket ?? 'Barang/Jasa Pengadaan';

        // 1. Notifikasi ke Unit Pengadaan untuk memproses pengajuan pembayaran
        $judulPpbj = "Barang Diterima & Serah Terima Terbit: {$nomorBast}";
        $pesanPpbj = "Unit Perlengkapan telah menyelesaikan pemeriksaan fisik dan menerbitkan bukti serah terima {$nomorBast} untuk paket '{$namaPaket}'. Silakan ajukan berkas pembayaran.";
        $this->kirimKePeran('ppbj', $judulPpbj, $pesanPpbj, 'pembayaran', $bast->nomor_paket);

        // 2. Notifikasi ke Pengusul (User) bahwa barang sudah diterima
        $idUserPengaju = $bast->paket?->permohonan?->id_user;
        if ($idUserPengaju) {
            $judulUser = "Barang/Jasa Selesai Diterima: {$nomorBast}";
            $pesanUser = "Pengadaan '{$namaPaket}' telah tiba dan diverifikasi oleh Unit Perlengkapan ({$nomorBast}).";
            $this->kirimKeUser($idUserPengaju, $judulUser, $pesanUser, 'pengiriman', $bast->nomor_paket);
        }
    }

    /**
     * Event 11: PPBJ Membuat Pengajuan Pembayaran (Memo Bayar Level 1: Wadir II).
     * Notifikasi dikirimkan ke: Wakil Direktur II (Level 1) peninjau awal pembayaran.
     */
    public function notifyPengajuanBayar(Pembayaran $pembayaran, ?MemoBayar $memoLevel1 = null, ?MemoBayar $memoLevel2 = null): void
    {
        $namaPaket = $pembayaran->paket?->nama_paket ?? 'Pengadaan';
        $nominal = number_format($pembayaran->nominal_pengajuan ?? 0, 0, ',', '.');

        $judul = "Pengajuan Dokumen Pembayaran: {$pembayaran->nomor_pembayaran}";
        $pesan = "Unit Pengadaan mengajukan pembayaran termin ke-{$pembayaran->tahap_termin} untuk paket '{$namaPaket}' sebesar Rp {$nominal}. Menunggu persetujuan memo.";

        $memos = collect([$memoLevel1, $memoLevel2])->filter();
        $memoWadir = $memos->firstWhere('role_approver', 'wadir_2');
        $memoPpk = $memos->firstWhere('role_approver', 'ppk');

        // 1. Notifikasi ke Wadir II
        $wadirUserId = $memoWadir?->id_user;
        if ($wadirUserId) {
            $this->kirimKeUser($wadirUserId, $judul, $pesan, 'pembayaran', $pembayaran->nomor_paket);
        } else {
            $this->kirimKePeran('wadir_2', $judul, $pesan, 'pembayaran', $pembayaran->nomor_paket);
        }

        // 2. Notifikasi ke PPK (Paralel)
        $ppkUserId = $memoPpk?->id_user ?? $pembayaran->paket?->id_user_ppk ?? $pembayaran->paket?->permohonan?->id_user_ppk_awal;
        if ($ppkUserId) {
            $this->kirimKeUser($ppkUserId, $judul, $pesan, 'pembayaran', $pembayaran->nomor_paket);
        } else {
            $this->kirimKePeran('ppk', $judul, $pesan, 'pembayaran', $pembayaran->nomor_paket);
        }
    }

    /**
     * Event 12: Persetujuan / Disposisi Memo Bayar Paralel.
     * Jika kedua memo (Wadir 2 & PPK) telah disetujui: Notifikasi ke PPBJ bahwa memo lengkap dan siap input legalitas vendor & terbitkan SPP.
     * Jika Ditolak: Notifikasi ke PPBJ untuk perbaikan dokumen pembayaran.
     */
    public function notifyApproveMemoBayar(MemoBayar $memo): void
    {
        $pembayaran = $memo->pembayaran;
        $namaPaket = $pembayaran?->paket?->nama_paket ?? 'Pengadaan';

        if ($memo->status_memo === 'ditolak') {
            $approverLabel = $memo->role_approver === 'wadir_2' ? 'Wakil Direktur II' : 'PPK';
            $judul = "Dokumen Pembayaran Ditolak: {$approverLabel}";
            $pesan = "Dokumen pembayaran untuk paket '{$namaPaket}' ditolak oleh {$approverLabel}. Alasan: ".($memo->catatan_memo ?: 'Perlu perbaikan berkas tagihan').'. Silakan perbaiki dokumen pembayaran.';
            $this->kirimKePeran('ppbj', $judul, $pesan, 'pembayaran', $pembayaran?->nomor_paket);

            $idPengaju = $pembayaran?->id_user_pengaju ?? $pembayaran?->paket?->permohonan?->id_user;
            if ($idPengaju) {
                $this->kirimKeUser($idPengaju, $judul, $pesan, 'pembayaran', $pembayaran?->nomor_paket);
            }

            return;
        }

        // Cek apakah seluruh memo (Wadir 2 dan PPK) telah disetujui lengkap
        $allApproved = $pembayaran && $pembayaran->memoBayar()->count() >= 2
            && $pembayaran->memoBayar()->where('status_memo', '!=', 'disetujui')->count() === 0;

        if ($allApproved) {
            $judulPpbj = "Memo Pembayaran Disetujui Lengkap: {$pembayaran->nomor_pembayaran}";
            $pesanPpbj = "Seluruh memo persetujuan (Wadir II dan PPK) telah disetujui untuk paket '{$namaPaket}'. Silakan periksa legalitas penyedia dan terbitkan Perintah Bayar ke Bagian Keuangan.";
            $this->kirimKePeran('ppbj', $judulPpbj, $pesanPpbj, 'pembayaran', $pembayaran->nomor_paket);
        } else {
            // Beri tahu Unit Pengadaan progres memo yang baru disetujui
            $approverLabel = $memo->role_approver === 'wadir_2' ? 'Wakil Direktur II' : 'PPK';
            $judulPpbj = "Memo {$approverLabel} Disetujui: {$pembayaran?->nomor_pembayaran}";
            $pesanPpbj = "{$approverLabel} telah menyetujui memo pembayaran untuk paket '{$namaPaket}' (No Memo: {$memo->nomor_memo}). Menunggu persetujuan pihak lainnya.";
            $this->kirimKePeran('ppbj', $judulPpbj, $pesanPpbj, 'pembayaran', $pembayaran?->nomor_paket);
        }
    }

    /**
     * Event 13: Penerbitan Surat Perintah Pembayaran (SPP) oleh PPBJ.
     * Notifikasi dikirimkan ke: Bagian Keuangan (verifikasi & pencairan SP2D).
     */
    public function notifyTerbitkanSpp(Pembayaran $pembayaran): void
    {
        $namaPaket = $pembayaran->paket?->nama_paket ?? 'Pengadaan';
        $nominal = number_format($pembayaran->nominal_pengajuan ?? 0, 0, ',', '.');

        $judul = "Perintah Bayar Terbit: {$pembayaran->nomor_pembayaran}";
        $pesan = "Unit Pengadaan menerbitkan Perintah Bayar nomor {$pembayaran->nomor_pembayaran} untuk paket '{$namaPaket}' senilai Rp {$nominal}. Siap pencairan dana.";

        $this->kirimKePeran('keuangan', $judul, $pesan, 'pembayaran', $pembayaran->nomor_paket);
    }

    /**
     * Event 14: Pencairan Dana SP2D oleh Bagian Keuangan (Siklus Selesai).
     * Notifikasi dikirimkan ke aktor yang terlibat langsung pada paket pengadaan ini:
     * - Pengusul awal (User)
     * - Koordinator PPBJ pembuat paket
     * - PPK penanggung jawab paket
     * - Pejabat Pengadaan (PP) jika ada
     * - Petugas Perlengkapan penerbit BAST
     * - Wadir 2, Perencanaan, dan Keuangan
     */
    public function notifyPencairanSp2d(TransaksiPencairan $transaksi): void
    {
        $nomorTransaksi = $transaksi->nomor_transaksi_bank;
        $nominal = number_format($transaksi->nominal_transfer ?? 0, 0, ',', '.');
        $namaPaket = $transaksi->pembayaran?->paket?->nama_paket ?? 'Pengadaan Barang/Jasa';
        $paket = $transaksi->pembayaran?->paket;
        $permohonan = $paket?->permohonan;

        $targetUserIds = collect();

        // 1. Pengusul awal usulan pengadaan
        if ($permohonan?->id_user) {
            $targetUserIds->push($permohonan->id_user);
        }

        // 2. Koordinator PPBJ pembuat paket
        if ($paket?->id_user_ppbj) {
            $targetUserIds->push($paket->id_user_ppbj);
        } else {
            $targetUserIds = $targetUserIds->merge(self::resolveActiveUsersByLogicalRoles('ppbj')->pluck('id_user'));
        }

        // 3. PPK yang bertanggung jawab atas paket
        if ($paket?->id_user_ppk) {
            $targetUserIds->push($paket->id_user_ppk);
        } elseif ($permohonan?->id_user_ppk_awal) {
            $targetUserIds->push($permohonan->id_user_ppk_awal);
        }

        // 4. Pejabat Pengadaan (PP) jika ada
        if ($paket?->id_user_pp) {
            $targetUserIds->push($paket->id_user_pp);
        }

        // 5. Petugas Perlengkapan penerbit BAST
        $bastUser = $paket?->penerimaanBarang?->first()?->id_user;
        if ($bastUser) {
            $targetUserIds->push($bastUser);
        } else {
            $targetUserIds = $targetUserIds->merge(self::resolveActiveUsersByLogicalRoles('perlengkapan')->pluck('id_user'));
        }

        // 6. Wadir 2 & Perencanaan & Keuangan pelaksana
        $otherRolesUsers = self::resolveActiveUsersByLogicalRoles(['wadir_2', 'perencanaan', 'keuangan'])->pluck('id_user');

        $targetUserIds = $targetUserIds->merge($otherRolesUsers)->unique()->filter();

        $judul = "Pencairan Dana Selesai: {$nomorTransaksi}";
        $pesan = "Pembayaran untuk paket '{$namaPaket}' telah dicairkan oleh Bagian Keuangan sebesar Rp {$nominal} (Nomor Transaksi: {$nomorTransaksi}). Seluruh proses pengadaan selesai.";

        foreach ($targetUserIds as $uId) {
            $this->kirimKeUser($uId, $judul, $pesan, 'pembayaran', $paket?->nomor_paket);
        }
    }
}
