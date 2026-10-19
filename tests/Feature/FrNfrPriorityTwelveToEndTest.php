<?php

namespace Tests\Feature;

use App\Models\AnggaranMAK;
use App\Models\KontrakSPK;
use App\Models\MemoBayar;
use App\Models\NotifikasiUser;
use App\Models\PaketPengadaan;
use App\Models\Pembayaran;
use App\Models\PenerimaanBarang;
use App\Models\PermohonanPengadaan;
use App\Models\TransaksiPencairan;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Middleware\ValidatePostSize;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pengujian Komprehensif FR & NFR Prioritas 12 Sampai Akhir (PHPUnit)
 *
 * Menguji implementasi sesuai dokumen sumber truth:
 * - FR 12 & NFR 5: Konfigurasi pengingat dan sinkronisasi notifikasi otomatis akurat.
 * - FR 13: Supervisi PPBJ atas siklus hidup pengadaan (status pengadaan, monitoring, dan berkas).
 * - FR 14 & NFR 6: Pemeriksaan barang oleh Perlengkapan, multi-foto fleksibel, dan BAST bertanda tangan HMAC.
 * - FR 15: Penyerahan berkas pembayaran oleh PPBJ (faktur, memo penunjang, resume kontrak).
 * - FR 16: Alur reviu memo bertingkat (Level 1: PPK, Level 2: Wadir 2).
 * - FR 17: Reviu akhir PPBJ dan penerbitan Surat Perintah Pembayaran (SPP) ke Keuangan.
 * - FR 18 & NFR 8: Pencairan dana SP2D oleh Keuangan, anti-duplikasi, dan pemutakhiran cascade status selesai.
 * - FR 20.1 & NFR 12: Pengunduhan berkas PDF resmi (BAST, SP2D, Rekapitulasi) di bawah 3 detik.
 * - FR 20.2: Laporan rekapitulasi realisasi anggaran berbasis filter multi-dimensi.
 * - NFR 10: Endpoint pemantauan kesehatan & ketersediaan sistem (High Availability >= 99%).
 * - AI Unslop & Zero CSS: Verifikasi antarmuka terbebas dari jargon pengembang/pengujian dan tanpa CSS class/style.
 */
class FrNfrPriorityTwelveToEndTest extends TestCase
{
    use RefreshDatabase;

    protected User $userPbj;

    protected User $userPerlengkapan;

    protected User $userPpk;

    protected User $userWadir2;

    protected User $userKeuangan;

    protected User $userPp;

    protected Unit $unitTik;

    protected Vendor $vendorCv;

    protected AnggaranMAK $anggaranMak;

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

        // Buat data master Rekening Anggaran MAK
        $this->anggaranMak = AnggaranMAK::create([
            'kode_mak' => '023.17.01.6210.537112',
            'uraian_mak' => 'Belanja Modal Peralatan Laboratorium TIK',
            'tahun_anggaran' => (int) date('Y'),
            'pagu_anggaran' => 500000000,
            'sisa_pagu_mak' => 500000000,
            'sumber_dana' => 'BOPTN',
            'is_locked' => false,
        ]);

        // Buat data master Vendor
        $this->vendorCv = Vendor::create([
            'npwp' => '01.234.567.8-012.000',
            'nib' => '1234567890123',
            'nama_perusahaan' => 'CV Solusi Teknologi Mandiri',
            'alamat' => 'Jl. Margonda Raya No. 100, Depok',
            'is_active' => true,
            'nama_pic' => 'Budi Mandiri',
            'telepon_pic' => '021-78901234',
            'email_pic' => 'kontak@solusiteknologi.co.id',
            'nama_bank' => 'Bank Mandiri',
            'nomor_rekening' => '1570001234567',
            'atas_nama' => 'CV Solusi Teknologi Mandiri',
        ]);

        // Buat aktor-aktor resmi
        $this->userPbj = User::create([
            'username' => 'koordinator_pbj',
            'nama' => 'Budi Santoso, S.T. (PPBJ)',
            'nip' => '198101012005011001',
            'email' => 'ppbj@pnj.ac.id',
            'password' => Hash::make('Password123!'),
            'role' => 'ppbj',
            'kode_unit' => 'TIK',
            'jabatan' => 'Koordinator Pengadaan Barang dan Jasa',
            'no_hp' => '081211110001',
            'status_aktif' => true,
        ]);

