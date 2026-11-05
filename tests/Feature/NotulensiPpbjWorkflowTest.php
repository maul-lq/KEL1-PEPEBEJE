<?php

namespace Tests\Feature;

use App\Models\AnggaranMAK;
use App\Models\KontrakSPK;
use App\Models\MemoBayar;
use App\Models\PaketPengadaan;
use App\Models\Pembayaran;
use App\Models\PenerimaanBarang;
use App\Models\PermohonanPengadaan;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pengujian End-to-End Alur Bisnis SIM-PBJ Berdasarkan Notulensi Pertemuan dengan PPBJ.
 *
 * Mencakup verifikasi menyeluruh atas 7 ketetapan utama:
 * 1. Alur persetujuan awal berantai: User -> Bidang 2 (Memo Cek MAK) -> Perencanaan (Cek/Buat MAK,
 *    pilih 1 dari 4 akun PPK, Memo Ketersediaan MAK) -> Wadir 2 & PPK terpilih sama-sama ACC -> PPBJ.
 * 2. Otoritas tunggal penguncian (lock) dan pembukaan kunci (unlock) MAK murni pada Perencanaan.
 * 3. Penentuan Metode Pengadaan (belanja_langsung, non_tender/e_katalog Inaproc, tender/spse) & 3 jalur:
 *    - <= Rp50.000.000: Belanja Langsung oleh PPBJ, output Kwitansi, TANPA Kontrak/SPK/Surat Pesanan.
 *    - > Rp50.000.000 s.d. Rp200.000.000: Melibatkan PP (Tender & Non-Tender), PP review + input
 *      Harga Perkiraan, Harga Akhir, & Vendor (PP tidak bisa klik Approval final), Approval oleh PPK,
 *      lalu PP mengeksekusi kewenangan belanja.
 *    - > Rp200.000.000: Melibatkan PPK (tanpa Memo Pengadaan PPBJ), PPK review, klik Approval, & belanja.
 * 4. Unggah tepat salah satu dokumen perikatan untuk paket > Rp50 Juta (surat_pesanan, spk, atau surat_kontrak).
 * 5. Penerbitan BAST eksklusif oleh Perlengkapan (baik paket > 50jt maupun <= 50jt Belanja Langsung).
 * 6. Informasi Dokumen Pembayaran memuat referensi dokumen perikatan tunggal + Kwitansi + Invoice.
 */
