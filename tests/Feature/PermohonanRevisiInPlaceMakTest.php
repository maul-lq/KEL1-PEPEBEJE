<?php

namespace Tests\Feature;

use App\Models\AlokasiMAK;
use App\Models\AnggaranMAK;
use App\Models\PermohonanPengadaan;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PermohonanRevisiInPlaceMakTest extends TestCase
{
    use RefreshDatabase;

    protected User $pemohon;

    protected User $staffB2;

    protected Unit $unit;

    protected AnggaranMAK $mak;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        Role::firstOrCreate(['nama_role' => 'user'], ['label' => 'User Pemohon', 'deskripsi' => 'Pengusul']);
        Role::firstOrCreate(['nama_role' => 'staff_bidang_2'], ['label' => 'Staf Bidang 2', 'deskripsi' => 'Verifikator Berkas']);
        Role::firstOrCreate(['nama_role' => 'admin'], ['label' => 'Admin Sistem', 'deskripsi' => 'Administrator']);

        $this->unit = Unit::firstOrCreate(
            ['kode_unit' => 'TIK'],
            ['nama_unit' => 'Teknologi Informasi & Komputer', 'jenis_unit' => 'jurusan']
        );

        $this->pemohon = User::factory()->create([
            'role' => 'user',
            'kode_unit' => $this->unit->kode_unit,
            'status_aktif' => true,
        ]);

        $this->staffB2 = User::factory()->create([
            'role' => 'staff_bidang_2',
            'kode_unit' => $this->unit->kode_unit,
            'status_aktif' => true,
        ]);

        $this->mak = AnggaranMAK::create([
            'kode_mak' => 'MAK-521111-TEST',
            'uraian_mak' => 'Belanja Modal Peralatan Komputer',
            'pagu_anggaran' => 500000000,
            'sisa_pagu_mak' => 400000000, // Sudah terpotong 100jt oleh usulan sebelumnya
            'tahun_anggaran' => 2026,
            'sumber_dana' => 'RM',
            'id_user' => $this->pemohon->id_user,
        ]);
    }

    public function test_in_place_revision_updates_same_record_without_duplicate_and_refunds_excess_mak(): void
    {
        $this->actingAs($this->pemohon);

        $induk = PermohonanPengadaan::create([
            'nomor_surat' => 'B/REVISI-INPLACE-01/TIK/2026',
            'perihal' => 'Pengadaan Laptop Praktikum',
            'anggaran_diajukan' => 100000000,
            'estimasi_total_pagu' => 100000000,
            'kode_unit' => $this->unit->kode_unit,
            'id_user' => $this->pemohon->id_user,
            'status_permohonan' => 'perlu_revisi',
            'status' => 'perlu_revisi',
            'kode_mak' => $this->mak->kode_mak,
            'status_anggaran' => 'tersedia',
            'revisi_ke' => 0,
        ]);

        AlokasiMAK::create([
            'nomor_surat' => $induk->nomor_surat,
            'kode_mak' => $this->mak->kode_mak,
            'nominal_alokasi' => 100000000,
            'tanggal_alokasi' => now(),
        ]);

        $initialCount = PermohonanPengadaan::count();

        // Pemohon mengirim revisi in-place dengan anggaran diturunkan menjadi 80 juta (surat sama)
        $response = $this->post(route('permohonan.revisi'), [
            'nomor_surat_lama' => $induk->nomor_surat,
            'nomor_surat_baru' => $induk->nomor_surat, // Nomor surat sama
            'perihal' => 'Pengadaan Laptop Praktikum (Revisi Spesifikasi)',
            'anggaran_diajukan' => 80000000,
            'keterangan_revisi' => 'Pagu dan spek dirampingkan sesuai arahan Wadir 2',
            'file_kak' => UploadedFile::fake()->create('kak_revisi.pdf', 500, 'application/pdf'),
        ]);

        $response->assertRedirect(route('permohonan.index'));
        $response->assertSessionHas('success');

        // Jumlah record di tabel permohonan TIDAK BERTAMBAH (in-place)
        $this->assertEquals($initialCount, PermohonanPengadaan::count());

        $induk->refresh();
        $this->mak->refresh();

        $this->assertEquals(1, $induk->revisi_ke);
        $this->assertEquals('Pengadaan Laptop Praktikum (Revisi Spesifikasi)', $induk->perihal);
        $this->assertEquals(80000000, (float) $induk->anggaran_diajukan);
        $this->assertEquals(PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2, $induk->status_permohonan);

        // MAK tetap terikat pada usulan, tidak terhapus
        $this->assertEquals($this->mak->kode_mak, $induk->kode_mak);

        // AlokasiMAK diperbarui nominalnya menjadi 80 juta
        $alokasi = AlokasiMAK::where('nomor_surat', $induk->nomor_surat)->first();
        $this->assertNotNull($alokasi);
        $this->assertEquals(80000000, (float) $alokasi->nominal_alokasi);

        // Sisa pagu MAK otomatis di-refund sebesar selisih (20 juta): dari 400jt -> 420jt
        $this->assertEquals(420000000, (float) $this->mak->sisa_pagu_mak);
    }

    public function test_in_place_revision_does_not_double_deduct_mak_when_budget_unchanged(): void
    {
        $this->actingAs($this->pemohon);

        $induk = PermohonanPengadaan::create([
            'nomor_surat' => 'B/REVISI-INPLACE-02/TIK/2026',
            'perihal' => 'Pengadaan Proyektor Kelas',
            'anggaran_diajukan' => 50000000,
            'estimasi_total_pagu' => 50000000,
            'kode_unit' => $this->unit->kode_unit,
            'id_user' => $this->pemohon->id_user,
            'status_permohonan' => 'perlu_revisi',
            'status' => 'perlu_revisi',
            'kode_mak' => $this->mak->kode_mak,
            'status_anggaran' => 'tersedia',
            'revisi_ke' => 0,
        ]);

        AlokasiMAK::create([
            'nomor_surat' => $induk->nomor_surat,
            'kode_mak' => $this->mak->kode_mak,
            'nominal_alokasi' => 50000000,
            'tanggal_alokasi' => now(),
        ]);

        $sisaSebelum = (float) $this->mak->sisa_pagu_mak;

        // Pemohon mengirim revisi berkas tanpa mengubah nominal anggaran
        $response = $this->post(route('permohonan.revisi'), [
            'nomor_surat_lama' => $induk->nomor_surat,
            'perihal' => 'Pengadaan Proyektor Kelas (Perbaikan KAK)',
            'anggaran_diajukan' => 50000000,
            'keterangan_revisi' => 'Hanya perbaikan redaksional KAK',
            'file_kak' => UploadedFile::fake()->create('kak_proyektor.pdf', 300, 'application/pdf'),
        ]);

        $response->assertRedirect(route('permohonan.index'));

        $this->mak->refresh();

        // Sisa pagu MAK tetap sama, TIDAK terpotong dua kali!
        $this->assertEquals($sisaSebelum, (float) $this->mak->sisa_pagu_mak);
    }

    public function test_revision_with_new_surat_transfers_mak_and_marks_old_as_replaced(): void
    {
        $this->actingAs($this->pemohon);

        $induk = PermohonanPengadaan::create([
            'nomor_surat' => 'B/ORIGINAL-01/TIK/2026',
            'perihal' => 'Pengadaan Router Core',
            'anggaran_diajukan' => 60000000,
            'estimasi_total_pagu' => 60000000,
            'kode_unit' => $this->unit->kode_unit,
            'id_user' => $this->pemohon->id_user,
            'status_permohonan' => 'perlu_revisi',
            'status' => 'perlu_revisi',
            'kode_mak' => $this->mak->kode_mak,
            'status_anggaran' => 'tersedia',
            'revisi_ke' => 0,
        ]);

        AlokasiMAK::create([
            'nomor_surat' => $induk->nomor_surat,
            'kode_mak' => $this->mak->kode_mak,
            'nominal_alokasi' => 60000000,
            'tanggal_alokasi' => now(),
        ]);

        $sisaSebelum = (float) $this->mak->sisa_pagu_mak;

        // Pemohon mengajukan dengan nomor surat baru dari Srikandi
        $response = $this->post(route('permohonan.revisi'), [
            'nomor_surat_lama' => $induk->nomor_surat,
            'nomor_surat_baru' => 'B/ORIGINAL-01-REV/TIK/2026',
            'perihal' => 'Pengadaan Router Core (Revisi)',
            'anggaran_diajukan' => 60000000,
            'keterangan_revisi' => 'Nomor agenda Srikandi baru',
            'file_kak' => UploadedFile::fake()->create('kak_router.pdf', 400, 'application/pdf'),
        ]);

        $response->assertRedirect(route('permohonan.index'));

        $induk->refresh();
        $this->mak->refresh();

        // Surat lama ditandai digantikan dan MAK-nya dilepas
        $this->assertEquals('digantikan_oleh_revisi', $induk->status_permohonan);
        $this->assertNull($induk->kode_mak);

        // Surat baru mewarisi alokasi MAK tanpa memotong saldo MAK kampus lagi
        $revisi = PermohonanPengadaan::where('nomor_surat', 'B/ORIGINAL-01-REV/TIK/2026')->first();
        $this->assertNotNull($revisi);
        $this->assertEquals($this->mak->kode_mak, $revisi->kode_mak);
        $this->assertEquals($induk->nomor_surat, $revisi->nomor_surat_revisi);
        $this->assertEquals(1, $revisi->revisi_ke);

        // AlokasiMAK lama dipindahkan ke nomor surat baru
        $this->assertDatabaseMissing('alokasi_mak', ['nomor_surat' => $induk->nomor_surat]);
        $this->assertDatabaseHas('alokasi_mak', [
            'nomor_surat' => $revisi->nomor_surat,
            'kode_mak' => $this->mak->kode_mak,
            'nominal_alokasi' => 60000000,
        ]);

        // Sisa pagu MAK tidak berkurang ganda
        $this->assertEquals($sisaSebelum, (float) $this->mak->sisa_pagu_mak);
    }

    public function test_destroy_permohonan_refunds_allocated_mak_budget(): void
    {
        $this->actingAs($this->pemohon);

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/BATAL-ME/TIK/2026',
            'perihal' => 'Pengadaan Dibatalkan',
            'anggaran_diajukan' => 75000000,
            'estimasi_total_pagu' => 75000000,
            'kode_unit' => $this->unit->kode_unit,
            'id_user' => $this->pemohon->id_user,
            'status_permohonan' => 'ditolak',
            'kode_mak' => $this->mak->kode_mak,
        ]);

        AlokasiMAK::create([
            'nomor_surat' => $permohonan->nomor_surat,
            'kode_mak' => $this->mak->kode_mak,
            'nominal_alokasi' => 75000000,
            'tanggal_alokasi' => now(),
        ]);

        $sisaAwal = (float) $this->mak->sisa_pagu_mak; // 400jt

        // User membatalkan / menghapus permohonan
        $response = $this->delete(route('permohonan.destroy', $permohonan->nomor_surat));
        $response->assertRedirect(route('permohonan.index'));

        $this->mak->refresh();

        // Sisa pagu MAK bertambah kembali sebesar 75 juta (dari 400jt -> 475jt)
        $this->assertEquals($sisaAwal + 75000000, (float) $this->mak->sisa_pagu_mak);
        $this->assertDatabaseMissing('alokasi_mak', ['nomor_surat' => $permohonan->nomor_surat]);
    }
}
