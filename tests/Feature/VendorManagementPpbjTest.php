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

class VendorManagementPpbjTest extends TestCase
{
    use RefreshDatabase;

    protected User $ppbjUser;

    protected User $ppUser;

    protected User $ppkUser;

    protected User $regularUser;

    protected Unit $unit;

    protected AnggaranMAK $anggaranMak;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->unit = Unit::firstOrCreate(
            ['kode_unit' => 'TIK'],
            ['nama_unit' => 'Teknik Informatika dan Komputer', 'jenis_unit' => 'jurusan', 'is_active' => true]
        );

        $rolePpbj = Role::firstOrCreate(['nama_role' => 'ppbj'], ['label' => 'PPBJ', 'deskripsi' => 'Pengadaan Barang & Jasa']);
        $rolePp = Role::firstOrCreate(['nama_role' => 'pp'], ['label' => 'PP', 'deskripsi' => 'Pejabat Pengadaan']);
        $rolePpk = Role::firstOrCreate(['nama_role' => 'ppk'], ['label' => 'PPK', 'deskripsi' => 'Pejabat Pembuat Komitmen']);
        $roleUser = Role::firstOrCreate(['nama_role' => 'user'], ['label' => 'Pengusul', 'deskripsi' => 'Pengusul']);

        $this->ppbjUser = User::factory()->create([
            'role' => 'ppbj',
            'id_role' => $rolePpbj->id,
            'kode_unit' => $this->unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->ppUser = User::factory()->create([
            'role' => 'pp',
            'id_role' => $rolePp->id,
            'kode_unit' => $this->unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->ppkUser = User::factory()->create([
            'role' => 'ppk',
            'id_role' => $rolePpk->id,
            'kode_unit' => $this->unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->regularUser = User::factory()->create([
            'role' => 'user',
            'id_role' => $roleUser->id,
            'kode_unit' => $this->unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);

        $this->anggaranMak = AnggaranMAK::create([
            'kode_mak' => 'MAK-2026-TIK-001',
            'uraian_mak' => 'Pengadaan Lab Komputer & Server AI 2026',
            'pagu_anggaran' => 100000000.00,
            'sisa_pagu_mak' => 80000000.00,
            'tahun_anggaran' => 2026,
            'sumber_dana' => 'RM',
            'is_locked' => false,
        ]);
    }

    /**
     * Memastikan route CRUD master /vendors telah dihapus sesuai instruksi penyederhanaan arsitektur.
     */
    public function test_vendor_master_crud_route_is_removed(): void
    {
        $response = $this->actingAs($this->ppbjUser)->get('/vendors');
        $response->assertStatus(404);
    }

    /**
     * Memastikan entitas Vendor baru bersifat minimalis (hanya butuh nama_vendor dan opsional dokumen legalitas, tanpa NPWP/NIB/bank).
     */
    public function test_vendor_model_can_be_created_with_minimal_attributes(): void
    {
        $vendor = Vendor::create([
            'nama_vendor' => 'CV Minimalis Sukses Perkasa',
            'file_dokumen_legalitas' => 'vendor_legalitas/sample_izin.pdf',
        ]);

        $this->assertDatabaseHas('vendors', [
            'id' => $vendor->id,
            'nama_vendor' => 'CV Minimalis Sukses Perkasa',
            'file_dokumen_legalitas' => 'vendor_legalitas/sample_izin.pdf',
        ]);
        $this->assertNull($vendor->npwp);
        $this->assertNull($vendor->nib);
        $this->assertNull($vendor->nomor_rekening);
    }

    /**
     * Menguji input inline rekanan vendor saat eksekusi Belanja Langsung (<= 50 Juta) oleh PPBJ.
     */
    public function test_ppbj_can_execute_belanja_langsung_with_inline_vendor(): void
    {
        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/TEST/BL/001',
            'judul_pengadaan' => 'Pengadaan ATK & Kertas Kantor TIK',
            'tujuan_pengadaan_ringkas' => 'Kebutuhan ATK',
            'estimasi_total_pagu' => 25000000.00,
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
            'status_anggaran' => 'tersedia',
            'kode_unit' => $this->unit->kode_unit,
            'kode_mak' => $this->anggaranMak->kode_mak,
            'id_user' => $this->regularUser->id_user,
        ]);

        $paket = PaketPengadaan::create([
            'kode_paket' => 'PKT-BL-2026-001',
            'nomor_paket' => 'PKT-BL-2026-001',
            'nama_paket' => 'Pengadaan ATK & Kertas Kantor TIK',
            'nomor_surat' => $permohonan->nomor_surat,
            'jenis_pengadaan' => 'barang',
            'platform_pengadaan' => 'Belanja Langsung',
            'mekanisme_pengadaan' => 'pengadaan_langsung',
            'jalur_routing' => PaketPengadaan::ROUTING_LANGSUNG,
            'nilai_hps' => 25000000.00,
            'alokasi_pagu_paket' => 25000000.00,
            'id_user_ppbj' => $this->ppbjUser->id_user,
            'status_paket' => 'terdaftar',
        ]);

        $dokumenLegalitas = UploadedFile::fake()->create('dokumen_legalitas_toko.pdf', 500, 'application/pdf');
        $kwitansiFile = UploadedFile::fake()->create('kwitansi_pembelian.pdf', 300, 'application/pdf');

        $response = $this->actingAs($this->ppbjUser)->post(route('paket.belanja-langsung'), [
            'nomor_paket' => $paket->nomor_paket,
            'nomor_kwitansi_belanja' => 'KWT/TIK/2026/001',
            'nominal_harga_akhir' => 24500000.00,
            'nama_vendor' => 'Toko Buku & ATK Mitra Kampus',
            'file_dokumen_legalitas' => $dokumenLegalitas,
            'file_kwitansi_belanja' => $kwitansiFile,
            'jadwal_pengiriman' => now()->addDays(3)->toDateString(),
        ]);

        $response->assertRedirect(route('paket.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('vendors', [
            'nama_vendor' => 'Toko Buku & ATK Mitra Kampus',
        ]);

        $createdVendor = Vendor::where('nama_vendor', 'Toko Buku & ATK Mitra Kampus')->first();
        $this->assertNotNull($createdVendor);
        $this->assertNotNull($createdVendor->file_dokumen_legalitas);

        $paket->refresh();
        $this->assertEquals($createdVendor->id, $paket->id_vendor);
        $this->assertEquals(PaketPengadaan::STATUS_BELANJA_LANGSUNG_SELESAI, $paket->status_paket);
    }

    /**
     * Menguji input inline rekanan vendor saat PP mereview paket 50-200 Juta.
     */
    public function test_pp_can_input_vendor_inline_during_package_review(): void
    {
        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/TEST/PP/001',
            'judul_pengadaan' => 'Pengadaan Jaringan Switch Gigabit',
            'tujuan_pengadaan_ringkas' => 'Upgrade Jaringan Lab',
            'estimasi_total_pagu' => 90000000.00,
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
            'status_anggaran' => 'tersedia',
            'kode_unit' => $this->unit->kode_unit,
            'kode_mak' => $this->anggaranMak->kode_mak,
            'id_user' => $this->regularUser->id_user,
        ]);

        $paket = PaketPengadaan::create([
            'kode_paket' => 'PKT-PP-2026-001',
            'nomor_paket' => 'PKT-PP-2026-001',
            'nama_paket' => 'Pengadaan Jaringan Switch Gigabit',
            'nomor_surat' => $permohonan->nomor_surat,
            'jenis_pengadaan' => 'barang',
            'platform_pengadaan' => 'SPSE',
            'mekanisme_pengadaan' => 'SPK',
            'jalur_routing' => PaketPengadaan::ROUTING_PP,
            'nilai_hps' => 90000000.00,
            'alokasi_pagu_paket' => 90000000.00,
            'id_user_ppbj' => $this->ppbjUser->id_user,
            'id_user_pp' => $this->ppUser->id_user,
            'id_user_ppk' => $this->ppkUser->id_user,
            'status_paket' => 'reviu_dokumen',
        ]);

        $dokumenLegalitas = UploadedFile::fake()->create('legalitas_pt_jaringan.pdf', 600, 'application/pdf');

        $response = $this->actingAs($this->ppUser)->post(route('kontrak.reviu'), [
            'nomor_paket' => $paket->nomor_paket,
            'status_reviu' => 'terverifikasi_pp',
            'nominal_harga_perkiraan' => 88000000.00,
            'nominal_harga_akhir' => 85000000.00,
            'nama_vendor' => 'PT Solusi Network Indonesia',
            'file_dokumen_legalitas' => $dokumenLegalitas,
            'catatan_perbaikan' => 'Dokumen lengkap dan negosiasi disetujui.',
        ]);

        $response->assertRedirect(route('kontrak.index'));

        $this->assertDatabaseHas('vendors', [
            'nama_vendor' => 'PT Solusi Network Indonesia',
        ]);

        $vendor = Vendor::where('nama_vendor', 'PT Solusi Network Indonesia')->first();
        $paket->refresh();
        $this->assertEquals($vendor->id, $paket->id_vendor);
        $this->assertEquals(85000000.00, (float) $paket->nominal_harga_akhir);
    }

    /**
     * Menguji input inline rekanan vendor saat PPBJ mengunggah dokumen perikatan kontrak SPK (> 50 Juta).
     */
    public function test_ppbj_can_publish_kontrak_with_inline_vendor(): void
    {
        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/TEST/KONTRAK/001',
            'judul_pengadaan' => 'Pengadaan Server AI GPU H100',
            'tujuan_pengadaan_ringkas' => 'Server Riset AI',
            'estimasi_total_pagu' => 250000000.00,
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
            'status_anggaran' => 'tersedia',
            'kode_unit' => $this->unit->kode_unit,
            'kode_mak' => $this->anggaranMak->kode_mak,
            'id_user' => $this->regularUser->id_user,
        ]);

        $paket = PaketPengadaan::create([
            'kode_paket' => 'PKT-AI-2026-001',
            'nomor_paket' => 'PKT-AI-2026-001',
            'nama_paket' => 'Pengadaan Server AI GPU H100',
            'nomor_surat' => $permohonan->nomor_surat,
            'jenis_pengadaan' => 'barang',
            'platform_pengadaan' => 'SPSE',
            'mekanisme_pengadaan' => 'Tender',
            'jalur_routing' => PaketPengadaan::ROUTING_PPK,
            'nilai_hps' => 250000000.00,
            'alokasi_pagu_paket' => 250000000.00,
            'id_user_ppbj' => $this->ppbjUser->id_user,
            'id_user_ppk' => $this->ppkUser->id_user,
            'status_paket' => PaketPengadaan::STATUS_SIAP_UPLOAD_DOKUMEN_PPBJ,
        ]);

        $dokumenLegalitas = UploadedFile::fake()->create('akta_dan_nib_server.pdf', 1000, 'application/pdf');
        $spkFile = UploadedFile::fake()->create('kontrak_spk_server.pdf', 1200, 'application/pdf');

        $response = $this->actingAs($this->ppbjUser)->post(route('kontrak.store'), [
            'kode_paket' => $paket->nomor_paket,
            'nama_vendor' => 'PT Graha Komputasi Cerdas',
            'file_dokumen_legalitas' => $dokumenLegalitas,
            'tipe_dokumen' => 'surat_kontrak',
            'nomor_kontrak_spk' => 'KTR/PNJ/AI/2026/001',
            'nilai_kontrak' => 245000000.00,
            'tanggal_mulai' => now()->toDateString(),
            'tanggal_selesai' => now()->addDays(30)->toDateString(),
            'jadwal_pengiriman' => now()->addDays(20)->toDateString(),
            'file_spk' => $spkFile,
        ]);

        $response->assertRedirect(route('kontrak.index'));

        $this->assertDatabaseHas('vendors', [
            'nama_vendor' => 'PT Graha Komputasi Cerdas',
        ]);

        $vendor = Vendor::where('nama_vendor', 'PT Graha Komputasi Cerdas')->first();
        $paket->refresh();
        $this->assertEquals($vendor->id, $paket->id_vendor);
        $this->assertEquals('spk_terbit', $paket->status_paket);

        $this->assertDatabaseHas('kontrak_spk', [
            'nomor_spk' => 'KTR/PNJ/AI/2026/001',
            'nomor_paket' => $paket->nomor_paket,
            'tipe_dokumen' => KontrakSPK::DOKUMEN_SURAT_KONTRAK,
        ]);
    }

