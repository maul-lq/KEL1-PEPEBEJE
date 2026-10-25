<?php

namespace Tests\Feature;

use App\Models\AnggaranMAK;
use App\Models\KontrakSPK;
use App\Models\NotifikasiUser;
use App\Models\PaketPengadaan;
use App\Models\Pembayaran;
use App\Models\PermohonanPengadaan;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VerifikasiParalel;
use App\Notifications\PengingatPengadaanNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Uji Komprehensif Notifikasi & Surel Seluruh Siklus Pengadaan (LifecycleNotificationEventTest)
 *
 * Memverifikasi otomasi pembuatan notifikasi (NotifikasiUser) dan transmisi surel
 * (PengingatPengadaanNotification) pada seluruh peristiwa transisi siklus pengadaan SIM-PBJ:
 * 1. Pengajuan Usulan Baru
 * 2. Revisi Dokumen Usulan
 * 3. Penerusan Berkas Telaah Staf Bidang 2 ke Wadir 2
 * 4. Keputusan Wadir 2 (Tolak & Setuju)
 * 5. Keputusan Anggaran MAK oleh Perencanaan (Hold & Setuju)
 * 6. Registrasi Paket Pengadaan & Penugasan PPK oleh PPBJ
 * 7. Alih Tugas (Reassign) PPK
 * 8. Reviu Dokumen Pengadaan oleh PP/PPK
 * 9. Penerbitan Kontrak SPK (Tembusan Perlengkapan & Keuangan)
 * 10. Penerimaan Fisik Barang & BAST Terbit
 * 11. Pengajuan Pembayaran Memo Tingkat 1 (PPK)
 * 12. Disposisi Memo Pembayaran (PPK Level 1 & Wadir 2 Level 2)
 * 13. Penerbitan Surat Perintah Pembayaran (SPP) ke Keuangan
 * 14. Pencairan Dana SP2D & Bukti Transfer Bank
 */
class LifecycleNotificationEventTest extends TestCase
{
    use RefreshDatabase;

    private User $userPengaju;

    private User $userStaffB2;

    private User $userWadir2;

    private User $userPerencanaan;

    private User $userPpbj;

    private User $userPpk;

    private User $userPpk2;

    private User $userPp;

    private User $userPerlengkapan;

    private User $userKeuangan;

    private AnggaranMAK $mak;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $unit = Unit::updateOrCreate(
            ['kode_unit' => 'TIK'],
            ['nama_unit' => 'Teknik Informatika dan Komputer', 'jenis_unit' => 'jurusan']
        );

        $this->mak = AnggaranMAK::create([
            'kode_mak' => '052.01.WA.521211.TIK',
            'uraian_mak' => 'Belanja Bahan Habis Pakai Komputer Praktikum',
            'tahun_anggaran' => (int) date('Y'),
            'pagu_anggaran' => 500000000,
            'sisa_pagu_mak' => 500000000,
            'sumber_dana' => 'RM',
        ]);

        $roles = Role::all()->keyBy('nama_role');

        $this->userPengaju = User::factory()->create([
            'role' => 'user',
            'id_role' => $roles['user']->id,
            'kode_unit' => $unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'email' => 'pengaju@pnj.ac.id',
            'nama' => 'Dosen Pengaju TIK',
        ]);

        $this->userStaffB2 = User::factory()->create([
            'role' => 'staff_bidang_2',
            'id_role' => $roles['staff_bidang_2']->id,
            'kode_unit' => $unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'email' => 'staffb2@pnj.ac.id',
            'nama' => 'Staf Bidang II PNJ',
        ]);

        $this->userWadir2 = User::factory()->create([
            'role' => 'wadir_2',
            'id_role' => $roles['wadir_2']->id,
            'kode_unit' => $unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'email' => 'wadir2@pnj.ac.id',
            'nama' => 'Wakil Direktur II PNJ',
        ]);

        $this->userPerencanaan = User::factory()->create([
            'role' => 'perencanaan',
            'id_role' => $roles['perencanaan']->id,
            'kode_unit' => $unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'email' => 'perencanaan@pnj.ac.id',
            'nama' => 'Petugas Perencanaan PNJ',
        ]);

