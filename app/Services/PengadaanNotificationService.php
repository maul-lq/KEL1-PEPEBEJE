<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis service PengadaanNotificationService ini untuk mengorkestrasi pengiriman notifikasi personal dan email otomatis pada seluruh titik transisi 8 siklus pengadaan SIM-PBJ.
 */

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
 * Layanan Orkestrasi Notifikasi Pengadaan (PengadaanNotificationService)
 *
 * membangun service ini agar setiap controller pengadaan cukup memanggil satu metode semantik per peristiwa
 * tanpa harus menyusun manual master Reminder, resolusi penerima berbasis RBAC, atau pemicuan email observer.
 *
 * Parameter konstruktor: Tidak ada.
 * Nilai kembali         : Instance layanan notifikasi yang biasanya disuntikkan via Dependency Injection Laravel.
 *
 * Contoh pemanggilan:
 * `$this->notificationService->notifyPengajuanBaru($permohonan);`
 */
class PengadaanNotificationService
{
    /**
     * Pemetaan peran logis ke slug permission RBAC agar penerima notifikasi tetap ditemukan meskipun peran dikustomisasi oleh Admin.
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
        'ppk' => ['verifikasi.ppk_approve', 'reviu.ppk', 'pembayaran.memo_ppk'],
        'perlengkapan' => ['penerimaan.bast_create', 'penerimaan.bast_sign'],
        'keuangan' => ['pembayaran.pencairan_sp2d'],
    ];

    /**
     * mencari seluruh akun pengguna aktif yang mengemban peran logis tertentu atau memiliki permission RBAC padanannya.
     *
     * @param  string|array<int, string>  $roles  menerima satu slug peran atau array slug peran sasaran notifikasi.
     * @return Collection<int, User> mengembalikan koleksi model User aktif (kosong jika input peran kosong).
     *
     * Contoh pemanggilan:
     * `$users = PengadaanNotificationService::resolveActiveUsersByLogicalRoles(['perlengkapan', 'keuangan']);`
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
                // mencocokkan nama_role sekaligus permission padanannya supaya peran kustom dari Admin tetap menerima notifikasi operasional.
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
     * membuat master reminder instan dan mengirim notifikasi personal (beserta email otomatis via observer) ke satu pengguna.
     *
     * @param  User|int  $user  menerima objek User atau ID user penerima notifikasi.
     * @param  string  $judul  menetapkan judul ringkas alert yang tampil di pop-over lonceng dan subjek email.
     * @param  string  $pesan  menyertakan narasi instruksi tindak lanjut lengkap untuk penerima.
     * @param  string  $tipe  mengisi kategori reminder (default 'pengingat').
     * @param  string|null  $nomorPaket  mengikat referensi paket bila tersedia untuk tautan cepat.
     * @return NotifikasiUser mengembalikan record NotifikasiUser yang baru tersimpan.
     *
     * Contoh pemanggilan:
     * `$service->kirimKeUser($permohonan->id_user, 'Usulan Disetujui', $pesan, 'verifikasi', $permohonan->nomor_surat);`
     */
    public function kirimKeUser(User|int $user, string $judul, string $pesan, string $tipe = 'pengingat', ?string $nomorPaket = null): NotifikasiUser
    {
        $userId = $user instanceof User ? $user->id_user : $user;

        // memverifikasi apakah referensi adalah nomor_paket valid di tabel paket_pengadaan agar tidak melanggar constraint foreign key saat dipanggil dengan nomor_surat.
        $validNomorPaket = null;
        if (! empty($nomorPaket) && PaketPengadaan::where('nomor_paket', $nomorPaket)->exists()) {
            $validNomorPaket = $nomorPaket;
        }

        // menandai reminder instan ini nonaktif (is_active=false) dan sudah terpicu agar scheduler berkala tidak memprosesnya ulang.
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

        // hanya perlu menyimpan NotifikasiUser karena NotifikasiUserObserver::created otomatis mengirimkan email ke penerima.
        return NotifikasiUser::create([
            'id_user' => $userId,
            'id_reminder' => $reminder->id_reminder,
            'is_read' => false,
            'is_dismissed' => false,
        ]);
    }

