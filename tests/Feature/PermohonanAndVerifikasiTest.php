<?php

namespace Tests\Feature;

use App\Models\AnggaranMAK;
use App\Models\PermohonanPengadaan;
use App\Models\Unit;
use App\Models\User;
use App\Models\VerifikasiParalel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Test Suite Komprehensif Milestone 3: Permohonan & Verifikasi Views Enhancement
 *
 * Menguji fitur-fitur:
 * - Formulir Usulan Pengadaan Srikandi & Upload Multi-Berkas (FR 3.1)
 * - Validasi Tipe Berkas & Ukuran Maksimal 10 MB (FR 3.2)
 * - Modal Interaktif Revisi Permohonan (FR 4 / Figma 509:1651)
 * - Pembatalan / Hapus Pengajuan Draft & Ditolak (Figma 498:4950)
 * - Telaah Berkas Staf Bidang II (FR 5.1 / Figma 565:19857)
 * - Disposisi 3 Opsi Eksekutif Wadir II (FR 5.2 - FR 5.3 / Figma 565:20075)
 * - Alokasi MAK & Hold Anggaran Perencanaan (FR 6.1 - FR 6.2 / Figma 565:17450)
 * - Pemecahan Dinamis Pos MAK / Split MAK (FR 6.3)
 * - Dual Viewmode Rendering Parity (DEV_VIEWMODE)
 * - Zero Inline Script Policy Compliance
 */
class PermohonanAndVerifikasiTest extends TestCase
{
    use RefreshDatabase;

    protected User $userPemohon;

    protected User $staffB2;

    protected User $wadir2;

    protected User $perencanaan;

    protected User $admin;

    protected Unit $unit;

    protected AnggaranMAK $mak1;

    protected AnggaranMAK $mak2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('public');

        $this->unit = Unit::firstOrCreate(
            ['kode_unit' => 'TIK'],
            ['nama_unit' => 'Jurusan Teknik Informatika dan Komputer', 'jenis_unit' => 'jurusan']
        );

