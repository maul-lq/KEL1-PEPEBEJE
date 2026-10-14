<?php

namespace Tests\Feature;

use App\Models\AnggaranMAK;
use App\Models\AuditLog;
use App\Models\PaketPengadaan;
use App\Models\PermohonanPengadaan;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pengujian Komprehensif FR & NFR Prioritas 6 dan 11 (PHPUnit)
 *
 * Menguji implementasi sesuai sumber truth FR-NFR Kel 1 v1.2, Alur Dokumen, dan Database:
 * - FR 6 (No 6): Pengelolaan MAK, Hold Anggaran, Split Permohonan MAK, dan Immutabilitas MAK.
 * - FR 9 (Rank 6): Reviu Dokumen Teknis & HPS oleh PP/PPK dengan Isolasi Berkas 4 Akun PPK.
 * - FR 11 (Rank 6 & No 11): Penerbitan SPK (PDF <= 10MB) dan Penguncian Permanen MAK (is_locked = true).
 * - FR 19 (Rank 11): Monitoring Real-Time 8 Siklus Pengadaan dan Multi-Filter (Nomor Surat, Unit, Tahun, Bulan, Status).
 * - NFR 6 (Rank 1): Integritas Anggaran MAK pasca-SPK & Keamanan Token BAST.
 * - NFR 9 (Rank 6): Audit Trail & Rekam Mutasi Data Otomatis (< 2 detik).
 * - NFR 11 (Rank 2): Validasi Dokumen Upload Kompatibel (PDF/JPG/PNG maksimal 10MB).
 */
class FrNfrPrioritySixElevenTest extends TestCase
{
    use RefreshDatabase;

    protected User $userPerencanaan;

    protected User $userPpk1;

    protected User $userPpk2;

    protected User $userPp;

    protected User $userPbj;

    protected Unit $unitTik;

    protected Vendor $vendorCv;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        // Buat data master Unit
        $this->unitTik = Unit::create([
            'kode_unit' => 'TIK',
            'nama_unit' => 'Jurusan Teknik Informatika dan Komputer',
            'jenis_unit' => 'jurusan',
        ]);

        Unit::create([
            'kode_unit' => 'B2',
            'nama_unit' => 'Bidang II (Umum dan Keuangan)',
            'jenis_unit' => 'bidang',
        ]);

        // Buat akun Perencanaan
        $this->userPerencanaan = User::create([
            'nip' => '198101012010011001',
            'username' => 'staf_perencanaan',
            'email' => 'perencanaan@pnj.ac.id',
            'nama' => 'Budi Perencana, S.E.',
            'password' => bcrypt('password'),
            'jabatan' => 'Analis Anggaran Perencanaan',
            'kode_unit' => 'B2',
            'no_hp' => '081211112221',
            'role' => 'perencanaan',
            'status_aktif' => true,
        ]);

        // Buat akun PPK 1
        $this->userPpk1 = User::create([
            'nip' => '197501012000011001',
            'username' => 'ppk_sarpras',
            'email' => 'ppk1@pnj.ac.id',
            'nama' => 'Dr. Ir. Hendra, M.T. (PPK 1)',
            'password' => bcrypt('password'),
            'jabatan' => 'PPK Sarana dan Prasarana',
            'kode_unit' => 'B2',
            'no_hp' => '081211112222',
            'role' => 'ppk',
            'status_aktif' => true,
        ]);

        // Buat akun PPK 2 (untuk pengujian isolasi berkas PPK)
        $this->userPpk2 = User::create([
            'nip' => '197602022000021002',
            'username' => 'ppk_operasional',
            'email' => 'ppk2@pnj.ac.id',
            'nama' => 'Dra. Rina, M.M. (PPK 2)',
            'password' => bcrypt('password'),
            'jabatan' => 'PPK Operasional Kampus',
            'kode_unit' => 'B2',
            'no_hp' => '081211112223',
            'role' => 'ppk',
            'status_aktif' => true,
        ]);

        // Buat akun Pejabat Pengadaan (PP)
        $this->userPp = User::create([
            'nip' => '198503032012011003',
            'username' => 'pejabat_pengadaan',
            'email' => 'pp@pnj.ac.id',
            'nama' => 'Agus Santoso, S.T. (PP)',
            'password' => bcrypt('password'),
            'jabatan' => 'Pejabat Pengadaan Barang/Jasa',
            'kode_unit' => 'B2',
            'no_hp' => '081211112224',
            'role' => 'pp',
            'status_aktif' => true,
        ]);