class NotulensiPpbjWorkflowTest extends TestCase
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

    private User $ppk2;

    private User $perlengkapan;

    private User $keuangan;

    private Vendor $vendor;

    private AnggaranMAK $mak;

    /**
     * Menyiapkan 9 peran organisasi PNJ (termasuk 1 akun PP dan akun PPK terisolasi),
     * master unit kerja, master rekanan vendor, dan mata anggaran kegiatan (MAK) awal.
     */
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

        $this->ppk2 = User::factory()->create([
            'role' => 'ppk',
            'id_role' => $roles['ppk']->id,
            'kode_unit' => 'TIK',
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'PPK 2 Bidang Infrastruktur',
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
            'tahun_anggaran' => 2026,
            'pagu_anggaran' => 1000000000,
            'sisa_pagu_mak' => 1000000000,
            'sumber_dana' => 'RM',
            'is_locked' => false,
            'id_user' => $this->perencanaan->id_user,
        ]);
    }

    /**
     * Menguji alur persetujuan awal berantai:
     * User -> Staff Bidang 2 (Memo Cek MAK) -> Perencanaan (Cek MAK + Pilih PPK 1 + Memo Ketersediaan MAK)
     * -> Wadir 2 & PPK 1 wajib sama-sama ACC sebelum berstatus disetujui dan masuk ke PPBJ,
     * disertai proteksi 403 apabila PPK 2 mencoba menyetujui usulan milik PPK 1.
     */
    public function test_alur_persetujuan_awal_berantai_dan_isolasi_ppk_awal(): void
    {
        // 1. User mengajukan surat permohonan ke Bidang 2
        $this->actingAs($this->pemohon);
        $resAjukan = $this->postJson(route('permohonan-pengadaan.store'), [
            'nomor_surat' => 'B/100/PL3.TIK/PG/2026',
            'judul_pengadaan' => 'Pengadaan Perangkat Praktikum Jaringan',
            'tujuan_pengadaan_ringkas' => 'Upgrade switch dan router laboratorium',
            'file_permohonan' => UploadedFile::fake()->create('usulan_100.pdf', 512, 'application/pdf'),
            'estimasi_total_pagu' => 135000000,
            'kode_unit' => 'TIK',
        ]);

        $resAjukan->assertStatus(201)
            ->assertJsonPath('data.status_permohonan', PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2);

        // 2. Staff Bidang 2 memeriksa berkas & mengirim Memo Cek MAK ke Perencanaan
        $this->actingAs($this->staffB2);
        $resMemoCek = $this->postJson(route('verifikasi-paralel.memo-cek-mak'), [
            'nomor_surat' => 'B/100/PL3.TIK/PG/2026',
            'nomor_memo_cek_mak' => 'MEMO/B2-CEK/001/2026',
            'catatan_pemeriksaan' => 'KAK dan spesifikasi lengkap, mohon cek ketersediaan MAK.',
        ]);

        $resMemoCek->assertStatus(200)
            ->assertJsonPath('data.status_permohonan', PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN)
            ->assertJsonPath('data.nomor_memo_cek_mak', 'MEMO/B2-CEK/001/2026');

        // 3. Perencanaan mengecek ketersediaan MAK, memilih PPK 1, & mengirim Memo Ketersediaan MAK
        $this->actingAs($this->perencanaan);
        $resMemoMak = $this->postJson(route('verifikasi-paralel.memo-ketersediaan-mak'), [
            'nomor_surat' => 'B/100/PL3.TIK/PG/2026',
            'keputusan_anggaran' => 'tersedia',
            'kode_mak' => $this->mak->kode_mak,
            'nominal_alokasi' => 135000000,
            'id_user_ppk_awal' => $this->ppk1->id_user,
            'nomor_memo_ketersediaan_mak' => 'MEMO/REN-MAK/001/2026',
            'catatan_memo_ketersediaan_mak' => 'MAK tersedia cukup dalam DIPA 2026.',
        ]);

        $resMemoMak->assertStatus(200)
            ->assertJsonPath('data.status_permohonan', PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK)
            ->assertJsonPath('data.id_user_ppk_awal', $this->ppk1->id_user);

        // 4. PPK 2 (bukan PPK terpilih) mencoba menyetujui -> wajib ditolak 403 Forbidden
        $this->actingAs($this->ppk2);
        $resPpkSalah = $this->postJson(route('verifikasi-paralel.keputusan-awal'), [
            'nomor_surat' => 'B/100/PL3.TIK/PG/2026',
            'status_keputusan' => 'disetujui',
            'catatan_alasan' => 'Mencoba akses lintas akun PPK',
        ]);
        $resPpkSalah->assertStatus(403);

        // 5. Wadir 2 menyetujui kegiatan terlebih dahulu -> status belum boleh lompat ke disetujui karena PPK 1 belum ACC
        $this->actingAs($this->wadir2);
        $resWadir = $this->postJson(route('verifikasi-paralel.keputusan-awal'), [
            'nomor_surat' => 'B/100/PL3.TIK/PG/2026',
            'status_keputusan' => 'disetujui',
            'catatan_alasan' => 'Kegiatan disetujui Wadir II.',
        ]);
        $resWadir->assertStatus(200);
        $this->assertSame(
            PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK,
            PermohonanPengadaan::findOrFail('B/100/PL3.TIK/PG/2026')->status_permohonan
        );

        // 6. PPK 1 terpilih menyetujui usulan -> barulah status cascade menjadi disetujui untuk diproses PPBJ
        $this->actingAs($this->ppk1);
        $resPpkBenar = $this->postJson(route('verifikasi-paralel.keputusan-awal'), [
            'nomor_surat' => 'B/100/PL3.TIK/PG/2026',
            'status_keputusan' => 'disetujui',
            'catatan_alasan' => 'Spesifikasi dan pagu disetujui PPK 1.',
        ]);
        $resPpkBenar->assertStatus(200)
            ->assertJsonPath('data.status_permohonan', PermohonanPengadaan::STATUS_DISETUJUI);
    }

    /**
     * Menguji otoritas tunggal penguncian (lock) dan pembukaan kunci (unlock) MAK pada role Perencanaan.
     */
    public function test_otoritas_eksklusif_lock_dan_unlock_mak_hanya_di_perencanaan(): void
    {
        // Role selain perencanaan (PPK, PPBJ, PP) dilarang mengunci maupun membuka kunci MAK (403)
        foreach ([$this->ppk1, $this->ppbj, $this->pp] as $unprivilegedUser) {
            $this->actingAs($unprivilegedUser);
            $this->postJson(route('anggaran-mak.lock', $this->mak->kode_mak))->assertStatus(403);
            $this->postJson(route('anggaran-mak.unlock', $this->mak->kode_mak))->assertStatus(403);
        }

        // Perencanaan berhak mengunci MAK
        $this->actingAs($this->perencanaan);
        $resLock = $this->postJson(route('anggaran-mak.lock', $this->mak->kode_mak), [
            'catatan' => 'Penguncian pagu MAK sebelum realisasi fisik.',
        ]);
        $resLock->assertStatus(200)
            ->assertJsonPath('data.is_locked', true);

        // Saat MAK terkunci, perubahan nilai pagu ditolak (422)
        $resMutateLocked = $this->putJson(route('anggaran-mak.update', $this->mak->kode_mak), [
            'pagu_anggaran' => 800000000,
        ]);
        $resMutateLocked->assertStatus(422);

        // Perencanaan berhak membuka kunci MAK kembali apabila ada revisi DIPA resmi
        $resUnlock = $this->postJson(route('anggaran-mak.unlock', $this->mak->kode_mak), [
            'catatan' => 'Pembukaan kunci MAK untuk penyesuaian revisi DIPA.',
        ]);
        $resUnlock->assertStatus(200)
            ->assertJsonPath('data.is_locked', false);
    }

    /**
     * Menguji jalur <= Rp50.000.000 (Belanja Langsung oleh PPBJ):
     * Output berupa Kwitansi, penolakan tegas pembuatan Kontrak/SPK/Surat Pesanan (422),
     * penerbitan BAST oleh Perlengkapan tanpa Kontrak/SPK, hingga pelunasan Pembayaran ber-Kwitansi & Invoice.
     */
    public function test_jalur_belanja_langsung_dibawah_50_juta_output_kwitansi_tanpa_kontrak_sampai_lunas(): void
    {
        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/BL-42JT/TIK/2026',
            'judul_pengadaan' => 'Belanja Langsung Modul Mikrokontroler IoT',
            'tujuan_pengadaan_ringkas' => 'Bahan praktikum bengkel IoT',
            'file_permohonan' => 'permohonan/bl_42jt.pdf',
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
            'status_anggaran' => 'disetujui',
            'estimasi_total_pagu' => 42000000,
            'kode_unit' => 'TIK',
            'id_user' => $this->pemohon->id_user,
            'kode_mak' => $this->mak->kode_mak,
            'id_user_ppk_awal' => $this->ppk1->id_user,
        ]);

        // 1. PPBJ mendaftarkan paket Belanja Langsung (Rp 42.000.000 <= Rp 50.000.000)
        $this->actingAs($this->ppbj);
        $resPaket = $this->postJson(route('paket-pengadaan.store'), [
            'nomor_paket' => 'PKT-BL-42JT',
            'nama_paket' => 'Belanja Langsung Modul Mikrokontroler IoT',
            'jenis_pengadaan' => 'barang',
            'metode_pengadaan' => 'belanja_langsung',
            'nilai_hps' => 42000000,
            'alokasi_pagu_paket' => 42000000,
            'nomor_surat' => $permohonan->nomor_surat,
            'id_user_ppk' => $this->ppk1->id_user,
        ]);

        $resPaket->assertStatus(201)
            ->assertJsonPath('data.jalur_routing', PaketPengadaan::ROUTING_LANGSUNG);

        // 2. Percobaan mengunggah Kontrak/SPK/Surat Pesanan pada paket <= 50 juta WAJIB ditolak 422
        $resSpkForbidden = $this->postJson(route('kontrak-spk.store'), [
            'nomor_spk' => 'SPK-ILEGAL-42JT',
            'nomor_paket' => 'PKT-BL-42JT',
            'tipe_dokumen' => 'spk',
            'tanggal_spk' => '2026-11-01',
            'jadwal_pengiriman' => '2026-11-10',
            'nilai_kontrak' => 41500000,
            'file_spk' => UploadedFile::fake()->create('spk_ilegal.pdf', 256, 'application/pdf'),
        ]);
        $resSpkForbidden->assertStatus(422)
            ->assertJsonValidationErrors(['nomor_paket']);

        // 3. PPBJ mengeksekusi Belanja Langsung dengan output Kwitansi & jadwal Reminder pengiriman
        $resBelanjaLangsung = $this->postJson(route('paket-pengadaan.belanja-langsung', 'PKT-BL-42JT'), [
            'nomor_kwitansi_belanja' => 'KWT/BL/2026/042',
            'nominal_harga_akhir' => 41250000,
            'npwp_vendor' => $this->vendor->npwp,
            'jadwal_pengiriman' => '2026-11-12',
            'file_kwitansi_belanja' => UploadedFile::fake()->create('kwitansi_bl_042.pdf', 300, 'application/pdf'),
        ]);

        $resBelanjaLangsung->assertStatus(200)
            ->assertJsonPath('data.status_paket', PaketPengadaan::STATUS_BELANJA_LANGSUNG_SELESAI)
            ->assertJsonPath('data.nomor_kwitansi_belanja', 'KWT/BL/2026/042');

        // 4. Perlengkapan memeriksa fisik barang & menerbitkan BAST (tanpa memerlukan Kontrak/SPK)
        $this->actingAs($this->perlengkapan);
        $resBast = $this->post(route('penerimaan.store'), [
            'nomor_paket' => 'PKT-BL-42JT',
            'nomor_bast' => 'BAST/BL/2026/042',
            'tanggal_terima' => '2026-11-11',
            'kondisi_barang' => 'terima',
            'kategori_belanja' => PenerimaanBarang::KATEGORI_BELANJA_BARANG,
            'catatan_pemeriksaan' => 'Seluruh modul IoT diterima lengkap sesuai kwitansi belanja langsung.',
        ]);
        $resBast->assertRedirect(route('penerimaan.index'));
        $this->assertDatabaseHas('penerimaan_barang', [
            'nomor_bast' => 'BAST/BL/2026/042',
            'nomor_paket' => 'PKT-BL-42JT',
            'kategori_belanja' => PenerimaanBarang::KATEGORI_BELANJA_BARANG,
        ]);

        // 5. PPBJ mengajukan berkas Pembayaran (otomatis mewarisi Kwitansi Belanja Langsung + melengkapi Invoice)
        $this->actingAs($this->ppbj);
        $resBayar = $this->postJson('/api/pembayaran', [
            'nomor_pembayaran' => 'BYR/BL/2026/042',
            'nomor_paket' => 'PKT-BL-42JT',
            'tahap_termin' => 1,
            'nominal_pengajuan' => 41250000,
            'nomor_invoice' => 'INV/MTN/2026/042',
            'file_invoice' => UploadedFile::fake()->create('invoice_042.pdf', 256, 'application/pdf'),
        ]);

        $resBayar->assertStatus(201)
            ->assertJsonPath('informasi_dokumen_pembayaran.dokumen_perikatan.jenis', 'tanpa_kontrak_belanja_langsung')
            ->assertJsonPath('informasi_dokumen_pembayaran.kwitansi.nomor_kwitansi', 'KWT/BL/2026/042')
            ->assertJsonPath('informasi_dokumen_pembayaran.invoice.nomor_invoice', 'INV/MTN/2026/042');
    }

    /**
     * Menguji jalur > Rp50.000.000 s.d. Rp200.000.000 (melibatkan PP baik Tender maupun Non-Tender):
     * - PP tidak berwenang memberikan Approval final (422).
     * - PP mereview kelengkapan + menginput Harga Perkiraan, Harga Akhir, & Vendor Pemenang.
     * - PP tidak bisa mengeksekusi belanja sebelum Approval PPK (422).
     * - PPK memberikan Approval final (tanpa mengunci MAK).
     * - PP mengeksekusi kewenangan belanja.
     * - PPBJ mengunggah tepat 1 dokumen perikatan terpilih (percobaan unggah dokumen ke-2 ditolak 422).
     */
    public function test_jalur_50_sampai_200_juta_melibatkan_pp_approval_ppk_eksekusi_belanja_pp_dan_satu_dokumen_perikatan(): void
    {
        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/PP-150JT/TIK/2026',
            'judul_pengadaan' => 'Pengadaan Workstation Desain Grafis Lab Multimedia',
            'tujuan_pengadaan_ringkas' => 'Workstation praktikum mahasiswa',
            'file_permohonan' => 'permohonan/pp_150jt.pdf',
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
            'status_anggaran' => 'disetujui',
            'estimasi_total_pagu' => 200000000,
            'kode_unit' => 'TIK',
            'id_user' => $this->pemohon->id_user,
            'kode_mak' => $this->mak->kode_mak,
            'id_user_ppk_awal' => $this->ppk1->id_user,
        ]);

        // 1. PPBJ mendaftarkan paket tepat Rp 200.000.000 dengan metode e_katalog (Inaproc) -> masuk jalur PP
        $this->actingAs($this->ppbj);
        $resPaket = $this->postJson(route('paket-pengadaan.store'), [
            'nomor_paket' => 'PKT-PP-200JT',
            'nama_paket' => 'Pengadaan Workstation Desain Grafis Lab Multimedia',
            'jenis_pengadaan' => 'barang',
            'metode_pengadaan' => 'e_katalog',
            'nilai_hps' => 200000000,
            'alokasi_pagu_paket' => 200000000,
            'nomor_memo_ppbj' => 'MEMO/PPBJ/200JT/2026',
            'nomor_surat' => $permohonan->nomor_surat,
            'id_user_ppk' => $this->ppk1->id_user,
        ]);

        $resPaket->assertStatus(201)
            ->assertJsonPath('data.jalur_routing', PaketPengadaan::ROUTING_PP)
            ->assertJsonPath('data.id_user_pp', $this->pp->id_user)
            ->assertJsonPath('data.id_user_ppk', $this->ppk1->id_user);

        // 2. PP mencoba langsung klik Approval ('disetujui') -> WAJIB ditolak 422
        $this->actingAs($this->pp);
        $resPpApproveIllegal = $this->postJson(route('reviu-dokumen.store'), [
            'nomor_paket' => 'PKT-PP-200JT',
            'status_reviu' => 'disetujui',
        ]);
        $resPpApproveIllegal->assertStatus(422)
            ->assertJsonValidationErrors(['status_reviu']);

        // 3. PP melakukan review kelengkapan + input Nominal Harga Perkiraan, Nominal Harga Akhir, & Vendor
        $resReviewPp = $this->postJson(route('paket-pengadaan.review-pp', 'PKT-PP-200JT'), [
            'hasil_verifikasi' => 'lengkap',
            'nominal_harga_perkiraan' => 198000000,
            'nominal_harga_akhir' => 194500000,
            'npwp_vendor' => $this->vendor->npwp,
            'catatan_verifikasi_pp' => 'Dokumen spesifikasi Inaproc dan negosiasi harga akhir lengkap, mohon Approval PPK.',
        ]);

        $resReviewPp->assertStatus(200)
            ->assertJsonPath('data.status_paket', PaketPengadaan::STATUS_MENUNGGU_APPROVAL_PPK)
            ->assertJsonPath('data.npwp_vendor', $this->vendor->npwp);

        // 4. PP mencoba mengeksekusi belanja sebelum disetujui PPK -> WAJIB ditolak 422
        $resEarlySpend = $this->postJson(route('paket-pengadaan.eksekusi-belanja', 'PKT-PP-200JT'));
        $resEarlySpend->assertStatus(422);

        // 5. PPK 2 (tidak ditugaskan) mencoba klik Approval -> ditolak 403; PPK 1 klik Approval -> sukses
        $this->actingAs($this->ppk2);
        $this->postJson(route('reviu-dokumen.store'), [
            'nomor_paket' => 'PKT-PP-200JT',
            'status_reviu' => 'disetujui',
        ])->assertStatus(403);

        $this->actingAs($this->ppk1);
        $resApprovalPpk = $this->postJson(route('reviu-dokumen.store'), [
            'nomor_paket' => 'PKT-PP-200JT',
            'status_reviu' => 'disetujui',
            'catatan_perbaikan' => 'Disetujui PPK 1, silakan PP mengeksekusi belanja E-Katalog.',
        ]);
        $resApprovalPpk->assertStatus(201);
        $this->assertSame(
            PaketPengadaan::STATUS_DISETUJUI_PPK_SIAP_BELANJA_PP,
            PaketPengadaan::findOrFail('PKT-PP-200JT')->status_paket
        );
        $this->assertFalse((bool) $this->mak->fresh()->is_locked);

        // 6. PP mengeksekusi kewenangan belanja (bukan ke PPBJ untuk belanja)
        $this->actingAs($this->pp);
        $resBelanjaPp = $this->postJson(route('paket-pengadaan.eksekusi-belanja', 'PKT-PP-200JT'), [
            'catatan_belanja' => 'Pembelian klik E-Katalog Inaproc telah dieksekusi oleh PP.',
        ]);
        $resBelanjaPp->assertStatus(200)
            ->assertJsonPath('data.status_paket', PaketPengadaan::STATUS_SIAP_UPLOAD_DOKUMEN_PPBJ)
            ->assertJsonPath('data.dieksekusi_belanja_oleh', $this->pp->id_user);

        // 7. PPBJ mengunggah tepat 1 dokumen perikatan terpilih (Surat Pesanan untuk E-Katalog)
        $this->actingAs($this->ppbj);
        $resDoc1 = $this->postJson(route('kontrak-spk.store'), [
            'nomor_spk' => 'SPES/INAPROC/2026/001',
            'nomor_paket' => 'PKT-PP-200JT',
            'tipe_dokumen' => KontrakSPK::DOKUMEN_SURAT_PESANAN,
            'tanggal_spk' => '2026-11-02',
            'jadwal_pengiriman' => '2026-11-20',
            'nilai_kontrak' => 194500000,
            'file_spk' => UploadedFile::fake()->create('surat_pesanan_001.pdf', 512, 'application/pdf'),
        ]);
        $resDoc1->assertStatus(201)
            ->assertJsonPath('data.tipe_dokumen', KontrakSPK::DOKUMEN_SURAT_PESANAN);

        // 8. Percobaan mengunggah dokumen ke-2 (misal Surat Kontrak) untuk paket yang sama WAJIB ditolak 422
        $resDoc2Duplicate = $this->postJson(route('kontrak-spk.store'), [
            'nomor_spk' => 'KONTRAK/GANDA/2026/002',
            'nomor_paket' => 'PKT-PP-200JT',
            'tipe_dokumen' => KontrakSPK::DOKUMEN_SURAT_KONTRAK,
            'tanggal_spk' => '2026-11-02',
            'jadwal_pengiriman' => '2026-11-20',
            'nilai_kontrak' => 194500000,
            'file_spk' => UploadedFile::fake()->create('surat_kontrak_002.pdf', 512, 'application/pdf'),
        ]);
        $resDoc2Duplicate->assertStatus(422)
            ->assertJsonValidationErrors(['nomor_paket']);
    }

    /**
     * Menguji jalur > Rp200.000.000 (SPSE / Tender melibatkan PPK):
     * - Reassign PPK oleh PPBJ berfungsi penuh.
     * - Tanpa nomor_memo_ppbj.
     * - PPK memberikan Approval & mengeksekusi belanja -> PPBJ unggah Surat Kontrak ->
     *   Perlengkapan terbitkan BAST (belanja_modal) -> Informasi Dokumen Pembayaran lengkap
     *   (Surat Kontrak + Kwitansi + Invoice) -> Memo Bayar PPK & Wadir 2 -> SPP -> SP2D Lunas.
     */
    public function test_jalur_diatas_200_juta_melibatkan_ppk_spse_kontrak_bast_perlengkapan_dan_informasi_dokumen_pembayaran(): void
    {
        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/PPK-320JT/TIK/2026',
            'judul_pengadaan' => 'Pengadaan Klaster Server AI Kampus',
            'tujuan_pengadaan_ringkas' => 'Infrastruktur riset kecerdasan buatan',
            'file_permohonan' => 'permohonan/ppk_320jt.pdf',
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
            'status_anggaran' => 'disetujui',
            'estimasi_total_pagu' => 320000000,
            'kode_unit' => 'TIK',
            'id_user' => $this->pemohon->id_user,
            'kode_mak' => $this->mak->kode_mak,
            'id_user_ppk_awal' => $this->ppk1->id_user,
        ]);

        // 1. PPBJ mendaftarkan paket Rp 320.000.000 dengan metode 'spse' (otomatis mewarisi PPK 1 dari Perencanaan)
        $this->actingAs($this->ppbj);
        $resPaket = $this->postJson(route('paket-pengadaan.store'), [
            'nomor_paket' => 'PKT-SPSE-320JT',
            'nama_paket' => 'Pengadaan Klaster Server AI Kampus',
            'jenis_pengadaan' => 'barang',
            'metode_pengadaan' => 'spse',
            'nilai_hps' => 320000000,
            'alokasi_pagu_paket' => 320000000,
            'nominal_harga_perkiraan' => 320000000,
            'nominal_harga_akhir' => 312000000,
            'npwp_vendor' => $this->vendor->npwp,
            'nomor_memo_ppbj' => 'MEMO-HARUS-NULL',
            'nomor_surat' => $permohonan->nomor_surat,
        ]);

        $resPaket->assertStatus(201)
            ->assertJsonPath('data.jalur_routing', PaketPengadaan::ROUTING_PPK)
            ->assertJsonPath('data.nomor_memo_ppbj', null)
            ->assertJsonPath('data.id_user_ppk', $this->ppk1->id_user);

        // 2. PPBJ melakukan penyesuaian (reassign) PPK dari PPK 1 ke PPK 2
        $resReassign = $this->postJson(route('paket-pengadaan.reassign-ppk', 'PKT-SPSE-320JT'), [
            'id_user_ppk_baru' => $this->ppk2->id_user,
            'alasan_perubahan' => 'Penyesuaian beban kerja paket infrastruktur server ke PPK 2.',
        ]);
        $resReassign->assertStatus(200)
            ->assertJsonPath('data.id_user_ppk', $this->ppk2->id_user);

        // 3. PPK 2 memberikan Approval & mengeksekusi belanja untuk paket > 200 juta
        $this->actingAs($this->ppk2);
        $this->postJson(route('reviu-dokumen.store'), [
            'nomor_paket' => 'PKT-SPSE-320JT',
            'status_reviu' => 'disetujui',
            'catatan_perbaikan' => 'Dokumen lelang SPSE disetujui PPK 2.',
        ])->assertStatus(201);

        $this->postJson(route('paket-pengadaan.eksekusi-belanja', 'PKT-SPSE-320JT'), [
            'catatan_belanja' => 'Penunjukan pemenang lelang SPSE dieksekusi oleh PPK 2.',
        ])->assertStatus(200)
            ->assertJsonPath('data.status_paket', PaketPengadaan::STATUS_SIAP_UPLOAD_DOKUMEN_PPBJ);

        // 4. PPBJ mengunggah Surat Kontrak tunggal
        $this->actingAs($this->ppbj);
        $this->postJson(route('kontrak-spk.store'), [
            'nomor_spk' => 'KONTRAK/SPSE/2026/320',
            'nomor_paket' => 'PKT-SPSE-320JT',
            'tipe_dokumen' => KontrakSPK::DOKUMEN_SURAT_KONTRAK,
            'tanggal_spk' => '2026-11-03',
            'jadwal_pengiriman' => '2026-11-25',
            'nilai_kontrak' => 312000000,
            'file_spk' => UploadedFile::fake()->create('kontrak_320.pdf', 600, 'application/pdf'),
        ])->assertStatus(201);

        // 5. Perlengkapan menerbitkan BAST dengan klasifikasi Belanja Modal
        $this->actingAs($this->perlengkapan);
        $this->postJson(route('penerimaan-barang.store'), [
            'nomor_bast' => 'BAST/SPSE/2026/320',
            'nomor_paket' => 'PKT-SPSE-320JT',
            'tanggal_penerimaan' => '2026-11-24',
            'status_fisik' => PenerimaanBarang::STATUS_DITERIMA_LENGKAP,
            'kategori_belanja' => PenerimaanBarang::KATEGORI_BELANJA_MODAL,
            'file_bast_signed' => UploadedFile::fake()->create('bast_320.pdf', 400, 'application/pdf'),
        ])->assertStatus(201);

        // 6. PPBJ mengajukan Pembayaran lengkap dengan Kwitansi & Invoice
        $this->actingAs($this->ppbj);
        $resBayar = $this->postJson('/api/pembayaran', [
            'nomor_pembayaran' => 'BYR/SPSE/2026/320',
            'nomor_paket' => 'PKT-SPSE-320JT',
            'tahap_termin' => 1,
            'nominal_pengajuan' => 312000000,
            'nomor_kwitansi' => 'KWT/SPSE/2026/320',
            'file_kwitansi' => UploadedFile::fake()->create('kwitansi_320.pdf', 300, 'application/pdf'),
            'nomor_invoice' => 'INV/SPSE/2026/320',
            'file_invoice' => UploadedFile::fake()->create('invoice_320.pdf', 300, 'application/pdf'),
        ]);

        $resBayar->assertStatus(201)
            ->assertJsonPath('informasi_dokumen_pembayaran.dokumen_perikatan.nomor', 'KONTRAK/SPSE/2026/320')
            ->assertJsonPath('informasi_dokumen_pembayaran.dokumen_perikatan.jenis', KontrakSPK::DOKUMEN_SURAT_KONTRAK)
            ->assertJsonPath('informasi_dokumen_pembayaran.kwitansi.nomor_kwitansi', 'KWT/SPSE/2026/320')
            ->assertJsonPath('informasi_dokumen_pembayaran.invoice.nomor_invoice', 'INV/SPSE/2026/320');

        // 7. Memo Bayar berjenjang (PPK 2 Level 1 -> Wadir 2 Level 2) -> Perintah Bayar PPBJ -> Pencairan SP2D Keuangan
        $memoPpk = MemoBayar::where('nomor_pembayaran', 'BYR/SPSE/2026/320')
            ->where('role_approver', MemoBayar::ROLE_PPK)
            ->firstOrFail();
        $memoWadir = MemoBayar::where('nomor_pembayaran', 'BYR/SPSE/2026/320')
            ->where('role_approver', MemoBayar::ROLE_WADIR_2)
            ->firstOrFail();

        $this->actingAs($this->ppk2)
            ->putJson(route('memo-bayar.update', $memoPpk->id), [
                'status_memo' => 'disetujui',
                'catatan_memo' => 'Disetujui PPK 2.',
            ])->assertStatus(200);

        $this->actingAs($this->wadir2)
            ->putJson(route('memo-bayar.update', $memoWadir->id), [
                'status_memo' => 'disetujui',
                'catatan_memo' => 'Disetujui Wadir II.',
            ])->assertStatus(200);

        $this->actingAs($this->ppbj)
            ->postJson(route('pembayaran.perintah-bayar', 'BYR/SPSE/2026/320'))
            ->assertStatus(200)
            ->assertJsonPath('data.status_pembayaran', Pembayaran::STATUS_PERINTAH_BAYAR);

        $this->actingAs($this->keuangan)
            ->postJson(route('transaksi-pencairan.store'), [
                'nomor_transaksi_bank' => 'SP2D-BNI-320001',
                'nomor_pembayaran' => 'BYR/SPSE/2026/320',
                'nomor_paket' => 'PKT-SPSE-320JT',
                'tanggal_pencairan' => '2026-11-28',
                'nominal_cair' => 312000000,
                'file_bukti_transfer' => UploadedFile::fake()->create('sp2d_320.pdf', 300, 'application/pdf'),
            ])->assertStatus(201);

        $this->assertSame('lunas', Pembayaran::findOrFail('BYR/SPSE/2026/320')->status_pembayaran);
        $this->assertSame('selesai', PaketPengadaan::findOrFail('PKT-SPSE-320JT')->status_paket);
        $this->assertSame('selesai', PermohonanPengadaan::findOrFail('B/PPK-320JT/TIK/2026')->status_permohonan);
    }

    /**
     * Menguji antarmuka web semantik Zero-CSS (tes_*.blade.php) untuk seluruh peran pada alur Notulensi:
     * Bidang 2 -> Perencanaan (buat MAK, lock/unlock MAK, Memo Ketersediaan MAK + pilih PPK) ->
     * Wadir 2 + PPK ACC -> PPBJ (Paket, Kontrak, Pembayaran) -> PP -> Perlengkapan -> Keuangan.
     */
    public function test_antarmuka_web_semantik_tes_merender_dan_menjalankan_alur_notulensi_tanpa_error(): void
    {
        // Pemohon mengajukan permohonan via web
        $this->actingAs($this->pemohon)
            ->post(route('permohonan.store'), [
                'nomor_surat' => 'B/WEB/001/TIK/2026',
                'perihal' => 'Pengadaan Router Kampus via Web',
                'tujuan_pengadaan_ringkas' => 'Peremajaan jaringan gedung perkuliahan',
                'anggaran_diajukan' => 95000000,
                'kode_unit' => 'TIK',
                'tanggal_pengajuan' => '2026-11-01',
                'file_surat_permohonan' => UploadedFile::fake()->create('usulan_web.pdf', 256, 'application/pdf'),
            ])->assertRedirect(route('permohonan.index'));

        // Staf Bidang 2 membuka halaman & mengirim Memo Cek MAK ke Perencanaan
        $this->actingAs($this->staffB2)
            ->get(route('verifikasi.bidang-2'))
            ->assertStatus(200)
            ->assertSee('Memo Cek MAK');

        $this->actingAs($this->staffB2)
            ->post(route('verifikasi.bidang-2.teruskan'), [
                'nomor_surat' => 'B/WEB/001/TIK/2026',
                'nomor_memo_cek_mak' => 'MEMO/B2/WEB/001',
                'catatan_staff' => 'Berkas lengkap, mohon cek ketersediaan MAK.',
            ])->assertRedirect(route('verifikasi.bidang-2'));

        // Perencanaan membuka halaman, membuat MAK baru, lock & unlock MAK, lalu kirim Memo Ketersediaan MAK
        $this->actingAs($this->perencanaan)
            ->get(route('verifikasi.perencanaan'))
            ->assertStatus(200)
            ->assertSee('Kontrol Kunci / Buka Kunci MAK (Eksklusif Perencanaan)');

        $this->actingAs($this->perencanaan)
            ->post(route('verifikasi.perencanaan.mak.store'), [
                'kode_mak' => '525112.WEB.2026',
                'uraian_mak' => 'Belanja Peralatan Jaringan Web',
                'sumber_dana' => 'BOPTN',
                'tahun_anggaran' => 2026,
                'pagu_anggaran' => 400000000,
            ])->assertRedirect(route('verifikasi.perencanaan'));

        $this->actingAs($this->perencanaan)
            ->post(route('verifikasi.perencanaan.mak.lock', '525112.WEB.2026'))
            ->assertRedirect(route('verifikasi.perencanaan'));
        $this->assertTrue((bool) AnggaranMAK::findOrFail('525112.WEB.2026')->is_locked);

        $this->actingAs($this->perencanaan)
            ->post(route('verifikasi.perencanaan.mak.unlock', '525112.WEB.2026'))
            ->assertRedirect(route('verifikasi.perencanaan'));
        $this->assertFalse((bool) AnggaranMAK::findOrFail('525112.WEB.2026')->is_locked);

        $this->actingAs($this->perencanaan)
            ->post(route('verifikasi.perencanaan.mak'), [
                'nomor_surat' => 'B/WEB/001/TIK/2026',
                'kode_mak' => '525112.WEB.2026',
                'nominal_alokasi' => 95000000,
                'keputusan_anggaran' => 'setuju',
                'id_user_ppk_awal' => $this->ppk1->id_user,
                'nomor_memo_ketersediaan_mak' => 'MEMO/PRC/WEB/001',
                'catatan_anggaran' => 'MAK tersedia, diteruskan ke Wadir 2 dan PPK 1.',
            ])->assertRedirect(route('verifikasi.perencanaan'));

        // Wadir 2 & PPK 1 membuka halaman verifikasi masing-masing dan menyetujui
        $this->actingAs($this->wadir2)
            ->get(route('verifikasi.wadir-2'))
            ->assertStatus(200)
            ->assertSee('MEMO/PRC/WEB/001');

        $this->actingAs($this->wadir2)
            ->post(route('verifikasi.wadir-2.keputusan'), [
                'nomor_surat' => 'B/WEB/001/TIK/2026',
                'keputusan' => 'disetujui',
                'catatan_alasan' => 'Kegiatan disetujui Wadir II.',
            ])->assertRedirect(route('verifikasi.wadir-2'));

        $this->actingAs($this->ppk1)
            ->get(route('verifikasi.ppk'))
            ->assertStatus(200)
            ->assertSee('MEMO/PRC/WEB/001');

        $this->actingAs($this->ppk1)
            ->post(route('verifikasi.ppk.keputusan'), [
                'nomor_surat' => 'B/WEB/001/TIK/2026',
                'keputusan' => 'disetujui',
                'catatan_alasan' => 'Usulan disetujui PPK 1.',
            ])->assertRedirect(route('verifikasi.ppk'));

        $this->assertSame(
            PermohonanPengadaan::STATUS_DISETUJUI,
            PermohonanPengadaan::findOrFail('B/WEB/001/TIK/2026')->status_permohonan
        );

        // Render halaman kerja utama PPBJ, PP, PPK, Perlengkapan, dan Keuangan tanpa error
        $this->actingAs($this->ppbj)->get(route('paket.create'))->assertStatus(200);
        $this->actingAs($this->ppbj)->get(route('paket.index'))->assertStatus(200);
        $this->actingAs($this->pp)->get(route('kontrak.index'))->assertStatus(200);
        $this->actingAs($this->ppk1)->get(route('kontrak.index'))->assertStatus(200);
        $this->actingAs($this->perlengkapan)->get(route('penerimaan.index'))->assertStatus(200);
        $this->actingAs($this->keuangan)->get(route('pembayaran.index'))->assertStatus(200);
    }
}