        $this->userPerlengkapan = User::create([
            'username' => 'staf_perlengkapan',
            'nama' => 'Ahmad Perlengkapan, A.Md. (BMN)',
            'nip' => '198505052010011002',
            'email' => 'perlengkapan@pnj.ac.id',
            'password' => Hash::make('Password123!'),
            'role' => 'perlengkapan',
            'kode_unit' => 'TIK',
            'jabatan' => 'Pengelola BMN dan Perlengkapan Kampus',
            'no_hp' => '081211110002',
            'status_aktif' => true,
        ]);

        $this->userPpk = User::create([
            'username' => 'ppk_bidang2',
            'nama' => 'Dr. Ir. Hendra, M.T. (PPK 2)',
            'nip' => '197503031999031001',
            'email' => 'ppk2@pnj.ac.id',
            'password' => Hash::make('Password123!'),
            'role' => 'ppk',
            'kode_unit' => 'TIK',
            'jabatan' => 'Pejabat Pembuat Komitmen Bidang Sarana & Prasarana',
            'no_hp' => '081211110003',
            'status_aktif' => true,
        ]);

        $this->userWadir2 = User::create([
            'username' => 'wadir2_keuangan',
            'nama' => 'Prof. Dr. Dra. Sri Rejeki, M.M. (Wadir 2)',
            'nip' => '196808081992032001',
            'email' => 'wadir2@pnj.ac.id',
            'password' => Hash::make('Password123!'),
            'role' => 'wadir_2',
            'kode_unit' => 'TIK',
            'jabatan' => 'Wakil Direktur Bidang Umum dan Keuangan',
            'no_hp' => '081211110004',
            'status_aktif' => true,
        ]);

        $this->userKeuangan = User::create([
            'username' => 'bendahara_keuangan',
            'nama' => 'Dewi Lestari, S.E. (Keuangan)',
            'nip' => '198707072012012001',
            'email' => 'keuangan@pnj.ac.id',
            'password' => Hash::make('Password123!'),
            'role' => 'keuangan',
            'kode_unit' => 'TIK',
            'jabatan' => 'Bendahara Pengeluaran Pembantu',
            'no_hp' => '081211110005',
            'status_aktif' => true,
        ]);