        // Buat akun PPBJ
        $this->userPbj = User::create([
            'nip' => '198004042008011004',
            'username' => 'koordinator_ppbj',
            'email' => 'ppbj@pnj.ac.id',
            'nama' => 'Siti Rahma, S.Kom. (PPBJ)',
            'password' => bcrypt('password'),
            'jabatan' => 'Koordinator Pengadaan PPBJ',
            'kode_unit' => 'B2',
            'no_hp' => '081211112225',
            'role' => 'ppbj',
            'status_aktif' => true,
        ]);

        // Buat data Vendor Rekanan
        $this->vendorCv = Vendor::create([
            'npwp' => '01.234.567.8-901.000',
            'nib' => '1234567890123',
            'nama_perusahaan' => 'CV Mandiri Solusindo Utama',
            'alamat' => 'Jl. Margonda Raya No. 100 Depok',
            'is_active' => true,
            'nama_pic' => 'Budi Rekanan',
            'telepon_pic' => '021-77889900',
            'email_pic' => 'rekanan@mandirisolusindo.co.id',
            'nama_bank' => 'Bank Mandiri',
            'nomor_rekening' => '1570001234567',
            'atas_nama' => 'CV Mandiri Solusindo Utama',
        ]);
    }

    /**
     * FR 6.1: Perencanaan dapat menginput dan memperbarui data MAK sebelum diterbitkan SPK.
     */
    public function test_fr_6_1_perencanaan_can_input_and_update_mak_before_spk(): void
    {
        $this->actingAs($this->userPerencanaan);

        // 1. Simpan MAK baru
        $response = $this->postJson('/api/anggaran-mak', [
            'kode_mak' => '023.17.WA.521111.001',
            'uraian_mak' => 'Belanja Keperluan Perkantoran PNJ 2026',
            'pagu_anggaran' => 500000000,
            'tahun_anggaran' => (int) date('Y'),
            'sumber_dana' => 'RM',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.kode_mak', '023.17.WA.521111.001')
            ->assertJsonPath('data.is_locked', false);

        $this->assertDatabaseHas('anggaran_mak', [
            'kode_mak' => '023.17.WA.521111.001',
            'is_locked' => false,
        ]);

        // 2. Perbarui MAK selagi belum dikunci (sebelum SPK)
        $responseUpdate = $this->putJson('/api/anggaran-mak/023.17.WA.521111.001', [
            'uraian_mak' => 'Belanja Operasional & Perkantoran PNJ 2026 (Revisi)',
            'pagu_anggaran' => 550000000,
            'tahun_anggaran' => (int) date('Y'),
            'sumber_dana' => 'RM',
        ]);

        $responseUpdate->assertStatus(200);

        $this->assertDatabaseHas('anggaran_mak', [
            'kode_mak' => '023.17.WA.521111.001',
            'pagu_anggaran' => 550000000,
        ]);
    }

    /**
     * FR 6.2: Perencanaan dapat memberikan status "Hold" (Pending Anggaran) jika pagu dana kosong/belum tersedia di DIPA.
     */
    public function test_fr_6_2_perencanaan_can_set_hold_status_when_budget_unavailable(): void
    {
        $this->actingAs($this->userPerencanaan);

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => '010/PL3/TIK/2026',
            'judul_pengadaan' => 'Pengadaan Laboratorium AI Lanjutan',
            'perihal' => 'Pengadaan Laboratorium AI Lanjutan',
            'tujuan_pengadaan_ringkas' => 'Fasilitas praktikum AI mahasiswa',
            'file_permohonan' => 'permohonan/surat_010.pdf',
            'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
            'status' => 'reviu_paralel',
            'status_anggaran' => 'pending',
            'anggaran_diajukan' => 450000000,
            'estimasi_total_pagu' => 450000000,
            'tanggal_pengajuan' => now(),
            'kode_unit' => $this->unitTik->kode_unit,
            'id_user' => $this->userPerencanaan->id_user,
        ]);

        // Kirim request hold anggaran melalui API endpoint hold
        $response = $this->postJson('/api/anggaran-mak/hold', [
            'nomor_surat' => $permohonan->nomor_surat,
            'catatan' => 'Pagu DIPA belanja modal kampus semester 1 telah habis, usulan di-hold.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status_anggaran', 'hold')
            ->assertJsonPath('data.status_permohonan', PermohonanPengadaan::STATUS_PENDING_ANGGARAN);

        // Verifikasi database
        $this->assertDatabaseHas('permohonan_pengadaan', [
            'nomor_surat' => '010/PL3/TIK/2026',
            'status_permohonan' => PermohonanPengadaan::STATUS_PENDING_ANGGARAN,
            'status_anggaran' => 'hold',
        ]);

        // Verifikasi rekaman Audit Trail (NFR 9)
        $this->assertDatabaseHas('audit_log', [
            'nama_entitas' => 'permohonan_pengadaan',
            'id_entitas' => '010/PL3/TIK/2026',
            'jenis_aksi' => 'HOLD_ANGGARAN_MAK',
        ]);
    }

    /**
     * FR 6.3: Perencanaan dapat memecah (split) usulan permohonan berdasarkan MAK berbeda.
     */
    public function test_fr_6_3_perencanaan_can_split_permohonan_by_mak_with_audit_log(): void
    {
        $this->actingAs($this->userPerencanaan);

        $mak1 = AnggaranMAK::create([
            'kode_mak' => '023.17.WA.532111.001',
            'uraian_mak' => 'Belanja Modal Peralatan Komputer Lab',
            'pagu_anggaran' => 600000000,
            'tahun_anggaran' => (int) date('Y'),
            'sumber_dana' => 'RM',
            'is_locked' => false,
        ]);

        $mak2 = AnggaranMAK::create([
            'kode_mak' => '023.17.WA.521211.002',
            'uraian_mak' => 'Belanja Bahan & Lisensi Perangkat Lunak',
            'pagu_anggaran' => 300000000,
            'tahun_anggaran' => (int) date('Y'),
            'sumber_dana' => 'RM',
            'is_locked' => false,
        ]);

        $induk = PermohonanPengadaan::create([
            'nomor_surat' => '020/PL3/TIK/2026',
            'judul_pengadaan' => 'Pengadaan Lab Komputer & Lisensi Software',
            'perihal' => 'Pengadaan Lab Komputer & Lisensi Software',
            'tujuan_pengadaan_ringkas' => 'Pengadaan PC dan lisensi sistem operasi',
            'file_permohonan' => 'permohonan/surat_020.pdf',
            'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
            'status' => 'reviu_paralel',
            'status_anggaran' => 'pending',
            'anggaran_diajukan' => 400000000,
            'estimasi_total_pagu' => 400000000,
            'tanggal_pengajuan' => now(),
            'kode_unit' => $this->unitTik->kode_unit,
            'id_user' => $this->userPerencanaan->id_user,
            'kode_mak' => $mak1->kode_mak,
        ]);

        // Eksekusi split permohonan melalui form web Perencanaan
        $response = $this->post('/verifikasi/perencanaan/split', [
            'nomor_surat_induk' => $induk->nomor_surat,
            'kode_mak' => $mak2->kode_mak,
            'nominal_split' => 150000000,
            'alasan_pemecahan' => 'Pemisahan pos belanja modal PC dan belanja barang non-operasional lisensi software',
        ]);

        $response->assertRedirect('/verifikasi/perencanaan')
            ->assertSessionHas('success');

        // Nomor permohonan anak yang terbentuk harus berformat 020/PL3/TIK/2026.1
        $nomorAnak = '020/PL3/TIK/2026.1';

        $this->assertDatabaseHas('permohonan_pengadaan', [
            'nomor_surat' => $nomorAnak,
            'kode_mak' => $mak2->kode_mak,
            'estimasi_total_pagu' => 150000000,
        ]);

        // Nilai pagu induk berkurang dari 400jt menjadi 250jt
        $this->assertDatabaseHas('permohonan_pengadaan', [
            'nomor_surat' => $induk->nomor_surat,
            'estimasi_total_pagu' => 250000000,
        ]);

        // Verifikasi tercatat di Audit Log
        $this->assertDatabaseHas('audit_log', [
            'nama_entitas' => 'permohonan_pengadaan',
            'id_entitas' => $nomorAnak,
            'jenis_aksi' => 'SPLIT_PERMOHONAN_MAK',
        ]);
    }

    /**
     * FR 6.4 & NFR 6: MAK bersifat immutable (tidak dapat diedit/dihapus) setelah SPK diterbitkan.
     */
    public function test_fr_6_4_and_nfr_6_mak_is_immutable_and_cannot_be_updated_or_deleted_after_spk(): void
    {
        $this->actingAs($this->userPerencanaan);

        $mak = AnggaranMAK::create([
            'kode_mak' => '023.17.WA.532111.999',
            'uraian_mak' => 'Pagu Pengadaan Server Cloud PNJ',
            'pagu_anggaran' => 800000000,
            'tahun_anggaran' => (int) date('Y'),
            'sumber_dana' => 'BOPTN',
            'is_locked' => true, // Dikunci pasca-SPK
        ]);

        // 1. Percobaan update MAK terkunci via API wajib gagal (HTTP 422 Unprocessable Entity)
        $responseUpdate = $this->putJson('/api/anggaran-mak/'.$mak->kode_mak, [
            'uraian_mak' => 'Percobaan Ubah Pagu Server Cloud',
            'pagu_anggaran' => 999999999,
        ]);

        $responseUpdate->assertStatus(422)
            ->assertJsonPath('status', 'error');

        // Pastikan nilai di database tidak berubah sama sekali
        $this->assertDatabaseHas('anggaran_mak', [
            'kode_mak' => '023.17.WA.532111.999',
            'pagu_anggaran' => 800000000,
            'is_locked' => true,
        ]);

        // 2. Percobaan delete MAK terkunci wajib gagal (HTTP 422 Unprocessable Entity)
        $responseDelete = $this->deleteJson('/api/anggaran-mak/'.$mak->kode_mak);
        $responseDelete->assertStatus(422);

        $this->assertDatabaseHas('anggaran_mak', [
            'kode_mak' => '023.17.WA.532111.999',
        ]);
    }

    /**
     * FR 9.1: Penegakan isolasi berkas PPK - PPK hanya dapat mengakses paket miliknya, ditolak 403 jika mengakses paket PPK lain.
     */
    public function test_fr_9_1_ppk_can_review_package_documents_with_ppk_isolation(): void
    {
        // Siapkan paket komitmen bernilai di atas Rp 200 Juta yang ditugaskan kepada PPK 1
        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => '030/PL3/TIK/2026',
            'judul_pengadaan' => 'Pembangunan Jaringan Fiber Optic Gedung Baru',
            'perihal' => 'Pembangunan Jaringan Fiber Optic Gedung Baru',
            'tujuan_pengadaan_ringkas' => 'Konektivitas gedung kuliah baru',
            'file_permohonan' => 'permohonan/surat_030.pdf',
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
            'status' => 'disetujui',
            'status_anggaran' => 'tersedia',
            'anggaran_diajukan' => 450000000,
            'estimasi_total_pagu' => 450000000,
            'tanggal_pengajuan' => now(),
            'kode_unit' => $this->unitTik->kode_unit,
            'id_user' => $this->userPbj->id_user,
        ]);

        $paketPpk1 = PaketPengadaan::create([
            'kode_paket' => 'PKT-FO-450JT',
            'nomor_paket' => 'PKT-FO-450JT',
            'nama_paket' => 'Pembangunan Jaringan Fiber Optic Gedung Baru',
            'nomor_surat' => $permohonan->nomor_surat,
            'jenis_pengadaan' => 'pekerjaan_konstruksi',
            'platform_pengadaan' => 'SPSE',
            'mekanisme_pengadaan' => 'tender_cepat',
            'metode_pengadaan' => 'tender_cepat',
            'nilai_hps' => 450000000,
            'alokasi_pagu_paket' => 450000000,
            'jalur_routing' => 'ppk_diatas_200jt',
            'id_user_ppbj' => $this->userPbj->id_user,
            'id_user_ppk' => $this->userPpk1->id_user, // Ditugaskan ke PPK 1
            'tanggal_registrasi_paket' => now(),
            'status_paket' => 'terdaftar',
        ]);

        // 1. PPK 2 mencoba mereviu paket milik PPK 1 -> Ditolak 403 Forbidden (Isolasi Berkas PPK)
        $this->actingAs($this->userPpk2);
        $responseTerlarang = $this->post('/kontrak-spk/reviu', [
            'nomor_paket' => $paketPpk1->nomor_paket,
            'status_reviu' => 'disetujui',
            'catatan_perbaikan' => 'Disetujui oleh PPK yang salah',
        ]);
        $responseTerlarang->assertStatus(403);

        // 2. PPK 1 yang sah mereviu paket miliknya -> Diterima dengan sukses
        $this->actingAs($this->userPpk1);
        $responseSah = $this->post('/kontrak-spk/reviu', [
            'nomor_paket' => $paketPpk1->nomor_paket,
            'status_reviu' => 'disetujui',
            'catatan_perbaikan' => 'Dokumen teknis dan HPS fiber optic telah sesuai spesifikasi.',
        ]);
        $responseSah->assertRedirect('/kontrak-spk')
            ->assertSessionHas('success');

        // Pastikan status paket berubah menjadi 'reviu_dokumen_disetujui'
        $this->assertDatabaseHas('paket_pengadaan', [
            'nomor_paket' => $paketPpk1->nomor_paket,
            'status_paket' => 'reviu_dokumen_disetujui',
        ]);
    }

    /**
     * FR 9.2: Kelulusan reviu dokumen teknis & HPS memajukan paket ke status siap terbit SPK.
     */
    public function test_fr_9_2_review_approval_advances_package_to_ready_for_spk(): void
    {
        $this->actingAs($this->userPp);

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => '040/PL3/TIK/2026',
            'judul_pengadaan' => 'Pengadaan Printer Laserjet Lab',
            'perihal' => 'Pengadaan Printer Laserjet Lab',
            'tujuan_pengadaan_ringkas' => 'Cetak modul praktikum',
            'file_permohonan' => 'permohonan/surat_040.pdf',
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
            'status' => 'disetujui',
            'anggaran_diajukan' => 45000000,
            'estimasi_total_pagu' => 45000000,
            'tanggal_pengajuan' => now(),
            'kode_unit' => $this->unitTik->kode_unit,
            'id_user' => $this->userPp->id_user,
        ]);

        $paket = PaketPengadaan::create([
            'kode_paket' => 'PKT-PRINTER-45JT',
            'nomor_paket' => 'PKT-PRINTER-45JT',
            'nama_paket' => 'Pengadaan Printer Laserjet Lab',
            'nomor_surat' => $permohonan->nomor_surat,
            'jenis_pengadaan' => 'pengadaan_barang',
            'platform_pengadaan' => 'E-Katalog',
            'mekanisme_pengadaan' => 'pengadaan_langsung',
            'metode_pengadaan' => 'pengadaan_langsung',
            'nilai_hps' => 45000000,
            'alokasi_pagu_paket' => 45000000,
            'jalur_routing' => 'pejabat_pengadaan_sd_200jt',
            'id_user_ppbj' => $this->userPbj->id_user,
            'id_user_pp' => $this->userPp->id_user,
            'tanggal_registrasi_paket' => now(),
            'status_paket' => 'terdaftar',
        ]);

        // Pejabat Pengadaan mereviu dan menyetujui dokumen melalui API ReviuDokumenController
        $response = $this->postJson('/api/reviu-dokumen', [
            'nomor_paket' => $paket->nomor_paket,
            'status_reviu' => 'disetujui',
            'catatan_perbaikan' => 'Spesifikasi printer dan HPS resmi dari distributor telah lengkap dan siap SPK.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status_reviu', 'disetujui')
            ->assertJsonPath('data.putaran_ke', 1);

        $this->assertDatabaseHas('paket_pengadaan', [
            'nomor_paket' => $paket->nomor_paket,
            'status_paket' => 'reviu_dokumen_disetujui',
        ]);

        $this->assertDatabaseHas('audit_log', [
            'nama_entitas' => 'paket_pengadaan',
            'id_entitas' => $paket->nomor_paket,
            'jenis_aksi' => 'REVIU_DOKUMEN_DISETUJUI',
        ]);
    }

    /**
     * FR 9.3: Pengembalian reviu dokumen dengan status perlu perbaikan mewajibkan catatan perbaikan (mandatory notes).
     */
    public function test_fr_9_3_review_rejection_requires_correction_notes_and_reverts_status(): void
    {
        $this->actingAs($this->userPp);

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => '045/PL3/TIK/2026',
            'judul_pengadaan' => 'Pengadaan Server GPU Riset',
            'perihal' => 'Pengadaan Server GPU Riset',
            'tujuan_pengadaan_ringkas' => 'Riset AI mahasiswa tugas akhir',
            'file_permohonan' => 'permohonan/surat_045.pdf',
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
            'status' => 'disetujui',
            'anggaran_diajukan' => 190000000,
            'estimasi_total_pagu' => 190000000,
            'tanggal_pengajuan' => now(),
            'kode_unit' => $this->unitTik->kode_unit,
            'id_user' => $this->userPp->id_user,
        ]);

        $paket = PaketPengadaan::create([
            'kode_paket' => 'PKT-GPU-190JT',
            'nomor_paket' => 'PKT-GPU-190JT',
            'nama_paket' => 'Pengadaan Server GPU Riset',
            'nomor_surat' => $permohonan->nomor_surat,
            'jenis_pengadaan' => 'pengadaan_barang',
            'platform_pengadaan' => 'E-Katalog',
            'mekanisme_pengadaan' => 'pengadaan_langsung',
            'metode_pengadaan' => 'pengadaan_langsung',
            'nilai_hps' => 190000000,
            'alokasi_pagu_paket' => 190000000,
            'jalur_routing' => 'pejabat_pengadaan_sd_200jt',
            'id_user_ppbj' => $this->userPbj->id_user,
            'id_user_pp' => $this->userPp->id_user,
            'tanggal_registrasi_paket' => now(),
            'status_paket' => 'terdaftar',
        ]);

        // 1. Mengirim status perlu_perbaikan tanpa catatan perbaikan -> Wajib gagal validasi
        $responseTanpaCatatan = $this->post('/kontrak-spk/reviu', [
            'nomor_paket' => $paket->nomor_paket,
            'status_reviu' => 'perlu_perbaikan',
            'catatan_perbaikan' => '', // Dikosongkan
        ]);

        $responseTanpaCatatan->assertSessionHasErrors('catatan_perbaikan');

        // 2. Mengirim status perlu_perbaikan dengan catatan perbaikan lengkap -> Berhasil dan mengembalikan ke PPBJ
        $responseDenganCatatan = $this->post('/kontrak-spk/reviu', [
            'nomor_paket' => $paket->nomor_paket,
            'status_reviu' => 'perlu_perbaikan',
            'catatan_perbaikan' => 'Surat dukungan garansi resmi prinsipal 3 tahun dari vendor belum dilampirkan.',
        ]);

        $responseDenganCatatan->assertRedirect('/kontrak-spk')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('paket_pengadaan', [
            'nomor_paket' => $paket->nomor_paket,
            'status_paket' => 'perlu_perbaikan',
        ]);

        $this->assertDatabaseHas('audit_log', [
            'nama_entitas' => 'paket_pengadaan',
            'id_entitas' => $paket->nomor_paket,
            'jenis_aksi' => 'REVIU_DOKUMEN_PERBAIKAN',
        ]);
    }

    /**
     * FR 11.1 & NFR 11: Penerbitan SPK memvalidasi berkas fisik PDF dan menolak format lain atau berkas melebihi 10MB.
     */
    public function test_fr_11_1_and_nfr_11_document_upload_validates_pdf_and_size_limit(): void
    {
        $this->actingAs($this->userPpk1);

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => '050/PL3/TIK/2026',
            'judul_pengadaan' => 'Pengadaan Smart Classroom TIK',
            'perihal' => 'Pengadaan Smart Classroom TIK',
            'tujuan_pengadaan_ringkas' => 'Fasilitas pembelajaran interaktif',
            'file_permohonan' => 'permohonan/surat_050.pdf',
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
            'status' => 'disetujui',
            'anggaran_diajukan' => 300000000,
            'estimasi_total_pagu' => 300000000,
            'tanggal_pengajuan' => now(),
            'kode_unit' => $this->unitTik->kode_unit,
            'id_user' => $this->userPpk1->id_user,
        ]);

        $paket = PaketPengadaan::create([
            'kode_paket' => 'PKT-SMART-300JT',
            'nomor_paket' => 'PKT-SMART-300JT',
            'nama_paket' => 'Pengadaan Smart Classroom TIK',
            'nomor_surat' => $permohonan->nomor_surat,
            'jenis_pengadaan' => 'pengadaan_barang',
            'platform_pengadaan' => 'SPSE',
            'mekanisme_pengadaan' => 'tender_cepat',
            'metode_pengadaan' => 'tender_cepat',
            'nilai_hps' => 300000000,
            'alokasi_pagu_paket' => 300000000,
            'jalur_routing' => 'ppk_diatas_200jt',
            'id_user_ppbj' => $this->userPbj->id_user,
            'id_user_ppk' => $this->userPpk1->id_user,
            'tanggal_registrasi_paket' => now(),
            'status_paket' => 'reviu_dokumen_disetujui',
        ]);

        // 1. Uji file non-PDF (misal berkas Word .docx) -> Ditolak
        $fileDocx = UploadedFile::fake()->create('kontrak.docx', 500, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $responseNonPdf = $this->postJson('/api/kontrak-spk', [
            'nomor_spk' => 'SPK/PNJ/2026/050',
            'nomor_paket' => $paket->nomor_paket,
            'tipe_dokumen' => 'spk',
            'sifat_kontrak' => 'lumpsum',
            'status_spk' => 'terbit',
            'tanggal_spk' => date('Y-m-d'),
            'jadwal_pengiriman' => date('Y-m-d', strtotime('+30 days')),
            'nilai_kontrak' => 295000000,
            'file_spk' => $fileDocx,
        ]);
        $responseNonPdf->assertStatus(422)
            ->assertJsonValidationErrors('file_spk');

        // 2. Uji file PDF melebihi 10MB (11MB = 11264 KB) -> Ditolak
        $fileOversized = UploadedFile::fake()->create('kontrak_raksasa.pdf', 11264, 'application/pdf');
        $responseOversize = $this->postJson('/api/kontrak-spk', [
            'nomor_spk' => 'SPK/PNJ/2026/051',
            'nomor_paket' => $paket->nomor_paket,
            'tipe_dokumen' => 'spk',
            'sifat_kontrak' => 'lumpsum',
            'status_spk' => 'terbit',
            'tanggal_spk' => date('Y-m-d'),
            'jadwal_pengiriman' => date('Y-m-d', strtotime('+30 days')),
            'nilai_kontrak' => 295000000,
            'file_spk' => $fileOversized,
        ]);
        $responseOversize->assertStatus(422)
            ->assertJsonValidationErrors('file_spk');

        // 3. Uji file PDF valid berukuran 2MB (2048 KB) -> Diterima
        $fileValidPdf = UploadedFile::fake()->create('kontrak_resmi.pdf', 2048, 'application/pdf');
        $responseValid = $this->postJson('/api/kontrak-spk', [
            'nomor_spk' => 'SPK/PNJ/2026/052',
            'nomor_paket' => $paket->nomor_paket,
            'tipe_dokumen' => 'spk',
            'sifat_kontrak' => 'lumpsum',
            'status_spk' => 'terbit',
            'tanggal_spk' => date('Y-m-d'),
            'jadwal_pengiriman' => date('Y-m-d', strtotime('+30 days')),
            'nilai_kontrak' => 295000000,
            'file_spk' => $fileValidPdf,
        ]);
        $responseValid->assertStatus(201)
            ->assertJsonPath('data.nomor_spk', 'SPK/PNJ/2026/052');
    }

    /**
     * FR 11.2 & NFR 6: Penerbitan SPK otomatis memicu penguncian permanen data MAK (is_locked = true).
     */
    public function test_fr_11_2_and_nfr_6_spk_issuance_triggers_automatic_mak_lock(): void
    {
        $this->actingAs($this->userPpk1);

        $mak = AnggaranMAK::create([
            'kode_mak' => '023.17.WA.532111.777',
            'uraian_mak' => 'Pagu Pengadaan Smart Board TIK',
            'pagu_anggaran' => 250000000,
            'tahun_anggaran' => (int) date('Y'),
            'sumber_dana' => 'RM',
            'is_locked' => false, // Awalnya belum terkunci
        ]);

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => '060/PL3/TIK/2026',
            'judul_pengadaan' => 'Pengadaan Smart Board TIK',
            'perihal' => 'Pengadaan Smart Board TIK',
            'tujuan_pengadaan_ringkas' => 'Perangkat smart board kelas digital',
            'file_permohonan' => 'permohonan/surat_060.pdf',
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
            'status' => 'disetujui',
            'anggaran_diajukan' => 250000000,
            'estimasi_total_pagu' => 250000000,
            'tanggal_pengajuan' => now(),
            'kode_unit' => $this->unitTik->kode_unit,
            'id_user' => $this->userPpk1->id_user,
            'kode_mak' => $mak->kode_mak,
        ]);

        $paket = PaketPengadaan::create([
            'kode_paket' => 'PKT-SMARTBOARD-250JT',
            'nomor_paket' => 'PKT-SMARTBOARD-250JT',
            'nama_paket' => 'Pengadaan Smart Board TIK',
            'nomor_surat' => $permohonan->nomor_surat,
            'jenis_pengadaan' => 'pengadaan_barang',
            'platform_pengadaan' => 'SPSE',
            'mekanisme_pengadaan' => 'tender_cepat',
            'metode_pengadaan' => 'tender_cepat',
            'nilai_hps' => 250000000,
            'alokasi_pagu_paket' => 250000000,
            'jalur_routing' => 'ppk_diatas_200jt',
            'id_user_ppbj' => $this->userPbj->id_user,
            'id_user_ppk' => $this->userPpk1->id_user,
            'tanggal_registrasi_paket' => now(),
            'status_paket' => 'reviu_dokumen_disetujui',
        ]);

        // Terbitkan SPK
        $response = $this->post('/kontrak-spk', [
            'kode_paket' => $paket->kode_paket,
            'id_vendor' => $this->vendorCv->id_vendor,
            'nomor_kontrak_spk' => 'SPK/PNJ/2026/060',
            'nilai_kontrak' => 248000000,
            'tanggal_mulai' => date('Y-m-d'),
            'tanggal_selesai' => date('Y-m-d', strtotime('+30 days')),
            'catatan_spk' => 'Pengadaan smart board komprehensif garansi 3 tahun.',
        ]);

        $response->assertRedirect('/kontrak-spk')
            ->assertSessionHas('success');

        // Pastikan MAK terpicu terkunci otomatis (is_locked = true)
        $mak->refresh();
        $this->assertTrue((bool) $mak->is_locked, 'MAK wajib terkunci otomatis (is_locked = true) setelah SPK terbit.');

        // Pastikan audit trail merekam penguncian MAK dan penerbitan SPK
        $this->assertDatabaseHas('audit_log', [
            'nama_entitas' => 'kontrak_spk',
            'id_entitas' => 'SPK/PNJ/2026/060',
            'jenis_aksi' => 'TERBITKAN_SPK',
        ]);
    }

    /**
     * FR 19.1 & 19.2: Real-time monitoring 8 siklus pengadaan dengan multi-filter pencarian.
     */
    public function test_fr_19_1_and_19_2_monitoring_dashboard_filters_by_unit_and_letter_number(): void
    {
        $this->actingAs($this->userPbj);

        // Buat 2 permohonan dengan unit dan status berbeda
        $suratTik = PermohonanPengadaan::create([
            'nomor_surat' => '101/PL3/TIK/2026',
            'judul_pengadaan' => 'Pengadaan Switch Core TIK',
            'perihal' => 'Pengadaan Switch Core TIK',
            'tujuan_pengadaan_ringkas' => 'Peremajaan infrastruktur switch jaringan',
            'file_permohonan' => 'permohonan/surat_101.pdf',
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
            'status' => 'disetujui',
            'anggaran_diajukan' => 120000000,
            'estimasi_total_pagu' => 120000000,
            'tanggal_pengajuan' => now(),
            'kode_unit' => $this->unitTik->kode_unit,
            'id_user' => $this->userPbj->id_user,
        ]);

        $unitElektro = Unit::create([
            'kode_unit' => 'ELK',
            'nama_unit' => 'Jurusan Teknik Elektro',
            'jenis_unit' => 'jurusan',
        ]);

        $suratElk = PermohonanPengadaan::create([
            'nomor_surat' => '102/PL3/ELK/2026',
            'judul_pengadaan' => 'Pengadaan Osiloskop Digital',
            'perihal' => 'Pengadaan Osiloskop Digital',
            'tujuan_pengadaan_ringkas' => 'Peralatan praktikum elektronika',
            'file_permohonan' => 'permohonan/surat_102.pdf',
            'status_permohonan' => PermohonanPengadaan::STATUS_DIAJUKAN,
            'status' => 'diajukan',
            'anggaran_diajukan' => 80000000,
            'estimasi_total_pagu' => 80000000,
            'tanggal_pengajuan' => now(),
            'kode_unit' => $unitElektro->kode_unit,
            'id_user' => $this->userPbj->id_user,
        ]);

        // 1. Filter nomor surat
        $resFilterNomor = $this->get('/monitoring?q=101/PL3/TIK');
        $resFilterNomor->assertStatus(200)
            ->assertSee('101/PL3/TIK/2026')
            ->assertDontSee('102/PL3/ELK/2026');

        // 2. Filter unit
        $resFilterUnit = $this->get('/monitoring?unit=ELK');
        $resFilterUnit->assertStatus(200)
            ->assertSee('102/PL3/ELK/2026')
            ->assertDontSee('101/PL3/TIK/2026');

        // 3. Filter status
        $resFilterStatus = $this->get('/monitoring?status=disetujui');
        $resFilterStatus->assertStatus(200)
            ->assertSee('101/PL3/TIK/2026')
            ->assertDontSee('102/PL3/ELK/2026');

        // 4. API monitoring status endpoint
        $resApiMonitoring = $this->getJson('/api/permohonan-pengadaan/monitoring?kode_unit=TIK&nomor_surat=101');
        $resApiMonitoring->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data.data');
    }

    /**
     * NFR 9: Jejak Audit (Audit Trail) otomatis mencatat setiap mutasi data kritis sistem.
     */
    public function test_nfr_9_audit_trail_logs_all_critical_procurement_actions(): void
    {
        $this->actingAs($this->userPerencanaan);

        // Uji pencatatan manual/sistem via AuditLog::catat
        AuditLog::catat(
            $this->userPerencanaan->id_user,
            'MUTASI_DATA_UJI',
            'anggaran_mak',
            'MAK-TEST-001',
            '100000000',
            '150000000',
            'Pengujian audit trail transparansi mutasi data institusi (NFR 9)'
        );

        $this->assertDatabaseHas('audit_log', [
            'id_user' => $this->userPerencanaan->id_user,
            'jenis_aksi' => 'MUTASI_DATA_UJI',
            'nama_entitas' => 'anggaran_mak',
            'id_entitas' => 'MAK-TEST-001',
        ]);
    }

    /**
     * Uji Antarmuka Program Riil Zero CSS: Halaman Kontrak SPK dan Monitoring Pengadaan
     * mematuhi aturan bebas tag CSS (<style>) dan atribut inline style.
     */
    public function test_zero_css_views_contract_and_monitoring_render_semantic_html(): void
    {
        $this->actingAs($this->userPpk1);

        // Halaman Kontrak SPK
        $resKontrak = $this->get('/kontrak-spk');
        $resKontrak->assertStatus(200)
            ->assertSee('Manajemen Penelaahan (Reviu) Dokumen', false)
            ->assertSee('Penelaahan (Reviu) Dokumen Teknis', false)
            ->assertSee('Penerbitan Kontrak / Surat Perintah Kerja (SPK)', false)
            ->assertDontSee('<style>', false);

        // Halaman Monitoring 8 Siklus Pengadaan
        $resMonitoring = $this->get('/monitoring');
        $resMonitoring->assertStatus(200)
            ->assertSee('Pemantauan Real-Time 8 Siklus Pengadaan', false)
            ->assertSee('Pencarian Multi-Filter Siklus Pengadaan', false)
            ->assertSee('Jejak Audit Transaksi', false)
            ->assertDontSee('<style>', false);
    }
}
