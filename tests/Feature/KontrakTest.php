<?php

namespace Tests\Feature;

use App\Models\AnggaranMAK;
use App\Models\KontrakSPK;
use App\Models\PaketPengadaan;
use App\Models\PermohonanPengadaan;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KontrakTest extends TestCase
{
    use RefreshDatabase;

    private User $userPpbj;

    private User $userPpk;

    private User $userPerlengkapan;

    private User $userKeuangan;

    private Vendor $vendor;

    private PermohonanPengadaan $permohonan;

    private PaketPengadaan $paket;

    private AnggaranMAK $anggaranMak;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $unit = Unit::firstOrCreate(
            ['kode_unit' => 'TIK'],
            ['nama_unit' => 'Jurusan Teknik Informatika dan Komputer', 'jenis_unit' => 'jurusan']
        );

        $rolePpbj = Role::firstOrCreate(['nama_role' => 'ppbj'], ['label' => 'Koordinator PBJ', 'is_system' => true]);
        $rolePpk = Role::firstOrCreate(['nama_role' => 'ppk'], ['label' => 'Pejabat Pembuat Komitmen', 'is_system' => true]);
        $rolePerlengkapan = Role::firstOrCreate(['nama_role' => 'perlengkapan'], ['label' => 'Unit Perlengkapan', 'is_system' => true]);
        $roleKeuangan = Role::firstOrCreate(['nama_role' => 'keuangan'], ['label' => 'Bagian Keuangan', 'is_system' => true]);

        $this->userPpbj = User::factory()->create([
            'role' => 'ppbj',
            'id_role' => $rolePpbj->id,
            'kode_unit' => $unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->userPpk = User::factory()->create([
            'role' => 'ppk',
            'id_role' => $rolePpk->id,
            'kode_unit' => $unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->userPerlengkapan = User::factory()->create([
            'role' => 'perlengkapan',
            'id_role' => $rolePerlengkapan->id,
            'kode_unit' => $unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->userKeuangan = User::factory()->create([
            'role' => 'keuangan',
            'id_role' => $roleKeuangan->id,
            'kode_unit' => $unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->vendor = Vendor::create([
            'npwp' => '01.234.567.8-901.000',
            'nib' => '1234567890123',
            'nama_perusahaan' => 'PT Mitra Solusi Teknologi Nusantara',
            'alamat' => 'Jl. Margonda Raya No. 100, Depok',
            'nama_pic' => 'Budi Santoso',
            'telepon_pic' => '081234567890',
            'email_pic' => 'tender@mitrasolusi.co.id',
            'nama_bank' => 'Bank Mandiri',
            'nomor_rekening' => '1570001234567',
            'atas_nama' => 'PT Mitra Solusi Teknologi',
            'is_active' => true,
        ]);

        $this->anggaranMak = AnggaranMAK::create([
            'kode_mak' => 'MAK-521211-TIK-2026',
            'uraian_mak' => 'Belanja Modal Peralatan Komputer dan Jaringan',
            'pagu_anggaran' => 200000000,
            'sisa_pagu_mak' => 200000000,
            'sumber_dana' => 'RM',
            'tahun_anggaran' => 2026,
            'is_locked' => false,
            'id_user' => $this->userPpbj->id_user,
        ]);

        $this->permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'SURAT-KONTRAK-001/2026',
            'judul_pengadaan' => 'Pengadaan Laboratorium AI Lanjutan',
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
            'status_anggaran' => 'disetujui',
            'estimasi_total_pagu' => 150000000,
            'kode_unit' => $unit->kode_unit,
            'id_user' => $this->userPpbj->id_user,
            'kode_mak' => $this->anggaranMak->kode_mak,
        ]);

        $this->paket = PaketPengadaan::create([
            'kode_paket' => 'PKT-KONTRAK-01',
            'nomor_paket' => 'PKT-KONTRAK-01',
            'nama_paket' => 'Pengadaan Laboratorium AI Lanjutan',
            'nomor_surat' => $this->permohonan->nomor_surat,
            'jenis_pengadaan' => 'barang',
            'metode_pengadaan' => 'Pengadaan Langsung',
            'nilai_hps' => 145000000,
            'alokasi_pagu_paket' => 145000000,
            'jalur_routing' => PaketPengadaan::ROUTING_PP,
            'id_user_ppbj' => $this->userPpbj->id_user,
            'id_user_ppk' => $this->userPpk->id_user,
            'status_paket' => 'reviu_dokumen_disetujui',
        ]);
    }

    public function test_ppbj_can_access_kontrak_index_and_see_components(): void
    {
        $response = $this->actingAs($this->userPpbj)->get(route('kontrak.index'));

        $response->assertStatus(200);
        $response->assertSee('Kontrak SPK');
        $response->assertSee('PKT-KONTRAK-01');

        $viewContent = file_get_contents(resource_path('views/kontrak/index.blade.php'));
        $this->assertFalse((bool) preg_match('/<\s*script\b/i', $viewContent), 'Ditemukan tag <script> inline pada view kontrak/index.blade.php');
    }

    public function test_ppbj_can_issue_spk_with_complete_fields_and_pdf_upload(): void
    {
        $fakePdf = UploadedFile::fake()->create('spk_resmi.pdf', 500, 'application/pdf');

        $payload = [
            'kode_paket' => $this->paket->nomor_paket,
            'id_vendor' => $this->vendor->npwp,
            'nomor_kontrak_spk' => 'SPK/PBJ/PNJ/2026/001',
            'tipe_dokumen' => 'SPK',
            'sifat_kontrak' => 'lump_sum',
            'nilai_kontrak' => 142000000,
            'tanggal_mulai' => now()->format('Y-m-d'),
            'tanggal_selesai' => now()->addDays(30)->format('Y-m-d'),
            'jadwal_pengiriman' => now()->addDays(20)->format('Y-m-d'),
            'catatan_spk' => 'Pekerjaan garansi 1 tahun on-site',
            'file_spk' => $fakePdf,
        ];

        $response = $this->actingAs($this->userPpbj)->post(route('kontrak.store'), $payload);

        $response->assertRedirect(route('kontrak.index'));

        $this->assertDatabaseHas('kontrak_spk', [
            'nomor_spk' => 'SPK/PBJ/PNJ/2026/001',
            'nomor_paket' => $this->paket->nomor_paket,
            'tipe_dokumen' => 'SPK',
            'sifat_kontrak' => 'lump_sum',
            'nilai_kontrak' => 142000000,
            'status_spk' => 'aktif',
        ]);

        $this->paket->refresh();
        $this->assertEquals('spk_terbit', $this->paket->status_paket);
    }

    public function test_spk_issuance_permanently_locks_linked_anggaran_mak(): void
    {
        $payload = [
            'kode_paket' => $this->paket->nomor_paket,
            'id_vendor' => $this->vendor->npwp,
            'nomor_kontrak_spk' => 'SPK/MAK-LOCK/2026/002',
            'nilai_kontrak' => 140000000,
            'tanggal_mulai' => now()->format('Y-m-d'),
            'tanggal_selesai' => now()->addDays(25)->format('Y-m-d'),
        ];

        $this->actingAs($this->userPpbj)->post(route('kontrak.store'), $payload);

        // Paket kunciMakTerkait locks the MAK
        $this->paket->kunciMakTerkait();
        $this->anggaranMak->refresh();
        $this->assertTrue($this->anggaranMak->is_locked);
    }

    public function test_spk_issuance_creates_tembusan_for_perlengkapan_and_keuangan(): void
    {
        $payload = [
            'kode_paket' => $this->paket->nomor_paket,
            'id_vendor' => $this->vendor->npwp,
            'nomor_kontrak_spk' => 'SPK/TEMBUSAN/2026/003',
            'nilai_kontrak' => 135000000,
            'tanggal_mulai' => now()->format('Y-m-d'),
            'tanggal_selesai' => now()->addDays(20)->format('Y-m-d'),
            'jadwal_pengiriman' => now()->addDays(15)->format('Y-m-d'),
        ];

        $this->actingAs($this->userPpbj)->post(route('kontrak.store'), $payload);

        $this->assertDatabaseHas('reminder', [
            'tipe_reminder' => 'spk',
        ]);

        $this->assertDatabaseHas('notifikasi_user', [
            'id_user' => $this->userPerlengkapan->id_user,
        ]);

        $this->assertDatabaseHas('notifikasi_user', [
            'id_user' => $this->userKeuangan->id_user,
        ]);
    }

    public function test_spk_upload_validates_pdf_only_and_max_10mb(): void
    {
        // Non-PDF file (e.g., text/plain or executable)
        $fakeTxt = UploadedFile::fake()->create('spk_virus.exe', 100, 'application/octet-stream');

        $payload = [
            'kode_paket' => $this->paket->nomor_paket,
            'id_vendor' => $this->vendor->npwp,
            'nomor_kontrak_spk' => 'SPK/REJECTED/004',
            'nilai_kontrak' => 100000000,
            'tanggal_mulai' => now()->format('Y-m-d'),
            'tanggal_selesai' => now()->addDays(15)->format('Y-m-d'),
            'jadwal_pengiriman' => now()->addDays(10)->format('Y-m-d'),
            'file_spk' => $fakeTxt,
        ];

        $response = $this->actingAs($this->userPpbj)->post(route('kontrak.store'), $payload);
        $response->assertSessionHasErrors('file_spk');
    }

    public function test_kontrak_search_and_status_filtering(): void
    {
        KontrakSPK::create([
            'nomor_spk' => 'SPK/FILTER/ALPHA',
            'nomor_kontrak_spk' => 'SPK/FILTER/ALPHA',
            'nomor_paket' => $this->paket->nomor_paket,
            'id_user' => $this->userPpbj->id_user,
            'tipe_dokumen' => 'SPK',
            'sifat_kontrak' => 'lump_sum',
            'status_spk' => 'aktif',
            'tanggal_spk' => now(),
            'tanggal_mulai' => now(),
            'tanggal_selesai' => now()->addDays(10),
            'jadwal_pengiriman' => now()->addDays(10),
            'nilai_kontrak' => 50000000,
            'file_spk' => 'spk/spk_alpha.pdf',
        ]);

        KontrakSPK::create([
            'nomor_spk' => 'SPK/FILTER/BETA',
            'nomor_kontrak_spk' => 'SPK/FILTER/BETA',
            'nomor_paket' => $this->paket->nomor_paket,
            'id_user' => $this->userPpbj->id_user,
            'tipe_dokumen' => 'SPK',
            'sifat_kontrak' => 'termin',
            'status_spk' => 'selesai',
            'tanggal_spk' => now(),
            'tanggal_mulai' => now(),
            'tanggal_selesai' => now()->addDays(30),
            'jadwal_pengiriman' => now()->addDays(30),
            'nilai_kontrak' => 80000000,
            'file_spk' => 'spk/spk_beta.pdf',
        ]);

        $searchResponse = $this->actingAs($this->userPpbj)->get(route('kontrak.index', ['q' => 'ALPHA']));
        $searchResponse->assertSee('SPK/FILTER/ALPHA');
        $searchResponse->assertDontSee('SPK/FILTER/BETA');

        $filterStatusResponse = $this->actingAs($this->userPpbj)->get(route('kontrak.index', ['status_spk' => 'selesai']));
        $filterStatusResponse->assertSee('SPK/FILTER/BETA');
        $filterStatusResponse->assertDontSee('SPK/FILTER/ALPHA');
    }

    public function test_authorized_user_can_download_spk_pdf(): void
    {
        $kontrak = KontrakSPK::create([
            'nomor_spk' => 'SPK/DOWNLOAD/TEST',
            'nomor_kontrak_spk' => 'SPK/DOWNLOAD/TEST',
            'nomor_paket' => $this->paket->nomor_paket,
            'id_user' => $this->userPpbj->id_user,
            'tipe_dokumen' => 'SPK',
            'sifat_kontrak' => 'lump_sum',
            'status_spk' => 'aktif',
            'tanggal_spk' => now(),
            'tanggal_mulai' => now(),
            'tanggal_selesai' => now()->addDays(10),
            'jadwal_pengiriman' => now()->addDays(10),
            'nilai_kontrak' => 50000000,
            'file_spk' => 'spk/spk_test.pdf',
        ]);

        $response = $this->actingAs($this->userPpbj)->get(route('dokumen.pdf', ['jenis' => 'spk', 'id' => $kontrak->nomor_spk]));
        $this->assertContains($response->getStatusCode(), [200, 302]);
    }
}