        $this->userPemohon = User::where('role', 'user')->first() ?? User::factory()->create([
            'role' => 'user',
            'kode_unit' => $this->unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->staffB2 = User::where('role', 'staff_bidang_2')->first() ?? User::factory()->create([
            'role' => 'staff_bidang_2',
            'kode_unit' => $this->unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->wadir2 = User::where('role', 'wadir_2')->first() ?? User::factory()->create([
            'role' => 'wadir_2',
            'kode_unit' => $this->unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->perencanaan = User::where('role', 'perencanaan')->first() ?? User::factory()->create([
            'role' => 'perencanaan',
            'kode_unit' => $this->unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->admin = User::where('role', 'admin')->first() ?? User::factory()->create([
            'role' => 'admin',
            'kode_unit' => $this->unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->mak1 = AnggaranMAK::firstOrCreate(
            ['kode_mak' => '023.17.WA.521211'],
            [
                'uraian_mak' => 'Belanja Bahan Penunjang Laboratorium Komputer',
                'sumber_dana' => 'BOPTN',
                'tahun_anggaran' => (int) date('Y'),
                'pagu_anggaran' => 300000000,
                'sisa_pagu_mak' => 300000000,
                'is_locked' => false,
            ]
        );

        $this->mak2 = AnggaranMAK::firstOrCreate(
            ['kode_mak' => '023.17.WA.532111'],
            [
                'uraian_mak' => 'Belanja Modal Peralatan dan Mesin Server Cloud',
                'sumber_dana' => 'PNBP',
                'tahun_anggaran' => (int) date('Y'),
                'pagu_anggaran' => 500000000,
                'sisa_pagu_mak' => 500000000,
                'is_locked' => false,
            ]
        );
    }

    /**
     * 1. Halaman permohonan create dapat diakses pada kedua viewmode (backend & frontend).
     */
    public function test_permohonan_create_page_renders_in_both_viewmodes(): void
    {
        $this->actingAs($this->userPemohon);

        // Mode backend
        config(['app.dev_viewmode' => 'backend']);
        $respBackend = $this->get(route('permohonan.create'));
        $respBackend->assertStatus(200);

        // Mode frontend
        config(['app.dev_viewmode' => 'frontend']);
        $respFrontend = $this->get(route('permohonan.create'));
        $respFrontend->assertStatus(200);
        $respFrontend->assertSee('Ajukan Usulan Pengadaan Baru');
        $respFrontend->assertSee('Nomor Registrasi Surat Srikandi');
    }

    /**
     * 2. User dapat menyimpan usulan permohonan pengadaan baru dengan berkas PDF.
     */
    public function test_user_can_store_permohonan_pengadaan_with_uploads(): void
    {
        $this->actingAs($this->userPemohon);

        $fakePdf = UploadedFile::fake()->create('surat_srikandi.pdf', 1024, 'application/pdf');
        $fakeKak = UploadedFile::fake()->create('kak_pengadaan.pdf', 2048, 'application/pdf');
        $fakeSpek = UploadedFile::fake()->create('spek_rab.pdf', 1536, 'application/pdf');

        $payload = [
            'nomor_surat' => 'B/TEST-M3/PL3/TIK/2026',
            'perihal' => 'Pengadaan Fasilitas Lab AI dan Komputasi Awan',
            'tujuan_pengadaan_ringkas' => 'Mendukung praktikum AI mahasiswa PNJ',
            'anggaran_diajukan' => 175000000,
            'kode_unit' => $this->unit->kode_unit,
            'tanggal_pengajuan' => date('Y-m-d'),
            'file_surat_permohonan' => $fakePdf,
            'file_kak' => $fakeKak,
            'file_spesifikasi_teknis' => $fakeSpek,
        ];

        $response = $this->post(route('permohonan.store'), $payload);
        $response->assertRedirect(route('permohonan.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('permohonan_pengadaan', [
            'nomor_surat' => 'B/TEST-M3/PL3/TIK/2026',
            'judul_pengadaan' => 'Pengadaan Fasilitas Lab AI dan Komputasi Awan',
            'kode_unit' => $this->unit->kode_unit,
        ]);

        // Verifikasi inisialisasi antrean verifikasi paralel untuk Staf B2, Wadir 2, dan Perencanaan
        $this->assertDatabaseHas('verifikasi_paralel', [
            'nomor_surat' => 'B/TEST-M3/PL3/TIK/2026',
            'role_verifikator' => 'staff_bidang_2',
            'status_keputusan' => 'menunggu',
        ]);
        $this->assertDatabaseHas('verifikasi_paralel', [
            'nomor_surat' => 'B/TEST-M3/PL3/TIK/2026',
            'role_verifikator' => 'wadir_2',
            'status_keputusan' => 'menunggu',
        ]);
        $this->assertDatabaseHas('verifikasi_paralel', [
            'nomor_surat' => 'B/TEST-M3/PL3/TIK/2026',
            'role_verifikator' => 'perencanaan',
            'status_keputusan' => 'menunggu',
        ]);
    }

    /**
     * 3. Validasi kegagalan input wajib dan penolakan berkas non-PDF.
     */
    public function test_store_permohonan_validates_required_fields_and_mimetypes(): void
    {
        $this->actingAs($this->userPemohon);

        // Uji kolom kosong
        $response = $this->post(route('permohonan.store'), []);
        $response->assertSessionHasErrors(['nomor_surat', 'perihal', 'anggaran_diajukan', 'kode_unit', 'tanggal_pengajuan']);

        // Uji berkas bukan PDF (misal .exe atau .txt)
        $invalidFile = UploadedFile::fake()->create('script.sh', 500, 'application/x-sh');
        $responseFile = $this->post(route('permohonan.store'), [
            'nomor_surat' => 'B/INVALID-FILE/PL3/TIK/2026',
            'perihal' => 'Pengadaan Server',
            'anggaran_diajukan' => 100000000,
            'kode_unit' => $this->unit->kode_unit,
            'tanggal_pengajuan' => date('Y-m-d'),
            'file_surat_permohonan' => $invalidFile,
        ]);
        $responseFile->assertSessionHasErrors(['file_surat_permohonan']);
    }

    /**
     * 4. User dapat mengirimkan revisi usulan permohonan pengadaan yang ditolak/perlu revisi.
     */
    public function test_user_can_submit_permohonan_revisi_via_modal_form(): void
    {
        $this->actingAs($this->userPemohon);

        $induk = PermohonanPengadaan::create([
            'nomor_surat' => 'B/INDUK-TOLAK/PL3/TIK/2026',
            'perihal' => 'Pengadaan Server Awal',
            'tujuan_pengadaan_ringkas' => 'Server praktikum',
            'anggaran_diajukan' => 200000000,
            'estimasi_total_pagu' => 200000000,
            'kode_unit' => $this->unit->kode_unit,
            'id_user' => $this->userPemohon->id_user,
            'status_permohonan' => PermohonanPengadaan::STATUS_DITOLAK,
            'alasan_revisi' => 'Pagu terlalu tinggi, mohon rasionalkan',
            'tanggal_pengajuan' => now(),
            'revisi_ke' => 0,
        ]);

        $fakeKak = UploadedFile::fake()->create('kak_revisi.pdf', 1024, 'application/pdf');

        $payload = [
            'nomor_surat_lama' => $induk->nomor_surat,
            'nomor_surat_baru' => 'B/INDUK-TOLAK-REV1/PL3/TIK/2026',
            'perihal' => 'Pengadaan Server Awal (Revisi)',
            'tujuan_pengadaan_ringkas' => 'Server praktikum yang telah disesuaikan',
            'anggaran_diajukan' => 160000000,
            'keterangan_revisi' => 'Pagu telah dirasionalkan menjadi 160 juta sesuai arahan Wadir 2',
            'file_kak' => $fakeKak,
        ];

        $response = $this->post(route('permohonan.revisi'), $payload);
        $response->assertRedirect(route('permohonan.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('permohonan_pengadaan', [
            'nomor_surat' => 'B/INDUK-TOLAK-REV1/PL3/TIK/2026',
            'nomor_surat_revisi' => $induk->nomor_surat,
            'revisi_ke' => 1,
            'estimasi_total_pagu' => 160000000,
            'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
        ]);
    }

    /**
     * 5. User dapat membatalkan dan menghapus pengajuan yang masih berstatus draft/diajukan/ditolak.
     */
    public function test_user_can_delete_draft_or_rejected_permohonan(): void
    {
        $this->actingAs($this->userPemohon);

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/HAPUS-ME/PL3/TIK/2026',
            'perihal' => 'Usulan Salah Ketik',
            'tujuan_pengadaan_ringkas' => 'Draft usulan',
            'anggaran_diajukan' => 50000000,
            'estimasi_total_pagu' => 50000000,
            'kode_unit' => $this->unit->kode_unit,
            'id_user' => $this->userPemohon->id_user,
            'status_permohonan' => PermohonanPengadaan::STATUS_DIAJUKAN,
            'tanggal_pengajuan' => now(),
        ]);

        $response = $this->delete(route('permohonan.destroy', $permohonan->nomor_surat));
        $response->assertRedirect(route('permohonan.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('permohonan_pengadaan', [
            'nomor_surat' => 'B/HAPUS-ME/PL3/TIK/2026',
        ]);

        $this->assertDatabaseHas('audit_log', [
            'jenis_aksi' => 'HAPUS_PERMOHONAN',
            'id_entitas' => 'B/HAPUS-ME/PL3/TIK/2026',
        ]);
    }

    /**
     * 6. User tidak dapat membatalkan permohonan yang telah disetujui (ACC) atau diproses lanjut.
     */
    public function test_user_cannot_delete_approved_or_processed_permohonan(): void
    {
        $this->actingAs($this->userPemohon);

        $approved = PermohonanPengadaan::create([
            'nomor_surat' => 'B/APPROVED-LOCKED/PL3/TIK/2026',
            'perihal' => 'Usulan Pengadaan Resmi',
            'anggaran_diajukan' => 120000000,
            'estimasi_total_pagu' => 120000000,
            'kode_unit' => $this->unit->kode_unit,
            'id_user' => $this->userPemohon->id_user,
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
            'tanggal_pengajuan' => now(),
        ]);

        $response = $this->delete(route('permohonan.destroy', $approved->nomor_surat));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('permohonan_pengadaan', [
            'nomor_surat' => 'B/APPROVED-LOCKED/PL3/TIK/2026',
        ]);
    }

    /**
     * 7. Staf Bidang II dapat memeriksa checklist administrasi dan meneruskan telaah ke Wadir II.
     */
    public function test_staff_bidang_2_can_review_and_forward_permohonan(): void
    {
        $this->actingAs($this->staffB2);

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/STAFF-B2-TEST/PL3/TIK/2026',
            'perihal' => 'Pengadaan Modul Praktikum IoT',
            'tujuan_pengadaan_ringkas' => 'Modul IoT untuk mata kuliah Embedded Systems',
            'anggaran_diajukan' => 85000000,
            'estimasi_total_pagu' => 85000000,
            'kode_unit' => $this->unit->kode_unit,
            'id_user' => $this->userPemohon->id_user,
            'status_permohonan' => PermohonanPengadaan::STATUS_DIAJUKAN,
            'tanggal_pengajuan' => now(),
        ]);

        VerifikasiParalel::create([
            'id_user' => $this->staffB2->id_user,
            'nomor_surat' => $permohonan->nomor_surat,
            'role_verifikator' => 'staff_bidang_2',
            'status_keputusan' => 'menunggu',
        ]);

        $payload = [
            'nomor_surat' => $permohonan->nomor_surat,
            'is_kak_valid' => 1,
            'is_spesifikasi_valid' => 1,
            'is_srikandi_valid' => 1,
            'catatan_staff' => 'Berkas KAK dan spek telah diverifikasi lengkap, direkomendasikan ACC.',
        ];

        $response = $this->post(route('verifikasi.bidang-2.teruskan'), $payload);
        $response->assertRedirect(route('verifikasi.bidang-2'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('verifikasi_paralel', [
            'nomor_surat' => $permohonan->nomor_surat,
            'role_verifikator' => 'staff_bidang_2',
            'status_keputusan' => 'disetujui',
            'catatan_alasan' => 'Berkas KAK dan spek telah diverifikasi lengkap, direkomendasikan ACC.',
        ]);
    }

    /**
     * 8. Wadir II dapat memberikan persetujuan (ACC), mengalirkan ke status disetujui jika Perencanaan telah ACC.
     */
    public function test_wadir_2_can_approve_permohonan_acc(): void
    {
        $this->actingAs($this->wadir2);

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/WADIR2-ACC-TEST/PL3/TIK/2026',
            'perihal' => 'Pengadaan Fasilitas Cloud Laboratorium',
            'anggaran_diajukan' => 90000000,
            'estimasi_total_pagu' => 90000000,
            'kode_unit' => $this->unit->kode_unit,
            'id_user' => $this->userPemohon->id_user,
            'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
            'tanggal_pengajuan' => now(),
        ]);

        // Simulasikan Perencanaan telah menyetujui lebih dulu
        VerifikasiParalel::create([
            'id_user' => $this->perencanaan->id_user,
            'nomor_surat' => $permohonan->nomor_surat,
            'role_verifikator' => 'perencanaan',
            'status_keputusan' => 'disetujui',
        ]);

        $payload = [
            'nomor_surat' => $permohonan->nomor_surat,
            'keputusan' => 'disetujui',
            'catatan_alasan' => 'Disetujui untuk diproses ke PPBJ.',
        ];

        $response = $this->post(route('verifikasi.wadir-2.keputusan'), $payload);
        $response->assertRedirect(route('verifikasi.wadir-2'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('verifikasi_paralel', [
            'nomor_surat' => $permohonan->nomor_surat,
            'role_verifikator' => 'wadir_2',
            'status_keputusan' => 'disetujui',
        ]);

        // Karena Perencanaan sudah ACC, status permohonan harus menjadi DISETUJUI
        $this->assertDatabaseHas('permohonan_pengadaan', [
            'nomor_surat' => $permohonan->nomor_surat,
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
        ]);
    }

    /**
     * 9. Wadir II menolak usulan mewajibkan pengisian catatan alasan (FR 5.3).
     */
    public function test_wadir_2_rejecting_permohonan_requires_mandatory_catatan(): void
    {
        $this->actingAs($this->wadir2);

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/WADIR2-REJECT-TEST/PL3/TIK/2026',
            'perihal' => 'Pengadaan Barang Berlebihan',
            'anggaran_diajukan' => 400000000,
            'estimasi_total_pagu' => 400000000,
            'kode_unit' => $this->unit->kode_unit,
            'id_user' => $this->userPemohon->id_user,
            'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
            'tanggal_pengajuan' => now(),
        ]);

        // Uji tolak tanpa catatan alasan -> harus gagal validasi
        $respFailed = $this->post(route('verifikasi.wadir-2.keputusan'), [
            'nomor_surat' => $permohonan->nomor_surat,
            'keputusan' => 'ditolak',
            'catatan_alasan' => '',
        ]);
        $respFailed->assertSessionHasErrors(['catatan_alasan']);

        // Uji tolak dengan catatan alasan -> berhasil disimpan
        $respSuccess = $this->post(route('verifikasi.wadir-2.keputusan'), [
            'nomor_surat' => $permohonan->nomor_surat,
            'keputusan' => 'ditolak',
            'catatan_alasan' => 'Pagu anggaran tidak efisien dan melampaui standar satuan harga.',
        ]);
        $respSuccess->assertRedirect(route('verifikasi.wadir-2'));

        $this->assertDatabaseHas('permohonan_pengadaan', [
            'nomor_surat' => $permohonan->nomor_surat,
            'status_permohonan' => PermohonanPengadaan::STATUS_DITOLAK,
            'alasan_revisi' => 'Pagu anggaran tidak efisien dan melampaui standar satuan harga.',
        ]);
    }

    /**
     * 10. Wadir II dapat meminta revisi berkas dengan catatan wajib (Figma 565:20075).
     */
    public function test_wadir_2_can_request_revision_with_mandatory_catatan(): void
    {
        $this->actingAs($this->wadir2);

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/WADIR2-REVISI-TEST/PL3/TIK/2026',
            'perihal' => 'Pengadaan Fasilitas Lab Robotika',
            'anggaran_diajukan' => 250000000,
            'estimasi_total_pagu' => 250000000,
            'kode_unit' => $this->unit->kode_unit,
            'id_user' => $this->userPemohon->id_user,
            'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
            'tanggal_pengajuan' => now(),
        ]);

        // Uji opsi revisi dengan catatan perbaikan
        $response = $this->post(route('verifikasi.wadir-2.keputusan'), [
            'nomor_surat' => $permohonan->nomor_surat,
            'keputusan' => 'revisi',
            'catatan_alasan' => 'Mohon revisi KAK pada bagian spesifikasi sensor dan motor servo.',
        ]);
        $response->assertRedirect(route('verifikasi.wadir-2'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('verifikasi_paralel', [
            'nomor_surat' => $permohonan->nomor_surat,
            'role_verifikator' => 'wadir_2',
            'status_keputusan' => 'revisi',
            'catatan_alasan' => 'Mohon revisi KAK pada bagian spesifikasi sensor dan motor servo.',
        ]);

        $this->assertDatabaseHas('permohonan_pengadaan', [
            'nomor_surat' => $permohonan->nomor_surat,
            'status_permohonan' => 'perlu_revisi',
            'alasan_revisi' => 'Mohon revisi KAK pada bagian spesifikasi sensor dan motor servo.',
        ]);
    }

    /**
     * 11. Subbagian Perencanaan dapat mengalokasikan rekening MAK DIPA yang tersedia.
     */
    public function test_perencanaan_can_allocate_mak_budget(): void
    {
        $this->actingAs($this->perencanaan);

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/PERENC-ALOKASI-TEST/PL3/TIK/2026',
            'perihal' => 'Belanja Bahan Habis Pakai Lab',
            'anggaran_diajukan' => 60000000,
            'estimasi_total_pagu' => 60000000,
            'kode_unit' => $this->unit->kode_unit,
            'id_user' => $this->userPemohon->id_user,
            'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
            'tanggal_pengajuan' => now(),
        ]);

        $paguAwal = (float) $this->mak1->sisa_pagu_mak;

        $payload = [
            'nomor_surat' => $permohonan->nomor_surat,
            'kode_mak' => $this->mak1->kode_mak,
            'nominal_alokasi' => 60000000,
            'keputusan_anggaran' => 'setuju',
            'catatan_anggaran' => 'Dialokasikan menggunakan MAK BOPTN 2026.',
        ];

        $response = $this->post(route('verifikasi.perencanaan.mak'), $payload);
        $response->assertRedirect(route('verifikasi.perencanaan'));
        $response->assertSessionHas('success');

        // Verifikasi tabel alokasi_mak
        $this->assertDatabaseHas('alokasi_mak', [
            'nomor_surat' => $permohonan->nomor_surat,
            'kode_mak' => $this->mak1->kode_mak,
            'nominal_alokasi' => 60000000,
        ]);

        // Verifikasi pengurangan sisa pagu MAK
        $this->mak1->refresh();
        $this->assertEquals($paguAwal - 60000000, (float) $this->mak1->sisa_pagu_mak);
    }

    /**
     * 12. Subbagian Perencanaan dapat menahan (Hold) anggaran jika pagu belum mencukupi (FR 6.2).
     */
    public function test_perencanaan_can_hold_budget_status(): void
    {
        $this->actingAs($this->perencanaan);

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/PERENC-HOLD-TEST/PL3/TIK/2026',
            'perihal' => 'Pengadaan Fasilitas Luar Pagu',
            'anggaran_diajukan' => 450000000,
            'estimasi_total_pagu' => 450000000,
            'kode_unit' => $this->unit->kode_unit,
            'id_user' => $this->userPemohon->id_user,
            'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
            'tanggal_pengajuan' => now(),
        ]);

        $payload = [
            'nomor_surat' => $permohonan->nomor_surat,
            'kode_mak' => $this->mak1->kode_mak,
            'nominal_alokasi' => 450000000,
            'keputusan_anggaran' => 'tahan',
            'catatan_anggaran' => 'Pagu DIPA saat ini belum mencukupi, usulan ditahan menunggu revisi DIPA.',
        ];

        $response = $this->post(route('verifikasi.perencanaan.mak'), $payload);
        $response->assertRedirect(route('verifikasi.perencanaan'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('permohonan_pengadaan', [
            'nomor_surat' => $permohonan->nomor_surat,
            'status_permohonan' => PermohonanPengadaan::STATUS_PENDING_ANGGARAN,
            'status_anggaran' => 'hold',
        ]);
    }

    /**
     * 13. Subbagian Perencanaan dapat memecah usulan MAK menjadi berkas pecahan dinamis (FR 6.3).
     */
    public function test_perencanaan_can_split_mak_into_multiple_sub_berkas(): void
    {
        $this->actingAs($this->perencanaan);

        $induk = PermohonanPengadaan::create([
            'nomor_surat' => 'B/SPLIT-INDUK-01/PL3/TIK/2026',
            'perihal' => 'Pengadaan Laboratorium Jaringan Terpadu',
            'tujuan_pengadaan_ringkas' => 'Laboratorium terpadu',
            'anggaran_diajukan' => 200000000,
            'estimasi_total_pagu' => 200000000,
            'kode_unit' => $this->unit->kode_unit,
            'id_user' => $this->userPemohon->id_user,
            'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
            'tanggal_pengajuan' => now(),
        ]);

        $payload = [
            'nomor_surat_induk' => $induk->nomor_surat,
            'alasan_pemecahan' => 'Pemisahan pos MAK belanja bahan dan belanja modal peralatan (FR 6.3)',
            'items' => [
                [
                    'nomor_surat_pecahan' => 'B/SPLIT-ANAK-01A/PL3/TIK/2026',
                    'judul_pengadaan' => 'Pengadaan Bahan Praktikum Jaringan (Bagian 1)',
                    'kode_mak' => $this->mak1->kode_mak,
                    'estimasi_pagu_pecahan' => 80000000,
                ],
                [
                    'nomor_surat_pecahan' => 'B/SPLIT-ANAK-01B/PL3/TIK/2026',
                    'judul_pengadaan' => 'Pengadaan Switch & Router Cisco (Bagian 2)',
                    'kode_mak' => $this->mak2->kode_mak,
                    'estimasi_pagu_pecahan' => 100000000,
                ],
            ],
        ];

        $response = $this->post(route('verifikasi.perencanaan.split'), $payload);
        $response->assertRedirect(route('verifikasi.perencanaan'));
        $response->assertSessionHas('success');

        // Verifikasi anak split terbentuk
        $this->assertDatabaseHas('permohonan_pengadaan', [
            'nomor_surat' => 'B/SPLIT-ANAK-01A/PL3/TIK/2026',
            'nomor_surat_induk_split' => $induk->nomor_surat,
            'estimasi_total_pagu' => 80000000,
            'kode_mak' => $this->mak1->kode_mak,
        ]);

        $this->assertDatabaseHas('permohonan_pengadaan', [
            'nomor_surat' => 'B/SPLIT-ANAK-01B/PL3/TIK/2026',
            'nomor_surat_induk_split' => $induk->nomor_surat,
            'estimasi_total_pagu' => 100000000,
            'kode_mak' => $this->mak2->kode_mak,
        ]);

        // Verifikasi sisa pagu induk berkurang
        $induk->refresh();
        $this->assertEquals(20000000, (float) $induk->estimasi_total_pagu);

        // Uji pemecahan melebihi pagu induk ditolak
        $respOver = $this->post(route('verifikasi.perencanaan.split'), [
            'nomor_surat_induk' => $induk->nomor_surat,
            'alasan_pemecahan' => 'Split melebihi pagu',
            'items' => [
                [
                    'nomor_surat_pecahan' => 'B/OVER-1/PL3/TIK/2026',
                    'judul_pengadaan' => 'Pecahan A',
                    'kode_mak' => $this->mak1->kode_mak,
                    'estimasi_pagu_pecahan' => 50000000,
                ],
                [
                    'nomor_surat_pecahan' => 'B/OVER-2/PL3/TIK/2026',
                    'judul_pengadaan' => 'Pecahan B',
                    'kode_mak' => $this->mak2->kode_mak,
                    'estimasi_pagu_pecahan' => 50000000,
                ],
            ],
        ]);
        $respOver->assertSessionHas('error');
    }

    /**
     * 14. Seluruh tampilan M3 bebas dari tag inline <script> dan mematuhi integrasi komponen.
     */
    public function test_m3_views_render_components_correctly_without_inline_scripts(): void
    {
        $viewPaths = [
            resource_path('views/permohonan/create.blade.php'),
            resource_path('views/permohonan/index.blade.php'),
            resource_path('views/permohonan/show.blade.php'),
            resource_path('views/verifikasi/bidang_2.blade.php'),
            resource_path('views/verifikasi/wadir_2.blade.php'),
            resource_path('views/verifikasi/perencanaan.blade.php'),
        ];

        foreach ($viewPaths as $path) {
            $this->assertFileExists($path);
            $content = file_get_contents($path);

            // Kebijakan Tanpa Inline Script: tidak boleh ada tag <script> di dalam view Blade
            $this->assertStringNotContainsString(
                '<script',
                $content,
                "Pelanggaran Kebijakan: Berkas {$path} masih memuat inline <script> tag."
            );
        }
    }
}
