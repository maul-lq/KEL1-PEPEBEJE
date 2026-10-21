<?php

namespace Tests\Feature;

use App\Models\KontrakSPK;
use App\Models\NotifikasiUser;
use App\Models\PaketPengadaan;
use App\Models\Pembayaran;
use App\Models\PenerimaanBarang;
use App\Models\PermohonanPengadaan;
use App\Models\TransaksiPencairan;
use App\Models\Unit;
use App\Models\User;
use App\Models\VerifikasiParalel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pengujian Prioritas 4 & 5 FR/NFR serta Penyelarasan Keputusan Bisnis (FrNfrPriorityFourFiveTest)
 * Mencakup FR 4.1, FR 5.1-5.4, FR 7.2 (Split Jenis Pengadaan PPBJ), FR 7.4 (Auto-Tembusan SPK),
 * FR 10 (Kategori Belanja Barang vs Modal), NFR 4.1 (Boundary Routing <=50M, <200M, >=200M & Tanpa Memo >=200M),
 * NFR 4.2 (Reassign PPK), NFR 5 (Reminder), dan NFR 12 (Unduh PDF < 3 detik).
 */
class FrNfrPriorityFourFiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('public');
    }

    public function test_fr4_1_user_riwayat_permohonan_returns_user_submissions_with_rejection_notes_and_verifier_details(): void
    {
        $user1 = User::create([
            'nip' => '199001012020011001',
            'username' => 'user_riwayat_satu',
            'email' => 'user_riwayat1@pnj.ac.id',
            'nama' => 'User Pemohon Satu',
            'password' => bcrypt('password'),
            'jabatan' => 'Staf Pengusul Jurusan',
            'kode_unit' => Unit::first()->kode_unit,
            'no_hp' => '081234567891',
            'role' => 'user',
            'status_aktif' => true,
        ]);

        $user2 = User::create([
            'nip' => '199002022020011002',
            'username' => 'user_riwayat_dua',
            'email' => 'user_riwayat2@pnj.ac.id',
            'nama' => 'User Pemohon Dua',
            'password' => bcrypt('password'),
            'jabatan' => 'Staf Pengusul Jurusan',
            'kode_unit' => Unit::first()->kode_unit,
            'no_hp' => '081234567892',
            'role' => 'user',
            'status_aktif' => true,
        ]);

        // Permohonan 1: Milik User 1 (Ditolak)
        $permohonan1 = PermohonanPengadaan::create([
            'nomor_surat' => 'B/USER1/001/2026',
            'judul_pengadaan' => 'Pengadaan Komputer Desktop User 1',
            'tujuan_pengadaan_ringkas' => 'Untuk lab komputer',
            'file_permohonan' => 'permohonan/u1.pdf',
            'status_permohonan' => PermohonanPengadaan::STATUS_DITOLAK,
            'status_anggaran' => 'pending',
            'estimasi_total_pagu' => 120000000,
            'tanggal_pengajuan' => now()->subDays(2),
            'kode_unit' => $user1->kode_unit ?? Unit::first()->kode_unit,
            'id_user' => $user1->id_user,
        ]);

        $wadir = User::where('role', 'wadir_2')->first();
        VerifikasiParalel::create([
            'id_user' => $wadir->id_user,
            'nomor_surat' => $permohonan1->nomor_surat,
            'role_verifikator' => 'wadir_2',
            'status_keputusan' => 'ditolak',
            'catatan_alasan' => 'Spesifikasi teknis prosesor belum dicantumkan secara detail.',
            'tanggal_keputusan' => now()->subDay(),
        ]);

        // Permohonan 2: Milik User 2
        PermohonanPengadaan::create([
            'nomor_surat' => 'B/USER2/001/2026',
            'judul_pengadaan' => 'Pengadaan Kursi Kuliah User 2',
            'tujuan_pengadaan_ringkas' => 'Untuk ruang kuliah',
            'file_permohonan' => 'permohonan/u2.pdf',
            'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
            'status_anggaran' => 'pending',
            'estimasi_total_pagu' => 45000000,
            'tanggal_pengajuan' => now(),
            'kode_unit' => $user2->kode_unit ?? Unit::first()->kode_unit,
            'id_user' => $user2->id_user,
        ]);

        // User 1 mengakses riwayat permohonan
        $this->actingAs($user1);
        $response = $this->getJson(route('permohonan-pengadaan.riwayat-user'));

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('total_usulan', 1)
            ->assertJsonPath('data.0.nomor_surat', 'B/USER1/001/2026')
            ->assertJsonPath('data.0.status_permohonan', PermohonanPengadaan::STATUS_DITOLAK)
            ->assertJsonPath('data.0.riwayat_penolakan.0.role_verifikator', 'wadir_2')
            ->assertJsonPath('data.0.riwayat_penolakan.0.catatan_alasan', 'Spesifikasi teknis prosesor belum dicantumkan secara detail.');
    }

    public function test_fr5_1_staff_bidang_2_forwards_incoming_applications_to_wadir_2_with_audit_trail(): void
    {
        $staffB2 = User::where('role', 'staff_bidang_2')->first();
        $user = User::where('role', 'user')->first();

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/STAFFB2/001/2026',
            'judul_pengadaan' => 'Pengadaan Router Jaringan',
            'tujuan_pengadaan_ringkas' => 'Router backbone gedung',
            'file_permohonan' => 'permohonan/router.pdf',
            'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
            'status_anggaran' => 'pending',
            'estimasi_total_pagu' => 60000000,
            'tanggal_pengajuan' => now(),
            'kode_unit' => $user->kode_unit ?? Unit::first()->kode_unit,
            'id_user' => $user->id_user,
        ]);

        $this->actingAs($staffB2);
        $response = $this->postJson(route('verifikasi-paralel.teruskan-ke-wadir'), [
            'nomor_surat' => $permohonan->nomor_surat,
            'catatan_pemeriksaan' => 'Dokumen KAK dan rincian HPS lengkap, siap disetujui Wadir 2.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.nomor_surat', $permohonan->nomor_surat);

        $this->assertDatabaseHas('audit_log', [
            'jenis_aksi' => 'STAFF_B2_TERUSKAN_KE_WADIR',
            'id_entitas' => $permohonan->nomor_surat,
        ]);
    }

    public function test_fr5_2_and_5_3_wadir_2_has_absolute_decision_requires_reason_when_rejecting_and_optional_note_when_approving(): void
    {
        $wadir = User::where('role', 'wadir_2')->first();
        $user = User::where('role', 'user')->first();

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/WADIR2/001/2026',
            'judul_pengadaan' => 'Pengadaan Server Cloud Lab',
            'tujuan_pengadaan_ringkas' => 'Server praktikum mahasiswa',
            'file_permohonan' => 'permohonan/server.pdf',
            'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
            'status_anggaran' => 'pending',
            'estimasi_total_pagu' => 250000000,
            'tanggal_pengajuan' => now(),
            'kode_unit' => $user->kode_unit ?? Unit::first()->kode_unit,
            'id_user' => $user->id_user,
        ]);

        $verifWadir = VerifikasiParalel::create([
            'id_user' => $wadir->id_user,
            'nomor_surat' => $permohonan->nomor_surat,
            'role_verifikator' => 'wadir_2',
            'status_keputusan' => 'menunggu',
        ]);

        $this->actingAs($wadir);

        // 1. Menolak tanpa catatan alasan -> Wajib ditolak validasi (HTTP 422)
        $responseRejectNoReason = $this->putJson(route('verifikasi-paralel.update', ['verifikasi_paralel' => $verifWadir->id]), [
            'status_keputusan' => 'ditolak',
            'catatan_alasan' => '',
        ]);
        $responseRejectNoReason->assertStatus(422)
            ->assertJsonValidationErrors(['catatan_alasan']);

        // 2. Menolak dengan catatan alasan -> Berhasil dan status permohonan menjadi ditolak
        $responseRejectWithReason = $this->putJson(route('verifikasi-paralel.update', ['verifikasi_paralel' => $verifWadir->id]), [
            'status_keputusan' => 'ditolak',
            'catatan_alasan' => 'Anggaran server lab ditunda ke tahun anggaran berikutnya.',
        ]);
        $responseRejectWithReason->assertStatus(200)
            ->assertJsonPath('data.status_keputusan', 'ditolak');

        $permohonan->refresh();
        $this->assertSame(PermohonanPengadaan::STATUS_DITOLAK, $permohonan->status_permohonan);

        // 3. Menyetujui (ACC) dengan catatan kosong -> Berhasil (catatan opsional)
        $responseApproveNoNote = $this->putJson(route('verifikasi-paralel.update', ['verifikasi_paralel' => $verifWadir->id]), [
            'status_keputusan' => 'disetujui',
            'catatan_alasan' => null,
        ]);
        $responseApproveNoNote->assertStatus(200)
            ->assertJsonPath('data.status_keputusan', 'disetujui');
    }

    public function test_fr5_4_dual_approval_by_wadir_2_and_perencanaan_cascades_permohonan_to_disetujui(): void
    {
        $wadir = User::where('role', 'wadir_2')->first();
        $perencanaan = User::where('role', 'perencanaan')->first();
        $user = User::where('role', 'user')->first();

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/CASCADE/001/2026',
            'judul_pengadaan' => 'Pengadaan Lisensi Software AI',
            'tujuan_pengadaan_ringkas' => 'Lisensi riset mahasiswa',
            'file_permohonan' => 'permohonan/ai.pdf',
            'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
            'status_anggaran' => 'pending',
            'estimasi_total_pagu' => 80000000,
            'tanggal_pengajuan' => now(),
            'kode_unit' => $user->kode_unit ?? Unit::first()->kode_unit,
            'id_user' => $user->id_user,
        ]);

        $verifPerencanaan = VerifikasiParalel::create([
            'id_user' => $perencanaan->id_user,
            'nomor_surat' => $permohonan->nomor_surat,
            'role_verifikator' => 'perencanaan',
            'status_keputusan' => 'menunggu',
        ]);

        $verifWadir = VerifikasiParalel::create([
            'id_user' => $wadir->id_user,
            'nomor_surat' => $permohonan->nomor_surat,
            'role_verifikator' => 'wadir_2',
            'status_keputusan' => 'menunggu',
        ]);

        // 1. Perencanaan ACC
        $this->actingAs($perencanaan);
        $this->putJson(route('verifikasi-paralel.update', ['verifikasi_paralel' => $verifPerencanaan->id]), [
            'status_keputusan' => 'disetujui',
            'catatan_alasan' => 'Pagu MAK tersedia',
        ]);

        $permohonan->refresh();
        $this->assertSame(PermohonanPengadaan::STATUS_REVIU_PARALEL, $permohonan->status_permohonan);

        // 2. Wadir 2 ACC -> Memicu status 'disetujui' siap ke PPBJ (FR 5.4)
        $this->actingAs($wadir);
        $this->putJson(route('verifikasi-paralel.update', ['verifikasi_paralel' => $verifWadir->id]), [
            'status_keputusan' => 'disetujui',
        ]);

        $permohonan->refresh();
        $this->assertSame(PermohonanPengadaan::STATUS_DISETUJUI, $permohonan->status_permohonan);
    }

    public function test_nfr4_1_threshold_routing_computes_jalur_routing_with_exact_boundaries_and_nullifies_memo_for_200m_and_above(): void
    {
        $ppbj = User::where('role', 'ppbj')->first();
        $ppk = User::where('role', 'ppk')->first();
        $this->actingAs($ppbj);

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/ROUTING/001/2026',
            'judul_pengadaan' => 'Uji Coba Routing Ambang Batas',
            'tujuan_pengadaan_ringkas' => 'Testing jalur routing',
            'file_permohonan' => 'permohonan/route.pdf',
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
            'status_anggaran' => 'disetujui',
            'estimasi_total_pagu' => 500000000,
            'tanggal_pengajuan' => now(),
            'kode_unit' => Unit::first()->kode_unit,
            'id_user' => $ppbj->id_user,
        ]);

        // 1. HPS <= 0 ditolak
        $responseZeroHps = $this->postJson(route('paket-pengadaan.store'), [
            'nomor_paket' => 'PKT-ZERO-001',
            'nama_paket' => 'Paket Zero HPS',
            'jenis_pengadaan' => 'barang',
            'platform_pengadaan' => 'SPSE',
            'mekanisme_pengadaan' => 'Non-Tender',
            'nilai_hps' => 0,
            'alokasi_pagu_paket' => 10000000,
            'nomor_surat' => $permohonan->nomor_surat,
            'id_user_ppbj' => $ppbj->id_user,
        ]);
        $responseZeroHps->assertStatus(422)
            ->assertJsonValidationErrors(['nilai_hps']);

        // 2. Boundary HPS tepat Rp 50.000.000 (<= 50jt) -> langsung_sd_50jt (status awal: spk_terbit / siap dibuat SPK oleh PPBJ)
        $resLangsungBoundary = $this->postJson(route('paket-pengadaan.store'), [
            'nomor_paket' => 'PKT-LANGSUNG-50M',
            'nama_paket' => 'Pengadaan Tinta & Kertas Tepat 50 Juta',
            'jenis_pengadaan' => 'barang',
            'platform_pengadaan' => 'E-Katalog',
            'mekanisme_pengadaan' => 'Pengadaan Langsung',
            'nilai_hps' => 50000000,
            'alokasi_pagu_paket' => 50000000,
            'nomor_memo_ppbj' => 'MEMO/PPBJ/50M/2026',
            'nomor_surat' => $permohonan->nomor_surat,
            'id_user_ppbj' => $ppbj->id_user,
        ]);
        $resLangsungBoundary->assertStatus(201)
            ->assertJsonPath('data.jalur_routing', PaketPengadaan::ROUTING_LANGSUNG)
            ->assertJsonPath('data.nomor_memo_ppbj', 'MEMO/PPBJ/50M/2026');

        // 3. HPS Rp 120.000.000 (> 50jt dan < 200jt) -> pp_50jt_sd_200jt
        $resPp = $this->postJson(route('paket-pengadaan.store'), [
            'nomor_paket' => 'PKT-PP-001',
            'nama_paket' => 'Pengadaan PC All-in-One',
            'jenis_pengadaan' => 'barang',
            'platform_pengadaan' => 'SPSE',
            'mekanisme_pengadaan' => 'Pengadaan Langsung PP',
            'nilai_hps' => 120000000,
            'alokasi_pagu_paket' => 120000000,
            'nomor_memo_ppbj' => 'MEMO/PPBJ/120M/2026',
            'nomor_surat' => $permohonan->nomor_surat,
            'id_user_ppbj' => $ppbj->id_user,
        ]);
        $resPp->assertStatus(201)
            ->assertJsonPath('data.jalur_routing', PaketPengadaan::ROUTING_PP)
            ->assertJsonPath('data.nomor_memo_ppbj', 'MEMO/PPBJ/120M/2026');

        // 4. Boundary HPS tepat Rp 200.000.000 (>= 200jt) -> ppk_diatas_200jt & nomor_memo_ppbj otomatis NULL (K2/Q7)
        $resPpkBoundary = $this->postJson(route('paket-pengadaan.store'), [
            'nomor_paket' => 'PKT-PPK-200M',
            'nama_paket' => 'Pengadaan Alat Berat Lab Sipil Tepat 200 Juta',
            'jenis_pengadaan' => 'barang',
            'platform_pengadaan' => 'SPSE-Tender',
            'mekanisme_pengadaan' => 'Tender Cepat',
            'nilai_hps' => 200000000,
            'alokasi_pagu_paket' => 200000000,
            'nomor_memo_ppbj' => 'MEMO-INI-HARUS-DIKOSONGKAN-OTOMATIS',
            'nomor_surat' => $permohonan->nomor_surat,
            'id_user_ppbj' => $ppbj->id_user,
            'id_user_ppk' => $ppk->id_user,
        ]);
        $resPpkBoundary->assertStatus(201)
            ->assertJsonPath('data.jalur_routing', PaketPengadaan::ROUTING_PPK)
            ->assertJsonPath('data.nomor_memo_ppbj', null);
    }

    public function test_fr7_2_ppbj_can_split_permohonan_by_jenis_pengadaan(): void
    {
        $ppbj = User::where('role', 'ppbj')->first();
        $this->actingAs($ppbj);

        $induk = PermohonanPengadaan::create([
            'nomor_surat' => 'B/SPLIT-PPBJ/001/2026',
            'judul_pengadaan' => 'Pengadaan Terpadu Peralatan dan Renovasi Lab',
            'tujuan_pengadaan_ringkas' => 'Pengadaan gabungan barang dan konstruksi',
            'file_permohonan' => 'permohonan/gabungan.pdf',
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
            'status_anggaran' => 'disetujui',
            'estimasi_total_pagu' => 300000000,
            'tanggal_pengajuan' => now(),
            'kode_unit' => Unit::first()->kode_unit,
            'id_user' => $ppbj->id_user,
        ]);

        $response = $this->postJson(route('permohonan.split-ppbj', ['permohonan_pengadaan' => $induk->nomor_surat]), [
            'items' => [
                [
                    'nomor_surat_pecahan' => 'B/SPLIT-PPBJ/001-BRG/2026',
                    'judul_pengadaan' => 'Pecahan Pengadaan Barang Peralatan Lab',
                    'jenis_pengadaan_split' => 'barang',
                    'estimasi_pagu_pecahan' => 180000000,
                ],
                [
                    'nomor_surat_pecahan' => 'B/SPLIT-PPBJ/001-KST/2026',
                    'judul_pengadaan' => 'Pecahan Pekerjaan Konstruksi Partisi Lab',
                    'jenis_pengadaan_split' => 'konstruksi',
                    'estimasi_pagu_pecahan' => 120000000,
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('permohonan_pengadaan', [
            'nomor_surat' => 'B/SPLIT-PPBJ/001-BRG/2026',
            'nomor_surat_induk_split' => 'B/SPLIT-PPBJ/001/2026',
            'jenis_pengadaan_split' => 'barang',
        ]);
        $this->assertDatabaseHas('permohonan_pengadaan', [
            'nomor_surat' => 'B/SPLIT-PPBJ/001-KST/2026',
            'nomor_surat_induk_split' => 'B/SPLIT-PPBJ/001/2026',
            'jenis_pengadaan_split' => 'konstruksi',
        ]);
    }

    public function test_fr7_4_and_fr10_ppbj_issues_spk_with_auto_tembusan_and_perlengkapan_records_kategori_belanja(): void
    {
        $ppbj = User::where('role', 'ppbj')->first();
        $perlengkapan = User::where('role', 'perlengkapan')->first();
        $keuangan = User::where('role', 'keuangan')->first();

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/TEMBUSAN/001/2026',
            'judul_pengadaan' => 'Pengadaan Proyektor Kelas Pintar',
            'tujuan_pengadaan_ringkas' => 'Proyektor ruang kelas',
            'file_permohonan' => 'permohonan/proyektor.pdf',
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
            'status_anggaran' => 'disetujui',
            'estimasi_total_pagu' => 45000000,
            'tanggal_pengajuan' => now(),
            'kode_unit' => Unit::first()->kode_unit,
            'id_user' => $ppbj->id_user,
        ]);

        $paket = PaketPengadaan::create([
            'nomor_paket' => 'PKT-TEMBUSAN-001',
            'nama_paket' => 'Pengadaan Proyektor Kelas Pintar',
            'jenis_pengadaan' => 'barang',
            'platform_pengadaan' => 'E-Katalog',
            'mekanisme_pengadaan' => 'Pengadaan Langsung',
            'nilai_hps' => 45000000,
            'alokasi_pagu_paket' => 45000000,
            'jalur_routing' => PaketPengadaan::ROUTING_LANGSUNG,
            'status_paket' => 'spk_terbit',
            'nomor_surat' => $permohonan->nomor_surat,
            'id_user_ppbj' => $ppbj->id_user,
            'tanggal_registrasi_paket' => now(),
        ]);

        // 1. PPBJ menerbitkan Kontrak/SPK -> Otomatis tembusan ke Perlengkapan & Keuangan
        $this->actingAs($ppbj);
        $fileSpk = UploadedFile::fake()->create('spk_proyektor.pdf', 1024, 'application/pdf');
        $resSpk = $this->postJson(route('kontrak-spk.store'), [
            'nomor_spk' => 'SPK-TEMBUSAN-001',
            'nomor_paket' => $paket->nomor_paket,
            'id_user' => $ppbj->id_user,
            'tipe_dokumen' => 'spk',
            'sifat_kontrak' => 'lumpsum',
            'status_spk' => 'terbit',
            'tanggal_spk' => now()->toDateString(),
            'jadwal_pengiriman' => now()->addDays(10)->toDateString(),
            'nilai_kontrak' => 44500000,
            'file_spk' => $fileSpk,
        ]);

        $resSpk->assertStatus(201);

        // Pastikan Reminder tembusan_spk terbentuk dan NotifikasiUser terkirim ke Perlengkapan & Keuangan
        $this->assertDatabaseHas('reminder', [
            'nomor_paket' => $paket->nomor_paket,
            'tipe_reminder' => 'tembusan_spk',
        ]);

        $notifPerlengkapan = NotifikasiUser::where('id_user', $perlengkapan->id_user)
            ->whereHas('reminder', fn ($q) => $q->where('nomor_paket', $paket->nomor_paket)->where('tipe_reminder', 'tembusan_spk'))
            ->first();
        $notifKeuangan = NotifikasiUser::where('id_user', $keuangan->id_user)
            ->whereHas('reminder', fn ($q) => $q->where('nomor_paket', $paket->nomor_paket)->where('tipe_reminder', 'tembusan_spk'))
            ->first();

        $this->assertNotNull($notifPerlengkapan);
        $this->assertNotNull($notifKeuangan);

        // 2. Perlengkapan membuat BAST dengan kategori_belanja = belanja_modal
        $this->actingAs($perlengkapan);
        $fileBast = UploadedFile::fake()->create('bast_proyektor.pdf', 1024, 'application/pdf');
        $resBast = $this->postJson(route('penerimaan-barang.store'), [
            'nomor_bast' => 'BAST-MODAL-001',
            'nomor_paket' => $paket->nomor_paket,
            'id_user' => $perlengkapan->id_user,
            'tanggal_penerimaan' => now()->toDateString(),
            'status_fisik' => 'terima',
            'kategori_belanja' => PenerimaanBarang::KATEGORI_BELANJA_MODAL,
            'file_bast_signed' => $fileBast,
            'catatan_perlengkapan' => 'Aset proyektor dicatat ke dalam SIMAK-BMN sebagai Belanja Modal.',
        ]);

        $resBast->assertStatus(201)
            ->assertJsonPath('data.kategori_belanja', PenerimaanBarang::KATEGORI_BELANJA_MODAL);

        $this->assertDatabaseHas('penerimaan_barang', [
            'nomor_bast' => 'BAST-MODAL-001',
            'kategori_belanja' => PenerimaanBarang::KATEGORI_BELANJA_MODAL,
        ]);
    }

    public function test_nfr4_2_reassign_ppk_enforces_role_validation_records_mutasi_history_and_restricts_unauthorized_roles(): void
    {
        $ppbj = User::where('role', 'ppbj')->first();
        $ppkList = User::where('role', 'ppk')->get();
        $ppk1 = $ppkList[0];
        $ppk2 = $ppkList[1];
        $userNonPpk = User::where('role', 'user')->first();

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/REASSIGN/001/2026',
            'judul_pengadaan' => 'Paket Renovasi Gedung Workshop',
            'tujuan_pengadaan_ringkas' => 'Renovasi fasilitas lab',
            'file_permohonan' => 'permohonan/workshop.pdf',
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
            'status_anggaran' => 'disetujui',
            'estimasi_total_pagu' => 300000000,
            'tanggal_pengajuan' => now(),
            'kode_unit' => Unit::first()->kode_unit,
            'id_user' => $ppbj->id_user,
        ]);

        $paket = PaketPengadaan::create([
            'nomor_paket' => 'PKT-REASSIGN-001',
            'nama_paket' => 'Renovasi Gedung Workshop Elektro',
            'jenis_pengadaan' => 'konstruksi',
            'platform_pengadaan' => 'SPSE',
            'mekanisme_pengadaan' => 'Tender',
            'nilai_hps' => 300000000,
            'alokasi_pagu_paket' => 300000000,
            'jalur_routing' => PaketPengadaan::ROUTING_PPK,
            'status_paket' => 'draft',
            'nomor_surat' => $permohonan->nomor_surat,
            'id_user_ppbj' => $ppbj->id_user,
            'id_user_ppk' => $ppk1->id_user,
            'tanggal_registrasi_paket' => now(),
        ]);

        // 1. Alih tugas ke user non-PPK ditolak dengan 422
        $this->actingAs($ppbj);
        $resInvalidTarget = $this->postJson(route('paket-pengadaan.reassign-ppk', ['paket_pengadaan' => $paket->nomor_paket]), [
            'ppk_baru_user_id' => $userNonPpk->id_user,
            'diubah_oleh_user_id' => $ppbj->id_user,
            'alasan_perubahan' => 'Coba alihkan ke unit pemohon',
        ]);
        $resInvalidTarget->assertStatus(422)
            ->assertJsonValidationErrors(['ppk_baru_user_id']);

        // 2. Alih tugas oleh PPBJ ke PPK 2 berhasil
        $resSuccess = $this->postJson(route('paket-pengadaan.reassign-ppk', ['paket_pengadaan' => $paket->nomor_paket]), [
            'ppk_baru_user_id' => $ppk2->id_user,
            'diubah_oleh_user_id' => $ppbj->id_user,
            'alasan_perubahan' => 'Pemerataan beban kerja PPK 1 yang memegang 5 paket konstruksi.',
        ]);
        $resSuccess->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $paket->refresh();
        $this->assertSame((int) $ppk2->id_user, (int) $paket->id_user_ppk);

        // Verifikasi tabel riwayat_penugasan_ppk
        $this->assertDatabaseHas('riwayat_penugasan_ppk', [
            'nomor_paket' => $paket->nomor_paket,
            'ppk_lama_user_id' => $ppk1->id_user,
            'ppk_baru_user_id' => $ppk2->id_user,
            'alasan_perubahan' => 'Pemerataan beban kerja PPK 1 yang memegang 5 paket konstruksi.',
        ]);

        // 3. User biasa tidak berhak melakukan alih tugas PPK (403 Forbidden)
        $this->actingAs($userNonPpk);
        $resForbidden = $this->postJson(route('paket-pengadaan.reassign-ppk', ['paket_pengadaan' => $paket->nomor_paket]), [
            'ppk_baru_user_id' => $ppk1->id_user,
            'diubah_oleh_user_id' => $userNonPpk->id_user,
            'alasan_perubahan' => 'Mencoba bypass role',
        ]);
        $resForbidden->assertStatus(403);
    }

    public function test_nfr12_official_pdf_documents_download_responds_in_under_three_seconds_with_pdf_headers(): void
    {
        $ppbj = User::where('role', 'ppbj')->first();
        $ppk = User::where('role', 'ppk')->first();
        $keuangan = User::where('role', 'keuangan')->first();
        $this->actingAs($ppbj);

        // 1. Dokumen Permohonan PDF
        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/PDF/001/2026',
            'judul_pengadaan' => 'Pengadaan Sound System Auditorium',
            'tujuan_pengadaan_ringkas' => 'Sound system auditorium utama',
            'file_permohonan' => 'permohonan/sound.pdf',
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
            'status_anggaran' => 'disetujui',
            'estimasi_total_pagu' => 95000000,
            'tanggal_pengajuan' => now(),
            'kode_unit' => Unit::first()->kode_unit,
            'id_user' => $ppbj->id_user,
        ]);

        $start = microtime(true);
        $resPdfPermohonan = $this->get(route('permohonan-pengadaan.unduh-pdf', ['permohonan_pengadaan' => $permohonan->nomor_surat]));
        $duration = (microtime(true) - $start) * 1000;

        $resPdfPermohonan->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $resPdfPermohonan->headers->get('content-type'));
        $this->assertLessThan(3000, $duration);

        // 2. Dokumen Kontrak SPK PDF
        $paket = PaketPengadaan::create([
            'nomor_paket' => 'PKT-PDF-001',
            'nama_paket' => 'Sound System Auditorium',
            'jenis_pengadaan' => 'barang',
            'platform_pengadaan' => 'SPSE',
            'mekanisme_pengadaan' => 'Pengadaan Langsung',
            'nilai_hps' => 95000000,
            'alokasi_pagu_paket' => 95000000,
            'jalur_routing' => PaketPengadaan::ROUTING_PP,
            'status_paket' => 'spk_terbit',
            'nomor_surat' => $permohonan->nomor_surat,
            'id_user_ppbj' => $ppbj->id_user,
            'tanggal_registrasi_paket' => now(),
        ]);

        $spk = KontrakSPK::create([
            'nomor_spk' => 'SPK-2026-PDF-001',
            'nomor_paket' => $paket->nomor_paket,
            'id_user' => $ppk->id_user,
            'tipe_dokumen' => 'spk',
            'sifat_kontrak' => 'lumpsum',
            'status_spk' => 'terbit',
            'tanggal_spk' => now(),
            'jadwal_pengiriman' => now()->addDays(14),
            'nilai_kontrak' => 95000000,
            'file_spk' => 'spk/spk.pdf',
        ]);

        $startSpk = microtime(true);
        $resPdfSpk = $this->get(route('kontrak-spk.unduh-pdf', ['kontrak_spk' => $spk->nomor_spk]));
        $durationSpk = (microtime(true) - $startSpk) * 1000;

        $resPdfSpk->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $resPdfSpk->headers->get('content-type'));
        $this->assertLessThan(3000, $durationSpk);

        // 3. Dokumen BAST PDF
        $bast = PenerimaanBarang::create([
            'nomor_bast' => 'BAST-2026-PDF-001',
            'nomor_paket' => $paket->nomor_paket,
            'id_user' => $ppbj->id_user,
            'tanggal_penerimaan' => now(),
            'status_fisik' => 'terima',
            'kategori_belanja' => PenerimaanBarang::KATEGORI_BELANJA_BARANG,
            'ttd_digital_bast' => 'TOKEN-HMAC-TEST-123',
        ]);

        $resPdfBast = $this->get(route('penerimaan-barang.unduh-pdf', ['penerimaan_barang' => $bast->id_penerimaan]));
        $resPdfBast->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $resPdfBast->headers->get('content-type'));

        // 4. Dokumen Bukti Transfer PDF
        $pembayaran = Pembayaran::create([
            'nomor_pembayaran' => 'SPP-2026-PDF-001',
            'nomor_paket' => $paket->nomor_paket,
            'id_user_pengaju' => $keuangan->id_user,
            'tahap_termin' => 1,
            'nominal_pengajuan' => 95000000,
            'tanggal_pengajuan' => now(),
            'jatuh_tempo' => now()->addDays(14),
            'status_pembayaran' => 'lunas',
        ]);

        $cair = TransaksiPencairan::create([
            'nomor_transaksi_bank' => 'TRX-BANK-PDF-001',
            'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
            'id_user' => $keuangan->id_user,
            'nominal_transfer' => 95000000,
            'file_bukti_transfer' => 'transfer/slip.pdf',
            'tanggal_transfer' => now(),
        ]);

        $resPdfTransfer = $this->get(route('transaksi-pencairan.unduh-pdf', ['transaksi_pencairan' => $cair->nomor_transaksi_bank]));
        $resPdfTransfer->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $resPdfTransfer->headers->get('content-type'));
    }

    public function test_nfr5_reminder_configuration_sends_alerts_to_designated_ppbj_pp_and_ppk_roles(): void
    {
        $ppbj = User::where('role', 'ppbj')->first();
        $pp = User::where('role', 'pp')->first();
        $ppk = User::where('role', 'ppk')->first();

        $this->actingAs($ppbj);

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/REMINDER/001/2026',
            'judul_pengadaan' => 'Pengadaan Genset Kampus',
            'tujuan_pengadaan_ringkas' => 'Backup listrik kampus',
            'file_permohonan' => 'permohonan/genset.pdf',
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
            'status_anggaran' => 'disetujui',
            'estimasi_total_pagu' => 180000000,
            'tanggal_pengajuan' => now(),
            'kode_unit' => Unit::first()->kode_unit,
            'id_user' => $ppbj->id_user,
        ]);

        $paket = PaketPengadaan::create([
            'nomor_paket' => 'PKT-REMINDER-001',
            'nama_paket' => 'Pengadaan Genset Kampus',
            'jenis_pengadaan' => 'barang',
            'platform_pengadaan' => 'SPSE',
            'mekanisme_pengadaan' => 'Pengadaan Langsung',
            'nilai_hps' => 180000000,
            'alokasi_pagu_paket' => 180000000,
            'jalur_routing' => PaketPengadaan::ROUTING_PP,
            'status_paket' => 'draft',
            'nomor_surat' => $permohonan->nomor_surat,
            'id_user_ppbj' => $ppbj->id_user,
            'id_user_pp' => $pp->id_user,
            'tanggal_registrasi_paket' => now(),
        ]);

        // Konfigurasi reminder pengiriman barang
        $responseReminder = $this->postJson(route('reminders.store'), [
            'nomor_paket' => $paket->nomor_paket,
            'tipe_reminder' => 'pengiriman',
            'waktu_trigger' => now()->addDays(5)->toDateTimeString(),
            'judul_alert' => 'Jadwal Pengiriman Barang: Genset Kampus',
            'pesan_alert' => 'Harap bagian perlengkapan bersiap menerima kiriman vendor.',
            'user_ids' => [$ppbj->id_user, $pp->id_user, $ppk->id_user],
        ]);

        $responseReminder->assertStatus(201)
            ->assertJsonPath('status', 'success');

        // Pastikan tabel notifikasi_user mencatat ketiga user
        $this->assertDatabaseHas('notifikasi_user', [
            'id_user' => $ppbj->id_user,
            'is_read' => false,
        ]);
        $this->assertDatabaseHas('notifikasi_user', [
            'id_user' => $pp->id_user,
            'is_read' => false,
        ]);
        $this->assertDatabaseHas('notifikasi_user', [
            'id_user' => $ppk->id_user,
            'is_read' => false,
        ]);
    }
}