    /**
     * Menguji sinkronisasi sisa pagu MAK saat PPBJ mengubah nilai HPS berbeda dari alokasi awal permohonan.
     */
    public function test_mak_is_synchronized_when_ppbj_changes_hps_during_registration(): void
    {
        // 1. Kasus Efisiensi: Awal 50.000.000 menjadi 40.000.000 -> sisa pagu MAK bertambah 10.000.000
        $permohonanEfisiensi = PermohonanPengadaan::create([
            'nomor_surat' => 'B/TEST/MAK/EFISIENSI/001',
            'judul_pengadaan' => 'Pengadaan Fasilitas Audio Lab',
            'tujuan_pengadaan_ringkas' => 'Audio Lab',
            'estimasi_total_pagu' => 50000000.00,
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
            'status_anggaran' => 'tersedia',
            'kode_unit' => $this->unit->kode_unit,
            'kode_mak' => $this->anggaranMak->kode_mak,
            'id_user' => $this->regularUser->id_user,
        ]);

        $saldoAwal = (float) $this->anggaranMak->sisa_pagu_mak; // 80.000.000

        $responseEfisiensi = $this->actingAs($this->ppbjUser)->post(route('paket.store'), [
            'kode_paket' => 'PKT-AUDIO-2026-001',
            'nama_paket' => 'Pengadaan Fasilitas Audio Lab',
            'nomor_surat' => $permohonanEfisiensi->nomor_surat,
            'jenis_pengadaan' => 'Barang',
            'metode_pengadaan' => 'Pengadaan Langsung',
            'nilai_hps' => 40000000.00, // Turun 10 jt
        ]);

        $responseEfisiensi->assertRedirect(route('paket.index'));

        $this->anggaranMak->refresh();
        $this->assertEquals($saldoAwal + 10000000.00, (float) $this->anggaranMak->sisa_pagu_mak);

        // 2. Kasus Kenaikan Melebihi Saldo: Permohonan pagu 10jt, HPS diubah jadi 150jt saat saldo hanya 90jt -> Ditolak
        $permohonanDefisit = PermohonanPengadaan::create([
            'nomor_surat' => 'B/TEST/MAK/DEFISIT/002',
            'judul_pengadaan' => 'Pengadaan Drone Mapping',
            'tujuan_pengadaan_ringkas' => 'Drone TIK',
            'estimasi_total_pagu' => 10000000.00,
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
            'status_anggaran' => 'tersedia',
            'kode_unit' => $this->unit->kode_unit,
            'kode_mak' => $this->anggaranMak->kode_mak,
            'id_user' => $this->regularUser->id_user,
        ]);

        $responseDefisit = $this->actingAs($this->ppbjUser)->post(route('paket.store'), [
            'kode_paket' => 'PKT-DRONE-2026-001',
            'nama_paket' => 'Pengadaan Drone Mapping',
            'nomor_surat' => $permohonanDefisit->nomor_surat,
            'jenis_pengadaan' => 'Barang',
            'metode_pengadaan' => 'Tender',
            'nilai_hps' => 200000000.00, // Selisih 190jt padahal sisa saldo 90jt
            'id_user_ppk' => $this->ppkUser->id_user,
        ]);

        $responseDefisit->assertSessionHasErrors('nilai_hps');
    }
}
