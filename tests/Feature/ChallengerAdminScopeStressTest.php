<?php

namespace Tests\Feature;

use App\Models\AlokasiMAK;
use App\Models\AnggaranMAK;
use App\Models\AuditLog;
use App\Models\DokumentasiPenerimaanBarang;
use App\Models\KontrakSPK;
use App\Models\MemoBayar;
use App\Models\NotifikasiUser;
use App\Models\PaketPengadaan;
use App\Models\Pembayaran;
use App\Models\PenerimaanBarang;
use App\Models\PermohonanPengadaan;
use App\Models\Reminder;
use App\Models\Role;
use App\Models\TransaksiPencairan;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VerifikasiParalel;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * ChallengerAdminScopeStressTest
 *
 * Empirical challenger adversarial test harness specifically testing:
 * 1. Admin Technical Route Prohibition (Web, API, and Policies).
 * 2. Admin Universal Cascade Deletion across complex multi-branch hierarchies and disk storage files.
 * 3. Cross-role creation deletion by Admin (universal scope).
 * 4. Non-Admin / Regular User IDOR prevention and state machine delete guard.
 */
class ChallengerAdminScopeStressTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $userPemohon1;

    protected User $userPemohon2;

    protected User $staffB2;

    protected User $perencanaan;

    protected User $ppbj;

    protected User $pp;

    protected User $ppk;

    protected Unit $unitTIK;

    protected Vendor $vendor;

    protected AnggaranMAK $mak;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');

        $this->unitTIK = Unit::firstOrCreate(
            ['kode_unit' => 'TIK'],
            ['nama_unit' => 'Teknik Informatika dan Komputer', 'jenis_unit' => 'jurusan']
        );

        $roles = Role::all()->keyBy('nama_role');

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'id_role' => $roles['admin']->id,
            'kode_unit' => $this->unitTIK->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Challenger Admin Target',
            'username' => 'challenger_admin',
            'email' => 'challenger_admin@pnj.ac.id',
        ]);

        $this->userPemohon1 = User::factory()->create([
            'role' => 'user',
            'id_role' => $roles['user']->id,
            'kode_unit' => $this->unitTIK->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Challenger Pemohon 1',
            'username' => 'challenger_pemohon_1',
            'email' => 'challenger_pemohon_1@pnj.ac.id',
        ]);

        $this->userPemohon2 = User::factory()->create([
            'role' => 'user',
            'id_role' => $roles['user']->id,
            'kode_unit' => $this->unitTIK->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Challenger Pemohon 2',
            'username' => 'challenger_pemohon_2',
            'email' => 'challenger_pemohon_2@pnj.ac.id',
        ]);

        $this->staffB2 = User::factory()->create([
            'role' => 'staff_bidang_2',
            'id_role' => $roles['staff_bidang_2']->id,
            'kode_unit' => $this->unitTIK->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Challenger Staf B2',
            'username' => 'challenger_staff_b2',
            'email' => 'challenger_staff_b2@pnj.ac.id',
        ]);

        $this->perencanaan = User::factory()->create([
            'role' => 'perencanaan',
            'id_role' => $roles['perencanaan']->id,
            'kode_unit' => $this->unitTIK->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Challenger Perencanaan',
            'username' => 'challenger_perencanaan',
            'email' => 'challenger_perencanaan@pnj.ac.id',
        ]);

        $this->ppbj = User::factory()->create([
            'role' => 'ppbj',
            'id_role' => $roles['ppbj']->id,
            'kode_unit' => $this->unitTIK->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Challenger Koordinator PPBJ',
            'username' => 'challenger_ppbj',
            'email' => 'challenger_ppbj@pnj.ac.id',
        ]);

        $this->pp = User::factory()->create([
            'role' => 'pp',
            'id_role' => $roles['pp']->id,
            'kode_unit' => $this->unitTIK->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Challenger Pejabat Pengadaan',
            'username' => 'challenger_pp',
            'email' => 'challenger_pp@pnj.ac.id',
        ]);

        $this->ppk = User::factory()->create([
            'role' => 'ppk',
            'id_role' => $roles['ppk']->id,
            'kode_unit' => $this->unitTIK->kode_unit,
            'status_aktif' => true,
            'status_registrasi' => 'disetujui',
            'nama' => 'Challenger PPK',
            'username' => 'challenger_ppk',
            'email' => 'challenger_ppk@pnj.ac.id',
        ]);

        $this->vendor = Vendor::firstOrCreate(
            ['npwp' => '99.888.777.6-555.000'],
            [
                'nib' => '9988776655443',
                'nama_perusahaan' => 'CV Challenger Vendor Mandiri',
                'alamat' => 'Jl. Kukusan No. 88 Beji Depok',
                'is_active' => true,
                'nama_pic' => 'Andi Wijaya',
                'email_pic' => 'andi@challengervendor.id',
                'telepon_pic' => '081299887766',
                'nama_bank' => 'Bank Mandiri',
                'nomor_rekening' => '1570099887766',
                'atas_nama' => 'CV Challenger Vendor Mandiri',
            ]
        );

        $this->mak = AnggaranMAK::firstOrCreate(
            ['kode_mak' => '023.17.WA.6251.EBA.994.051.A.521899'],
            [
                'uraian_mak' => 'Pagu Uji Challenger MAK',
                'pagu_anggaran' => 500000000,
                'sisa_pagu_mak' => 500000000,
                'sumber_dana' => 'BOPTN',
                'tahun_anggaran' => (int) date('Y'),
                'is_locked' => false,
            ]
        );
    }

    /**
     * Challenge 1: Admin Attempting Technical Routes and Gate Policies MUST be Forbidden (403).
     */
    public function test_admin_strictly_forbidden_from_technical_procurement_actions(): void
    {
        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => 'B/CHALLENGER-ADM-TECH/TIK/2026',
            'perihal' => 'Pengujian Adversarial Teknis Admin',
            'tujuan_pengadaan_ringkas' => 'Uji Teknis',
            'anggaran_diajukan' => 90000000,
            'estimasi_total_pagu' => 90000000,
            'kode_unit' => $this->unitTIK->kode_unit,
            'id_user' => $this->userPemohon1->id_user,
            'id_user_ppk_awal' => $this->ppk->id_user,
            'kode_mak' => $this->mak->kode_mak,
            'status_permohonan' => PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
            'tanggal_pengajuan' => now(),
            'file_permohonan' => 'permohonan/challenger_dummy.pdf',
        ]);

        $paket = PaketPengadaan::create([
            'nomor_paket' => 'PKT-CHALLENGER-ADM-001',
            'nama_paket' => 'Paket Adversarial Teknis Admin',
            'jenis_pengadaan' => PaketPengadaan::JENIS_BARANG,
            'metode_pengadaan' => PaketPengadaan::METODE_PENGADAAN_LANGSUNG,
            'jalur_routing' => PaketPengadaan::ROUTING_PP,
            'nominal_harga_perkiraan' => 85000000,
            'alokasi_pagu_paket' => 90000000,
            'status_paket' => PaketPengadaan::STATUS_TERDAFTAR,
            'nomor_surat' => $permohonan->nomor_surat,
            'tanggal_registrasi_paket' => now(),
            'id_user_ppbj' => $this->ppbj->id_user,
            'id_user_ppk' => $this->ppk->id_user,
            'id_user_pp' => $this->pp->id_user,
        ]);

        $pembayaran = Pembayaran::create([
            'nomor_pembayaran' => 'BYR-TEST-ADM-FORBIDDEN',
            'nomor_paket' => $paket->nomor_paket,
            'id_user_pengaju' => $this->ppbj->id_user,
            'tahap_termin' => 1,
            'nominal_pengajuan' => 85000000,
            'tanggal_pengajuan' => now(),
            'jatuh_tempo' => now()->addDays(30),
            'status_pembayaran' => Pembayaran::STATUS_PENGAJUAN,
        ]);

        $this->actingAs($this->admin);

        // 1. Policy check: Admin cannot update or reassign PPK on PaketPengadaan
        $this->assertFalse(Gate::forUser($this->admin)->allows('update', $paket));
        $this->assertFalse(Gate::forUser($this->admin)->allows('reassignPpk', $paket));

        // 2. HTTP Route Checks: API routes
        $technicalApiEndpoints = [
            "/api/paket-pengadaan/{$paket->nomor_paket}/review-pp" => ['nominal_harga_akhir' => 80000000],
            "/api/paket-pengadaan/{$paket->nomor_paket}/belanja-langsung" => ['nomor_kwitansi_belanja' => 'KW-CHALLENGER'],
            "/api/paket-pengadaan/{$paket->nomor_paket}/eksekusi-belanja" => ['npwp_vendor' => $this->vendor->npwp],
            '/api/verifikasi-paralel/memo-cek-mak' => ['nomor_surat' => $permohonan->nomor_surat, 'nomor_memo_cek_mak' => 'MEMO-CHALLENGER'],
            '/api/verifikasi-paralel/memo-ketersediaan-mak' => ['nomor_surat' => $permohonan->nomor_surat, 'kode_mak' => $this->mak->kode_mak, 'id_user_ppk' => $this->ppk->id_user],
            '/api/verifikasi-paralel/keputusan-awal' => ['nomor_surat' => $permohonan->nomor_surat, 'keputusan' => 'disetujui'],
            "/api/paket-pengadaan/{$paket->nomor_paket}/reassign-ppk" => ['id_user_ppk_baru' => $this->ppk->id_user, 'alasan' => 'Uji coba'],
            "/api/permohonan-pengadaan/{$permohonan->nomor_surat}/split" => ['pemecahan' => [['kode_mak' => $this->mak->kode_mak, 'nominal' => 45000000]]],
            "/api/permohonan/{$permohonan->nomor_surat}/split-ppbj" => ['alasan_pemecahan' => 'Uji coba split'],
            "/api/pembayaran/{$pembayaran->nomor_pembayaran}/perintah-bayar" => [],
        ];

        foreach ($technicalApiEndpoints as $uri => $payload) {
            $resp = $this->postJson($uri, $payload);
            $resp->assertStatus(403);
            $resp->assertJsonFragment([
                'status' => 'error',
                'message' => 'Akses Ditolak: Role Administrator dibatasi untuk fungsi administratif dan dilarang mengakses proses verifikasi teknis pengadaan.',
            ]);
        }

        // 3. HTTP Web Routes: GET & POST
        $technicalWebEndpoints = [
            '/paket-pengadaan/reassign-ppk' => 'get',
            '/verifikasi/bidang-2' => 'get',
            '/verifikasi/wadir-2' => 'get',
            '/verifikasi/perencanaan' => 'get',
            '/verifikasi/ppk' => 'get',
            '/verifikasi/bidang-2/teruskan' => 'post',
            '/verifikasi/wadir-2/keputusan' => 'post',
            '/verifikasi/ppk/keputusan' => 'post',
            '/verifikasi/perencanaan/mak' => 'post',
        ];

        foreach ($technicalWebEndpoints as $uri => $method) {
            $resp = $method === 'get' ? $this->get($uri) : $this->post($uri, []);
            $resp->assertStatus(403);
        }
    }

    /**
     * Challenge 2: Admin Universal Cascade Deletion with Multiple Child Packages and Deep Storage Purging.
     */
    public function test_admin_cascade_delete_handles_multiple_packages_and_deep_storage_files(): void
    {
        $nomorSuratInduk = 'B/CHALLENGER-CASCADE-MULTI/TIK/2026';
        $nomorSuratAnakSplit1 = 'B/CHALLENGER-CHILD-SPLIT1/TIK/2026';
        $nomorSuratAnakSplit2 = 'B/CHALLENGER-CHILD-SPLIT2/TIK/2026';
        $nomorSuratRevisi = 'B/CHALLENGER-CHILD-REVISI/TIK/2026';

        // 1. Buat berkas-berkas mock
        $files = [
            'permohonan' => 'permohonan/surat_induk.pdf',
            'kak' => 'permohonan/kak_induk.pdf',
            'spek' => 'permohonan/spek_induk.pdf',
            'dok_pkt_1' => 'paket/dok_pkt_1.pdf',
            'kwitansi_1' => 'paket/kwitansi_1.pdf',
            'dok_pkt_2' => 'paket/dok_pkt_2.pdf',
            'spk_2' => 'kontrak/spk_pkt_2.pdf',
            'bast_2' => 'penerimaan/bast_pkt_2.pdf',
            'foto_2a' => 'penerimaan/foto_2a.png',
            'foto_2b' => 'penerimaan/foto_2b.png',
            'invoice_2' => 'pembayaran/invoice_pkt_2.pdf',
            'bukti_trx_2' => 'pembayaran/trx_transfer_2.pdf',
        ];

        foreach ($files as $fPath) {
            Storage::disk('public')->put($fPath, 'mock binary content for '.$fPath);
        }

        // 2. Buat surat induk
        $permohonanInduk = PermohonanPengadaan::create([
            'nomor_surat' => $nomorSuratInduk,
            'perihal' => 'Pengadaan Komprehensif Multi-Paket',
            'tujuan_pengadaan_ringkas' => 'Multi-Paket',
            'anggaran_diajukan' => 150000000,
            'estimasi_total_pagu' => 150000000,
            'kode_unit' => $this->unitTIK->kode_unit,
            'id_user' => $this->userPemohon1->id_user,
            'status_permohonan' => PermohonanPengadaan::STATUS_SELESAI,
            'file_permohonan' => $files['permohonan'],
            'file_kak' => $files['kak'],
            'file_spesifikasi_teknis' => $files['spek'],
            'tanggal_pengajuan' => now(),
        ]);

        // Anak split 1 & 2
        PermohonanPengadaan::create([
            'nomor_surat' => $nomorSuratAnakSplit1,
            'perihal' => 'Split Bagian 1',
            'tujuan_pengadaan_ringkas' => 'Split 1',
            'anggaran_diajukan' => 75000000,
            'estimasi_total_pagu' => 75000000,
            'kode_unit' => $this->unitTIK->kode_unit,
            'id_user' => $this->userPemohon1->id_user,
            'nomor_surat_induk_split' => $nomorSuratInduk,
            'status_permohonan' => PermohonanPengadaan::STATUS_DIAJUKAN,
            'tanggal_pengajuan' => now(),
        ]);

        PermohonanPengadaan::create([
            'nomor_surat' => $nomorSuratAnakSplit2,
            'perihal' => 'Split Bagian 2',
            'tujuan_pengadaan_ringkas' => 'Split 2',
            'anggaran_diajukan' => 75000000,
            'estimasi_total_pagu' => 75000000,
            'kode_unit' => $this->unitTIK->kode_unit,
            'id_user' => $this->userPemohon1->id_user,
            'nomor_surat_induk_split' => $nomorSuratInduk,
            'status_permohonan' => PermohonanPengadaan::STATUS_DIAJUKAN,
            'tanggal_pengajuan' => now(),
        ]);

        // Anak revisi
        PermohonanPengadaan::create([
            'nomor_surat' => $nomorSuratRevisi,
            'perihal' => 'Revisi Surat Induk',
            'tujuan_pengadaan_ringkas' => 'Revisi',
            'anggaran_diajukan' => 150000000,
            'estimasi_total_pagu' => 150000000,
            'kode_unit' => $this->unitTIK->kode_unit,
            'id_user' => $this->userPemohon1->id_user,
            'nomor_surat_revisi' => $nomorSuratInduk,
            'status_permohonan' => PermohonanPengadaan::STATUS_DIAJUKAN,
            'tanggal_pengajuan' => now(),
        ]);

        // Verifikasi Paralel & Alokasi MAK
        VerifikasiParalel::create([
            'id_user' => $this->staffB2->id_user,
            'nomor_surat' => $nomorSuratInduk,
            'role_verifikator' => VerifikasiParalel::ROLE_STAFF_BIDANG_2,
            'status_keputusan' => VerifikasiParalel::STATUS_DISETUJUI,
            'tanggal_keputusan' => now(),
        ]);

        AlokasiMAK::create([
            'nomor_surat' => $nomorSuratInduk,
            'kode_mak' => $this->mak->kode_mak,
            'nominal_alokasi' => 150000000,
            'tanggal_alokasi' => now(),
        ]);

        // Paket 1 (Belanja Langsung <= 50jt)
        $paket1 = PaketPengadaan::create([
            'nomor_paket' => 'PKT-CHALLENGER-MULTI-1',
            'nama_paket' => 'Paket ATK dan Bahan',
            'jenis_pengadaan' => PaketPengadaan::JENIS_BARANG,
            'metode_pengadaan' => PaketPengadaan::METODE_PENGADAAN_LANGSUNG,
            'jalur_routing' => PaketPengadaan::ROUTING_LANGSUNG,
            'nominal_harga_perkiraan' => 45000000,
            'alokasi_pagu_paket' => 50000000,
            'file_dokumen_pengadaan' => $files['dok_pkt_1'],
            'file_kwitansi_belanja' => $files['kwitansi_1'],
            'status_paket' => PaketPengadaan::STATUS_SELESAI,
            'nomor_surat' => $nomorSuratInduk,
            'tanggal_registrasi_paket' => now(),
            'id_user_ppbj' => $this->ppbj->id_user,
        ]);

        // Paket 2 (SPK & Pembayaran Kontrak > 50jt)
        $paket2 = PaketPengadaan::create([
            'nomor_paket' => 'PKT-CHALLENGER-MULTI-2',
            'nama_paket' => 'Paket Perangkat Server Jaringan',
            'jenis_pengadaan' => PaketPengadaan::JENIS_BARANG,
            'metode_pengadaan' => PaketPengadaan::METODE_PENGADAAN_LANGSUNG,
            'jalur_routing' => PaketPengadaan::ROUTING_PP,
            'nominal_harga_perkiraan' => 95000000,
            'alokasi_pagu_paket' => 100000000,
            'file_dokumen_pengadaan' => $files['dok_pkt_2'],
            'status_paket' => PaketPengadaan::STATUS_SELESAI,
            'nomor_surat' => $nomorSuratInduk,
            'tanggal_registrasi_paket' => now(),
            'id_user_ppbj' => $this->ppbj->id_user,
            'id_user_ppk' => $this->ppk->id_user,
            'id_user_pp' => $this->pp->id_user,
        ]);

        // Relasi Paket 2
        KontrakSPK::create([
            'nomor_spk' => 'SPK/CHALLENGER/2026/02',
            'nomor_paket' => $paket2->nomor_paket,
            'id_user' => $this->ppk->id_user,
            'tipe_dokumen' => KontrakSPK::DOKUMEN_SPK,
            'sifat_kontrak' => KontrakSPK::TIPE_SEKALI_SELESAI,
            'status_spk' => KontrakSPK::STATUS_SELESAI,
            'tanggal_spk' => now(),
            'jadwal_pengiriman' => now()->addDays(7),
            'nilai_kontrak' => 95000000,
            'file_spk' => $files['spk_2'],
        ]);

        $penerimaan = PenerimaanBarang::create([
            'nomor_bast' => 'BAST/CHALLENGER/02',
            'nomor_paket' => $paket2->nomor_paket,
            'id_user' => $this->admin->id_user,
            'tanggal_penerimaan' => now(),
            'status_fisik' => PenerimaanBarang::STATUS_DITERIMA_LENGKAP,
            'kategori_belanja' => PenerimaanBarang::KATEGORI_BELANJA_MODAL,
            'file_bast_signed' => $files['bast_2'],
        ]);

        DokumentasiPenerimaanBarang::create([
            'id_penerimaan' => $penerimaan->id_penerimaan,
            'foto_dokumentasi' => $files['foto_2a'],
            'keterangan_foto' => 'Foto Rak Server',
            'uploaded_at' => now(),
        ]);

        DokumentasiPenerimaanBarang::create([
            'id_penerimaan' => $penerimaan->id_penerimaan,
            'foto_dokumentasi' => $files['foto_2b'],
            'keterangan_foto' => 'Foto Kabel Jaringan',
            'uploaded_at' => now(),
        ]);

        $pembayaran = Pembayaran::create([
            'nomor_pembayaran' => 'BYR/CHALLENGER/02',
            'nomor_paket' => $paket2->nomor_paket,
            'id_user_pengaju' => $this->ppbj->id_user,
            'tahap_termin' => 1,
            'nominal_pengajuan' => 95000000,
            'tanggal_pengajuan' => now(),
            'jatuh_tempo' => now()->addDays(14),
            'status_pembayaran' => Pembayaran::STATUS_LUNAS,
            'file_invoice' => $files['invoice_2'],
        ]);

        MemoBayar::create([
            'id_user' => $this->ppk->id_user,
            'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
            'role_approver' => MemoBayar::ROLE_PPK,
            'urutan_level' => 1,
            'status_memo' => MemoBayar::STATUS_DISETUJUI,
            'catatan_memo' => 'Memo disetujui',
            'tanggal_acc' => now(),
        ]);

        TransaksiPencairan::create([
            'nomor_transaksi_bank' => 'TRX-CHALLENGER-BANK-02',
            'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
            'id_user' => $this->admin->id_user,
            'tanggal_transfer' => now(),
            'nominal_transfer' => 95000000,
            'file_bukti_transfer' => $files['bukti_trx_2'],
        ]);

        $reminder = Reminder::create([
            'nomor_paket' => $paket2->nomor_paket,
            'tipe_reminder' => Reminder::TIPE_PENGIRIMAN,
            'waktu_trigger' => now()->addDays(3),
            'judul_alert' => 'Pengingat Pengiriman',
            'pesan_alert' => 'Batas waktu pengiriman server',
        ]);

        NotifikasiUser::create([
            'id_user' => $this->userPemohon1->id_user,
            'id_reminder' => $reminder->id_reminder,
            'is_read' => false,
            'is_dismissed' => false,
        ]);

        // 3. Eksekusi DELETE oleh Admin
        $this->actingAs($this->admin);
        $resp = $this->delete(route('permohonan.destroy', $permohonanInduk->nomor_surat));
        $resp->assertRedirect(route('permohonan.index'));
        $resp->assertSessionHas('success');

        // 4. Verifikasi Database: Semua relasi induk dan kedua paket terhapus
        $this->assertDatabaseMissing('permohonan_pengadaan', ['nomor_surat' => $nomorSuratInduk]);
        $this->assertDatabaseMissing('verifikasi_paralel', ['nomor_surat' => $nomorSuratInduk]);
        $this->assertDatabaseMissing('alokasi_mak', ['nomor_surat' => $nomorSuratInduk]);
        $this->assertDatabaseMissing('paket_pengadaan', ['nomor_paket' => $paket1->nomor_paket]);
        $this->assertDatabaseMissing('paket_pengadaan', ['nomor_paket' => $paket2->nomor_paket]);
        $this->assertDatabaseMissing('kontrak_spk', ['nomor_spk' => 'SPK/CHALLENGER/2026/02']);
        $this->assertDatabaseMissing('penerimaan_barang', ['nomor_bast' => 'BAST/CHALLENGER/02']);
        $this->assertDatabaseMissing('penerimaan_barang_foto_dokumentasi', ['id_penerimaan' => $penerimaan->id_penerimaan]);
        $this->assertDatabaseMissing('pembayaran', ['nomor_pembayaran' => $pembayaran->nomor_pembayaran]);
        $this->assertDatabaseMissing('memo_bayar', ['nomor_pembayaran' => $pembayaran->nomor_pembayaran]);
        $this->assertDatabaseMissing('transaksi_pencairan', ['nomor_transaksi_bank' => 'TRX-CHALLENGER-BANK-02']);
        $this->assertDatabaseMissing('reminder', ['nomor_paket' => $paket2->nomor_paket]);
        $this->assertDatabaseMissing('notifikasi_user', ['id_reminder' => $reminder->id_reminder]);

        // Anak split dan anak revisi tetap eksis, relasi ter-unlink
        $this->assertDatabaseHas('permohonan_pengadaan', [
            'nomor_surat' => $nomorSuratAnakSplit1,
            'nomor_surat_induk_split' => null,
        ]);
        $this->assertDatabaseHas('permohonan_pengadaan', [
            'nomor_surat' => $nomorSuratAnakSplit2,
            'nomor_surat_induk_split' => null,
        ]);
        $this->assertDatabaseHas('permohonan_pengadaan', [
            'nomor_surat' => $nomorSuratRevisi,
            'nomor_surat_revisi' => null,
        ]);

        // 5. Verifikasi Storage: Semua berkas pada disk public benar-benar terhapus
        foreach ($files as $name => $fPath) {
            Storage::disk('public')->assertMissing($fPath);
        }

        // 6. Audit log terisi
        $this->assertTrue(
            AuditLog::where('jenis_aksi', 'HAPUS_PERMOHONAN')
                ->where('id_entitas', $nomorSuratInduk)
                ->where('id_user', $this->admin->id_user)
                ->exists()
        );
    }

    /**
     * Challenge 3: Admin Can Universally Delete Submissions Created by ANY Role.
     */
    public function test_admin_can_universally_delete_submissions_created_by_any_role(): void
    {
        $creators = [
            'user' => $this->userPemohon1,
            'staff_b2' => $this->staffB2,
            'perencanaan' => $this->perencanaan,
            'ppbj' => $this->ppbj,
        ];

        foreach ($creators as $label => $creator) {
            $nomorSurat = "B/CHALLENGER-ROLE-{$label}/TIK/2026";

            PermohonanPengadaan::create([
                'nomor_surat' => $nomorSurat,
                'perihal' => "Pengajuan oleh {$label}",
                'tujuan_pengadaan_ringkas' => "Uji Role {$label}",
                'anggaran_diajukan' => 25000000,
                'estimasi_total_pagu' => 25000000,
                'kode_unit' => $this->unitTIK->kode_unit,
                'id_user' => $creator->id_user,
                'status_permohonan' => PermohonanPengadaan::STATUS_DIPROSES_PPBJ,
                'tanggal_pengajuan' => now(),
            ]);

            // Admin login dan hapus
            $this->actingAs($this->admin);
            $resp = $this->delete(route('permohonan.destroy', $nomorSurat));
            $resp->assertRedirect(route('permohonan.index'));
            $resp->assertSessionHas('success');

            $this->assertDatabaseMissing('permohonan_pengadaan', ['nomor_surat' => $nomorSurat]);
        }
    }

    /**
     * Challenge 4: Non-Admin Roles Attempting Cross-User Deletions MUST be Blocked (403 Forbidden).
     */
    public function test_non_admin_roles_attempting_cross_user_deletions_are_forbidden(): void
    {
        $targetProposal = PermohonanPengadaan::create([
            'nomor_surat' => 'B/CHALLENGER-PROTECTED-OWNER/TIK/2026',
            'perihal' => 'Usulan Rahasia Pemohon 1',
            'tujuan_pengadaan_ringkas' => 'Rahasia Pemohon 1',
            'anggaran_diajukan' => 50000000,
            'estimasi_total_pagu' => 50000000,
            'kode_unit' => $this->unitTIK->kode_unit,
            'id_user' => $this->userPemohon1->id_user,
            'status_permohonan' => PermohonanPengadaan::STATUS_DIAJUKAN,
            'tanggal_pengajuan' => now(),
        ]);

        $attackers = [
            $this->userPemohon2,
            $this->staffB2,
            $this->perencanaan,
            $this->ppbj,
            $this->pp,
            $this->ppk,
        ];

        foreach ($attackers as $attacker) {
            $this->actingAs($attacker);
            $resp = $this->delete(route('permohonan.destroy', $targetProposal->nomor_surat));
            $resp->assertStatus(403);

            // Pastikan data tidak tersentuh
            $this->assertDatabaseHas('permohonan_pengadaan', [
                'nomor_surat' => $targetProposal->nomor_surat,
            ]);
        }
    }

    /**
     * Challenge 5: Regular User Cannot Delete Own Proposal Once It Enters Review/Approval Lifecycle.
     */
    public function test_regular_user_cannot_delete_own_proposal_in_active_lifecycle_statuses(): void
    {
        $lifecycleStatuses = [
            PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
            PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN,
            PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK,
            PermohonanPengadaan::STATUS_DISETUJUI,
            PermohonanPengadaan::STATUS_DIPROSES_PPBJ,
            PermohonanPengadaan::STATUS_SELESAI,
        ];

        foreach ($lifecycleStatuses as $idx => $status) {
            $nomorSurat = "B/CHALLENGER-LIFECYCLE-{$idx}/TIK/2026";

            PermohonanPengadaan::create([
                'nomor_surat' => $nomorSurat,
                'perihal' => "Usulan Tahap {$status}",
                'tujuan_pengadaan_ringkas' => "Tahap {$status}",
                'anggaran_diajukan' => 30000000,
                'estimasi_total_pagu' => 30000000,
                'kode_unit' => $this->unitTIK->kode_unit,
                'id_user' => $this->userPemohon1->id_user,
                'status_permohonan' => $status,
                'tanggal_pengajuan' => now(),
            ]);

            $this->actingAs($this->userPemohon1);
            $resp = $this->delete(route('permohonan.destroy', $nomorSurat));

            // Harus dialihkan kembali dengan peringatan error
            $resp->assertRedirect(route('permohonan.index'));
            $resp->assertSessionHas('error', 'Permohonan yang telah diverifikasi atau diproses paket tidak dapat dibatalkan.');

            // Dokumen tetap aman di DB
            $this->assertDatabaseHas('permohonan_pengadaan', [
                'nomor_surat' => $nomorSurat,
            ]);
        }
    }
}
