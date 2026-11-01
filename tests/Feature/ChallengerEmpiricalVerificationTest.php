<?php

namespace Tests\Feature;

use App\Models\AnggaranMAK;
use App\Models\PaketPengadaan;
use App\Models\PermohonanPengadaan;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VerifikasiParalel;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * ChallengerEmpiricalVerificationTest
 *
 * Verifikasi empiris adversarial untuk memastikan kepatuhan tampilan frontend (non-tes_*),
 * dual engine rendering (DEV_VIEWMODE=frontend), batasan UI role PP, batasan Belanja Langsung <= 50jt,
 * opsi BAST nomor_paket Belanja Langsung di Perlengkapan, serta eksklusivitas Lock/Unlock MAK di Perencanaan.
 */
class ChallengerEmpiricalVerificationTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unitTik;

    private User $pemohon;

    private User $staffB2;

    private User $wadir2;

    private User $perencanaan;

    private User $ppbj;

    private User $pp;

    private User $ppk1;

    private User $perlengkapan;

    private User $keuangan;

    private Vendor $vendor;

    private AnggaranMAK $mak;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Notification::fake();

        $this->seed(RolePermissionSeeder::class);
        $roles = Role::all()->keyBy('nama_role');

        $this->unitTik = Unit::firstOrCreate(
            ['kode_unit' => 'TIK'],
            ['nama_unit' => 'Jurusan Teknik Informatika dan Komputer', 'jenis_unit' => 'jurusan']
        );

        $this->pemohon = User::factory()->create([
            'role' => 'user',
            'id_role' => $roles['user']->id,
            'kode_unit' => 'TIK',
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Dosen Pengusul TIK',
        ]);

        $this->staffB2 = User::factory()->create([
            'role' => 'staff_bidang_2',
            'id_role' => $roles['staff_bidang_2']->id,
            'kode_unit' => 'TIK',
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Staf Bidang II PNJ',
        ]);

        $this->wadir2 = User::factory()->create([
            'role' => 'wadir_2',
            'id_role' => $roles['wadir_2']->id,
            'kode_unit' => 'TIK',
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Wakil Direktur II PNJ',
        ]);

        $this->perencanaan = User::factory()->create([
            'role' => 'perencanaan',
            'id_role' => $roles['perencanaan']->id,
            'kode_unit' => 'TIK',
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Analis Perencanaan PNJ',
        ]);

        $this->ppbj = User::factory()->create([
            'role' => 'ppbj',
            'id_role' => $roles['ppbj']->id,
            'kode_unit' => 'TIK',
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Koordinator PPBJ PNJ',
        ]);

        $this->pp = User::factory()->create([
            'role' => 'pp',
            'id_role' => $roles['pp']->id,
            'kode_unit' => 'TIK',
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Pejabat Pengadaan Tunggal PNJ',
        ]);

        $this->ppk1 = User::factory()->create([
            'role' => 'ppk',
            'id_role' => $roles['ppk']->id,
            'kode_unit' => 'TIK',
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'PPK 1 Bidang Akademik',
        ]);

        $this->perlengkapan = User::factory()->create([
            'role' => 'perlengkapan',
            'id_role' => $roles['perlengkapan']->id,
            'kode_unit' => 'TIK',
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Petugas Unit Perlengkapan',
        ]);

        $this->keuangan = User::factory()->create([
            'role' => 'keuangan',
            'id_role' => $roles['keuangan']->id,
            'kode_unit' => 'TIK',
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Bendahara Keuangan PNJ',
        ]);

        $this->vendor = Vendor::create([
            'npwp' => '02.345.678.9-012.000',
            'nib' => '9876543210123',
            'nama_perusahaan' => 'PT Mitra Teknologi Nusantara',
            'alamat' => 'Jl. Prof. Dr. G.A. Siwabessy Depok',
            'is_active' => true,
            'nama_pic' => 'Rudi Hartono',
            'telepon_pic' => '081299998888',
            'email_pic' => 'rudi@mitrateknologi.co.id',
            'nama_bank' => 'BNI',
            'nomor_rekening' => '0098765432',
            'atas_nama' => 'PT Mitra Teknologi Nusantara',
        ]);

        $this->mak = AnggaranMAK::create([
            'kode_mak' => '023.18.WA.521211.001',
            'uraian_mak' => 'Belanja Pengadaan Peralatan Laboratorium Terpadu',
            'tahun_anggaran' => (int) date('Y'),
            'pagu_anggaran' => 500000000,
            'sisa_pagu_mak' => 500000000,
            'sumber_dana' => 'RM',
            'is_locked' => false,
        ]);
    }

    private function createPermohonan(string $nomorSurat, float $pagu = 100000000): PermohonanPengadaan
    {
        return PermohonanPengadaan::create([
            'nomor_surat' => $nomorSurat,
            'perihal' => 'Pengadaan Laboratorium Uji '.$nomorSurat,
            'tanggal_pengajuan' => now(),
            'kode_unit' => 'TIK',
            'id_user' => $this->pemohon->id_user,
            'tujuan_pengadaan_ringkas' => 'Kebutuhan riset dan pengujian sistem.',
            'file_permohonan' => 'permohonan/surat_test.pdf',
            'estimasi_total_pagu' => $pagu,
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
            'kode_mak' => $this->mak->kode_mak,
        ]);
    }

    /**
     * 1A. Memverifikasi view verifikasi/ppk.blade.php terkompilasi dan me-render bersih
     * di bawah DEV_VIEWMODE=frontend saat antrean kosong tanpa 500 error.
     */
    public function test_verifikasi_ppk_view_compiles_and_renders_cleanly_when_empty_under_frontend_mode(): void
    {
        config(['app.dev_viewmode' => 'frontend']);

        $response = $this->actingAs($this->ppk1)->get(route('verifikasi.ppk'));

        $response->assertStatus(200);
        $response->assertSee('Persetujuan Awal Usulan Pengadaan (PPK)');
        $response->assertSee('PPK Terpilih');
        $response->assertSee('Tidak Ada Antrean Usulan');
        $response->assertSee('build/assets/app-');
        $response->assertDontSee('cdn.tailwindcss.com');
    }

    /**
     * 1B. Memverifikasi view verifikasi/ppk.blade.php me-render data antrean usulan, memo MAK,
     * status Wadir 2, dan formulir persetujuan awal PPK tanpa error 500 di bawah DEV_VIEWMODE=frontend.
     */
    public function test_verifikasi_ppk_view_compiles_and_renders_with_active_antrean_under_frontend_mode(): void
    {
        config(['app.dev_viewmode' => 'frontend']);

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/TEST/001/TIK/2026',
            'perihal' => 'Pengadaan Server AI Laboratorium Riset',
            'tanggal_pengajuan' => now(),
            'kode_unit' => 'TIK',
            'id_user' => $this->pemohon->id_user,
            'tujuan_pengadaan_ringkas' => 'Server riset komputasi mahasiswa dan dosen.',
            'file_permohonan' => 'permohonan/surat_srikandi_test.pdf',
            'estimasi_total_pagu' => 175000000,
            'status_permohonan' => PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK,
            'kode_mak' => $this->mak->kode_mak,
            'nomor_memo_ketersediaan_mak' => 'MEMO/PRC/TEST/001',
            'catatan_memo_ketersediaan_mak' => 'Pagu DIPA mencukupi pada akun 521211',
            'id_user_ppk_awal' => $this->ppk1->id_user,
        ]);

        VerifikasiParalel::create([
            'nomor_surat' => $permohonan->nomor_surat,
            'role_verifikator' => VerifikasiParalel::ROLE_WADIR_2,
            'id_user' => $this->wadir2->id_user,
            'status_keputusan' => 'disetujui',
            'catatan_alasan' => 'Kegiatan disetujui Wadir II.',
            'tanggal_keputusan' => now(),
        ]);

        VerifikasiParalel::create([
            'nomor_surat' => $permohonan->nomor_surat,
            'role_verifikator' => VerifikasiParalel::ROLE_PERENCANAAN,
            'id_user' => $this->perencanaan->id_user,
            'status_keputusan' => 'disetujui',
            'catatan_alasan' => 'MAK siap didanai.',
            'tanggal_keputusan' => now(),
        ]);

        $response = $this->actingAs($this->ppk1)->get(route('verifikasi.ppk'));

        $response->assertStatus(200);
        $response->assertSee('B/TEST/001/TIK/2026');
        $response->assertSee('Pengadaan Server AI Laboratorium Riset');
        $response->assertSee('MEMO/PRC/TEST/001');
        $response->assertSee('Telah Disetujui (ACC)');
        $response->assertSee('SETUJUI USULAN (ACC PPK)');
        $response->assertSee('TOLAK / MINTA REVISI');
        $response->assertSee('Simpan Keputusan Persetujuan Awal PPK');
        $response->assertSee('build/assets/app-');
        $response->assertDontSee('cdn.tailwindcss.com');
    }

    /**
     * 2. Memverifikasi pembatasan UI role PP pada resources/views/kontrak/index.blade.php:
     * PP tidak memiliki tombol / radio Approval ('disetujui'), melainkan hanya 'terverifikasi_pp' dan 'perlu_perbaikan'.
     */
    public function test_role_pp_ui_restriction_in_kontrak_index(): void
    {
        config(['app.dev_viewmode' => 'frontend']);

        $permohonan = $this->createPermohonan('B/TEST/PP/2026', 120000000);

        $paket = PaketPengadaan::create([
            'nomor_paket' => 'PKT-TEST-PP-001',
            'kode_paket' => 'PKT-TEST-PP-001',
            'nomor_surat' => $permohonan->nomor_surat,
            'nama_paket' => 'Pengadaan Perangkat Jaringan Kampus',
            'jenis_pengadaan' => 'barang',
            'metode_pengadaan' => 'E-Purchasing',
            'nilai_hps' => 120000000,
            'jalur_routing' => 'pp_50jt_sd_200jt',
            'status_paket' => 'reviu_dokumen',
            'id_user_pp' => $this->pp->id_user,
            'id_user_ppk' => $this->ppk1->id_user,
        ]);

        $response = $this->actingAs($this->pp)->get(route('kontrak.index'));

        $response->assertStatus(200);
        $response->assertSee('Pejabat Pengadaan (PP)');
        $response->assertSee('value="terverifikasi_pp"', false);
        $response->assertSee('value="perlu_perbaikan"', false);
        // Memastikan tidak ada opsi radio 'disetujui' (Approval) untuk role PP pada blok keputusan reviu
        $response->assertDontSee('Disetujui (ACC PPK)');
        $response->assertSee('Kirim Hasil Reviu &amp; Negosiasi PP', false);
        $response->assertSee('id="nominal_harga_perkiraan"', false);
        $response->assertSee('id="nilai_hasil_negosiasi"', false);
        $response->assertSee('id="npwp_vendor_pp"', false);
    }

    /**
     * 2B. Memverifikasi bahwa role PPK mendapatkan opsi radio Approval ('disetujui') pada kontrak/index.blade.php.
     */
    public function test_role_ppk_has_approval_in_kontrak_index(): void
    {
        config(['app.dev_viewmode' => 'frontend']);

        $permohonan = $this->createPermohonan('B/TEST/PPK/2026', 250000000);

        $paket = PaketPengadaan::create([
            'nomor_paket' => 'PKT-TEST-PPK-001',
            'kode_paket' => 'PKT-TEST-PPK-001',
            'nomor_surat' => $permohonan->nomor_surat,
            'nama_paket' => 'Pengadaan Fasilitas Server Cloud Kampus',
            'jenis_pengadaan' => 'barang',
            'metode_pengadaan' => 'Tender',
            'nilai_hps' => 250000000,
            'jalur_routing' => 'ppk_diatas_200jt',
            'status_paket' => 'reviu_dokumen',
            'id_user_ppk' => $this->ppk1->id_user,
        ]);

        $response = $this->actingAs($this->ppk1)->get(route('kontrak.index'));

        $response->assertStatus(200);
        $response->assertSee('PPK:');
        $response->assertSee('value="disetujui"', false);
        $response->assertSee('Disetujui (ACC PPK)');
        $response->assertSee('Kirim Persetujuan Dokumen (ACC PPK)');
    }

    /**
     * 3. Memverifikasi batasan Belanja Langsung <= 50jt pada paket/create.blade.php & paket/index.blade.php:
     * Tampilan strictly menampilkan output Kwitansi dan tanpa SPK/Kontrak generation.
     */
    public function test_belanja_langsung_constraints_in_paket_create_and_index(): void
    {
        config(['app.dev_viewmode' => 'frontend']);

        // A. Cek paket.create
        $responseCreate = $this->actingAs($this->ppbj)->get(route('paket.create'));
        $responseCreate->assertStatus(200);
        $responseCreate->assertSee('Nilai HPS &le; Rp 50 Juta adalah Belanja Langsung (output Kwitansi sah tanpa Kontrak/SPK/Surat Pesanan)', false);

        // B. Cek paket.index dengan paket Belanja Langsung <= 50jt
        $permohonanBL = $this->createPermohonan('B/TEST/BL/2026', 25000000);

        $paketLangsung = PaketPengadaan::create([
            'nomor_paket' => 'PKT-TEST-BL-001',
            'kode_paket' => 'PKT-TEST-BL-001',
            'nomor_surat' => $permohonanBL->nomor_surat,
            'nama_paket' => 'Belanja ATK dan Kertas Kantor',
            'jenis_pengadaan' => 'barang',
            'metode_pengadaan' => 'Pengadaan Langsung',
            'nilai_hps' => 25000000,
            'jalur_routing' => 'langsung_sd_50jt',
            'status_paket' => 'terdaftar',
        ]);

        $responseIndex = $this->actingAs($this->ppbj)->get(route('paket.index'));
        $responseIndex->assertStatus(200);
        $responseIndex->assertSee('modal-belanja-langsung');
        $responseIndex->assertSee('Pencatatan pembelian langsung dengan output Kwitansi sah (tanpa SPK/Kontrak)');
        $responseIndex->assertSee('id="modal_nomor_kwitansi"', false);
        $responseIndex->assertSee('id="modal_file_kwitansi"', false);
        $responseIndex->assertSee('Simpan Belanja Langsung');
    }

    /**
     * 4. Memverifikasi bahwa penerimaan/index.blade.php menyediakan opsi nomor_paket
     * untuk paket Belanja Langsung (<= 50jt) siap BAST di Unit Perlengkapan.
     */
    public function test_perlengkapan_bast_provides_nomor_paket_for_belanja_langsung(): void
    {
        config(['app.dev_viewmode' => 'frontend']);

        $permohonanBL = $this->createPermohonan('B/TEST/BLBAST/2026', 45000000);

        $paketBL = PaketPengadaan::create([
            'nomor_paket' => 'PKT-BL-BAST-001',
            'kode_paket' => 'PKT-BL-BAST-001',
            'nomor_surat' => $permohonanBL->nomor_surat,
            'nama_paket' => 'Pengadaan Meja Kursi Ruang Rapat',
            'jenis_pengadaan' => 'barang',
            'metode_pengadaan' => 'Pengadaan Langsung',
            'nilai_hps' => 45000000,
            'jalur_routing' => 'langsung_sd_50jt',
            'status_paket' => PaketPengadaan::STATUS_BELANJA_LANGSUNG_SELESAI,
            'nomor_kwitansi_belanja' => 'KWT/2026/001',
            'nominal_harga_akhir' => 44000000,
            'npwp_vendor' => $this->vendor->npwp,
        ]);

        $response = $this->actingAs($this->perlengkapan)->get(route('penerimaan.index'));

        $response->assertStatus(200);
        $response->assertSee('Opsi B: Paket Belanja Langsung (&le; Rp 50 Juta)', false);
        $response->assertSee('id="nomor_paket" name="nomor_paket"', false);
        $response->assertSee('PKT-BL-BAST-001');
        $response->assertSee('KWT/2026/001');
    }

    /**
     * 5. Memverifikasi eksklusivitas Lock dan Unlock MAK pada verifikasi/perencanaan.blade.php:
     * Hanya role perencanaan yang dapat mengakses dan mengeksekusi aksi lock/unlock; role lain 403 Forbidden.
     */
    public function test_mak_lock_and_unlock_exclusivity_to_perencanaan_role(): void
    {
        config(['app.dev_viewmode' => 'frontend']);

        // A. Role Perencanaan dapat melihat form Lock/Unlock di view
        $responsePerencanaan = $this->actingAs($this->perencanaan)->get(route('verifikasi.perencanaan'));
        $responsePerencanaan->assertStatus(200);
        $responsePerencanaan->assertSee('Aksi (Lock/Unlock)');
        $responsePerencanaan->assertSee(route('verifikasi.perencanaan.mak.lock', $this->mak->kode_mak));

        // B. Eksekusi Lock oleh Perencanaan
        $responseLock = $this->actingAs($this->perencanaan)
            ->post(route('verifikasi.perencanaan.mak.lock', $this->mak->kode_mak));
        $responseLock->assertRedirect(route('verifikasi.perencanaan'));
        $this->assertTrue((bool) $this->mak->fresh()->is_locked);

        // C. Eksekusi Unlock oleh Perencanaan
        $responseUnlock = $this->actingAs($this->perencanaan)
            ->post(route('verifikasi.perencanaan.mak.unlock', $this->mak->kode_mak));
        $responseUnlock->assertRedirect(route('verifikasi.perencanaan'));
        $this->assertFalse((bool) $this->mak->fresh()->is_locked);

        // D. Non-Perencanaan (PPK) diblokir 403 Forbidden
        $this->actingAs($this->ppk1)->get(route('verifikasi.perencanaan'))->assertStatus(403);
        $this->actingAs($this->ppk1)->post(route('verifikasi.perencanaan.mak.lock', $this->mak->kode_mak))->assertStatus(403);
        $this->actingAs($this->ppk1)->post(route('verifikasi.perencanaan.mak.unlock', $this->mak->kode_mak))->assertStatus(403);

        // E. Non-Perencanaan (PP) diblokir 403 Forbidden
        $this->actingAs($this->pp)->get(route('verifikasi.perencanaan'))->assertStatus(403);
        $this->actingAs($this->pp)->post(route('verifikasi.perencanaan.mak.lock', $this->mak->kode_mak))->assertStatus(403);
        $this->actingAs($this->pp)->post(route('verifikasi.perencanaan.mak.unlock', $this->mak->kode_mak))->assertStatus(403);

        // F. Non-Perencanaan (User Pemohon) diblokir 403 Forbidden
        $this->actingAs($this->pemohon)->get(route('verifikasi.perencanaan'))->assertStatus(403);
        $this->actingAs($this->pemohon)->post(route('verifikasi.perencanaan.mak.lock', $this->mak->kode_mak))->assertStatus(403);
        $this->actingAs($this->pemohon)->post(route('verifikasi.perencanaan.mak.unlock', $this->mak->kode_mak))->assertStatus(403);
    }
}