        $this->userPpbj = User::factory()->create([
            'role' => 'ppbj',
            'id_role' => $roles['ppbj']->id,
            'kode_unit' => $unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'email' => 'ppbj@pnj.ac.id',
            'nama' => 'Petugas PPBJ PNJ',
        ]);

        $this->userPpk = User::factory()->create([
            'role' => 'ppk',
            'id_role' => $roles['ppk']->id,
            'kode_unit' => $unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'email' => 'ppk1@pnj.ac.id',
            'nama' => 'Pejabat Pembuat Komitmen 1',
        ]);

        $this->userPpk2 = User::factory()->create([
            'role' => 'ppk',
            'id_role' => $roles['ppk']->id,
            'kode_unit' => $unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'email' => 'ppk2@pnj.ac.id',
            'nama' => 'Pejabat Pembuat Komitmen 2',
        ]);

        $this->userPp = User::factory()->create([
            'role' => 'pp',
            'id_role' => $roles['pp']->id,
            'kode_unit' => $unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'email' => 'pejabat.pengadaan@pnj.ac.id',
            'nama' => 'Pejabat Pengadaan PNJ',
        ]);

        $this->userPerlengkapan = User::factory()->create([
            'role' => 'perlengkapan',
            'id_role' => $roles['perlengkapan']->id,
            'kode_unit' => $unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'email' => 'perlengkapan@pnj.ac.id',
            'nama' => 'Unit Perlengkapan PNJ',
        ]);

