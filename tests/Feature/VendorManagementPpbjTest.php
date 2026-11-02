<?php

namespace Tests\Feature;

use App\Models\PaketPengadaan;
use App\Models\PermohonanPengadaan;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorFileLegalitas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VendorManagementPpbjTest extends TestCase
{
    use RefreshDatabase;

    protected User $ppbjUser;

    protected User $regularUser;

    protected User $adminUser;

    protected Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        // Siapkan unit & role
        $this->unit = Unit::firstOrCreate(
            ['kode_unit' => 'TIK'],
            ['nama_unit' => 'Teknik Informatika dan Komputer', 'jenis_unit' => 'jurusan', 'is_active' => true]
        );

        $rolePpbj = Role::firstOrCreate(['nama_role' => 'ppbj'], ['label' => 'PPBJ', 'deskripsi' => 'Pengadaan Barang & Jasa']);
        $roleUser = Role::firstOrCreate(['nama_role' => 'user'], ['label' => 'Pengusul', 'deskripsi' => 'Pengusul']);
        $roleAdmin = Role::firstOrCreate(['nama_role' => 'admin'], ['label' => 'Administrator', 'deskripsi' => 'Administrator']);

        $this->ppbjUser = User::factory()->create([
            'role' => 'ppbj',
            'id_role' => $rolePpbj->id,
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

        $this->adminUser = User::factory()->create([
            'role' => 'admin',
            'id_role' => $roleAdmin->id,
            'kode_unit' => $this->unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
        ]);
    }

    public function test_ppbj_can_view_vendor_index_page(): void
    {
        $vendor = Vendor::create([
            'npwp' => '01.234.567.8-001.000',
            'nib' => '1234567890001',
            'nama_perusahaan' => 'PT Mitra Solusi Digital PNJ',
            'alamat' => 'Jl. Prof. G.A. Siwabessy Kampus UI Depok',
            'is_active' => true,
            'nama_pic' => 'Ahmad Fauzi',
            'telepon_pic' => '081234567890',
            'email_pic' => 'fauzi@mitrasolusi.com',
            'nama_bank' => 'Bank Mandiri',
            'nomor_rekening' => '1570001234567',
            'atas_nama' => 'PT Mitra Solusi Digital PNJ',
        ]);

        $response = $this->actingAs($this->ppbjUser)->get(route('vendors.index'));

        $response->assertOk();
        $response->assertSee('Direktori Rekanan &amp; Vendor Penyedia', false);
        $response->assertSee('PT Mitra Solusi Digital PNJ');
        $response->assertSee('01.234.567.8-001.000');
    }

    public function test_non_ppbj_cannot_access_vendor_management(): void
    {
        // User biasa tidak berwenang
        $responseUser = $this->actingAs($this->regularUser)->get(route('vendors.index'));
        $responseUser->assertForbidden();

        // Admin juga dibatasi (admin hanya RBAC, user CRUD, hapus pengajuan)
        $responseAdmin = $this->actingAs($this->adminUser)->get(route('vendors.index'));
        $responseAdmin->assertForbidden();
    }

    public function test_ppbj_can_store_new_vendor_with_legalitas_file(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('dokumen_nib.pdf', 500, 'application/pdf');

        $payload = [
            'npwp' => '02.345.678.9-002.000',
            'nib' => '9876543210002',
            'nama_perusahaan' => 'CV Multi Karya Rekatama',
            'alamat' => 'Jl. Margonda Raya No. 100 Depok',
            'is_active' => '1',
            'nama_pic' => 'Budi Santoso',
            'telepon_pic' => '081398765432',
            'email_pic' => 'budi@multikarya.co.id',
            'nama_bank' => 'Bank BNI',
            'nomor_rekening' => '0234567891',
            'atas_nama' => 'CV Multi Karya Rekatama',
            'file_legalitas' => $file,
            'nama_dokumen' => 'NIB & SIUP 2026',
        ];

        $response = $this->actingAs($this->ppbjUser)->post(route('vendors.store'), $payload);

        $response->assertRedirect(route('vendors.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('vendors', [
            'npwp' => '02.345.678.9-002.000',
            'nib' => '9876543210002',
            'nama_perusahaan' => 'CV Multi Karya Rekatama',
        ]);

        $this->assertDatabaseHas('vendor_file_legalitas', [
            'npwp' => '02.345.678.9-002.000',
            'nama_dokumen' => 'NIB & SIUP 2026',
        ]);
    }

    public function test_store_vendor_validation_enforces_unique_npwp_and_nib(): void
    {
        Vendor::create([
            'npwp' => '01.111.111.1-111.000',
            'nib' => '1111111111111',
            'nama_perusahaan' => 'PT Pertama',
            'alamat' => 'Alamat A',
            'is_active' => true,
            'nama_pic' => 'PIC A',
            'telepon_pic' => '0811111111',
            'email_pic' => 'pica@mail.com',
            'nama_bank' => 'Bank BCA',
            'nomor_rekening' => '111111',
            'atas_nama' => 'PT Pertama',
        ]);

        $payloadDuplicateNpwp = [
            'npwp' => '01.111.111.1-111.000', // DUPLIKAT
            'nib' => '2222222222222',
            'nama_perusahaan' => 'PT Kedua',
            'alamat' => 'Alamat B',
            'nama_pic' => 'PIC B',
            'telepon_pic' => '0822222222',
            'email_pic' => 'picb@mail.com',
            'nama_bank' => 'Bank Mandiri',
            'nomor_rekening' => '222222',
            'atas_nama' => 'PT Kedua',
        ];

        $response = $this->actingAs($this->ppbjUser)->post(route('vendors.store'), $payloadDuplicateNpwp);
        $response->assertSessionHasErrors(['npwp']);
    }

    public function test_ppbj_can_update_vendor(): void
    {
        $vendor = Vendor::create([
            'npwp' => '03.333.333.3-333.000',
            'nib' => '3333333333333',
            'nama_perusahaan' => 'PT Tri Daya Teknologi',
            'alamat' => 'Jl. Lenteng Agung',
            'is_active' => true,
            'nama_pic' => 'Citra Dewi',
            'telepon_pic' => '081333333333',
            'email_pic' => 'citra@tridaya.com',
            'nama_bank' => 'Bank BRI',
            'nomor_rekening' => '333300003333',
            'atas_nama' => 'PT Tri Daya Teknologi',
        ]);

        $updatePayload = [
            'nib' => '3333333333333',
            'nama_perusahaan' => 'PT Tri Daya Solusindo Nusantara',
            'alamat' => 'Jl. Lenteng Agung No. 50 Jakarta Selatan',
            'is_active' => '1',
            'nama_pic' => 'Citra Dewi Lestari',
            'telepon_pic' => '081333333334',
            'email_pic' => 'citra.lestari@tridaya.com',
            'nama_bank' => 'Bank Mandiri',
            'nomor_rekening' => '1570003333333',
            'atas_nama' => 'PT Tri Daya Solusindo Nusantara',
        ];

        $response = $this->actingAs($this->ppbjUser)->put(route('vendors.update', $vendor->npwp), $updatePayload);

        $response->assertRedirect(route('vendors.index'));
        $this->assertDatabaseHas('vendors', [
            'npwp' => '03.333.333.3-333.000',
            'nama_perusahaan' => 'PT Tri Daya Solusindo Nusantara',
            'telepon_pic' => '081333333334',
        ]);
    }

    public function test_ppbj_can_toggle_vendor_status(): void
    {
        $vendor = Vendor::create([
            'npwp' => '04.444.444.4-444.000',
            'nib' => '4444444444444',
            'nama_perusahaan' => 'CV Empat Pilar',
            'alamat' => 'Alamat D',
            'is_active' => true,
            'nama_pic' => 'Doni',
            'telepon_pic' => '081444444444',
            'email_pic' => 'doni@empatpilar.com',
            'nama_bank' => 'Bank BNI',
            'nomor_rekening' => '444444',
            'atas_nama' => 'CV Empat Pilar',
        ]);

        // Toggle pertama: aktif -> nonaktif
        $response = $this->actingAs($this->ppbjUser)->post(route('vendors.toggle-status', $vendor->npwp));
        $response->assertRedirect(route('vendors.index'));

        $vendor->refresh();
        $this->assertFalse($vendor->is_active);

        // Toggle kedua: nonaktif -> aktif
        $this->actingAs($this->ppbjUser)->post(route('vendors.toggle-status', $vendor->npwp));
        $vendor->refresh();
        $this->assertTrue($vendor->is_active);
    }

    public function test_ppbj_can_upload_and_delete_additional_legalitas_doc(): void
    {
        Storage::fake('public');

        $vendor = Vendor::create([
            'npwp' => '05.555.555.5-555.000',
            'nib' => '5555555555555',
            'nama_perusahaan' => 'PT Lima Berkah',
            'alamat' => 'Alamat E',
            'is_active' => true,
            'nama_pic' => 'Eko',
            'telepon_pic' => '081555555555',
            'email_pic' => 'eko@limaberkah.com',
            'nama_bank' => 'Bank Mandiri',
            'nomor_rekening' => '555555',
            'atas_nama' => 'PT Lima Berkah',
        ]);

        $file = UploadedFile::fake()->create('akta_perubahan.pdf', 300, 'application/pdf');

        $responseUpload = $this->actingAs($this->ppbjUser)->post(route('vendors.upload-legalitas', $vendor->npwp), [
            'nama_dokumen' => 'Akta Perubahan Notaris 2026',
            'file_legalitas' => $file,
        ]);

        $responseUpload->assertRedirect(route('vendors.index'));
        $this->assertDatabaseHas('vendor_file_legalitas', [
            'npwp' => '05.555.555.5-555.000',
            'nama_dokumen' => 'Akta Perubahan Notaris 2026',
        ]);

        $fileRecord = VendorFileLegalitas::where('nama_dokumen', 'Akta Perubahan Notaris 2026')->first();

        // Hapus dokumen
        $responseDelete = $this->actingAs($this->ppbjUser)->delete(route('vendors.legalitas.destroy', $fileRecord->id));
        $responseDelete->assertRedirect(route('vendors.index'));
        $this->assertDatabaseMissing('vendor_file_legalitas', ['id' => $fileRecord->id]);
    }

    public function test_ppbj_can_delete_unbound_vendor(): void
    {
        $vendor = Vendor::create([
            'npwp' => '06.666.666.6-666.000',
            'nib' => '6666666666666',
            'nama_perusahaan' => 'CV Enam Bersaudara',
            'alamat' => 'Alamat F',
            'is_active' => true,
            'nama_pic' => 'Fajar',
            'telepon_pic' => '081666666666',
            'email_pic' => 'fajar@enam.com',
            'nama_bank' => 'Bank BRI',
            'nomor_rekening' => '666666',
            'atas_nama' => 'CV Enam Bersaudara',
        ]);

        $response = $this->actingAs($this->ppbjUser)->delete(route('vendors.destroy', $vendor->npwp));

        $response->assertRedirect(route('vendors.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('vendors', ['npwp' => '06.666.666.6-666.000']);
    }

    public function test_ppbj_cannot_delete_vendor_tied_to_existing_package(): void
    {
        $vendor = Vendor::create([
            'npwp' => '07.777.777.7-777.000',
            'nib' => '7777777777777',
            'nama_perusahaan' => 'PT Tujuh Pilar Niaga',
            'alamat' => 'Alamat G',
            'is_active' => true,
            'nama_pic' => 'Gilang',
            'telepon_pic' => '081777777777',
            'email_pic' => 'gilang@tujuh.com',
            'nama_bank' => 'Bank Mandiri',
            'nomor_rekening' => '777777',
            'atas_nama' => 'PT Tujuh Pilar Niaga',
        ]);

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/TEST/VENDOR/001',
            'judul_pengadaan' => 'Pengadaan Uji Vendor',
            'tujuan_pengadaan_ringkas' => 'Uji pengadaan',
            'estimasi_total_pagu' => 45000000,
            'kode_unit' => $this->unit->kode_unit,
            'id_user' => $this->regularUser->id_user,
            'tanggal_pengajuan' => now(),
            'status_permohonan' => 'disetujui',
            'status_anggaran' => 'disetujui',
        ]);

        PaketPengadaan::create([
            'nomor_paket' => 'PKT-TEST-001',
            'nomor_surat' => $permohonan->nomor_surat,
            'nama_paket' => 'Paket Uji Vendor',
            'alokasi_pagu_paket' => 45000000,
            'nilai_hps' => 45000000,
            'jenis_pengadaan' => 'barang',
            'metode_pengadaan' => 'belanja_langsung',
            'jalur_routing' => 'langsung_sd_50jt',
            'status_paket' => 'terdaftar',
            'tanggal_registrasi_paket' => now(),
            'npwp_vendor' => $vendor->npwp,
        ]);

        // Coba hapus vendor
        $response = $this->actingAs($this->ppbjUser)->delete(route('vendors.destroy', $vendor->npwp));

        $response->assertRedirect(route('vendors.index'));
        $response->assertSessionHas('error');
        // Vendor masih ada
        $this->assertDatabaseHas('vendors', ['npwp' => '07.777.777.7-777.000']);
    }
}
