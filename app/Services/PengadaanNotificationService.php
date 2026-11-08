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
use App\Models\TransaksiPencairan;
use App\Models\User;
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

        // 1. Buat master reminder sebagai rujukan alarm
        $reminder = Reminder::create([
            'nomor_paket' => $validNomorPaket,
            'tipe_reminder' => $tipe,
            'waktu_trigger' => now(),
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
     * Mengirimkan notifikasi ke seluruh pengguna aktif yang memiliki peran (role) tertentu.
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

        $targetUsers = User::whereIn('role', $rolesList)
            ->where('status_aktif', true)
            ->get();

        if ($targetUsers->isEmpty()) {
            return collect();
        }

        // Validasi keberadaan nomor paket agar mematuhi integritas foreign key basis data
        $validNomorPaket = null;
        if (! empty($nomorPaket) && PaketPengadaan::where('nomor_paket', $nomorPaket)->exists()) {
            $validNomorPaket = $nomorPaket;
        }

        // Buat 1 master reminder
        $reminder = Reminder::create([
            'nomor_paket' => $validNomorPaket,
            'tipe_reminder' => $tipe,
            'waktu_trigger' => now(),
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

        $pesan = "Unit {$unitName} mengajukan surat permohonan baru ke Bidang 2: '{$permohonan->perihal}' (Estimasi Rp {$estimasi}). Menunggu pemeriksaan berkas & pembuatan Memo Cek MAK oleh Staf Bidang 2 ke Perencanaan.";

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

        $pesan = "Unit {$unitName} memperbarui dokumen usulan pengadaan ke Bidang 2: '{$permohonan->perihal}' (Revisi ke-{$permohonan->revisi_ke}). Silakan periksa dan kirim Memo Cek MAK ke Perencanaan.";

        $this->kirimKePeran(['staff_bidang_2', 'wadir_2'], $judul, $pesan, 'verifikasi', $permohonan->nomor_surat);
    }

    /**
     * Event 3: Staf Bidang 2 Mengirimkan Memo Cek MAK ke Bagian Perencanaan.
     * Notifikasi dikirimkan ke: Perencanaan.
     */
    public function notifyMemoCekMak(PermohonanPengadaan $permohonan, ?string $nomorMemo = null, ?string $catatan = null): void
    {
        $memoRef = $nomorMemo ?: ($permohonan->nomor_memo_cek_mak ?: '-');
        $judul = "Memo Cek MAK dari Bidang 2: {$permohonan->nomor_surat}";
        $pesan = "Bidang 2 mengirimkan Memo Cek MAK ({$memoRef}) untuk usulan '{$permohonan->perihal}'. Catatan: ".($catatan ?: 'Mohon periksa/buat ketersediaan MAK DIPA dan pilih PPK').'.';

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
            $judul = "ACC Kegiatan Wadir 2 Dicatat: {$permohonan->nomor_surat}";
            $pesan = "Wakil Direktur II telah menyetujui kegiatan '{$permohonan->perihal}'. Menunggu persetujuan usulan awal oleh PPK untuk diteruskan ke PPBJ.";

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
                $pesan = "Usulan permohonan '{$permohonan->perihal}' ditolak oleh Pejabat Pembuat Komitmen (PPK). Alasan: ".($catatan ?: 'Perlu perbaikan spesifikasi/KAK').'.';

                $this->kirimKeUser($permohonan->id_user, $judul, $pesan, 'verifikasi', $permohonan->nomor_surat);
            }

            return;
        }

        if ($permohonan->status_permohonan === PermohonanPengadaan::STATUS_DISETUJUI) {
            $this->notifyPersetujuanPenuh($permohonan, $permohonan->kode_mak);
        } else {
            $judul = "Persetujuan Awal PPK Dicatat: {$permohonan->nomor_surat}";
            $pesan = "PPK terpilih telah menyetujui usulan '{$permohonan->perihal}'. Menunggu persetujuan kegiatan (ACC) oleh Wakil Direktur II.";

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
                $judul = "Anggaran Usulan Ditahan (Hold): {$permohonan->nomor_surat}";
                $pesan = "Bagian Perencanaan menandai usulan '{$permohonan->perihal}' sebagai pending anggaran. Catatan: ".($catatan ?: 'Pagu belum tersedia di DIPA').'.';

                $this->kirimKeUser($permohonan->id_user, $judul, $pesan, 'verifikasi', $permohonan->nomor_surat);
            }

            return;
        }

        if ($permohonan->status_permohonan === PermohonanPengadaan::STATUS_DISETUJUI) {
            $this->notifyPersetujuanPenuh($permohonan, $mak->kode_mak);

            return;
        }

        $memoRef = $permohonan->nomor_memo_ketersediaan_mak ?: '-';
        $judul = "Memo Ketersediaan MAK ({$mak->kode_mak}): {$permohonan->nomor_surat}";
        $pesan = "Tim Perencanaan telah memastikan ketersediaan MAK {$mak->kode_mak} (Memo: {$memoRef}) untuk usulan '{$permohonan->perihal}'. Mohon persetujuan Wadir 2 dan PPK agar berkas dapat diteruskan ke PPBJ.";

        $this->kirimKePeran('wadir_2', $judul, $pesan, 'verifikasi', $permohonan->nomor_surat);

        if ($permohonan->id_user_ppk_awal) {
            $this->kirimKeUser($permohonan->id_user_ppk_awal, $judul, $pesan, 'verifikasi', $permohonan->nomor_surat);
        }
    }

    /**
     * Helper: Notifikasi persetujuan penuh (Perencanaan MAK + Wadir 2 + PPK).
     * Dikirimkan ke: Pengaju (User) dan Seluruh Petugas PPBJ.
     */
    public function notifyPersetujuanPenuh(PermohonanPengadaan $permohonan, ?string $kodeMak = null): void
    {
        $makInfo = $kodeMak ? " pada MAK {$kodeMak}" : '';

        // 1. Notifikasi ke Pengusul (User)
        if ($permohonan->id_user) {
            $judulUser = "Permohonan Pengadaan Disetujui: {$permohonan->nomor_surat}";
            $pesanUser = "Usulan pengadaan '{$permohonan->perihal}' telah disetujui lengkap oleh Bidang 2 (Wadir 2) dan PPK{$makInfo}. Dokumen otomatis diteruskan ke PPBJ untuk penentuan metode pengadaan.";

            $this->kirimKeUser($permohonan->id_user, $judulUser, $pesanUser, 'verifikasi', $permohonan->nomor_surat);
        }

        // 2. Notifikasi ke seluruh petugas PPBJ
        $judulPpbj = "Paket Siap Diregistrasi PPBJ: {$permohonan->nomor_surat}";
        $pesanPpbj = "Usulan '{$permohonan->perihal}' nomor {$permohonan->nomor_surat} telah disetujui penuh oleh Wadir 2 dan PPK. Silakan registrasi paket dan tentukan metode pengadaan (Belanja Langsung / Non-Tender / Tender SPSE).";

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

        $pesan = "Anda ditugaskan mengawal paket '{$paket->nama_paket}' (HPS Rp {$hps}, Jalur: {$routing}). Harap lakukan penelaahan dokumen KAK dan persiapan pengadaan.";

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
            $pesan = "Pejabat penelaah meminta perbaikan berkas untuk paket '{$paket->nama_paket}'. Catatan: ".($catatan ?: 'Perbaiki spesifikasi teknis / HPS').'.';

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
        $judul = "Permintaan Approval PPK atas Review PP: {$paket->nomor_paket}";
        $pesan = "Pejabat Pengadaan (PP) telah memverifikasi kelengkapan paket '{$paket->nama_paket}' (Harga Akhir Rp {$hargaAkhir}). Catatan: ".($catatan ?: 'Berkas lengkap, mohon Approval PPK').'.';

        $this->kirimKeUser($paket->id_user_ppk, $judul, $pesan, 'verifikasi', $paket->nomor_paket);
    }

    /**
     * Event 8C: PPK Memberikan Approval pada Paket > 50jt s.d. 200jt -> Memberitahu PP untuk Eksekusi Belanja.
     */
    public function notifyApprovalPpkKePp(PaketPengadaan $paket): void
    {
        $judul = "Approval PPK Diterbitkan (Siap Belanja PP): {$paket->nomor_paket}";
        $pesan = "PPK telah memberikan Approval pada paket '{$paket->nama_paket}'. Silakan Pejabat Pengadaan (PP) mengeksekusi kewenangan belanja.";

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
        $judul = "Eksekusi Belanja Selesai (Upload Dokumen): {$paket->nomor_paket}";
        $pesan = strtoupper($rolePelaksana)." telah mengeksekusi belanja paket '{$paket->nama_paket}'. Silakan PPBJ mengunggah salah satu dokumen perikatan (Surat Pesanan / SPK / Surat Kontrak) dan mengatur Reminder.";

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

        // 1. Tembusan ke Unit Perlengkapan (Persiapan pemeriksaan BAST fisik)
        $judulPerlengkapan = "Tembusan SPK Diterbitkan: {$kontrak->nomor_spk}";
        $pesanPerlengkapan = "SPK nomor {$kontrak->nomor_spk} untuk paket '{$namaPaket}' telah resmi diterbitkan. Harap pantau jadwal pengiriman rekanan dan persiapkan inspeksi fisik barang.";
        $this->kirimKePeran('perlengkapan', $judulPerlengkapan, $pesanPerlengkapan, 'spk', $kontrak->nomor_spk);

        // 2. Tembusan ke Bagian Keuangan (Persiapan pagu dan administrasi pembayaran)
        $judulKeuangan = "Tembusan SPK Baru: {$kontrak->nomor_spk}";
        $pesanKeuangan = "SPK nomor {$kontrak->nomor_spk} untuk paket '{$namaPaket}' senilai Rp {$nilai} telah terbit. Harap catat komitmen belanja anggaran pada MAK terkait.";
        $this->kirimKePeran('keuangan', $judulKeuangan, $pesanKeuangan, 'spk', $kontrak->nomor_spk);

        // 3. Notifikasi ke Pengusul (User)
        $idUserPengaju = $kontrak->paket?->permohonan?->id_user;
        if ($idUserPengaju) {
            $judulPengaju = "SPK Kontrak Diterbitkan: {$kontrak->nomor_spk}";
            $pesanPengaju = "Kontrak pengadaan '{$namaPaket}' telah resmi diterbitkan ke pihak rekanan (SPK: {$kontrak->nomor_spk}). Rekanan akan segera melakukan pengiriman/pengerjaan.";
            $this->kirimKeUser($idUserPengaju, $judulPengaju, $pesanPengaju, 'spk', $kontrak->nomor_spk);
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

        // 1. Notifikasi ke PPBJ untuk segera memproses pengajuan pembayaran
        $judulPpbj = "Barang Diterima & BAST Terbit: {$nomorBast}";
        $pesanPpbj = "Unit Perlengkapan telah menyelesaikan inspeksi fisik dan menerbitkan BAST {$nomorBast} untuk paket '{$namaPaket}'. Silakan ajukan berkas pembayaran ke PPK.";
        $this->kirimKePeran('ppbj', $judulPpbj, $pesanPpbj, 'pembayaran', $bast->nomor_paket);

        // 2. Notifikasi ke Pengusul (User) bahwa barang sudah diterima
        $idUserPengaju = $bast->paket?->permohonan?->id_user;
        if ($idUserPengaju) {
            $judulUser = "Barang/Jasa Selesai Diterima: {$nomorBast}";
            $pesanUser = "Pengadaan '{$namaPaket}' telah tiba dan berhasil diverifikasi oleh Unit Perlengkapan dengan BAST resmi {$nomorBast}.";
            $this->kirimKeUser($idUserPengaju, $judulUser, $pesanUser, 'pengiriman', $bast->nomor_paket);
        }
    }

    /**
     * Event 11: PPBJ Membuat Pengajuan Pembayaran (Memo Bayar Level 1: Wadir II).
     * Notifikasi dikirimkan ke: Wakil Direktur II (Level 1) peninjau awal pembayaran.
     */
    public function notifyPengajuanBayar(Pembayaran $pembayaran, MemoBayar $memoLevel1): void
    {
        $namaPaket = $pembayaran->paket?->nama_paket ?? 'Pengadaan';
        $nominal = number_format($pembayaran->nominal_pengajuan ?? 0, 0, ',', '.');

        $judul = "Pengajuan Pembayaran Termin {$pembayaran->tahap_termin}: {$pembayaran->nomor_pembayaran}";
        $pesan = "PPBJ mengajukan pembayaran termin ke-{$pembayaran->tahap_termin} untuk paket '{$namaPaket}' sebesar Rp {$nominal}. Menunggu telaah dan persetujuan Memo Bayar Tingkat 1 (Wadir II).";

        if ($memoLevel1->id_user) {
            $this->kirimKeUser($memoLevel1->id_user, $judul, $pesan, 'pembayaran', $pembayaran->nomor_paket);
        } else {
            $this->kirimKePeran('wadir_2', $judul, $pesan, 'pembayaran', $pembayaran->nomor_paket);
        }
    }

    /**
     * Event 12: Persetujuan / Disposisi Memo Bayar Bertingkat.
     * Jika Level 1 (Wadir 2) Setuju: Notifikasi ke PPK (Level 2).
     * Jika Level 2 (PPK) Setuju: Notifikasi ke PPBJ bahwa seluruh memo telah di-ACC dan siap terbitkan SPP.
     * Jika Ditolak: Notifikasi ke PPBJ dan User Pengaju.
     */
    public function notifyApproveMemoBayar(MemoBayar $memo): void
    {
        $pembayaran = $memo->pembayaran;
        $namaPaket = $pembayaran?->paket?->nama_paket ?? 'Pengadaan';

        if ($memo->status_memo === 'ditolak') {
            $approverLabel = $memo->role_approver === 'wadir_2' ? 'Wakil Direktur II' : 'PPK';
            $judul = "Memo Pembayaran Ditolak: {$approverLabel}";
            $pesan = "Memo pembayaran untuk paket '{$namaPaket}' ditolak oleh {$approverLabel}. Alasan: ".($memo->catatan_memo ?: 'Perlu perbaikan berkas tagihan').'.';
            $this->kirimKePeran('ppbj', $judul, $pesan, 'pembayaran', $pembayaran?->nomor_paket);

            $idPengaju = $pembayaran?->id_user_pengaju ?? $pembayaran?->paket?->permohonan?->id_user;
            if ($idPengaju) {
                $this->kirimKeUser($idPengaju, $judul, $pesan, 'pembayaran', $pembayaran?->nomor_paket);
            }

            return;
        }

        if ($memo->urutan_level === 1) {
            // Wadir 2 telah ACC (Level 1), teruskan notifikasi ke PPK (Level 2)
            $judulPpk = "Memo Pembayaran Level 2: {$pembayaran?->nomor_pembayaran}";
            $pesanPpk = "Wakil Direktur II telah menyetujui memo bayar untuk paket '{$namaPaket}'. Menunggu telaah dan persetujuan akhir (ACC Level 2) oleh PPK.";
            $ppkId = $pembayaran?->paket?->id_user_ppk ?? $pembayaran?->paket?->permohonan?->id_user_ppk_awal;
            if ($ppkId) {
                $this->kirimKeUser($ppkId, $judulPpk, $pesanPpk, 'pembayaran', $pembayaran?->nomor_paket);
            } else {
                $this->kirimKePeran('ppk', $judulPpk, $pesanPpk, 'pembayaran', $pembayaran?->nomor_paket);
            }
        } elseif ($memo->urutan_level === 2) {
            // PPK telah ACC (Level 2), beri tahu PPBJ untuk menerbitkan Surat Perintah Pembayaran (SPP)
            $judulPpbj = "Memo Bayar Disetujui Lengkap: {$pembayaran?->nomor_pembayaran}";
            $pesanPpbj = "Persetujuan berjenjang Wadir II dan PPK telah lengkap untuk paket '{$namaPaket}'. Silakan terbitkan Surat Perintah Pembayaran (SPP) ke Bagian Keuangan.";
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

        $judul = "Surat Perintah Pembayaran (SPP) Terbit: {$pembayaran->nomor_pembayaran}";
        $pesan = "PPBJ telah menerbitkan SPP nomor {$pembayaran->nomor_pembayaran} untuk paket '{$namaPaket}' senilai Rp {$nominal}. Menunggu verifikasi berkas dan penerbitan SP2D oleh Bagian Keuangan.";

        $this->kirimKePeran('keuangan', $judul, $pesan, 'pembayaran', $pembayaran->nomor_paket);
    }

    /**
     * Event 14: Pencairan Dana SP2D oleh Bagian Keuangan (Siklus Selesai).
     * Notifikasi dikirimkan ke:
     * 1. Pengusul (User) bahwa dana pengadaan telah cair dan siklus tuntas.
     * 2. PPBJ bahwa pencairan SP2D telah selesai.
     */
    public function notifyPencairanSp2d(TransaksiPencairan $transaksi): void
    {
        $nomorTransaksi = $transaksi->nomor_transaksi_bank;
        $nominal = number_format($transaksi->nominal_transfer ?? 0, 0, ',', '.');
        $namaPaket = $transaksi->pembayaran?->paket?->nama_paket ?? 'Pengadaan Barang/Jasa';

        // 1. Notifikasi ke Pengusul (User)
        $idUserPengaju = $transaksi->pembayaran?->paket?->permohonan?->id_user;
        if ($idUserPengaju) {
            $judulUser = "Pencairan Dana SP2D Selesai: {$nomorTransaksi}";
            $pesanUser = "Kabar gembira! Pembayaran untuk usulan '{$namaPaket}' telah resmi dicairkan oleh Bagian Keuangan sebesar Rp {$nominal} (Bukti: {$nomorTransaksi}). Seluruh siklus pengadaan selesai.";
            $this->kirimKeUser($idUserPengaju, $judulUser, $pesanUser, 'pembayaran', $transaksi->pembayaran?->nomor_paket);
        }

        // 2. Notifikasi ke PPBJ
        $judulPpbj = "Pencairan SP2D Tuntas: {$nomorTransaksi}";
        $pesanPpbj = "Bagian Keuangan telah menyelesaikan pencairan SP2D nomor {$nomorTransaksi} sebesar Rp {$nominal} untuk paket '{$namaPaket}'.";
        $this->kirimKePeran('ppbj', $judulPpbj, $pesanPpbj, 'pembayaran', $transaksi->pembayaran?->nomor_paket);

        // 3. Notifikasi Untuk Seluruh Role Terkait (Sesuai Node BPMN N70: Mengirim Notifikasi Untuk Seluruh Role)
        $rolesNotif = ['ppk', 'wadir_2', 'perencanaan', 'perlengkapan', 'staff_bidang_2', 'pp', 'keuangan', 'admin'];
        $judulAll = "Pencairan SP2D Selesai: {$nomorTransaksi}";
        $pesanAll = "Bagian Keuangan telah menyelesaikan pembayaran SP2D {$nomorTransaksi} untuk paket '{$namaPaket}' sebesar Rp {$nominal}. Siklus pengadaan selesai.";
        foreach ($rolesNotif as $roleTarget) {
            $this->kirimKePeran($roleTarget, $judulAll, $pesanAll, 'pembayaran', $transaksi->pembayaran?->nomor_paket);
        }
    }

    /**
     * Fitur Uji Coba Simulasi: Mengirimkan Notifikasi & Email Contoh ke Akun Pengguna Aktif.
     *
     * @param  User  $user  Pengguna yang meminta simulasi uji coba
     * @param  string  $jenis  Kategori skenario simulasi
     * @return NotifikasiUser Rekaman notifikasi yang berhasil dibangkitkan
     */
    public function kirimUjiCoba(User $user, string $jenis = 'pengajuan_baru'): NotifikasiUser
    {
        $dataUji = match ($jenis) {
            'persetujuan_wadir' => [
                'judul' => 'Uji Coba: Permohonan Pengadaan Disetujui Penuh',
                'pesan' => 'Simulasi notifikasi resmi: Usulan pengadaan laboratorium komputer telah disetujui penuh oleh Wadir 2 dan Perencanaan.',
                'tipe' => 'verifikasi',
            ],
            'penolakan_wadir' => [
                'judul' => 'Uji Coba: Catatan Perbaikan Usulan Pengadaan',
                'pesan' => 'Simulasi notifikasi: Berkas KAK memerlukan pembaruan rincian spesifikasi teknis prosesor dan RAM.',
                'tipe' => 'verifikasi',
            ],
            'tembusan_spk' => [
                'judul' => 'Uji Coba: Tembusan Kontrak SPK Baru',
                'pesan' => 'Simulasi notifikasi: SPK nomor 045/PL3/SPK/2026 telah terbit. Rekanan akan segera melakukan pengiriman barang.',
                'tipe' => 'spk',
            ],
            'barang_sampai' => [
                'judul' => 'Uji Coba: Barang/Jasa Diterima & BAST Terbit',
                'pesan' => 'Simulasi notifikasi: Perlengkapan telah menyelesaikan inspeksi fisik dan menerbitkan BAST resmi token HMAC.',
                'tipe' => 'pengiriman',
            ],
            'memo_bayar' => [
                'judul' => 'Uji Coba: Pengajuan Memo Pembayaran',
                'pesan' => 'Simulasi notifikasi: Berkas tagihan rekanan telah diverifikasi dan siap disetujui berjenjang oleh PPK dan Wadir 2.',
                'tipe' => 'pembayaran',
            ],
            'spp_terbit' => [
                'judul' => 'Uji Coba: Surat Perintah Pembayaran (SPP) Terbit',
                'pesan' => 'Simulasi notifikasi: SPP telah diterbitkan ke Bagian Keuangan untuk proses verifikasi pencairan SP2D.',
                'tipe' => 'pembayaran',
            ],
            'sp2d_cair' => [
                'judul' => 'Uji Coba: Pencairan SP2D Selesai',
                'pesan' => 'Simulasi notifikasi: Dana pengadaan sebesar Rp 45.000.000 telah sukses ditransfer ke rekening bank rekanan.',
                'tipe' => 'pembayaran',
            ],
            default => [
                'judul' => 'Uji Coba: Pengajuan Usulan Pengadaan Baru',
                'pesan' => 'Simulasi notifikasi: Unit Anda mengajukan permohonan baru untuk pengadaan perangkat praktikum jaringan komputer.',
                'tipe' => 'verifikasi',
            ],
        };

        // Prioritaskan menghubungkan ke paket riil yang ada di database jika tersedia, atau gunakan null
        $existingPaket = PaketPengadaan::first();
        $nomorPaketUji = $existingPaket ? $existingPaket->nomor_paket : null;

        return $this->kirimKeUser($user, $dataUji['judul'], $dataUji['pesan'], $dataUji['tipe'], $nomorPaketUji);
    }
}