        $this->userPp = User::create([
            'username' => 'pejabat_pengadaan',
            'nama' => 'Fajar Pratama, S.T. (PP)',
            'nip' => '198909092015011001',
            'email' => 'pp@pnj.ac.id',
            'password' => Hash::make('Password123!'),
            'role' => 'pp',
            'kode_unit' => 'TIK',
            'jabatan' => 'Pejabat Pengadaan',
            'no_hp' => '081211110006',
            'status_aktif' => true,
        ]);
    }

    /**
     * Helper untuk membuat paket pengadaan aktif yang siap masuk siklus penerimaan barang / SPK.
     */
    protected function createPaketSiapPenerimaan(string $nomorPaket = 'PKT-TIK-2026-001', float $nominal = 75000000): PaketPengadaan
    {
        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'SRT/TIK/'.rand(100, 999),
            'judul_pengadaan' => 'Pengadaan Server dan Switch Jaringan Lab',
            'tujuan_pengadaan_ringkas' => 'Untuk praktikum mahasiswa',
            'estimasi_total_pagu' => $nominal,
            'tanggal_pengajuan' => now()->subDays(20),
            'status_permohonan' => 'disetujui',
            'status_anggaran' => 'terverifikasi',
            'kode_unit' => $this->unitTik->kode_unit,
            'id_user' => $this->userPbj->id_user,
            'kode_mak' => $this->anggaranMak->kode_mak,
        ]);

        $paket = PaketPengadaan::create([
            'nomor_paket' => $nomorPaket,
            'nama_paket' => 'Paket Pengadaan Server Lab Jaringan',
            'nomor_surat' => $permohonan->nomor_surat,
            'id_user_ppbj' => $this->userPbj->id_user,
            'id_user_ppk' => $this->userPpk->id_user,
            'id_user_pp' => $this->userPp->id_user,
            'npwp_vendor' => $this->vendorCv->npwp,
            'jenis_pengadaan' => 'pengadaan_barang',
            'platform_pengadaan' => 'E-Katalog',
            'mekanisme_pengadaan' => 'pengadaan_langsung',
            'jalur_routing' => 'pejabat_pengadaan_sd_200jt',
            'nilai_hps' => $nominal,
            'alokasi_pagu_paket' => $nominal,
            'status_paket' => 'spk_diterbitkan',
        ]);

        KontrakSPK::create([
            'nomor_spk' => 'SPK/TIK/'.date('Y').'/'.rand(100, 999),
            'nomor_paket' => $paket->nomor_paket,
            'id_user' => $this->userPpk->id_user,
            'tipe_dokumen' => 'spk',
            'sifat_kontrak' => 'lump_sum',
            'status_spk' => 'aktif',
            'tanggal_spk' => now()->subDays(10)->toDateString(),
            'tanggal_mulai' => now()->subDays(10)->toDateString(),
            'tanggal_selesai' => now()->addDays(5)->toDateString(),
            'jadwal_pengiriman' => now()->addDays(2)->toDateString(),
            'nilai_kontrak' => $nominal,
            'file_spk' => 'spk/spk_sample.pdf',
        ]);

        return $paket;
    }

    /**
     * FR 12 & NFR 5: Konfigurasi pengingat dan sinkronisasi notifikasi otomatis akurat.
     */
    public function test_fr_12_and_nfr_5_ppbj_reminder_configuration_and_automatic_alert_sync(): void
    {
        $this->actingAs($this->userPbj);

        // 1. Buat paket pengadaan dengan SPK aktif
        $paket = $this->createPaketSiapPenerimaan('PKT-REMINDER-001', 50000000);

        // 2. PPBJ mengonfigurasi pengingat kontrak baru
        $responseReminder = $this->postJson('/api/reminders', [
            'nomor_paket' => $paket->nomor_paket,
            'tipe_reminder' => 'pengiriman',
            'judul_alert' => 'Peringatan Jatuh Tempo Kontrak Server Lab',
            'pesan_alert' => 'Segera siapkan berkas pemeriksaan dan BAST sebelum batas akhir',
            'waktu_trigger' => now()->addDays(3)->toDateTimeString(),
            'target_roles' => ['perlengkapan'],
        ]);

        $responseReminder->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('reminder', [
            'nomor_paket' => $paket->nomor_paket,
            'judul_alert' => 'Peringatan Jatuh Tempo Kontrak Server Lab',
        ]);

        // 3. Jalankan sinkronisasi pengingat otomatis oleh sistem / service
        $syncResponse = $this->postJson('/api/reminders/sync');
        $syncResponse->assertStatus(200)
            ->assertJsonPath('status', 'success');

        // Pastikan notifikasi tersinkronisasi ke feed user target (NFR 5)
        $notifPerlengkapan = NotifikasiUser::where('id_user', $this->userPerlengkapan->id_user)->first();
        $this->assertNotNull($notifPerlengkapan, 'Notifikasi otomatis harus dibangkitkan untuk Perlengkapan');
        $this->assertFalse((bool) $notifPerlengkapan->is_read);

        // 4. Pengguna menandai atau mendismiss notifikasi
        $this->actingAs($this->userPerlengkapan);
        $dismissResponse = $this->post(route('notifikasi.dismiss', ['id' => $notifPerlengkapan->id]));
        $dismissResponse->assertRedirect();

        $notifPerlengkapan->refresh();
        $this->assertTrue((bool) $notifPerlengkapan->is_read);
    }

    /**
     * FR 13: Supervisi PPBJ atas siklus hidup pengadaan (status pengadaan, monitoring, dan berkas).
     */
    public function test_fr_13_ppbj_supervises_procurement_lifecycle(): void
    {
        $this->actingAs($this->userPbj);

        $paket = $this->createPaketSiapPenerimaan('PKT-SUPERVISI-001');

        // PPBJ membuka monitoring siklus pengadaan
        $responseMonitoring = $this->get(route('monitoring.index'));
        $responseMonitoring->assertStatus(200);
        $responseMonitoring->assertSee('PKT-SUPERVISI-001');

        // PPBJ membuka halaman modul pembayaran
        $responsePembayaran = $this->get(route('pembayaran.index'));
        $responsePembayaran->assertStatus(200);
        $responsePembayaran->assertSee('Alur Pengajuan, Persetujuan Memo');
    }

    /**
     * FR 14 & NFR 6: Pemeriksaan fisik oleh Perlengkapan, multi-foto fleksibel, dan BAST bertanda tangan HMAC.
     */
    public function test_fr_14_and_nfr_6_perlengkapan_inspects_goods_uploads_flexible_photos_and_generates_bast_hmac_token(): void
    {
        $this->actingAs($this->userPerlengkapan);
        $paket = $this->createPaketSiapPenerimaan('PKT-BAST-001');
        $spk = $paket->kontrakSpk;

        // Siapkan simulasi upload foto dokumentasi fisik fleksibel (multiple photos)
        $foto1 = UploadedFile::fake()->image('server_depan.jpg');
        $foto2 = UploadedFile::fake()->image('switch_belakang.png');
        $filePihak3 = UploadedFile::fake()->create('surat_jalan_vendor.pdf', 500);

        $responseBast = $this->post(route('penerimaan.store'), [
            'nomor_kontrak_spk' => $spk->nomor_spk,
            'nomor_bast' => 'BAST/2026/04/001',
            'tanggal_terima' => now()->toDateString(),
            'kondisi_barang' => 'terima',
            'catatan_pemeriksaan' => 'Barang diperiksa fisik lengkap sesuai spesifikasi teknis SPK',
            'foto_barang' => [$foto1, $foto2],
            'file_penerimaan_pihak3' => $filePihak3,
        ]);

        $responseBast->assertRedirect();
        $responseBast->assertSessionHas('success');

        // Verifikasi BAST tersimpan di database
        $bast = PenerimaanBarang::where('nomor_paket', $paket->nomor_paket)->first();
        $this->assertNotNull($bast);
        $this->assertEquals('terima', $bast->status_fisik);

        // NFR 6: Pastikan token tanda tangan digital HMAC-SHA256 terisi dan valid
        $this->assertNotEmpty($bast->ttd_digital_bast);
        $this->assertEquals(64, strlen($bast->ttd_digital_bast));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $bast->ttd_digital_bast);

        // Pastikan foto dokumentasi tersimpan di tabel relasi dokumentasi
        $this->assertCount(2, $bast->fotoDokumentasi);

        // Pastikan status paket diperbarui menjadi 'penerimaan_selesai'
        $paket->refresh();
        $this->assertEquals('penerimaan_selesai', $paket->status_paket);

        // Pastikan audit trail tercatat (NFR 9)
        $this->assertDatabaseHas('audit_log', [
            'jenis_aksi' => 'TERBITKAN_BAST',
            'nama_entitas' => 'penerimaan_barang',
            'id_entitas' => $bast->nomor_bast,
        ]);
    }

    /**
     * FR 15: Penyerahan berkas pembayaran oleh PPBJ (faktur, memo penunjang, resume kontrak).
     */
    public function test_fr_15_ppbj_submits_payment_dossier_with_supporting_documents(): void
    {
        $this->actingAs($this->userPbj);
        $paket = $this->createPaketSiapPenerimaan('PKT-TAGIHAN-001', 80000000);
        $paket->status_paket = 'penerimaan_selesai';
        $paket->save();

        $fileTagihan = UploadedFile::fake()->create('kuitansi_faktur.pdf', 300);
        $filePenunjang = UploadedFile::fake()->create('resume_kontrak.pdf', 400);

        $response = $this->post(route('pembayaran.pengajuan'), [
            'nomor_pembayaran' => 'SPP/2026/04/001',
            'nomor_paket' => $paket->nomor_paket,
            'tahap_termin' => 1,
            'nominal_pengajuan' => 80000000,
            'tanggal_pengajuan' => now()->toDateString(),
            'file_tagihan_vendor' => $fileTagihan,
            'file_dokumen_penunjang' => $filePenunjang,
            'jatuh_tempo' => now()->addDays(30)->toDateString(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Pastikan record Pembayaran tersimpan
        $pembayaran = Pembayaran::where('nomor_pembayaran', 'SPP/2026/04/001')->first();
        $this->assertNotNull($pembayaran);
        $this->assertEquals('reviu_memo', $pembayaran->status_pembayaran);
        $this->assertEquals(80000000, (float) $pembayaran->nominal_pengajuan);

        // Pastikan memo bertingkat Level 1 (PPK) dan Level 2 (Wadir 2) otomatis dibangkitkan (FR 16.1)
        $memos = MemoBayar::where('nomor_pembayaran', $pembayaran->nomor_pembayaran)->orderBy('urutan_level')->get();
        $this->assertCount(2, $memos);
        $this->assertEquals('ppk', $memos[0]->role_approver);
        $this->assertEquals('menunggu', $memos[0]->status_memo);
        $this->assertEquals('wadir_2', $memos[1]->role_approver);
        $this->assertEquals('menunggu', $memos[1]->status_memo);

        // Pastikan status paket diperbarui
        $paket->refresh();
        $this->assertEquals('pengajuan_pembayaran', $paket->status_paket);
    }

    /**
     * FR 16: Alur reviu memo bertingkat (Level 1: PPK, Level 2: Wadir 2).
     */
    public function test_fr_16_multilevel_memo_payment_approval_by_ppk_and_wadir_2(): void
    {
        $paket = $this->createPaketSiapPenerimaan('PKT-MEMO-001', 60000000);
        $pembayaran = Pembayaran::create([
            'nomor_pembayaran' => 'SPP/MEMO/001',
            'nomor_paket' => $paket->nomor_paket,
            'id_user_pengaju' => $this->userPbj->id_user,
            'tahap_termin' => 1,
            'nominal_pengajuan' => 60000000,
            'tanggal_pengajuan' => now()->toDateString(),
            'jatuh_tempo' => now()->addDays(30)->toDateString(),
            'status_pembayaran' => 'reviu_memo',
        ]);

        $memoPpk = MemoBayar::create([
            'id_user' => $this->userPpk->id_user,
            'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
            'role_approver' => 'ppk',
            'urutan_level' => 1,
            'status_memo' => 'menunggu',
        ]);

        $memoWadir = MemoBayar::create([
            'id_user' => $this->userWadir2->id_user,
            'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
            'role_approver' => 'wadir_2',
            'urutan_level' => 2,
            'status_memo' => 'menunggu',
        ]);

        // Step 1: PPK menyetujui memo Level 1
        $this->actingAs($this->userPpk);
        $respPpk = $this->post(route('pembayaran.memo.approve'), [
            'id_memo_bayar' => $memoPpk->id,
            'status_memo' => 'disetujui',
            'catatan_memo' => 'Dokumen kuitansi dan BAST telah sesuai, disetujui untuk diproses ke Wadir 2',
        ]);
        $respPpk->assertRedirect();

        $memoPpk->refresh();
        $this->assertEquals('disetujui', $memoPpk->status_memo);

        // Step 2: Wadir 2 menyetujui memo Level 2
        $this->actingAs($this->userWadir2);
        $respWadir = $this->post(route('pembayaran.memo.approve'), [
            'id_memo_bayar' => $memoWadir->id,
            'status_memo' => 'disetujui',
            'catatan_memo' => 'Disposisi disetujui, silakan terbitkan SPP ke Bagian Keuangan',
        ]);
        $respWadir->assertRedirect();

        $memoWadir->refresh();
        $this->assertEquals('disetujui', $memoWadir->status_memo);

        // Skenario Penolakan: Buat memo baru dan uji penolakan
        $memoTolak = MemoBayar::create([
            'id_user' => $this->userPpk->id_user,
            'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
            'role_approver' => 'ppk',
            'urutan_level' => 1,
            'status_memo' => 'menunggu',
        ]);

        $respTolak = $this->post(route('pembayaran.memo.approve'), [
            'id_memo_bayar' => $memoTolak->id,
            'status_memo' => 'ditolak',
            'catatan_memo' => 'Faktur pajak belum bermeterai, mohon diperbaiki',
        ]);
        $respTolak->assertRedirect();
        $memoTolak->refresh();
        $this->assertEquals('ditolak', $memoTolak->status_memo);
    }

    /**
     * FR 17: Reviu akhir PPBJ dan penerbitan Surat Perintah Pembayaran (SPP) ke Keuangan.
     */
    public function test_fr_17_ppbj_final_review_and_issues_payment_order_to_finance(): void
    {
        $this->actingAs($this->userPbj);
        $paket = $this->createPaketSiapPenerimaan('PKT-SPP-001', 90000000);

        $pembayaran = Pembayaran::create([
            'nomor_pembayaran' => 'SPP/TIK/001',
            'nomor_paket' => $paket->nomor_paket,
            'id_user_pengaju' => $this->userPbj->id_user,
            'tahap_termin' => 1,
            'nominal_pengajuan' => 90000000,
            'tanggal_pengajuan' => now()->toDateString(),
            'status_pembayaran' => 'reviu_memo',
        ]);

        $memo1 = MemoBayar::create([
            'id_user' => $this->userPpk->id_user,
            'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
            'role_approver' => 'ppk',
            'urutan_level' => 1,
            'status_memo' => 'menunggu',
        ]);

        // Coba terbitkan SPP sebelum memo disetujui -> harus gagal validasi
        $failResponse = $this->post(route('pembayaran.terbitkan-spp'), [
            'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
            'catatan_reviu_akhir' => 'Verifikasi kelengkapan berkas',
        ]);
        $failResponse->assertSessionHasErrors(['nomor_pembayaran']);

        // Sekarang setujui seluruh memo
        $memo1->update(['status_memo' => 'disetujui']);

        $successResponse = $this->post(route('pembayaran.terbitkan-spp'), [
            'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
            'catatan_reviu_akhir' => 'Seluruh memo telah disetujui, SPP diterbitkan ke Keuangan',
        ]);
        $successResponse->assertRedirect();
        $successResponse->assertSessionHas('success');

        $pembayaran->refresh();
        $this->assertEquals('perintah_bayar', $pembayaran->status_pembayaran);
        $this->assertEquals('disetujui', $pembayaran->status_reviu_akhir);

        $paket->refresh();
        $this->assertEquals('perintah_bayar', $paket->status_paket);
    }

    /**
     * FR 18 & NFR 8: Pencairan dana SP2D oleh Keuangan, anti-duplikasi, dan pemutakhiran cascade status selesai.
     */
    public function test_fr_18_and_nfr_8_finance_disburses_funds_prevents_duplicate_and_completes_procurement(): void
    {
        $this->actingAs($this->userKeuangan);
        $paket = $this->createPaketSiapPenerimaan('PKT-CAIR-001', 120000000);

        $pembayaran = Pembayaran::create([
            'nomor_pembayaran' => 'SPP/CAIR/001',
            'nomor_paket' => $paket->nomor_paket,
            'id_user_pengaju' => $this->userPbj->id_user,
            'tahap_termin' => 1,
            'nominal_pengajuan' => 120000000,
            'tanggal_pengajuan' => now()->toDateString(),
            'status_pembayaran' => 'perintah_bayar',
        ]);

        $slipBank = UploadedFile::fake()->create('slip_transfer_mandiri.pdf', 200);

        // 1. Eksekusi pencairan dana pertama
        $cairResponse = $this->post(route('pembayaran.store'), [
            'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
            'nomor_transaksi' => 'SP2D/2026/04/999',
            'tanggal_cair' => now()->toDateString(),
            'nominal_cair' => 120000000,
            'bank_tujuan' => 'Bank Mandiri (1570001234567 - CV Solusi Teknologi Mandiri)',
            'file_bukti_transfer' => $slipBank,
            'keterangan_cair' => 'Transfer berhasil via CMS Mandiri Corporate',
        ]);

        $cairResponse->assertRedirect();
        $cairResponse->assertSessionHas('success');

        // Verifikasi pencairan tersimpan
        $transaksi = TransaksiPencairan::where('nomor_transaksi_bank', 'SP2D/2026/04/999')->first();
        $this->assertNotNull($transaksi);
        $this->assertEquals(120000000, (float) $transaksi->nominal_transfer);

        // Verifikasi cascade status: Pembayaran lunas, Paket selesai
        $pembayaran->refresh();
        $this->assertEquals('lunas', $pembayaran->status_pembayaran);

        $paket->refresh();
        $this->assertEquals('selesai', $paket->status_paket);

        // 2. NFR 8: Uji Proteksi Anti-Duplicate Disbursement
        // Coba cairkan kembali pembayaran yang sama
        $dupResponse = $this->post(route('pembayaran.store'), [
            'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
            'nomor_transaksi' => 'SP2D/2026/04/DUPLICATE',
            'tanggal_cair' => now()->toDateString(),
            'nominal_cair' => 120000000,
            'bank_tujuan' => 'Bank Mandiri',
            'file_bukti_transfer' => $slipBank,
        ]);

        $dupResponse->assertSessionHasErrors(['nomor_pembayaran']);
    }

    /**
     * FR 20.1 & NFR 12: Pengunduhan berkas PDF resmi (BAST, SP2D, Rekapitulasi) di bawah 3 detik.
     */
    public function test_fr_20_1_and_nfr_12_official_pdf_downloads_under_three_seconds(): void
    {
        $this->actingAs($this->userPbj);
        $paket = $this->createPaketSiapPenerimaan('PKT-PDF-001', 45000000);

        $bast = PenerimaanBarang::create([
            'nomor_bast' => 'BAST/2026/04/TEST01',
            'nomor_paket' => $paket->nomor_paket,
            'id_user' => $this->userPerlengkapan->id_user,
            'tanggal_penerimaan' => now()->toDateString(),
            'status_fisik' => 'terima',
            'ttd_digital_bast' => PenerimaanBarang::generateSignatureToken('BAST/2026/04/TEST01', $this->userPerlengkapan->id_user),
        ]);

        $pembayaran = Pembayaran::create([
            'nomor_pembayaran' => 'SPP/PDF/001',
            'nomor_paket' => $paket->nomor_paket,
            'id_user_pengaju' => $this->userPbj->id_user,
            'tahap_termin' => 1,
            'nominal_pengajuan' => 45000000,
            'tanggal_pengajuan' => now()->toDateString(),
            'status_pembayaran' => 'lunas',
        ]);

        $transaksi = TransaksiPencairan::create([
            'nomor_transaksi_bank' => 'SP2D-2026-TEST01',
            'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
            'id_user' => $this->userKeuangan->id_user,
            'nominal_transfer' => 45000000,
            'tanggal_transfer' => now(),
            'file_bukti_transfer' => 'transfer/slip_test.pdf',
        ]);

        // 1. Pengunduhan PDF BAST
        $startBast = microtime(true);
        $respBastPdf = $this->get(route('dokumen.pdf', ['jenis' => 'bast', 'id' => $bast->nomor_bast]));
        $durationBast = microtime(true) - $startBast;

        $respBastPdf->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $respBastPdf->headers->get('Content-Type'));
        $this->assertLessThan(3.0, $durationBast, "Waktu unduh PDF BAST harus di bawah 3 detik (tercapai: {$durationBast}s) - NFR 12");

        // 2. Pengunduhan PDF SP2D
        $startSp2d = microtime(true);
        $respSp2dPdf = $this->get(route('dokumen.pdf', ['jenis' => 'sp2d', 'id' => $transaksi->nomor_transaksi_bank]));
        $durationSp2d = microtime(true) - $startSp2d;

        $respSp2dPdf->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $respSp2dPdf->headers->get('Content-Type'));
        $this->assertLessThan(3.0, $durationSp2d, "Waktu unduh PDF SP2D harus di bawah 3 detik (tercapai: {$durationSp2d}s) - NFR 12");

        // 3. Pengunduhan PDF Rekapitulasi Anggaran
        $startRekap = microtime(true);
        $respRekapPdf = $this->get(route('laporan.rekapitulasi.pdf', ['tahun' => date('Y')]));
        $durationRekap = microtime(true) - $startRekap;

        $respRekapPdf->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $respRekapPdf->headers->get('Content-Type'));
        $this->assertLessThan(3.0, $durationRekap, "Waktu unduh PDF Rekapitulasi harus di bawah 3 detik (tercapai: {$durationRekap}s) - NFR 12");
    }

    /**
     * FR 20.2: Laporan rekapitulasi realisasi anggaran berbasis filter multi-dimensi.
     */
    public function test_fr_20_2_budget_recapitulation_report_with_filters(): void
    {
        $this->actingAs($this->userWadir2);

        // Akses view rekapitulasi anggaran
        $responseWeb = $this->get(route('laporan.rekapitulasi', [
            'tahun' => date('Y'),
            'kode_unit' => $this->unitTik->kode_unit,
        ]));

        $responseWeb->assertStatus(200);
        $responseWeb->assertSee('Laporan Rekapitulasi Total Anggaran Pengadaan');
        $responseWeb->assertSee('Jurusan Teknik Informatika dan Komputer');

        // Akses endpoint API rekapitulasi anggaran
        $responseApi = $this->getJson('/api/laporan/rekapitulasi-anggaran?tahun='.date('Y'));
        $responseApi->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'tahun',
                'summary' => [
                    'total_pagu',
                    'total_realisasi',
                    'sisa_anggaran',
                    'persentase_realisasi',
                ],
                'data_mak',
            ]);
    }

    /**
     * NFR 10: Endpoint pemantauan kesehatan & ketersediaan sistem (High Availability >= 99%).
     */
    public function test_nfr_10_system_health_check_high_availability(): void
    {
        // Uji endpoint health check publik
        $response = $this->get(route('health'));
        $response->assertStatus(200)
            ->assertJsonPath('status', 'healthy')
            ->assertJsonPath('database', 'connected')
            ->assertJsonStructure([
                'status',
                'system',
                'timestamp',
                'database',
                'environment',
            ]);

        // Uji endpoint alias health check
        $responseAlias = $this->get('/health-check');
        $responseAlias->assertStatus(200)
            ->assertJsonPath('status', 'healthy');
    }

    /**
     * AI Unslop & Zero CSS: Verifikasi antarmuka terbebas dari jargon pengembang dan tanpa kelas CSS.
     */
    public function test_views_contain_no_developer_jargon_unslop_verification(): void
    {
        $forbiddenTerms = [
            'Overseer',
            'overseer',
            'Zero CSS Mode',
            'Testing Helper',
            'Testing Manual',
            '(FR 9)',
            '(FR 11)',
            '(FR 12)',
            '(FR 13)',
            '(FR 14)',
            '(FR 15)',
            '(FR 16)',
            '(FR 17)',
            '(FR 18)',
            '(FR 20)',
            '(NFR 6)',
            '(NFR 8)',
            '(NFR 10)',
            '(NFR 12)',
        ];

        // 1. Uji view login sebagai guest
        $responseLogin = $this->get(route('login'));
        $responseLogin->assertStatus(200);
        $contentLogin = $responseLogin->getContent();
        foreach ($forbiddenTerms as $term) {
            $this->assertStringNotContainsString(
                $term,
                $contentLogin,
                "Halaman Login masih memuat jargon pengembang yang belum di-unslop: '{$term}'"
            );
        }
        $this->assertDoesNotMatchRegularExpression('/<style\b[^>]*>/i', $contentLogin);

        // 2. Uji views operasional autentikasi sebagai PPBJ
        $this->actingAs($this->userPbj);

        $authUrls = [
            ['user' => $this->userPbj, 'url' => route('monitoring.index')],
            ['user' => $this->userPbj, 'url' => route('kontrak.index')],
            ['user' => $this->userPerlengkapan, 'url' => route('penerimaan.index')],
            ['user' => $this->userPbj, 'url' => route('pembayaran.index')],
            ['user' => $this->userPbj, 'url' => route('laporan.rekapitulasi')],
        ];

        foreach ($authUrls as $item) {
            $this->actingAs($item['user']);
            $url = $item['url'];
            $response = $this->get($url);
            $response->assertStatus(200);
            $content = $response->getContent();

            foreach ($forbiddenTerms as $term) {
                $this->assertStringNotContainsString(
                    $term,
                    $content,
                    "View pada URL [{$url}] masih memuat jargon pengembang / testing yang belum di-unslop: '{$term}'"
                );
            }

            // Zero CSS check: pastikan tidak ada tag <style>
            $this->assertDoesNotMatchRegularExpression(
                '/<style\b[^>]*>/i',
                $content,
                "View pada URL [{$url}] melanggar Zero CSS karena memuat tag <style>"
            );
        }
    }

    /**
     * NFR 11 & Ketahanan Server: Penanganan unggahan berkas melebihi batas POST (PostTooLargeException)
     * dialihkan secara anggun dengan pesan kesalahan informatif tanpa menyebabkan Error 500.
     */
    public function test_post_too_large_exception_redirects_gracefully_with_error(): void
    {
        $this->actingAs($this->userPbj);

        $this->instance(
            ValidatePostSize::class,
            new class
            {
                public function handle($request, $next)
                {
                    throw new PostTooLargeException;
                }
            }
        );

        $response = $this->from('/permohonan/create')->post('/permohonan', []);

        $response->assertRedirect('/permohonan/create');
        $response->assertSessionHasErrors(['file_upload']);
        $response->assertSessionHas('error');
    }
}