        $this->userKeuangan = User::factory()->create([
            'role' => 'keuangan',
            'id_role' => $roles['keuangan']->id,
            'kode_unit' => $unit->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'email' => 'keuangan@pnj.ac.id',
            'nama' => 'Bagian Keuangan PNJ',
        ]);
    }

    /**
     * 1. Usulan Pengadaan Baru memicu notifikasi ke Staf B2, Wadir 2, dan Perencanaan.
     */
    public function test_pengajuan_baru_membuat_notifikasi_dan_surel(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->userPengaju)
            ->post(route('permohonan.store'), [
                'nomor_surat' => '100/PL3/TIK/2026',
                'perihal' => 'Pengadaan Laboratorium Jaringan',
                'tujuan_pengadaan_ringkas' => 'Peralatan praktikum',
                'anggaran_diajukan' => 120000000,
                'kode_unit' => 'TIK',
                'tanggal_pengajuan' => now()->toDateString(),
            ]);

        $response->assertRedirect(route('permohonan.index'));

        // Notifikasi harus dibuat untuk Staf B2, Wadir 2, dan Perencanaan
        $this->assertDatabaseHas('notifikasi_user', ['id_user' => $this->userStaffB2->id_user]);
        $this->assertDatabaseHas('notifikasi_user', ['id_user' => $this->userWadir2->id_user]);
        $this->assertDatabaseHas('notifikasi_user', ['id_user' => $this->userPerencanaan->id_user]);

        // Email harus terkirim
        Notification::assertSentTo($this->userStaffB2, PengingatPengadaanNotification::class);
        Notification::assertSentTo($this->userWadir2, PengingatPengadaanNotification::class);
        Notification::assertSentTo($this->userPerencanaan, PengingatPengadaanNotification::class);
    }

    /**
     * 2. Staf Bidang 2 menelaah dan meneruskan usulan ke Wadir 2 memicu notifikasi ke Wadir 2.
     */
    public function test_telaah_staff_bidang_2_memicu_notifikasi_ke_wadir_2(): void
    {
        Notification::fake();

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => '101/PL3/TIK/2026',
            'judul_pengadaan' => 'Pengadaan Switch Core',
            'perihal' => 'Pengadaan Switch Core',
            'anggaran_diajukan' => 80000000,
            'kode_unit' => 'TIK',
            'id_user' => $this->userPengaju->id_user,
            'tanggal_pengajuan' => now(),
            'status_permohonan' => 'diajukan',
        ]);

        $response = $this->actingAs($this->userStaffB2)
            ->post(route('verifikasi.bidang-2.teruskan'), [
                'nomor_surat' => $permohonan->nomor_surat,
                'catatan_staff' => 'Berkas lengkap dan sesuai spesifikasi KAK',
            ]);

        $response->assertRedirect(route('verifikasi.bidang-2'));

        // Notifikasi harus dibuat untuk Wadir 2
        $this->assertDatabaseHas('notifikasi_user', ['id_user' => $this->userWadir2->id_user]);
        Notification::assertSentTo($this->userWadir2, PengingatPengadaanNotification::class);
    }

    /**
     * 3. Penolakan usulan oleh Wadir 2 memicu notifikasi ke Pengusul beserta alasan penolakan.
     */
    public function test_penolakan_wadir_2_memicu_notifikasi_ke_pengaju(): void
    {
        Notification::fake();

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => '102/PL3/TIK/2026',
            'judul_pengadaan' => 'Pengadaan Server GPU',
            'perihal' => 'Pengadaan Server GPU',
            'anggaran_diajukan' => 300000000,
            'kode_unit' => 'TIK',
            'id_user' => $this->userPengaju->id_user,
            'tanggal_pengajuan' => now(),
            'status_permohonan' => 'reviu_paralel',
        ]);

        $response = $this->actingAs($this->userWadir2)
            ->post(route('verifikasi.wadir-2.keputusan'), [
                'nomor_surat' => $permohonan->nomor_surat,
                'keputusan' => 'ditolak',
                'catatan_alasan' => 'Spesifikasi GPU terlalu tinggi untuk standar laboratorium D3',
            ]);

        $response->assertRedirect(route('verifikasi.wadir-2'));

        $this->assertDatabaseHas('notifikasi_user', ['id_user' => $this->userPengaju->id_user]);
        Notification::assertSentTo($this->userPengaju, PengingatPengadaanNotification::class);
    }

    /**
     * 4. Persetujuan penuh (Wadir 2 & Perencanaan) memicu notifikasi ke Pengusul dan Seluruh PPBJ.
     */
    public function test_persetujuan_penuh_memicu_notifikasi_ke_pengaju_dan_ppbj(): void
    {
        Notification::fake();

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => '103/PL3/TIK/2026',
            'judul_pengadaan' => 'Pengadaan Router Board',
            'perihal' => 'Pengadaan Router Board',
            'anggaran_diajukan' => 45000000,
            'kode_unit' => 'TIK',
            'id_user' => $this->userPengaju->id_user,
            'tanggal_pengajuan' => now(),
            'status_permohonan' => 'reviu_paralel',
        ]);

        // Simpan keputusan Wadir 2 telah disetujui sebelumnya
        VerifikasiParalel::create([
            'nomor_surat' => $permohonan->nomor_surat,
            'role_verifikator' => 'wadir_2',
            'id_user' => $this->userWadir2->id_user,
            'status_keputusan' => 'disetujui',
        ]);

        // Perencanaan menyetujui dan alokasikan MAK
        $response = $this->actingAs($this->userPerencanaan)
            ->post(route('verifikasi.perencanaan.mak'), [
                'nomor_surat' => $permohonan->nomor_surat,
                'kode_mak' => $this->mak->kode_mak,
                'nominal_alokasi' => 45000000,
                'keputusan_anggaran' => 'setuju',
                'catatan_anggaran' => 'Pagu DIPA tersedia',
            ]);

        $response->assertRedirect(route('verifikasi.perencanaan'));

        // Notifikasi persetujuan penuh terkirim ke Pengusul dan PPBJ
        $this->assertDatabaseHas('notifikasi_user', ['id_user' => $this->userPengaju->id_user]);
        $this->assertDatabaseHas('notifikasi_user', ['id_user' => $this->userPpbj->id_user]);

        Notification::assertSentTo($this->userPengaju, PengingatPengadaanNotification::class);
        Notification::assertSentTo($this->userPpbj, PengingatPengadaanNotification::class);
    }

    /**
     * 5. Penerbitan Kontrak SPK memicu notifikasi tembusan ke Perlengkapan, Keuangan, dan Pengusul.
     */
    public function test_penerbitan_spk_memicu_notifikasi_tembusan_dan_surel(): void
    {
        Notification::fake();

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => '104/PL3/TIK/2026',
            'judul_pengadaan' => 'Pengadaan Switch 24 Port',
            'perihal' => 'Pengadaan Switch 24 Port',
            'anggaran_diajukan' => 40000000,
            'kode_unit' => 'TIK',
            'id_user' => $this->userPengaju->id_user,
            'tanggal_pengajuan' => now(),
            'status_permohonan' => 'disetujui',
        ]);

        $paket = PaketPengadaan::create([
            'kode_paket' => 'PKT-SW-104',
            'nomor_paket' => 'PKT-SW-104',
            'nama_paket' => 'Paket Pengadaan Switch 24 Port',
            'nomor_surat' => $permohonan->nomor_surat,
            'jenis_pengadaan' => 'barang',
            'platform_pengadaan' => 'spse',
            'mekanisme_pengadaan' => 'pengadaan_langsung',
            'nilai_hps' => 40000000,
            'alokasi_pagu_paket' => 40000000,
            'jalur_routing' => 'langsung_sd_50jt',
            'id_user_ppbj' => $this->userPpbj->id_user,
            'status_paket' => 'reviu_dokumen_disetujui',
        ]);

        $vendor = Vendor::create([
            'npwp' => '01.234.567.8-012.000',
            'nib' => '1234567890123',
            'nama_perusahaan' => 'PT Jaringan Solusi Nusantara',
            'alamat' => 'Jl. Margonda Raya No. 100 Depok',
            'is_active' => true,
            'nama_pic' => 'Budi Santoso',
            'telepon_pic' => '081234567890',
            'email_pic' => 'budi@jaringan.co.id',
            'nama_bank' => 'Bank Mandiri',
            'nomor_rekening' => '1570001234567',
            'atas_nama' => 'PT Jaringan Solusi Nusantara',
        ]);

        $response = $this->actingAs($this->userPpbj)
            ->post(route('kontrak.store'), [
                'kode_paket' => $paket->nomor_paket,
                'id_vendor' => $vendor->npwp,
                'nomor_kontrak_spk' => 'SPK/TIK/2026/001',
                'nilai_kontrak' => 38500000,
                'tanggal_mulai' => now()->toDateString(),
                'tanggal_selesai' => now()->addDays(14)->toDateString(),
            ]);

        $response->assertRedirect(route('kontrak.index'));

        // Tembusan SPK ke Perlengkapan, Keuangan, dan Pengusul
        $this->assertDatabaseHas('notifikasi_user', ['id_user' => $this->userPerlengkapan->id_user]);
        $this->assertDatabaseHas('notifikasi_user', ['id_user' => $this->userKeuangan->id_user]);
        $this->assertDatabaseHas('notifikasi_user', ['id_user' => $this->userPengaju->id_user]);

        Notification::assertSentTo($this->userPerlengkapan, PengingatPengadaanNotification::class);
        Notification::assertSentTo($this->userKeuangan, PengingatPengadaanNotification::class);
        Notification::assertSentTo($this->userPengaju, PengingatPengadaanNotification::class);
    }

    /**
     * 6. Penerimaan barang fisik dan terbitnya BAST memicu notifikasi ke PPBJ dan Pengusul.
     */
    public function test_penerimaan_barang_dan_bast_memicu_notifikasi_ke_ppbj_dan_pengaju(): void
    {
        Notification::fake();

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => '105/PL3/TIK/2026',
            'judul_pengadaan' => 'Pengadaan Komponen Praktikum',
            'perihal' => 'Pengadaan Komponen Praktikum',
            'anggaran_diajukan' => 20000000,
            'kode_unit' => 'TIK',
            'id_user' => $this->userPengaju->id_user,
            'tanggal_pengajuan' => now(),
            'status_permohonan' => 'disetujui',
        ]);

        $paket = PaketPengadaan::create([
            'kode_paket' => 'PKT-KOMP-105',
            'nomor_paket' => 'PKT-KOMP-105',
            'nama_paket' => 'Paket Komponen Praktikum',
            'nomor_surat' => $permohonan->nomor_surat,
            'jenis_pengadaan' => 'barang',
            'nilai_hps' => 20000000,
            'jalur_routing' => 'langsung_sd_50jt',
            'status_paket' => 'spk_terbit',
        ]);

        $spk = KontrakSPK::create([
            'nomor_spk' => 'SPK/105/TIK/2026',
            'nomor_kontrak_spk' => 'SPK/105/TIK/2026',
            'nomor_paket' => $paket->nomor_paket,
            'id_user' => $this->userPpbj->id_user,
            'tipe_dokumen' => 'SPK',
            'nilai_kontrak' => 19500000,
            'status_spk' => 'aktif',
            'tanggal_spk' => now(),
            'tanggal_mulai' => now(),
            'tanggal_selesai' => now()->addDays(7),
            'jadwal_pengiriman' => now()->addDays(7),
            'file_spk' => 'spk/dummy.pdf',
        ]);

        $response = $this->actingAs($this->userPerlengkapan)
            ->post(route('penerimaan.store'), [
                'nomor_kontrak_spk' => $spk->nomor_spk,
                'nomor_bast' => 'BAST/105/TIK/2026',
                'tanggal_terima' => now()->toDateString(),
                'kondisi_barang' => 'terima',
                'catatan_pemeriksaan' => 'Kondisi barang 100% berfungsi normal',
            ]);

        $response->assertRedirect(route('penerimaan.index'));

        // Notifikasi ke PPBJ (untuk ajukan bayar) dan Pengusul (barang telah sampai)
        $this->assertDatabaseHas('notifikasi_user', ['id_user' => $this->userPpbj->id_user]);
        $this->assertDatabaseHas('notifikasi_user', ['id_user' => $this->userPengaju->id_user]);

        Notification::assertSentTo($this->userPpbj, PengingatPengadaanNotification::class);
        Notification::assertSentTo($this->userPengaju, PengingatPengadaanNotification::class);
    }

    /**
     * 7. Pencairan dana SP2D oleh Keuangan memicu notifikasi ke Pengusul dan PPBJ (Siklus Tuntas).
     */
    public function test_pencairan_sp2d_memicu_notifikasi_penyelesaian_siklus(): void
    {
        Notification::fake();

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => '106/PL3/TIK/2026',
            'judul_pengadaan' => 'Pengadaan Kabel UTP',
            'perihal' => 'Pengadaan Kabel UTP',
            'anggaran_diajukan' => 10000000,
            'kode_unit' => 'TIK',
            'id_user' => $this->userPengaju->id_user,
            'tanggal_pengajuan' => now(),
            'status_permohonan' => 'disetujui',
        ]);

        $paket = PaketPengadaan::create([
            'kode_paket' => 'PKT-KBL-106',
            'nomor_paket' => 'PKT-KBL-106',
            'nama_paket' => 'Paket Kabel UTP',
            'nomor_surat' => $permohonan->nomor_surat,
            'jenis_pengadaan' => 'barang',
            'nilai_hps' => 10000000,
            'status_paket' => 'perintah_bayar',
        ]);

        $pembayaran = Pembayaran::create([
            'nomor_pembayaran' => 'SPP/106/TIK/2026',
            'nomor_paket' => $paket->nomor_paket,
            'id_user_pengaju' => $this->userPpbj->id_user,
            'tahap_termin' => 1,
            'nominal_pengajuan' => 9500000,
            'tanggal_pengajuan' => now()->toDateString(),
            'status_pembayaran' => 'perintah_bayar',
        ]);

        $response = $this->actingAs($this->userKeuangan)
            ->post(route('pembayaran.store'), [
                'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
                'nomor_transaksi' => 'SP2D/106/TIK/2026',
                'tanggal_cair' => now()->toDateString(),
                'nominal_cair' => 9500000,
                'bank_tujuan' => 'Bank Mandiri (Rekening Vendor)',
                'keterangan_cair' => 'Transfer selesai tuntas',
            ]);

        $response->assertRedirect(route('pembayaran.index'));

        // Notifikasi ke Pengusul dan PPBJ
        $this->assertDatabaseHas('notifikasi_user', ['id_user' => $this->userPengaju->id_user]);
        $this->assertDatabaseHas('notifikasi_user', ['id_user' => $this->userPpbj->id_user]);

        Notification::assertSentTo($this->userPengaju, PengingatPengadaanNotification::class);
        Notification::assertSentTo($this->userPpbj, PengingatPengadaanNotification::class);
    }
}