    /**
     * menyiarkan satu notifikasi master ke seluruh pengguna aktif yang memegang peran atau permission sasaran.
     *
     * @param  string|array<string>  $roles  menerima satu atau daftar slug peran target.
     * @param  string  $judul  menentukan judul alert yang disiarkan.
     * @param  string  $pesan  menentukan isi instruksi pengadaan.
     * @param  string  $tipe  menentukan kategori reminder (default 'pengingat').
     * @param  string|null  $nomorPaket  menerima nomor paket referensi untuk validasi relasi FK.
     * @return Collection<int, NotifikasiUser> mengembalikan koleksi record NotifikasiUser yang berhasil diterbitkan.
     *
     * Contoh pemanggilan:
     * `$service->kirimKePeran(['staff_bidang_2', 'wadir_2'], $judul, $pesan, 'verifikasi');`
     */
    public function kirimKePeran(string|array $roles, string $judul, string $pesan, string $tipe = 'pengingat', ?string $nomorPaket = null): Collection
    {
        $rolesList = is_array($roles) ? $roles : [$roles];
        $targetUsers = self::resolveActiveUsersByLogicalRoles($rolesList);

        if ($targetUsers->isEmpty()) {
            return collect();
        }

        // menghindari pelanggaran FK PostgreSQL saat caller mengirim nomor_surat permohonan alih-alih nomor_paket.
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

        // membuat satu baris Reminder induk lalu membagikannya ke beberapa NotifikasiUser agar teks pesan tidak terduplikasi di database.
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
     * memberi tahu Staf Bidang 2 dan Wadir 2 ketika unit kerja mengajukan permohonan pengadaan baru.
     *
     * @param  PermohonanPengadaan  $permohonan  membaca nomor surat, perihal, unit pengusul, dan estimasi pagu dari usulan baru ini.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$this->notificationService->notifyPengajuanBaru($permohonan);`
     */
    public function notifyPengajuanBaru(PermohonanPengadaan $permohonan): void
    {
        $estimasi = number_format($permohonan->estimasi_total_pagu ?? $permohonan->anggaran_diajukan ?? 0, 0, ',', '.');
        $unitName = $permohonan->unit->nama_unit ?? $permohonan->kode_unit;

        $this->kirimKePeran(
            'perencanaan',
            "Usulan Pengadaan Baru (Cek Anggaran & PPK): {$permohonan->nomor_surat}",
            "Unit {$unitName} mengajukan permohonan baru: '{$permohonan->perihal}' (Estimasi Rp {$estimasi}). Silakan tentukan pos anggaran dan tetapkan PPK awal.",
            'verifikasi',
            $permohonan->nomor_surat
        );

        // mengirimkan informasi pasif bagi pemegang izin lihat usulan Bidang II tanpa instruksi verifikasi atau pembuatan memo.
        $this->kirimKePeran(
            'staff_bidang_2',
            "Pemantauan Usulan Pengadaan Baru: {$permohonan->nomor_surat}",
            "Unit {$unitName} mengajukan permohonan baru: '{$permohonan->perihal}' (Estimasi Rp {$estimasi}). Usulan sedang diteruskan ke Bagian Perencanaan.",
            'verifikasi',
            $permohonan->nomor_surat
        );
    }

    /**
     * memberi tahu Bagian Perencanaan dan pemantau Bidang II bahwa unit pengaju telah mengirimkan berkas revisi permohonan.
     *
     * @param  PermohonanPengadaan  $permohonan  membaca nomor surat revisi dan indeks `revisi_ke` dari usulan hasil perbaikan.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$this->notificationService->notifyPengajuanRevisi($permohonanRevisi);`
     */
    public function notifyPengajuanRevisi(PermohonanPengadaan $permohonan): void
    {
        $unitName = $permohonan->unit->nama_unit ?? $permohonan->kode_unit;

        $this->kirimKePeran(
            'perencanaan',
            "Revisi Usulan Pengadaan: {$permohonan->nomor_surat}",
            "Unit {$unitName} memperbarui dokumen usulan pengadaan '{$permohonan->perihal}' (Revisi ke-{$permohonan->revisi_ke}). Silakan periksa kembali pos anggaran dan PPK terkait.",
            'verifikasi',
            $permohonan->nomor_surat
        );

        $this->kirimKePeran(
            'staff_bidang_2',
            "Pemantauan Revisi Usulan Pengadaan: {$permohonan->nomor_surat}",
            "Unit {$unitName} memperbarui dokumen usulan pengadaan '{$permohonan->perihal}' (Revisi ke-{$permohonan->revisi_ke}).",
            'verifikasi',
            $permohonan->nomor_surat
        );
    }

    /**
     * mengarahkan notifikasi revisi langsung ke Bagian Perencanaan apabila usulan yang direvisi sebelumnya sudah sampai tahap verifikasi MAK.
     *
     * @param  PermohonanPengadaan  $permohonan  memakai nomor surat dan `revisi_ke` untuk menyusun informasi pengecekan anggaran ulang.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$this->notificationService->notifyRevisiKePerencanaan($permohonanRevisi);`
     */
    public function notifyRevisiKePerencanaan(PermohonanPengadaan $permohonan): void
    {
        $judul = "Revisi Usulan Pengadaan (Pasca-Anggaran): {$permohonan->nomor_surat}";
        $unitName = $permohonan->unit->nama_unit ?? $permohonan->kode_unit;

        $pesan = "Unit {$unitName} telah memperbarui dokumen usulan pengadaan '{$permohonan->perihal}' (Revisi ke-{$permohonan->revisi_ke}). Silakan periksa ketersediaan pos anggaran dan teruskan ke pemeriksa terkait.";

        $this->kirimKePeran('perencanaan', $judul, $pesan, 'verifikasi', $permohonan->nomor_surat);
    }

    /**
     * memberi tahu Bagian Perencanaan ketika Staf Bidang 2 mengirim Memo Cek MAK untuk pemeriksaan pos anggaran.
     *
     * @param  PermohonanPengadaan  $permohonan  membaca detail usulan terkait.
     * @param  string|null  $nomorMemo  menyertakan nomor memo dari form agar mudah dirujuk Bagian Perencanaan.
     * @param  string|null  $catatan  menampilkan catatan verifikator atau fallback instruksi default jika kosong.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$this->notificationService->notifyMemoCekMak($permohonan, $request->nomor_memo, $request->catatan);`
     */
    public function notifyMemoCekMak(PermohonanPengadaan $permohonan, ?string $nomorMemo = null, ?string $catatan = null): void
    {
        $memoRef = $nomorMemo ?: ($permohonan->nomor_memo_cek_mak ?: '-');
        $judul = "Memo Cek Anggaran dari Bidang 2: {$permohonan->nomor_surat}";
        $pesan = "Bidang 2 mengirimkan Memo Cek Anggaran ({$memoRef}) untuk usulan '{$permohonan->perihal}'. Catatan: ".($catatan ?: 'Mohon periksa/siapkan pos anggaran dan pilih PPK').'.';

        $this->kirimKePeran('perencanaan', $judul, $pesan, 'verifikasi', $permohonan->nomor_surat);
    }

    /**
     * mempertahankan metode alias ini agar pemanggil lama tetap dapat mengirim notifikasi Memo Cek MAK tanpa perubahan tanda tangan.
     *
     * @param  PermohonanPengadaan  $permohonan  meneruskan entitas permohonan ke `notifyMemoCekMak`.
     * @param  string|null  $catatan  meneruskan catatan penerusan berkas dari Staf Bidang 2.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$this->notificationService->notifyDiteruskanStaffB2($permohonan, $catatan);`
     */
    public function notifyDiteruskanStaffB2(PermohonanPengadaan $permohonan, ?string $catatan = null): void
    {
        $this->notifyMemoCekMak($permohonan, $permohonan->nomor_memo_cek_mak, $catatan);
    }

    /**
     * mendistribusikan hasil keputusan Wadir 2 (disetujui, ditolak, atau revisi) ke pihak berikutnya yang menunggu giliran.
     *
     * @param  PermohonanPengadaan  $permohonan  memeriksa status akhir permohonan dan apakah PPK awal sudah ditetapkan.
     * @param  string  $keputusan  membaca nilai keputusan ('disetujui', 'ditolak', atau 'revisi').
     * @param  string|null  $catatan  menyertakan alasan penolakan/revisi wajib agar pemohon tahu bagian yang harus diperbaiki.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$this->notificationService->notifyKeputusanWadir2($permohonan, 'disetujui', $catatan);`
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

        // hanya meneruskan ke PPBJ bila persetujuan Wadir 2 dan PPK awal sudah lengkap; jika belum, beri tahu PPK awal atau Perencanaan.
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
     * mengirimkan notifikasi tindak lanjut setelah PPK awal terpilih memberi keputusan setuju atau tolak.
     *
     * @param  PermohonanPengadaan  $permohonan  membaca status gabungan Wadir 2 & PPK untuk menentukan apakah usulan sudah siap ke PPBJ.
     * @param  string  $keputusan  memeriksa apakah keputusan PPK berupa 'ditolak' atau persetujuan.
     * @param  string|null  $catatan  melampirkan catatan alasan jika PPK menolak usulan.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$this->notificationService->notifyKeputusanPpkAwal($permohonan, 'disetujui', $catatan);`
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
     * memberi tahu Wadir 2 dan PPK terpilih (yang belum menyetujui) setelah Bagian Perencanaan memastikan ketersediaan MAK, atau mengabarkan penahanan anggaran ke pengusul.
     *
     * @param  PermohonanPengadaan  $permohonan  membaca status permohonan dan PPK awal terpilih.
     * @param  AnggaranMAK  $mak  menyertakan kode MAK teralokasi pada pesan notifikasi.
     * @param  string  $keputusan  memeriksa apakah Perencanaan memilih 'tahan' atau mengalokasikan anggaran.
     * @param  string|null  $catatan  menampilkan catatan penahanan anggaran apabila statusnya ditahan.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$this->notificationService->notifyAlokasiMak($permohonan, $mak, 'alokasi', $catatan);`
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

        $wadirAcc = VerifikasiParalel::where('nomor_surat', $permohonan->nomor_surat)
            ->where('role_verifikator', VerifikasiParalel::ROLE_WADIR_2)
            ->where('status_keputusan', 'disetujui')
            ->exists();

        $ppkAcc = VerifikasiParalel::where('nomor_surat', $permohonan->nomor_surat)
            ->where('role_verifikator', VerifikasiParalel::ROLE_PPK)
            ->where('status_keputusan', 'disetujui')
            ->exists();

        // hanya mengirim notifikasi ke verifikator yang statusnya masih menunggu agar pihak yang sudah ACC lebih awal tidak mendapat alert berulang.
        if (! $wadirAcc) {
            $this->kirimKePeran('wadir_2', $judul, $pesan, 'verifikasi', $permohonan->nomor_surat);
        }

        if (! $ppkAcc && $permohonan->id_user_ppk_awal) {
            $this->kirimKeUser($permohonan->id_user_ppk_awal, $judul, $pesan, 'verifikasi', $permohonan->nomor_surat);
        }
    }

    /**
     * mengabarkan persetujuan penuh (MAK + Wadir 2 + PPK) ke unit pemohon serta seluruh petugas PPBJ agar paket segera didaftarkan.
     *
     * @param  PermohonanPengadaan  $permohonan  membaca ID pengusul, perihal, dan nomor surat usulan.
     * @param  string|null  $kodeMak  menyisipkan kode MAK bila tersedia untuk transparansi anggaran pada notifikasi pemohon.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$this->notificationService->notifyPersetujuanPenuh($permohonan, $mak->kode_mak);`
     */
    public function notifyPersetujuanPenuh(PermohonanPengadaan $permohonan, ?string $kodeMak = null): void
    {
        $makInfo = $kodeMak ? " pada Pos Anggaran {$kodeMak}" : '';

        if ($permohonan->id_user) {
            $judulUser = "Permohonan Pengadaan Disetujui: {$permohonan->nomor_surat}";
            $pesanUser = "Usulan pengadaan '{$permohonan->perihal}' telah disetujui lengkap oleh Bidang 2 (Wadir 2) dan PPK{$makInfo}. Dokumen otomatis diteruskan ke Unit Pengadaan.";

            $this->kirimKeUser($permohonan->id_user, $judulUser, $pesanUser, 'verifikasi', $permohonan->nomor_surat);
        }

        $judulPpbj = "Paket Siap Didaftarkan Unit Pengadaan: {$permohonan->nomor_surat}";
        $pesanPpbj = "Usulan '{$permohonan->perihal}' nomor {$permohonan->nomor_surat} telah disetujui penuh oleh Wadir 2 dan PPK. Silakan daftarkan paket dan tentukan metode pengadaan (Belanja Langsung / Non-Tender / Tender Terbuka).";

        $this->kirimKePeran('ppbj', $judulPpbj, $pesanPpbj, 'verifikasi', $permohonan->nomor_surat);
    }

    /**
     * mengirim penugasan paket pengadaan baru ke akun PPK dan/atau Pejabat Pengadaan (PP) yang ditunjuk saat registrasi paket.
     *
     * @param  PaketPengadaan  $paket  membaca `id_user_ppk`, `id_user_pp`, nilai HPS, dan jalur routing paket.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$this->notificationService->notifyPendaftaranPaket($paket);`
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
     * memberi tahu akun PPK baru yang menerima pelimpahan tugas pengawalan paket pengadaan.
     *
     * @param  PaketPengadaan  $paket  membaca kode dan nama paket yang dialihkan.
     * @param  User  $ppkBaru  mengirim notifikasi personal hanya kepada PPK penerima pelimpahan.
     * @param  string|null  $alasan  mencantumkan alasan pengalihan tugas pada isi pesan.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$this->notificationService->notifyReassignPpk($paket, $ppkBaru, $request->alasan);`
     */
    public function notifyReassignPpk(PaketPengadaan $paket, User $ppkBaru, ?string $alasan = null): void
    {
        $judul = "Pelimpahan Paket Pengadaan: {$paket->kode_paket}";
        $pesan = "Tugas pengawalan paket '{$paket->nama_paket}' dialihkan kepada Anda. Alasan: ".($alasan ?: 'Penyesuaian beban kerja PPK').'. Harap segera tindak lanjuti.';

        $this->kirimKeUser($ppkBaru->id_user, $judul, $pesan, 'verifikasi', $paket->nomor_paket);
    }

    /**
     * memberi tahu PPBJ dan pengusul awal apabila PP atau PPK meminta perbaikan berkas persiapan pengadaan.
     *
     * @param  PaketPengadaan  $paket  membaca nomor paket dan relasi pemohon asalnya.
     * @param  string  $statusReviu  hanya memicu alert perbaikan bila status reviu bernilai 'perlu_perbaikan'.
     * @param  string|null  $catatan  meneruskan catatan penelaah agar bagian yang perlu direvisi langsung jelas.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$this->notificationService->notifyReviuDokumen($paket, 'perlu_perbaikan', $catatan);`
     */
    public function notifyReviuDokumen(PaketPengadaan $paket, string $statusReviu, ?string $catatan = null): void
    {
        if ($statusReviu === 'perlu_perbaikan') {
            $judul = "Revisi Dokumen Diperlukan: {$paket->kode_paket}";
            $pesan = "Pejabat penelaah meminta perbaikan berkas untuk paket '{$paket->nama_paket}'. Catatan: ".($catatan ?: 'Perbaiki spesifikasi teknis / perkiraan harga').'.';

            $this->kirimKePeran('ppbj', $judul, $pesan, 'pengingat', $paket->nomor_paket);

            if ($paket->permohonan && $paket->permohonan->id_user) {
                $this->kirimKeUser($paket->permohonan->id_user, $judul, $pesan, 'pengingat', $paket->nomor_paket);
            }
        }
    }

    /**
     * meneruskan hasil verifikasi kelengkapan & harga negosiasi dari PP (jalur 50-200jt) kepada PPK penanggung jawab untuk persetujuan.
     *
     * @param  PaketPengadaan  $paket  membaca ID PPK terassign dan nominal harga akhir hasil input PP.
     * @param  string|null  $catatan  menyertakan catatan verifikasi dari PP.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$this->notificationService->notifyReviewPpKePpk($paket, $request->catatan);`
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
     * memberi tahu Pejabat Pengadaan (PP) bahwa PPK telah menyetujui paket 50-200jt sehingga eksekusi belanja siap dilakukan.
     *
     * @param  PaketPengadaan  $paket  memeriksa `id_user_pp` terassign atau fallback ke seluruh akun berizin PP.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$this->notificationService->notifyApprovalPpkKePp($paket);`
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
     * memberi tahu PPBJ setelah PP atau PPK selesai mengeksekusi belanja agar PPBJ segera mengunggah 1 dokumen perikatan terpilih.
     *
     * @param  PaketPengadaan  $paket  menyertakan nomor dan nama paket pada pesan pemberitahuan.
     * @param  string  $rolePelaksana  menampilkan aktor pelaksana belanja ('pp' atau 'ppk') di teks instruksi.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$this->notificationService->notifySiapUploadDokumenPpbj($paket, 'pp');`
     */
    public function notifySiapUploadDokumenPpbj(PaketPengadaan $paket, string $rolePelaksana): void
    {
        $judul = "Eksekusi Belanja Selesai (Unggah Dokumen): {$paket->nomor_paket}";
        $pesan = strtoupper($rolePelaksana)." telah melaksanakan belanja paket '{$paket->nama_paket}'. Silakan Unit Pengadaan mengunggah dokumen kontrak (Surat Pesanan / Kontrak Kerja / Surat Kontrak) dan mengatur Pengingat.";

        $this->kirimKePeran('ppbj', $judul, $pesan, 'spk', $paket->nomor_paket);
    }

    /**
     * menyebarkan tembusan penerbitan dokumen Kontrak/SPK ke Unit Perlengkapan, Bagian Keuangan, dan unit pengusul.
     *
     * @param  KontrakSPK  $kontrak  membaca nomor SPK, nilai kontrak, dan relasi paket/permohonan asal.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$this->notificationService->notifyPenerbitanKontrak($kontrak);`
     */
    public function notifyPenerbitanKontrak(KontrakSPK $kontrak): void
    {
        $namaPaket = $kontrak->paket?->nama_paket ?? 'Pengadaan Barang/Jasa';
        $nilai = number_format($kontrak->nilai_kontrak ?? 0, 0, ',', '.');

        // mengirim tembusan ke Perlengkapan agar persiapan inspeksi barang dimulai sejak kontrak terbit.
        $judulPerlengkapan = "Tembusan Dokumen Kontrak Terbit: {$kontrak->nomor_spk}";
        $pesanPerlengkapan = "Dokumen Kontrak nomor {$kontrak->nomor_spk} untuk paket '{$namaPaket}' telah terbit. Harap pantau jadwal pengiriman penyedia dan persiapkan pemeriksaan barang.";
        $this->kirimKePeran('perlengkapan', $judulPerlengkapan, $pesanPerlengkapan, 'spk', $kontrak->nomor_paket);

        // mengirim tembusan ke Keuangan agar pos anggaran komitmen kontrak sudah dipantau sebelum tagihan SPP masuk.
        $judulKeuangan = "Tembusan Dokumen Kontrak Baru: {$kontrak->nomor_spk}";
        $pesanKeuangan = "Dokumen Kontrak nomor {$kontrak->nomor_spk} untuk paket '{$namaPaket}' senilai Rp {$nilai} telah terbit. Harap catat anggaran pada pos anggaran terkait.";
        $this->kirimKePeran('keuangan', $judulKeuangan, $pesanKeuangan, 'spk', $kontrak->nomor_paket);

        $idUserPengaju = $kontrak->paket?->permohonan?->id_user;
        if ($idUserPengaju) {
            $judulPengaju = "Dokumen Kontrak Diterbitkan: {$kontrak->nomor_spk}";
            $pesanPengaju = "Kontrak pengadaan '{$namaPaket}' telah diterbitkan ke pihak penyedia ({$kontrak->nomor_spk}). Penyedia akan segera melakukan pengiriman/pengerjaan.";
            $this->kirimKeUser($idUserPengaju, $judulPengaju, $pesanPengaju, 'spk', $kontrak->nomor_paket);
        }
    }

    /**
     * memberi tahu PPBJ dan pengusul setelah Perlengkapan menyelesaikan inspeksi fisik dan menerbitkan BAST.
     *
     * @param  PenerimaanBarang  $bast  membaca nomor BAST, nama paket, dan ID pengusul dari relasi permohonan.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$this->notificationService->notifyPenerimaanBarang($penerimaan);`
     */
    public function notifyPenerimaanBarang(PenerimaanBarang $bast): void
    {
        $nomorBast = $bast->nomor_bast;
        $namaPaket = $bast->paket?->nama_paket ?? 'Barang/Jasa Pengadaan';

        $judulPpbj = "Barang Diterima & Serah Terima Terbit: {$nomorBast}";
        $pesanPpbj = "Unit Perlengkapan telah menyelesaikan pemeriksaan fisik dan menerbitkan bukti serah terima {$nomorBast} untuk paket '{$namaPaket}'. Silakan ajukan berkas pembayaran.";
        $this->kirimKePeran('ppbj', $judulPpbj, $pesanPpbj, 'pembayaran', $bast->nomor_paket);

        $idUserPengaju = $bast->paket?->permohonan?->id_user;
        if ($idUserPengaju) {
            $judulUser = "Barang/Jasa Selesai Diterima: {$nomorBast}";
            $pesanUser = "Pengadaan '{$namaPaket}' telah tiba dan diverifikasi oleh Unit Perlengkapan ({$nomorBast}).";
            $this->kirimKeUser($idUserPengaju, $judulUser, $pesanUser, 'pengiriman', $bast->nomor_paket);
        }
    }

    /**
     * mengirim permintaan persetujuan memo pembayaran secara paralel kepada Wadir 2 dan PPK terkait.
     *
     * @param  Pembayaran  $pembayaran  membaca nomor pembayaran, termin, nominal tagihan, dan paket terkait.
     * @param  MemoBayar|null  $memoLevel1  memakai objek memo level 1 untuk mengambil ID approver spesifik bila sudah ditetapkan.
     * @param  MemoBayar|null  $memoLevel2  memakai objek memo level 2 untuk mengambil ID PPK penanggung jawab bila sudah ditetapkan.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$this->notificationService->notifyPengajuanBayar($pembayaran, $memoWadir, $memoPpk);`
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

        $wadirUserId = $memoWadir?->id_user;
        if ($wadirUserId) {
            $this->kirimKeUser($wadirUserId, $judul, $pesan, 'pembayaran', $pembayaran->nomor_paket);
        } else {
            $this->kirimKePeran('wadir_2', $judul, $pesan, 'pembayaran', $pembayaran->nomor_paket);
        }

        // mengarahkan notifikasi PPK langsung ke PPK pengawal paket agar 3 PPK lainnya tidak menerima gangguan tugas paket yang bukan miliknya.
        $ppkUserId = $memoPpk?->id_user ?? $pembayaran->paket?->id_user_ppk ?? $pembayaran->paket?->permohonan?->id_user_ppk_awal;
        if ($ppkUserId) {
            $this->kirimKeUser($ppkUserId, $judul, $pesan, 'pembayaran', $pembayaran->nomor_paket);
        } else {
            $this->kirimKePeran('ppk', $judul, $pesan, 'pembayaran', $pembayaran->nomor_paket);
        }
    }

    /**
     * memberi tahu PPBJ (dan pengusul saat ditolak) mengenai hasil persetujuan/penolakan memo bayar dari Wadir 2 atau PPK.
     *
     * @param  MemoBayar  $memo  memeriksa status memo serta apakah kedua memo (Wadir 2 dan PPK) sudah sama-sama disetujui.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$this->notificationService->notifyApproveMemoBayar($memo);`
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

        // mewajibkan minimal 2 memo dan nol memo non-disetujui sebelum menyatakan berkas siap diterbitkan SPP oleh PPBJ.
        $allApproved = $pembayaran && $pembayaran->memoBayar()->count() >= 2
            && $pembayaran->memoBayar()->where('status_memo', '!=', 'disetujui')->count() === 0;

        if ($allApproved) {
            $judulPpbj = "Memo Pembayaran Disetujui Lengkap: {$pembayaran->nomor_pembayaran}";
            $pesanPpbj = "Seluruh memo persetujuan (Wadir II dan PPK) telah disetujui untuk paket '{$namaPaket}'. Silakan periksa legalitas penyedia dan terbitkan Perintah Bayar ke Bagian Keuangan.";
            $this->kirimKePeran('ppbj', $judulPpbj, $pesanPpbj, 'pembayaran', $pembayaran->nomor_paket);
        } else {
            $approverLabel = $memo->role_approver === 'wadir_2' ? 'Wakil Direktur II' : 'PPK';
            $judulPpbj = "Memo {$approverLabel} Disetujui: {$pembayaran?->nomor_pembayaran}";
            $pesanPpbj = "{$approverLabel} telah menyetujui memo pembayaran untuk paket '{$namaPaket}' (No Memo: {$memo->nomor_memo}). Menunggu persetujuan pihak lainnya.";
            $this->kirimKePeran('ppbj', $judulPpbj, $pesanPpbj, 'pembayaran', $pembayaran?->nomor_paket);
        }
    }

    /**
     * memberi tahu Bagian Keuangan bahwa Perintah Bayar / SPP telah diterbitkan oleh PPBJ dan siap dicairkan.
     *
     * @param  Pembayaran  $pembayaran  mengambil nomor pembayaran, nama paket, dan nominal pengajuan tagihan.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$this->notificationService->notifyTerbitkanSpp($pembayaran);`
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
     * menutup siklus notifikasi dengan mengabarkan pencairan SP2D ke seluruh aktor yang terlibat pada paket pengadaan terkait.
     *
     * @param  TransaksiPencairan  $transaksi  menelusuri relasi pembayaran, paket, dan permohonan untuk mengumpulkan seluruh ID penerima terkait.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$this->notificationService->notifyPencairanSp2d($transaksi);`
     */
    public function notifyPencairanSp2d(TransaksiPencairan $transaksi): void
    {
        $nomorTransaksi = $transaksi->nomor_transaksi_bank;
        $nominal = number_format($transaksi->nominal_transfer ?? 0, 0, ',', '.');
        $namaPaket = $transaksi->pembayaran?->paket?->nama_paket ?? 'Pengadaan Barang/Jasa';
        $paket = $transaksi->pembayaran?->paket;
        $permohonan = $paket?->permohonan;

        $targetUserIds = collect();

        if ($permohonan?->id_user) {
            $targetUserIds->push($permohonan->id_user);
        }

        if ($paket?->id_user_ppbj) {
            $targetUserIds->push($paket->id_user_ppbj);
        } else {
            $targetUserIds = $targetUserIds->merge(self::resolveActiveUsersByLogicalRoles('ppbj')->pluck('id_user'));
        }

        if ($paket?->id_user_ppk) {
            $targetUserIds->push($paket->id_user_ppk);
        } elseif ($permohonan?->id_user_ppk_awal) {
            $targetUserIds->push($permohonan->id_user_ppk_awal);
        }

        if ($paket?->id_user_pp) {
            $targetUserIds->push($paket->id_user_pp);
        }

        $bastUser = $paket?->penerimaanBarang?->first()?->id_user;
        if ($bastUser) {
            $targetUserIds->push($bastUser);
        } else {
            $targetUserIds = $targetUserIds->merge(self::resolveActiveUsersByLogicalRoles('perlengkapan')->pluck('id_user'));
        }

        $otherRolesUsers = self::resolveActiveUsersByLogicalRoles(['wadir_2', 'perencanaan', 'keuangan'])->pluck('id_user');

        // melakukan unique()->filter() agar pengguna yang merangkap beberapa peran (role gabungan) hanya menerima satu notifikasi penutupan.
        $targetUserIds = $targetUserIds->merge($otherRolesUsers)->unique()->filter();

        $judul = "Pencairan Dana Selesai: {$nomorTransaksi}";
        $pesan = "Pembayaran untuk paket '{$namaPaket}' telah dicairkan oleh Bagian Keuangan sebesar Rp {$nominal} (Nomor Transaksi: {$nomorTransaksi}). Seluruh proses pengadaan selesai.";

        foreach ($targetUserIds as $uId) {
            $this->kirimKeUser($uId, $judul, $pesan, 'pembayaran', $paket?->nomor_paket);
        }
    }
}
