<?php

use App\Models\AlokasiMAK;
use App\Models\AnggaranMAK;
use App\Models\DokumentasiPenerimaanBarang;
use App\Models\KontrakSPK;
use App\Models\MemoBayar;
use App\Models\NotifikasiUser;
use App\Models\PaketPengadaan;
use App\Models\Pembayaran;
use App\Models\PenerimaanBarang;
use App\Models\Permission;
use App\Models\PermohonanPengadaan;
use App\Models\Reminder;
use App\Models\ReviuDokumen;
use App\Models\Role;
use App\Models\TransaksiPencairan;
use App\Models\User;
use App\Models\VerifikasiParalel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

it('keeps permohonan at stage 2 while waiting wadir and ppk approval even after mak is allocated', function (): void {
    $pengaju = User::where('username', 'user_pengaju')->firstOrFail();

    AnggaranMAK::create([
        'kode_mak' => 'MAK-2026-001',
        'uraian_mak' => 'Belanja Operasional Lab',
        'sumber_dana' => 'RM',
        'tahun_anggaran' => 2026,
        'pagu_anggaran' => 500000000,
        'sisa_pagu_mak' => 500000000,
        'is_locked' => false,
    ]);

    $permohonan = PermohonanPengadaan::create([
        'nomor_surat' => 'SRT-2026-0001',
        'perihal' => 'Pengadaan Komputer Lab',
        'judul_pengadaan' => 'Pengadaan Komputer Lab',
        'anggaran_diajukan' => 120000000,
        'estimasi_total_pagu' => 120000000,
        'status' => 'menunggu_acc_wadir_ppk',
        'status_permohonan' => 'menunggu_acc_wadir_ppk',
        'status_anggaran' => 'tersedia',
        'tanggal_pengajuan' => now(),
        'kode_unit' => $pengaju->kode_unit,
        'id_user' => $pengaju->id_user,
        'kode_mak' => 'MAK-2026-001',
        'mak_locked' => true,
    ]);

    AlokasiMAK::create([
        'nomor_surat' => $permohonan->nomor_surat,
        'kode_mak' => 'MAK-2026-001',
        'nominal_alokasi' => 120000000,
        'tanggal_alokasi' => now(),
    ]);

    expect($permohonan->fresh()->hitungTahapSiklus())->toBe(2);

    $this->actingAs($pengaju)
        ->get(route('permohonan.show', $permohonan->nomor_surat))
        ->assertOk()
        ->assertSee('Tahap 2 dari 8');

    $permohonan->update([
        'status' => 'disetujui',
        'status_permohonan' => 'disetujui',
    ]);

    expect($permohonan->fresh()->hitungTahapSiklus())->toBe(3);

    $ppk = User::where('username', 'ppk_1')->firstOrFail();
    $ppbj = User::where('username', 'ppbj')->firstOrFail();

    $paket = PaketPengadaan::create([
        'nomor_paket' => 'PKT-2026-0001',
        'nama_paket' => 'Paket Komputer Lab',
        'jenis_pengadaan' => 'barang',
        'nilai_hps' => 120000000,
        'nominal_harga_perkiraan' => 120000000,
        'alokasi_pagu_paket' => 120000000,
        'status_paket' => 'menunggu_reviu_pp',
        'nomor_surat' => $permohonan->nomor_surat,
        'tanggal_registrasi_paket' => now(),
        'id_user_ppbj' => $ppbj->id_user,
        'id_user_ppk' => $ppk->id_user,
    ]);

    expect($permohonan->fresh()->hitungTahapSiklus())->toBe(4);

    $paket->update(['status_paket' => 'disetujui_ppk_siap_belanja_pp']);
    expect($permohonan->fresh()->hitungTahapSiklus())->toBe(5);

    $pembayaran = Pembayaran::create([
        'nomor_pembayaran' => 'BYR-2026-0001',
        'nomor_paket' => $paket->nomor_paket,
        'id_user_pengaju' => $ppbj->id_user,
        'tahap_termin' => 'termin_1',
        'nominal_pengajuan' => 120000000,
        'tanggal_pengajuan' => now(),
        'status_pembayaran' => 'spp_terbit',
    ]);

    expect($permohonan->fresh()->hitungTahapSiklus())->toBe(7);

    $pembayaran->update(['status_pembayaran' => 'lunas']);
    expect($permohonan->fresh()->hitungTahapSiklus())->toBe(8);
});

it('shows memo bayar on wadir 2 and assigned or reassigned ppk dashboards in parallel across routings', function (): void {
    $pengaju = User::where('username', 'user_pengaju')->firstOrFail();
    $ppk = User::where('username', 'ppk_1')->firstOrFail();
    $ppk2 = User::where('username', 'ppk_2')->firstOrFail();
    $wadir = User::where('username', 'wadir2')->firstOrFail();
    $ppbj = User::where('username', 'ppbj')->firstOrFail();

    $permohonan = PermohonanPengadaan::create([
        'nomor_surat' => 'SRT-2026-0002',
        'perihal' => 'Pengadaan Server',
        'judul_pengadaan' => 'Pengadaan Server Kampus',
        'anggaran_diajukan' => 250000000,
        'estimasi_total_pagu' => 250000000,
        'status' => 'diproses_ppbj',
        'status_permohonan' => 'diproses_ppbj',
        'tanggal_pengajuan' => now(),
        'kode_unit' => $pengaju->kode_unit,
        'id_user' => $pengaju->id_user,
        'id_user_ppk_awal' => $ppk->id_user,
    ]);

    $paket = PaketPengadaan::create([
        'nomor_paket' => 'PKT-2026-0002',
        'nama_paket' => 'Paket Pengadaan Server',
        'jenis_pengadaan' => 'barang',
        'jalur_routing' => PaketPengadaan::ROUTING_PPK,
        'nilai_hps' => 250000000,
        'nominal_harga_perkiraan' => 250000000,
        'alokasi_pagu_paket' => 250000000,
        'status_paket' => 'kontrak_aktif',
        'nomor_surat' => $permohonan->nomor_surat,
        'tanggal_registrasi_paket' => now(),
        'id_user_ppbj' => $ppbj->id_user,
        'id_user_ppk' => $ppk->id_user,
    ]);

    $pembayaran = Pembayaran::create([
        'nomor_pembayaran' => 'BYR-2026-0002',
        'nomor_paket' => $paket->nomor_paket,
        'id_user_pengaju' => $ppbj->id_user,
        'tahap_termin' => 'lunas',
        'nominal_pengajuan' => 250000000,
        'tanggal_pengajuan' => now(),
        'status_pembayaran' => 'reviu_memo',
    ]);

    MemoBayar::create([
        'id_user' => $wadir->id_user,
        'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
        'role_approver' => MemoBayar::ROLE_WADIR_2,
        'status_memo' => MemoBayar::STATUS_DIAJUKAN,
    ]);

    MemoBayar::create([
        'id_user' => $ppk->id_user,
        'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
        'role_approver' => MemoBayar::ROLE_PPK,
        'status_memo' => MemoBayar::STATUS_DIAJUKAN,
    ]);

    // Wadir II dan PPK yang bertugas melihat antrean Memo Bayar secara paralel tanpa prasyarat berjenjang.
    $this->actingAs($wadir)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('BYR-2026-0002')
        ->assertSee('Memo Bayar Wadir II');

    $this->actingAs($ppk)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('BYR-2026-0002')
        ->assertSee('Memo Bayar PPK');

    // Alih tugas PPK memindahkan antrean Memo Bayar tertunda ke PPK pengganti.
    $this->actingAs($ppbj)
        ->post(route('paket.reassign-ppk'), [
            'kode_paket' => $paket->nomor_paket,
            'id_user_ppk_baru' => $ppk2->id_user,
            'alasan_pengalihan' => 'Penyesuaian penanggung jawab kontrak.',
        ])
        ->assertRedirect(route('paket.index'));

    $this->actingAs($ppk)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('BYR-2026-0002');

    $this->actingAs($ppk2)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('BYR-2026-0002');

    // Jalur Belanja Langsung <= 50 juta tanpa id_user_ppk juga tampil pada PPK awal yang dipilih Perencanaan.
    $permohonanLangsung = PermohonanPengadaan::create([
        'nomor_surat' => 'SRT-2026-BL01',
        'perihal' => 'Belanja ATK Jurusan',
        'judul_pengadaan' => 'Belanja ATK Jurusan',
        'anggaran_diajukan' => 25000000,
        'estimasi_total_pagu' => 25000000,
        'status' => 'diproses_ppbj',
        'status_permohonan' => 'diproses_ppbj',
        'tanggal_pengajuan' => now(),
        'kode_unit' => $pengaju->kode_unit,
        'id_user' => $pengaju->id_user,
        'id_user_ppk_awal' => $ppk->id_user,
    ]);

    $paketLangsung = PaketPengadaan::create([
        'nomor_paket' => 'PKT-2026-BL01',
        'nama_paket' => 'Paket Belanja ATK',
        'jenis_pengadaan' => 'barang',
        'jalur_routing' => PaketPengadaan::ROUTING_LANGSUNG,
        'nilai_hps' => 25000000,
        'nominal_harga_perkiraan' => 25000000,
        'alokasi_pagu_paket' => 25000000,
        'status_paket' => PaketPengadaan::STATUS_BELANJA_LANGSUNG_SELESAI,
        'nomor_surat' => $permohonanLangsung->nomor_surat,
        'tanggal_registrasi_paket' => now(),
        'id_user_ppbj' => $ppbj->id_user,
        'id_user_ppk' => null,
    ]);

    $bayarLangsung = Pembayaran::create([
        'nomor_pembayaran' => 'BYR-2026-BL01',
        'nomor_paket' => $paketLangsung->nomor_paket,
        'id_user_pengaju' => $ppbj->id_user,
        'tahap_termin' => 'lunas',
        'nominal_pengajuan' => 25000000,
        'tanggal_pengajuan' => now(),
        'status_pembayaran' => 'reviu_memo',
    ]);

    MemoBayar::create([
        'id_user' => $ppk->id_user,
        'nomor_pembayaran' => $bayarLangsung->nomor_pembayaran,
        'role_approver' => MemoBayar::ROLE_PPK,
        'status_memo' => MemoBayar::STATUS_MENUNGGU,
    ]);

    $this->actingAs($ppk)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('BYR-2026-BL01');
});

it('displays all actionable queues on ppbj, pp, and keuangan dashboards when statuses match', function (): void {
    $pengaju = User::where('username', 'user_pengaju')->firstOrFail();
    $ppbj = User::where('username', 'ppbj')->firstOrFail();
    $ppk = User::where('username', 'ppk_1')->firstOrFail();
    $pp = User::where('username', 'pp_user')->firstOrFail();
    $keuangan = User::where('username', 'keuangan')->firstOrFail();

    $permohonanSiapPaket = PermohonanPengadaan::create([
        'nomor_surat' => 'SRT-2026-0003',
        'perihal' => 'Usulan Siap Paket',
        'judul_pengadaan' => 'Usulan Siap Paket Test',
        'anggaran_diajukan' => 45000000,
        'estimasi_total_pagu' => 45000000,
        'status' => 'disetujui',
        'status_permohonan' => 'disetujui',
        'tanggal_pengajuan' => now(),
        'kode_unit' => $pengaju->kode_unit,
        'id_user' => $pengaju->id_user,
    ]);

    $permohonanPaket = PermohonanPengadaan::create([
        'nomor_surat' => 'SRT-2026-0004',
        'perihal' => 'Usulan Paket Berjalan',
        'judul_pengadaan' => 'Usulan Paket Berjalan',
        'anggaran_diajukan' => 90000000,
        'estimasi_total_pagu' => 90000000,
        'status' => 'diproses_ppbj',
        'status_permohonan' => 'diproses_ppbj',
        'tanggal_pengajuan' => now(),
        'kode_unit' => $pengaju->kode_unit,
        'id_user' => $pengaju->id_user,
    ]);

    PaketPengadaan::create([
        'nomor_paket' => 'PKT-2026-KTR',
        'nama_paket' => 'Paket Upload Kontrak PPBJ',
        'jenis_pengadaan' => 'barang',
        'nilai_hps' => 100000000,
        'nominal_harga_perkiraan' => 100000000,
        'alokasi_pagu_paket' => 100000000,
        'status_paket' => PaketPengadaan::STATUS_SIAP_UPLOAD_DOKUMEN_PPBJ,
        'nomor_surat' => $permohonanPaket->nomor_surat,
        'tanggal_registrasi_paket' => now(),
        'id_user_ppbj' => $ppbj->id_user,
        'id_user_ppk' => $ppk->id_user,
    ]);

    $paketSpp = PaketPengadaan::create([
        'nomor_paket' => 'PKT-2026-SPP',
        'nama_paket' => 'Paket Siap Terbit SPP',
        'jenis_pengadaan' => 'barang',
        'nilai_hps' => 80000000,
        'nominal_harga_perkiraan' => 80000000,
        'alokasi_pagu_paket' => 80000000,
        'status_paket' => 'kontrak_aktif',
        'nomor_surat' => $permohonanPaket->nomor_surat,
        'tanggal_registrasi_paket' => now(),
        'id_user_ppbj' => $ppbj->id_user,
        'id_user_ppk' => $ppk->id_user,
    ]);

    Pembayaran::create([
        'nomor_pembayaran' => 'BYR-2026-SPP',
        'nomor_paket' => $paketSpp->nomor_paket,
        'id_user_pengaju' => $ppbj->id_user,
        'tahap_termin' => 'lunas',
        'nominal_pengajuan' => 80000000,
        'tanggal_pengajuan' => now(),
        'status_pembayaran' => 'memo_disetujui',
    ]);

    $paketKeu = PaketPengadaan::create([
        'nomor_paket' => 'PKT-2026-KEU',
        'nama_paket' => 'Paket Siap Pencairan Keuangan',
        'jenis_pengadaan' => 'barang',
        'nilai_hps' => 75000000,
        'nominal_harga_perkiraan' => 75000000,
        'alokasi_pagu_paket' => 75000000,
        'status_paket' => 'kontrak_aktif',
        'nomor_surat' => $permohonanPaket->nomor_surat,
        'tanggal_registrasi_paket' => now(),
        'id_user_ppbj' => $ppbj->id_user,
        'id_user_ppk' => $ppk->id_user,
    ]);

    Pembayaran::create([
        'nomor_pembayaran' => 'BYR-2026-KEU',
        'nomor_paket' => $paketKeu->nomor_paket,
        'id_user_pengaju' => $ppbj->id_user,
        'tahap_termin' => 'lunas',
        'nominal_pengajuan' => 75000000,
        'tanggal_pengajuan' => now(),
        'status_pembayaran' => 'spp_terbit',
    ]);

    PaketPengadaan::create([
        'nomor_paket' => 'PKT-2026-PP',
        'nama_paket' => 'Paket Menunggu Reviu PP',
        'jenis_pengadaan' => 'barang',
        'jalur_routing' => PaketPengadaan::ROUTING_PP,
        'nilai_hps' => 95000000,
        'nominal_harga_perkiraan' => 95000000,
        'alokasi_pagu_paket' => 95000000,
        'status_paket' => 'menunggu_reviu_pp',
        'nomor_surat' => $permohonanPaket->nomor_surat,
        'tanggal_registrasi_paket' => now(),
        'id_user_ppbj' => $ppbj->id_user,
        'id_user_ppk' => $ppk->id_user,
    ]);

    $this->actingAs($ppbj)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee($permohonanSiapPaket->judul_pengadaan)
        ->assertSee('Paket Upload Kontrak PPBJ')
        ->assertSee('Paket Siap Terbit SPP')
        ->assertSee('Terbitkan SPP');

    $this->actingAs($pp)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Paket Menunggu Reviu PP');

    $this->actingAs($keuangan)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Paket Siap Pencairan Keuangan');
});

it('strictly hides action items when a custom role only has view permissions without execute permissions', function (): void {
    $pengaju = User::where('username', 'user_pengaju')->firstOrFail();

    PermohonanPengadaan::create([
        'nomor_surat' => 'SRT-2026-MAK1',
        'perihal' => 'Usulan Menunggu MAK',
        'judul_pengadaan' => 'Usulan Menunggu MAK View Only Check',
        'anggaran_diajukan' => 30000000,
        'estimasi_total_pagu' => 30000000,
        'status' => 'cek_mak_perencanaan',
        'status_permohonan' => 'cek_mak_perencanaan',
        'tanggal_pengajuan' => now(),
        'kode_unit' => $pengaju->kode_unit,
        'id_user' => $pengaju->id_user,
    ]);

    $viewOnlyRole = Role::create([
        'nama_role' => 'viewer_anggaran',
        'label' => 'Viewer Anggaran',
    ]);

    $makViewPerm = Permission::where('nama_permission', 'anggaran.mak_view')->firstOrFail();
    $viewOnlyRole->permissions()->attach($makViewPerm->id);

    $viewerUser = User::create([
        'nip' => '199001012020121001',
        'nama' => 'Viewer Anggaran',
        'username' => 'viewer_mak',
        'email' => 'viewer_mak@polban.ac.id',
        'password' => bcrypt('password'),
        'id_role' => $viewOnlyRole->id_role,
        'kode_unit' => $pengaju->kode_unit,
        'jabatan' => 'Staf Anggaran',
        'no_hp' => '081234567890',
        'status_aktif' => true,
    ]);

    $this->actingAs($viewerUser)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Usulan Menunggu MAK View Only Check');
});

it('routes submitted proposals directly to perencanaan and keeps staff_bidang_2 strictly read only', function (): void {
    $pengaju = User::where('username', 'user_pengaju')->firstOrFail();
    $staffB2 = User::where('username', 'staff_b2')->firstOrFail();
    $perencanaan = User::where('username', 'perencanaan')->firstOrFail();
    $wadir = User::where('username', 'wadir2')->firstOrFail();
    $ppk = User::where('username', 'ppk_1')->firstOrFail();

    AnggaranMAK::create([
        'kode_mak' => 'MAK-2026-ALUR',
        'uraian_mak' => 'Belanja Peralatan Lab',
        'sumber_dana' => 'RM',
        'tahun_anggaran' => 2026,
        'pagu_anggaran' => 300000000,
        'sisa_pagu_mak' => 300000000,
        'is_locked' => false,
    ]);

    expect(Route::has('verifikasi.bidang-2.teruskan'))->toBeFalse();

    $this->actingAs($pengaju)
        ->post(route('permohonan.store'), [
            'nomor_surat' => 'SRT-ALUR-001',
            'perihal' => 'Pengadaan Switch Jaringan',
            'tujuan_pengadaan_ringkas' => 'Kebutuhan jaringan gedung kuliah',
            'anggaran_diajukan' => 80000000,
            'kode_unit' => $pengaju->kode_unit,
        ])
        ->assertRedirect(route('permohonan.index'));

    $permohonan = PermohonanPengadaan::where('nomor_surat', 'SRT-ALUR-001')->firstOrFail();
    expect($permohonan->status_permohonan)->toBe(PermohonanPengadaan::STATUS_DIAJUKAN)
        ->and($permohonan->hitungTahapSiklus())->toBe(1)
        ->and(VerifikasiParalel::where('nomor_surat', 'SRT-ALUR-001')->where('role_verifikator', VerifikasiParalel::ROLE_PERENCANAAN)->exists())->toBeTrue()
        ->and(VerifikasiParalel::where('nomor_surat', 'SRT-ALUR-001')->where('role_verifikator', VerifikasiParalel::ROLE_STAFF_BIDANG_2)->exists())->toBeFalse();

    $this->actingAs($pengaju)
        ->get(route('permohonan.index', ['status' => 'diajukan']))
        ->assertOk()
        ->assertSee('SRT-ALUR-001')
        ->assertSee('Diajukan');

    $this->actingAs($pengaju)
        ->get(route('permohonan.index', ['status' => 'reviu_paralel']))
        ->assertOk()
        ->assertDontSee('SRT-ALUR-001');

    // Staf Bidang 2 hanya dapat membaca tanpa form upload memo atau tombol teruskan.
    $this->actingAs($staffB2)
        ->get(route('verifikasi.bidang-2'))
        ->assertOk()
        ->assertSee('SRT-ALUR-001')
        ->assertDontSee('Form Verifikasi Staf Bidang 2')
        ->assertDontSee('Unggah Dokumen Fisik Memo Cek Anggaran')
        ->assertDontSee('verifikasi/bidang-2/teruskan');

    $this->actingAs($staffB2)
        ->get(route('permohonan.index'))
        ->assertOk()
        ->assertDontSee('Form Verifikasi Staf Bidang 2')
        ->assertDontSee('Proses Telaah');

    $this->actingAs($staffB2)
        ->get(route('permohonan.show', $permohonan->nomor_surat))
        ->assertOk()
        ->assertDontSee('Form Verifikasi Staf Bidang 2')
        ->assertDontSee('verifikasi/bidang-2/teruskan');

    // Wadir 2 belum dapat menyetujui sebelum Perencanaan menetapkan anggaran & memilih PPK.
    $this->actingAs($wadir)
        ->post(route('verifikasi.wadir-2.keputusan'), [
            'nomor_surat' => $permohonan->nomor_surat,
            'keputusan' => 'disetujui',
        ])
        ->assertRedirect(route('verifikasi.wadir-2'))
        ->assertSessionHas('error');

    // Perencanaan menentukan pos anggaran & memilih PPK.
    $this->actingAs($perencanaan)
        ->post(route('verifikasi.perencanaan.mak'), [
            'nomor_surat' => $permohonan->nomor_surat,
            'kode_mak' => 'MAK-2026-ALUR',
            'nominal_alokasi' => 80000000,
            'keputusan_anggaran' => 'setuju',
            'id_user_ppk_awal' => $ppk->id_user,
        ])
        ->assertRedirect(route('verifikasi.perencanaan'));

    expect($permohonan->fresh()->status_permohonan)->toBe(PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK)
        ->and((int) $permohonan->fresh()->id_user_ppk_awal)->toBe((int) $ppk->id_user);

    // Wadir 2 dan PPK terpilih dapat memutuskan secara paralel untuk meloloskan ke PPBJ.
    $this->actingAs($wadir)
        ->from(route('verifikasi.wadir-2'))
        ->post(route('verifikasi.wadir-2.keputusan'), [
            'nomor_surat' => $permohonan->nomor_surat,
            'keputusan' => 'disetujui',
        ])
        ->assertRedirect(route('verifikasi.wadir-2'));

    expect($permohonan->fresh()->status_permohonan)->toBe(PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK);

    $this->actingAs($ppk)
        ->from(route('verifikasi.ppk'))
        ->post(route('verifikasi.ppk.keputusan'), [
            'nomor_surat' => $permohonan->nomor_surat,
            'keputusan' => 'disetujui',
        ])
        ->assertRedirect(route('verifikasi.ppk'));

    expect($permohonan->fresh()->status_permohonan)->toBe(PermohonanPengadaan::STATUS_DISETUJUI);
});

it('separates initial ppk approval permission from packet document review and merges duplicate lihat_pp', function (): void {
    $pengaju = User::where('username', 'user_pengaju')->firstOrFail();

    expect(Permission::where('nama_permission', 'lihat_pp')->exists())->toBeFalse()
        ->and(Permission::where('nama_permission', 'pp.monitoring_aktivitas')->exists())->toBeTrue();

    $verifPpkPerm = Permission::where('nama_permission', 'verifikasi.ppk_approve')->firstOrFail();
    $reviuPpkPerm = Permission::where('nama_permission', 'reviu.ppk')->firstOrFail();

    expect($verifPpkPerm->kategori_modul)->toBe('Verifikasi')
        ->and($reviuPpkPerm->kategori_modul)->toBe('Pengadaan');

    $roleAwalOnly = Role::create([
        'nama_role' => 'ppk_awal_custom',
        'label' => 'Penyetuju Usulan Awal PPK',
    ]);
    $roleAwalOnly->permissions()->attach($verifPpkPerm->id);

    $userAwalOnly = User::factory()->withRole($roleAwalOnly->nama_role)->create([
        'kode_unit' => $pengaju->kode_unit,
    ]);

    $this->actingAs($userAwalOnly)
        ->get(route('verifikasi.ppk'))
        ->assertOk();

    $this->actingAs($userAwalOnly)
        ->get(route('ppk.persetujuan-dokumen.index'))
        ->assertStatus(403);

    $roleDokumenOnly = Role::create([
        'nama_role' => 'ppk_dokumen_custom',
        'label' => 'Penyetuju Dokumen Paket PPK',
    ]);
    $roleDokumenOnly->permissions()->attach($reviuPpkPerm->id);

    $userDokumenOnly = User::factory()->withRole($roleDokumenOnly->nama_role)->create([
        'kode_unit' => $pengaju->kode_unit,
    ]);

    $this->actingAs($userDokumenOnly)
        ->get(route('ppk.persetujuan-dokumen.index'))
        ->assertOk();

    $this->actingAs($userDokumenOnly)
        ->get(route('verifikasi.ppk'))
        ->assertStatus(403);
});

it('prevents idor on permohonan detail, pdf download, and revision for view_own users while blocking unpermitted roles', function (): void {
    $pengaju = User::where('username', 'user_pengaju')->firstOrFail();
    $pengajuLain = User::factory()->withRole(User::ROLE_USER)->create([
        'kode_unit' => $pengaju->kode_unit,
    ]);
    $wadir = User::factory()->withRole(User::ROLE_WADIR_2)->create([
        'kode_unit' => $pengaju->kode_unit,
    ]);
    $perlengkapan = User::factory()->withRole(User::ROLE_PERLENGKAPAN)->create([
        'kode_unit' => $pengaju->kode_unit,
    ]);
    $keuangan = User::factory()->withRole(User::ROLE_KEUANGAN)->create([
        'kode_unit' => $pengaju->kode_unit,
    ]);

    $ownSurat = PermohonanPengadaan::create([
        'nomor_surat' => 'PNJ/001/OWN/2026',
        'perihal' => 'Pengadaan Printer Lab Milik Sendiri',
        'judul_pengadaan' => 'Pengadaan Printer Lab Milik Sendiri',
        'anggaran_diajukan' => 25000000,
        'estimasi_total_pagu' => 25000000,
        'status' => 'perlu_revisi',
        'status_permohonan' => 'perlu_revisi',
        'status_anggaran' => 'tersedia',
        'tanggal_pengajuan' => now(),
        'kode_unit' => $pengaju->kode_unit,
        'id_user' => $pengaju->id_user,
    ]);

    $otherSurat = PermohonanPengadaan::create([
        'nomor_surat' => 'B/123/BOM/BOOM',
        'perihal' => 'Pengadaan Alat Ukur Unit Lain',
        'judul_pengadaan' => 'Pengadaan Alat Ukur Unit Lain',
        'anggaran_diajukan' => 40000000,
        'estimasi_total_pagu' => 40000000,
        'status' => 'perlu_revisi',
        'status_permohonan' => 'perlu_revisi',
        'status_anggaran' => 'tersedia',
        'tanggal_pengajuan' => now(),
        'kode_unit' => $pengajuLain->kode_unit,
        'id_user' => $pengajuLain->id_user,
    ]);

    // Pengguna view_own hanya melihat suratnya di index dan ditolak (403) pada detail, PDF, serta revisi milik pengguna lain.
    $this->actingAs($pengaju)
        ->get(route('permohonan.index'))
        ->assertOk()
        ->assertSee('PNJ/001/OWN/2026')
        ->assertDontSee('B/123/BOM/BOOM');

    $this->actingAs($pengaju)
        ->get(route('permohonan.show', $ownSurat->nomor_surat))
        ->assertOk()
        ->assertSee('Pengadaan Printer Lab Milik Sendiri');

    $this->actingAs($pengaju)
        ->get('/permohonan/'.rawurlencode($otherSurat->nomor_surat))
        ->assertStatus(403);

    $this->actingAs($pengaju)
        ->get(route('dokumen.pdf', ['jenis' => 'permohonan', 'id' => $ownSurat->nomor_surat]))
        ->assertOk();

    $this->actingAs($pengaju)
        ->get(route('dokumen.pdf', ['jenis' => 'permohonan', 'id' => $otherSurat->nomor_surat]))
        ->assertStatus(403);

    $this->actingAs($pengaju)
        ->post(route('permohonan.revisi'), [
            'nomor_surat_lama' => $otherSurat->nomor_surat,
            'perihal' => 'Percobaan Ubah Surat Orang Lain',
            'anggaran_diajukan' => 35000000,
        ])
        ->assertStatus(403);

    $this->actingAs($pengaju)
        ->post(route('permohonan.revisi'), [
            'nomor_surat_lama' => $ownSurat->nomor_surat,
            'perihal' => 'Pengadaan Printer Lab Revisi 1',
            'anggaran_diajukan' => 24000000,
        ])
        ->assertRedirect(route('permohonan.index'));

    expect($ownSurat->fresh()->status_permohonan)->toBe(PermohonanPengadaan::STATUS_DIAJUKAN);

    // Pemegang permohonan.view_all tetap berhak melihat detail semua surat, sedangkan perlengkapan & keuangan ditolak 403.
    $this->actingAs($wadir)
        ->get(route('permohonan.show', $otherSurat->nomor_surat))
        ->assertOk();

    foreach ([$perlengkapan, $keuangan] as $roleTanpaAkses) {
        $this->actingAs($roleTanpaAkses)
            ->get(route('permohonan.index'))
            ->assertStatus(403);

        $this->actingAs($roleTanpaAkses)
            ->get(route('permohonan.show', $otherSurat->nomor_surat))
            ->assertStatus(403);
    }
});

it('protects paket, laporan, and pdf endpoints by rbac middleware and scopes dashboard statistics cleanly', function (): void {
    $pengaju = User::where('username', 'user_pengaju')->firstOrFail();
    $pengajuLain = User::factory()->withRole(User::ROLE_USER)->create([
        'kode_unit' => $pengaju->kode_unit,
    ]);
    $wadir = User::factory()->withRole(User::ROLE_WADIR_2)->create([
        'kode_unit' => $pengaju->kode_unit,
    ]);
    $ppbj = User::where('username', 'ppbj')->firstOrFail();
    $ppk1 = User::where('username', 'ppk_1')->firstOrFail();
    $ppk2 = User::factory()->withRole(User::ROLE_PPK)->create([
        'kode_unit' => $pengaju->kode_unit,
    ]);
    $perlengkapan = User::factory()->withRole(User::ROLE_PERLENGKAPAN)->create([
        'kode_unit' => $pengaju->kode_unit,
    ]);

    $ownSurat = PermohonanPengadaan::create([
        'nomor_surat' => 'STAT-OWN-001',
        'perihal' => 'Pengadaan Own',
        'judul_pengadaan' => 'Pengadaan Own',
        'anggaran_diajukan' => 15000000,
        'estimasi_total_pagu' => 15000000,
        'status' => 'disetujui',
        'status_permohonan' => 'disetujui',
        'status_anggaran' => 'tersedia',
        'tanggal_pengajuan' => now(),
        'kode_unit' => $pengaju->kode_unit,
        'id_user' => $pengaju->id_user,
    ]);

    PermohonanPengadaan::create([
        'nomor_surat' => 'STAT-ARCHIVED-001',
        'perihal' => 'Arsip Lama',
        'judul_pengadaan' => 'Arsip Lama',
        'anggaran_diajukan' => 10000000,
        'estimasi_total_pagu' => 10000000,
        'status' => 'digantikan_oleh_revisi',
        'status_permohonan' => 'digantikan_oleh_revisi',
        'status_anggaran' => 'tersedia',
        'tanggal_pengajuan' => now(),
        'kode_unit' => $pengaju->kode_unit,
        'id_user' => $pengaju->id_user,
    ]);

    $otherSurat = PermohonanPengadaan::create([
        'nomor_surat' => 'STAT-OTHER-002',
        'perihal' => 'Pengadaan Other',
        'judul_pengadaan' => 'Pengadaan Other',
        'anggaran_diajukan' => 250000000,
        'estimasi_total_pagu' => 250000000,
        'status' => 'disetujui',
        'status_permohonan' => 'disetujui',
        'status_anggaran' => 'tersedia',
        'tanggal_pengajuan' => now(),
        'kode_unit' => $pengajuLain->kode_unit,
        'id_user' => $pengajuLain->id_user,
    ]);

    $paket1 = PaketPengadaan::create([
        'nomor_paket' => 'PKT-RBAC-001',
        'nama_paket' => 'Paket PPK 1',
        'jenis_pengadaan' => 'barang',
        'nilai_hps' => 100000000,
        'nominal_harga_perkiraan' => 100000000,
        'alokasi_pagu_paket' => 100000000,
        'jalur_routing' => PaketPengadaan::ROUTING_PP,
        'status_paket' => 'terdaftar',
        'nomor_surat' => $ownSurat->nomor_surat,
        'tanggal_registrasi_paket' => now(),
        'id_user_ppbj' => $ppbj->id_user,
        'id_user_ppk' => $ppk1->id_user,
    ]);

    $paket2 = PaketPengadaan::create([
        'nomor_paket' => 'PKT-RBAC-002',
        'nama_paket' => 'Paket PPK 2',
        'jenis_pengadaan' => 'jasa',
        'nilai_hps' => 250000000,
        'nominal_harga_perkiraan' => 250000000,
        'alokasi_pagu_paket' => 250000000,
        'jalur_routing' => PaketPengadaan::ROUTING_PPK,
        'status_paket' => 'terdaftar',
        'nomor_surat' => $otherSurat->nomor_surat,
        'tanggal_registrasi_paket' => now(),
        'id_user_ppbj' => $ppbj->id_user,
        'id_user_ppk' => $ppk2->id_user,
    ]);

    // Statistik dashboard harus memisahkan total global (view_all) vs total milik sendiri (view_own / view_assigned).
    $expectedOwnPermohonan = PermohonanPengadaan::where('status_permohonan', '!=', 'digantikan_oleh_revisi')
        ->where('id_user', $pengaju->id_user)
        ->count();
    $expectedAllPermohonan = PermohonanPengadaan::where('status_permohonan', '!=', 'digantikan_oleh_revisi')
        ->count();
    $expectedOwnPaket = PaketPengadaan::whereHas('permohonan', fn ($q) => $q->where('id_user', $pengaju->id_user))->count();
    $expectedAllPaket = PaketPengadaan::count();
    $expectedPpk1Paket = PaketPengadaan::where('id_user_ppk', $ppk1->id_user)->count();

    expect($expectedOwnPermohonan)->toBeGreaterThanOrEqual(1)
        ->and($expectedAllPermohonan)->toBeGreaterThan($expectedOwnPermohonan)
        ->and($expectedOwnPaket)->toBeLessThan($expectedAllPaket);

    $this->actingAs($pengaju)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('stats', fn (array $stats): bool => $stats['total_permohonan'] === $expectedOwnPermohonan && $stats['total_paket'] === $expectedOwnPaket);

    $this->actingAs($perlengkapan)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('stats', fn (array $stats): bool => $stats['total_permohonan'] === 0 && $stats['total_paket'] === 0);

    $this->actingAs($wadir)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('stats', fn (array $stats): bool => $stats['total_permohonan'] === $expectedAllPermohonan);

    $this->actingAs($ppbj)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('stats', fn (array $stats): bool => $stats['total_permohonan'] === $expectedAllPermohonan && $stats['total_paket'] === $expectedAllPaket);

    $this->actingAs($ppk1)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('stats', fn (array $stats): bool => $stats['total_paket'] === $expectedPpk1Paket);

    // Akses /paket-pengadaan, /laporan, dan /dokumen-pdf terlindungi middleware RBAC serta validasi entitas.
    $this->actingAs($perlengkapan)
        ->get(route('paket.index'))
        ->assertStatus(403);

    $this->actingAs($pengaju)
        ->get(route('paket.index'))
        ->assertStatus(403);

    $this->actingAs($pengaju)
        ->get(route('paket.show', $paket1->nomor_paket))
        ->assertOk();

    $this->actingAs($pengaju)
        ->get(route('paket.show', $paket2->nomor_paket))
        ->assertStatus(403);

    $this->actingAs($ppbj)
        ->get(route('paket.index'))
        ->assertOk();

    $roleTanpaLaporan = Role::create([
        'nama_role' => 'tanpa_laporan',
        'label' => 'Tanpa Laporan',
    ]);
    $userTanpaLaporan = User::factory()->withRole($roleTanpaLaporan->nama_role)->create([
        'kode_unit' => $pengaju->kode_unit,
    ]);

    $this->actingAs($userTanpaLaporan)
        ->get(route('laporan.index'))
        ->assertStatus(403);

    $this->actingAs($perlengkapan)
        ->get(route('dokumen.pdf', ['jenis' => 'permohonan', 'id' => $ownSurat->nomor_surat]))
        ->assertStatus(403);

    $this->actingAs($ppbj)
        ->get(route('dokumen.pdf', ['jenis' => 'tidak_dikenal', 'id' => $paket1->nomor_paket]))
        ->assertStatus(404);
});

it('filters permohonan server-side across q, keyword, status, jenis, type, tanggal, and preserves query on pagination', function (): void {
    $pengaju = User::where('username', 'user_pengaju')->firstOrFail();

    for ($i = 1; $i <= 16; $i++) {
        PermohonanPengadaan::create([
            'nomor_surat' => sprintf('FLT-GEN-%03d', $i),
            'perihal' => "Pengadaan Meja Kursi Batch {$i}",
            'judul_pengadaan' => "Pengadaan Meja Kursi Batch {$i}",
            'jenis_pengadaan_split' => 'jasa',
            'anggaran_diajukan' => 10000000,
            'estimasi_total_pagu' => 10000000,
            'status' => 'disetujui',
            'status_permohonan' => 'disetujui',
            'status_anggaran' => 'tersedia',
            'tanggal_pengajuan' => '2026-04-01 08:00:00',
            'kode_unit' => $pengaju->kode_unit,
            'id_user' => $pengaju->id_user,
        ]);
    }

    PermohonanPengadaan::create([
        'nomor_surat' => 'FLT-TARGET-017',
        'perihal' => 'Pengadaan Server Rack Lab Komputasi',
        'judul_pengadaan' => 'Pengadaan Server Rack Lab Komputasi',
        'tujuan_pengadaan_ringkas' => 'Beli perangkat jaringan dan rak server baru',
        'jenis_pengadaan_split' => 'barang',
        'anggaran_diajukan' => 125000000,
        'estimasi_total_pagu' => 125000000,
        'status' => 'diajukan',
        'status_permohonan' => 'diajukan',
        'status_anggaran' => 'pending',
        'tanggal_pengajuan' => '2026-03-15 10:30:00',
        'kode_unit' => $pengaju->kode_unit,
        'id_user' => $pengaju->id_user,
    ]);

    AnggaranMAK::create([
        'kode_mak' => 'MAK-FLT-01',
        'uraian_mak' => 'Belanja Infrastruktur Jaringan',
        'sumber_dana' => 'RM',
        'tahun_anggaran' => 2026,
        'pagu_anggaran' => 200000000,
        'sisa_pagu_mak' => 105000000,
        'is_locked' => false,
    ]);

    PermohonanPengadaan::create([
        'nomor_surat' => 'FLT-PROSES-018',
        'perihal' => 'Pengadaan Switch Core Lab',
        'judul_pengadaan' => 'Pengadaan Switch Core Lab',
        'tujuan_pengadaan_ringkas' => 'Pengadaan perangkat jaringan gedung kuliah',
        'jenis_pengadaan_split' => 'barang',
        'anggaran_diajukan' => 95000000,
        'estimasi_total_pagu' => 95000000,
        'status' => 'menunggu_acc_wadir_ppk',
        'status_permohonan' => 'menunggu_acc_wadir_ppk',
        'status_anggaran' => 'tersedia',
        'kode_mak' => 'MAK-FLT-01',
        'tanggal_pengajuan' => '2026-03-16 09:00:00',
        'kode_unit' => $pengaju->kode_unit,
        'id_user' => $pengaju->id_user,
    ]);

    // Pencarian q dan keyword case-insensitive tetap menemukan data meski berada di balik halaman paginasi.
    $this->actingAs($pengaju)
        ->get(route('permohonan.index', ['q' => 'server rack']))
        ->assertOk()
        ->assertSee('FLT-TARGET-017')
        ->assertDontSee('FLT-GEN-001');

    $this->actingAs($pengaju)
        ->get(route('permohonan.index', ['q' => 'beli']))
        ->assertOk()
        ->assertSee('FLT-TARGET-017')
        ->assertDontSee('FLT-GEN-001');

    $this->actingAs($pengaju)
        ->get(route('permohonan.index', ['keyword' => 'SERVER RACK']))
        ->assertOk()
        ->assertSee('FLT-TARGET-017')
        ->assertDontSee('FLT-GEN-001');

    // Usulan baru yang belum ditetapkan pos anggarannya masuk filter Diajukan dan tidak masuk Dalam Proses.
    $this->actingAs($pengaju)
        ->get(route('permohonan.index', ['status' => 'diajukan']))
        ->assertOk()
        ->assertSee('FLT-TARGET-017')
        ->assertDontSee('FLT-PROSES-018')
        ->assertDontSee('FLT-GEN-001');

    // Filter status "Dalam Proses" (reviu_paralel) hanya menampilkan usulan yang sudah masuk proses penetapan anggaran/persetujuan.
    $this->actingAs($pengaju)
        ->get(route('permohonan.index', ['status' => 'reviu_paralel']))
        ->assertOk()
        ->assertSee('FLT-PROSES-018')
        ->assertDontSee('FLT-TARGET-017')
        ->assertDontSee('FLT-GEN-001');

    // Filter jenis dan type sama-sama berfungsi, beserta filter tanggal.
    $this->actingAs($pengaju)
        ->get(route('permohonan.index', ['jenis' => 'barang']))
        ->assertOk()
        ->assertSee('FLT-TARGET-017')
        ->assertDontSee('FLT-GEN-001');

    $this->actingAs($pengaju)
        ->get(route('permohonan.index', ['type' => 'barang']))
        ->assertOk()
        ->assertSee('FLT-TARGET-017')
        ->assertDontSee('FLT-GEN-001');

    $this->actingAs($pengaju)
        ->get(route('permohonan.index', ['tanggal' => '2026-03-15']))
        ->assertOk()
        ->assertSee('FLT-TARGET-017')
        ->assertDontSee('FLT-GEN-001');

    // Paginasi menjaga query string agar pindah ke halaman 2 tetap mempertahankan parameter filter aktif.
    $this->actingAs($pengaju)
        ->get(route('permohonan.index', ['status' => 'disetujui']))
        ->assertOk()
        ->assertSee('status=disetujui');
});

it('allows pengaju with permohonan.revisi to revise nomor_surat after wadir 2 revision without null id_user on verifikasi_paralel', function (): void {
    $pengaju = User::where('username', 'user_pengaju')->firstOrFail();
    $perencanaan = User::factory()->withRole(User::ROLE_PERENCANAAN)->create([
        'kode_unit' => $pengaju->kode_unit,
    ]);
    $wadir = User::factory()->withRole(User::ROLE_WADIR_2)->create([
        'kode_unit' => $pengaju->kode_unit,
    ]);
    $ppk = User::where('username', 'ppk_1')->firstOrFail();

    AnggaranMAK::create([
        'kode_mak' => 'MAK-REV-001',
        'uraian_mak' => 'Belanja Lab Komputasi',
        'sumber_dana' => 'RM',
        'tahun_anggaran' => 2026,
        'pagu_anggaran' => 500000000,
        'sisa_pagu_mak' => 325000000,
        'is_locked' => false,
    ]);

    $induk = PermohonanPengadaan::create([
        'nomor_surat' => 'B/PA-REV/001',
        'perihal' => 'Pengadaan Perangkat Lab',
        'judul_pengadaan' => 'Pengadaan Perangkat Lab',
        'tujuan_pengadaan_ringkas' => 'Membeli Laptop',
        'anggaran_diajukan' => 175000000,
        'estimasi_total_pagu' => 175000000,
        'status' => 'perlu_revisi',
        'status_permohonan' => 'perlu_revisi',
        'status_anggaran' => 'tersedia',
        'tanggal_pengajuan' => now(),
        'kode_unit' => $pengaju->kode_unit,
        'id_user' => $pengaju->id_user,
        'kode_mak' => 'MAK-REV-001',
        'id_user_ppk_awal' => $ppk->id_user,
        'nomor_memo_ketersediaan_mak' => 'MEMO-PRC/MAK/2026/609',
        'catatan_memo_ketersediaan_mak' => 'Tersedia',
    ]);

    AlokasiMAK::create([
        'nomor_surat' => $induk->nomor_surat,
        'kode_mak' => 'MAK-REV-001',
        'nominal_alokasi' => 175000000,
        'tanggal_alokasi' => now(),
    ]);

    VerifikasiParalel::create([
        'id_user' => $perencanaan->id_user,
        'nomor_surat' => $induk->nomor_surat,
        'role_verifikator' => VerifikasiParalel::ROLE_PERENCANAAN,
        'status_keputusan' => VerifikasiParalel::STATUS_DISETUJUI,
        'tanggal_keputusan' => now(),
    ]);

    VerifikasiParalel::create([
        'id_user' => $wadir->id_user,
        'nomor_surat' => $induk->nomor_surat,
        'role_verifikator' => VerifikasiParalel::ROLE_WADIR_2,
        'status_keputusan' => VerifikasiParalel::STATUS_REVISI,
        'catatan_alasan' => 'Sesuaikan nomor dan nominal',
        'tanggal_keputusan' => now(),
    ]);

    // Simulasikan PPK sudah menyetujui sebelumnya sehingga wajib tetap diwariskan saat revisi dikirim.
    VerifikasiParalel::create([
        'id_user' => $ppk->id_user,
        'nomor_surat' => $induk->nomor_surat,
        'role_verifikator' => VerifikasiParalel::ROLE_PPK,
        'status_keputusan' => VerifikasiParalel::STATUS_DISETUJUI,
        'nomor_memo' => 'MEMO-PPK/2026/01',
        'tanggal_keputusan' => now(),
    ]);

    $this->actingAs($pengaju)
        ->post(route('permohonan.revisi'), [
            'nomor_surat_lama' => 'B/PA-REV/001',
            'nomor_surat_baru' => '010/PL4.TIK/PPBJ/2026',
            'perihal' => 'Beli Peralatan Lab Revisi',
            'tujuan_pengadaan_ringkas' => 'Membeli Laptop Baru',
            'anggaran_diajukan' => 430000000,
            'keterangan_revisi' => 'Pembaruan nomor pengajuan dan anggaran',
        ])
        ->assertRedirect(route('permohonan.index'))
        ->assertSessionHas('success');

    $revisiBaru = PermohonanPengadaan::where('nomor_surat', '010/PL4.TIK/PPBJ/2026')->firstOrFail();

    expect($induk->fresh()->status_permohonan)->toBe('digantikan_oleh_revisi')
        ->and($induk->fresh()->kode_mak)->toBeNull()
        ->and($revisiBaru->status_permohonan)->toBe(PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN)
        ->and($revisiBaru->nomor_surat_revisi)->toBe('B/PA-REV/001')
        ->and((int) $revisiBaru->revisi_ke)->toBe(1)
        ->and((float) AnggaranMAK::findOrFail('MAK-REV-001')->sisa_pagu_mak)->toBe(70000000.0)
        ->and(AlokasiMAK::where('nomor_surat', '010/PL4.TIK/PPBJ/2026')->where('nominal_alokasi', 430000000)->exists())->toBeTrue();

    $vpPerencanaanBaru = VerifikasiParalel::where('nomor_surat', '010/PL4.TIK/PPBJ/2026')
        ->where('role_verifikator', VerifikasiParalel::ROLE_PERENCANAAN)
        ->firstOrFail();
    $vpWadirBaru = VerifikasiParalel::where('nomor_surat', '010/PL4.TIK/PPBJ/2026')
        ->where('role_verifikator', VerifikasiParalel::ROLE_WADIR_2)
        ->firstOrFail();
    $vpPpkBaru = VerifikasiParalel::where('nomor_surat', '010/PL4.TIK/PPBJ/2026')
        ->where('role_verifikator', VerifikasiParalel::ROLE_PPK)
        ->firstOrFail();

    expect((int) $vpPerencanaanBaru->id_user)->toBe((int) $perencanaan->id_user)
        ->and($vpPerencanaanBaru->status_keputusan)->toBe(VerifikasiParalel::STATUS_MENUNGGU)
        ->and((int) $vpWadirBaru->id_user)->toBe((int) $wadir->id_user)
        ->and($vpWadirBaru->status_keputusan)->toBe(VerifikasiParalel::STATUS_MENUNGGU)
        ->and($vpWadirBaru->catatan_alasan)->toBeNull()
        ->and((int) $vpPpkBaru->id_user)->toBe((int) $ppk->id_user)
        ->and($vpPpkBaru->status_keputusan)->toBe(VerifikasiParalel::STATUS_DISETUJUI)
        ->and($vpPpkBaru->nomor_memo)->toBe('MEMO-PPK/2026/01');
});

it('shows only real uploaded documents without generated surat srikandi pdf on ppk document review and packet detail', function (): void {
    $pengaju = User::where('username', 'user_pengaju')->firstOrFail();
    $ppbj = User::where('username', 'ppbj')->firstOrFail();
    $ppk = User::where('username', 'ppk_1')->firstOrFail();

    $permohonanWithFiles = PermohonanPengadaan::create([
        'nomor_surat' => 'DOC-REAL-001',
        'perihal' => 'Pengadaan Peralatan Lab',
        'judul_pengadaan' => 'Pengadaan Peralatan Lab',
        'anggaran_diajukan' => 430000000,
        'estimasi_total_pagu' => 430000000,
        'status' => 'diproses_ppbj',
        'status_permohonan' => 'diproses_ppbj',
        'status_anggaran' => 'tersedia',
        'tanggal_pengajuan' => now(),
        'kode_unit' => $pengaju->kode_unit,
        'id_user' => $pengaju->id_user,
        'file_permohonan' => 'lampiran_permohonan/asli-surat.pdf',
        'dokumen_lampiran' => [
            [
                'name' => 'Rab_Lab_Asli.pdf',
                'path' => 'lampiran_permohonan/asli-surat.pdf',
                'url' => asset('storage/lampiran_permohonan/asli-surat.pdf'),
                'type' => 'PDF',
                'size' => '1.2 MB',
                'label' => 'Rab_Lab_Asli.pdf',
            ],
        ],
    ]);

    $paketWithFiles = PaketPengadaan::create([
        'nomor_paket' => 'PKT-DOC-REAL-001',
        'nama_paket' => 'Paket Lab Real Docs',
        'jenis_pengadaan' => 'barang',
        'nilai_hps' => 430000000,
        'nominal_harga_perkiraan' => 430000000,
        'alokasi_pagu_paket' => 430000000,
        'jalur_routing' => PaketPengadaan::ROUTING_PPK,
        'status_paket' => PaketPengadaan::STATUS_MENUNGGU_APPROVAL_PPK,
        'nomor_surat' => $permohonanWithFiles->nomor_surat,
        'tanggal_registrasi_paket' => now(),
        'id_user_ppbj' => $ppbj->id_user,
        'id_user_ppk' => $ppk->id_user,
    ]);

    $this->actingAs($ppk)
        ->get(route('ppk.persetujuan-dokumen.index'))
        ->assertOk()
        ->assertSee('Rab_Lab_Asli.pdf')
        ->assertDontSee('Surat Srikandi (PDF)')
        ->assertDontSee('/dokumen-pdf/permohonan/DOC-REAL-001');

    $this->actingAs($ppk)
        ->get(route('paket.show', $paketWithFiles->nomor_paket))
        ->assertOk()
        ->assertSee('Rab_Lab_Asli.pdf')
        ->assertDontSee('/dokumen-pdf/permohonan/DOC-REAL-001');

    $permohonanNoFiles = PermohonanPengadaan::create([
        'nomor_surat' => 'DOC-EMPTY-002',
        'perihal' => 'Pengadaan Tanpa Lampiran',
        'judul_pengadaan' => 'Pengadaan Tanpa Lampiran',
        'anggaran_diajukan' => 300000000,
        'estimasi_total_pagu' => 300000000,
        'status' => 'diproses_ppbj',
        'status_permohonan' => 'diproses_ppbj',
        'status_anggaran' => 'tersedia',
        'tanggal_pengajuan' => now(),
        'kode_unit' => $pengaju->kode_unit,
        'id_user' => $pengaju->id_user,
        'file_permohonan' => 'permohonan/dummy.pdf',
    ]);

    $paketNoFiles = PaketPengadaan::create([
        'nomor_paket' => 'PKT-DOC-EMPTY-002',
        'nama_paket' => 'Paket Tanpa Upload',
        'jenis_pengadaan' => 'barang',
        'nilai_hps' => 300000000,
        'nominal_harga_perkiraan' => 300000000,
        'alokasi_pagu_paket' => 300000000,
        'jalur_routing' => PaketPengadaan::ROUTING_PPK,
        'status_paket' => PaketPengadaan::STATUS_MENUNGGU_APPROVAL_PPK,
        'nomor_surat' => $permohonanNoFiles->nomor_surat,
        'tanggal_registrasi_paket' => now(),
        'id_user_ppbj' => $ppbj->id_user,
        'id_user_ppk' => $ppk->id_user,
    ]);

    $this->actingAs($ppk)
        ->get(route('ppk.persetujuan-dokumen.index'))
        ->assertOk()
        ->assertSee('PKT-DOC-EMPTY-002')
        ->assertSee('Belum ada berkas yang diunggah.')
        ->assertDontSee('Surat Srikandi (PDF)');

    $this->actingAs($ppk)
        ->get(route('paket.show', $paketNoFiles->nomor_paket))
        ->assertOk()
        ->assertSee('Belum ada berkas yang diunggah.')
        ->assertDontSee('/dokumen-pdf/permohonan/DOC-EMPTY-002');
});

it('shows all pending ppk document approvals including belanja langsung in dashboard perlu tindakan', function (): void {
    $pengaju = User::where('username', 'user_pengaju')->firstOrFail();
    $ppbj = User::where('username', 'ppbj')->firstOrFail();
    $ppk = User::where('username', 'ppk_1')->firstOrFail();

    $permohonan = PermohonanPengadaan::create([
        'nomor_surat' => 'DSH-PPK-BL-001',
        'perihal' => 'Pengadaan Mouse Lab Komputer',
        'judul_pengadaan' => 'Pengadaan Mouse Lab Komputer',
        'anggaran_diajukan' => 25000000,
        'estimasi_total_pagu' => 25000000,
        'status' => 'diproses_ppbj',
        'status_permohonan' => 'diproses_ppbj',
        'status_anggaran' => 'tersedia',
        'tanggal_pengajuan' => now(),
        'kode_unit' => $pengaju->kode_unit,
        'id_user' => $pengaju->id_user,
    ]);

    PaketPengadaan::create([
        'nomor_paket' => 'PKT-DSH-BL-001',
        'nama_paket' => 'Paket Belanja Langsung Mouse Lab',
        'jenis_pengadaan' => 'barang',
        'nilai_hps' => 25000000,
        'nominal_harga_perkiraan' => 25000000,
        'alokasi_pagu_paket' => 25000000,
        'jalur_routing' => PaketPengadaan::ROUTING_LANGSUNG,
        'status_paket' => PaketPengadaan::STATUS_MENUNGGU_APPROVAL_PPK,
        'nomor_surat' => $permohonan->nomor_surat,
        'tanggal_registrasi_paket' => now(),
        'id_user_ppbj' => $ppbj->id_user,
        'id_user_ppk' => $ppk->id_user,
    ]);

    $this->actingAs($ppk)
        ->get(route('ppk.persetujuan-dokumen.index'))
        ->assertOk()
        ->assertSee('Paket Belanja Langsung Mouse Lab');

    $this->actingAs($ppk)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Paket Belanja Langsung Mouse Lab')
        ->assertViewHas('stats', fn (array $stats): bool => ($stats['antrean_dokumen_pengadaan'] ?? 0) >= 1);
});

it('refunds efficiency savings to mak when belanja langsung realized price is below nilai_hps and blocks price above nilai_hps', function (): void {
    $pengaju = User::where('username', 'user_pengaju')->firstOrFail();
    $ppbj = User::where('username', 'ppbj')->firstOrFail();
    $ppk = User::where('username', 'ppk_1')->firstOrFail();

    AnggaranMAK::create([
        'kode_mak' => 'MAK-BL-EFISIENSI',
        'uraian_mak' => 'Belanja ATK Laboratorium',
        'sumber_dana' => 'RM',
        'tahun_anggaran' => 2026,
        'pagu_anggaran' => 100000000,
        'sisa_pagu_mak' => 70000000,
        'is_locked' => false,
    ]);

    $permohonan = PermohonanPengadaan::create([
        'nomor_surat' => 'BL-EF-001',
        'perihal' => 'Belanja ATK Jurusan',
        'judul_pengadaan' => 'Belanja ATK Jurusan',
        'anggaran_diajukan' => 30000000,
        'estimasi_total_pagu' => 30000000,
        'status' => 'diproses_ppbj',
        'status_permohonan' => 'diproses_ppbj',
        'status_anggaran' => 'tersedia',
        'tanggal_pengajuan' => now(),
        'kode_unit' => $pengaju->kode_unit,
        'id_user' => $pengaju->id_user,
        'kode_mak' => 'MAK-BL-EFISIENSI',
    ]);

    AlokasiMAK::create([
        'nomor_surat' => $permohonan->nomor_surat,
        'kode_mak' => 'MAK-BL-EFISIENSI',
        'nominal_alokasi' => 30000000,
        'tanggal_alokasi' => now(),
    ]);

    $paket = PaketPengadaan::create([
        'nomor_paket' => 'PKT-BL-EF-001',
        'nama_paket' => 'Paket Belanja ATK Jurusan',
        'jenis_pengadaan' => 'barang',
        'nilai_hps' => 30000000,
        'nominal_harga_perkiraan' => 30000000,
        'alokasi_pagu_paket' => 30000000,
        'jalur_routing' => PaketPengadaan::ROUTING_LANGSUNG,
        'status_paket' => PaketPengadaan::STATUS_SIAP_UPLOAD_DOKUMEN_PPBJ,
        'nomor_surat' => $permohonan->nomor_surat,
        'tanggal_registrasi_paket' => now(),
        'id_user_ppbj' => $ppbj->id_user,
        'id_user_ppk' => $ppk->id_user,
    ]);

    // Harga realisasi melebihi Perkiraan Harga (nilai_hps) atau batas Rp 50 Juta harus ditolak.
    $this->actingAs($ppbj)
        ->from(route('paket.index'))
        ->post(route('paket.belanja-langsung'), [
            'nomor_paket' => $paket->nomor_paket,
            'nomor_kwitansi_belanja' => 'KWT/OVER/001',
            'nominal_harga_akhir' => 35000000,
        ])
        ->assertSessionHasErrors('nominal_harga_akhir');

    expect((float) AnggaranMAK::findOrFail('MAK-BL-EFISIENSI')->sisa_pagu_mak)->toBe(70000000.0);

    // Harga realisasi lebih rendah (Rp 22 Juta) harus mengembalikan selisih efisiensi Rp 8 Juta ke sisa MAK.
    $this->actingAs($ppbj)
        ->post(route('paket.belanja-langsung'), [
            'nomor_paket' => $paket->nomor_paket,
            'nomor_kwitansi_belanja' => 'KWT/OK/001',
            'nominal_harga_akhir' => 22000000,
            'nama_vendor' => 'Toko ATK Mitra',
        ])
        ->assertRedirect(route('paket.index'))
        ->assertSessionHas('success');

    $alokasi = AlokasiMAK::where('nomor_surat', $permohonan->nomor_surat)->firstOrFail();

    expect((float) AnggaranMAK::findOrFail('MAK-BL-EFISIENSI')->sisa_pagu_mak)->toBe(78000000.0)
        ->and((float) $alokasi->nominal_alokasi)->toBe(22000000.0)
        ->and((float) $permohonan->fresh()->estimasi_total_pagu)->toBe(22000000.0)
        ->and((float) $paket->fresh()->nilai_hps)->toBe(22000000.0)
        ->and((float) $paket->fresh()->nominal_harga_akhir)->toBe(22000000.0)
        ->and($paket->fresh()->status_paket)->toBe(PaketPengadaan::STATUS_BELANJA_LANGSUNG_SELESAI);
});

it('blocks idor and mid-process delete by regular user while allowing owner deletion only at diajukan or selesai with full cascade', function (): void {
    Storage::fake('public');

    $userA = User::where('username', 'user_pengaju')->firstOrFail();
    $userB = User::factory()->withRole(User::ROLE_USER)->create([
        'username' => 'baim_test',
        'kode_unit' => $userA->kode_unit,
    ]);
    $admin = User::factory()->withRole(User::ROLE_ADMIN)->create([
        'kode_unit' => $userA->kode_unit,
    ]);
    $perencanaan = User::factory()->withRole(User::ROLE_PERENCANAAN)->create([
        'kode_unit' => $userA->kode_unit,
    ]);
    $ppbj = User::where('username', 'ppbj')->firstOrFail();
    $ppk = User::where('username', 'ppk_1')->firstOrFail();
    $perlengkapan = User::factory()->withRole(User::ROLE_PERLENGKAPAN)->create([
        'kode_unit' => $userA->kode_unit,
    ]);
    $keuangan = User::factory()->withRole(User::ROLE_KEUANGAN)->create([
        'kode_unit' => $userA->kode_unit,
    ]);

    // Role user biasa tidak boleh memegang permohonan.delete; hak hapus global hanya milik admin.
    expect($userA->hasPermission('permohonan.delete'))->toBeFalse()
        ->and($userA->hasPermission('permohonan.create'))->toBeTrue()
        ->and($admin->hasPermission('permohonan.delete'))->toBeTrue();

    $milikBaim = PermohonanPengadaan::create([
        'nomor_surat' => 'B/PA-10/99',
        'perihal' => 'Pengadaan Laptop Milik Baim',
        'judul_pengadaan' => 'Pengadaan Laptop Milik Baim',
        'anggaran_diajukan' => 75000000,
        'estimasi_total_pagu' => 75000000,
        'status' => 'diajukan',
        'status_permohonan' => 'diajukan',
        'tanggal_pengajuan' => now(),
        'kode_unit' => $userB->kode_unit,
        'id_user' => $userB->id_user,
    ]);

    // Percobaan IDOR lihat detail maupun DELETE atas surat pengguna lain wajib ditolak HTTP 403.
    $this->actingAs($userA)
        ->get(route('permohonan.show', $milikBaim->nomor_surat))
        ->assertStatus(403)
        ->assertSee('Akses Ditolak')
        ->assertSee('Halaman ini tidak dapat diakses. Anda hanya dapat melihat detail permohonan yang Anda ajukan sendiri.');

    $this->actingAs($userA)
        ->delete(route('permohonan.destroy', $milikBaim->nomor_surat))
        ->assertStatus(403)
        ->assertSee('Akses Ditolak')
        ->assertSee('Halaman ini tidak dapat diakses. Anda hanya dapat membatalkan permohonan milik sendiri.');

    $this->actingAs($userA)
        ->deleteJson(route('permohonan.destroy', $milikBaim->nomor_surat))
        ->assertStatus(403)
        ->assertJsonPath('message', 'Akses Ditolak: Anda hanya dapat membatalkan permohonan milik sendiri.');

    expect(PermohonanPengadaan::where('nomor_surat', 'B/PA-10/99')->exists())->toBeTrue();

    // User biasa boleh membatalkan permohonannya sendiri yang baru diajukan (belum ditetapkan pos anggaran), meski baris tunggu awal sudah tercatat.
    Storage::disk('public')->put('permohonan/surat-diajukan.pdf', 'pdf');
    $this->actingAs($userA)
        ->post(route('permohonan.store'), [
            'nomor_surat' => '011/PL4.TIK/2026',
            'perihal' => 'Usulan Baru Pra MAK',
            'anggaran_diajukan' => 30000000,
            'kode_unit' => $userA->kode_unit,
        ])
        ->assertRedirect(route('permohonan.index'));

    $draftDiajukan = PermohonanPengadaan::where('nomor_surat', '011/PL4.TIK/2026')->firstOrFail();
    expect($draftDiajukan->status_permohonan)->toBe(PermohonanPengadaan::STATUS_DIAJUKAN)
        ->and($draftDiajukan->canBeDeletedByOwner())->toBeTrue();

    $this->actingAs($userA)
        ->get(route('permohonan.index'))
        ->assertOk()
        ->assertSee('id="modal-delete-permohonan"', false)
        ->assertSee('max-w-[596px]', false)
        ->assertSee('rounded-[12px] border border-[#dfe6ef]', false)
        ->assertSee('bg-[#f4f7fb]', false)
        ->assertSee('bg-[#dc2626]', false)
        ->assertDontSee('border-t-[5px] border-rose-600', false)
        ->assertSee('Hapus Pengajuan?');

    $this->actingAs($userA)
        ->get(route('permohonan.show', $draftDiajukan->nomor_surat))
        ->assertOk()
        ->assertSee('Diajukan')
        ->assertSee('Batalkan Usulan');

    $this->actingAs($userA)
        ->delete(route('permohonan.destroy', $draftDiajukan->nomor_surat))
        ->assertRedirect(route('permohonan.index'))
        ->assertSessionHas('success');

    expect(PermohonanPengadaan::where('nomor_surat', '011/PL4.TIK/2026')->exists())->toBeFalse()
        ->and(VerifikasiParalel::where('nomor_surat', '011/PL4.TIK/2026')->exists())->toBeFalse();

    // Admin dengan permohonan.delete dapat menghapus permohonan milik siapa pun pada tahap mana pun.
    $this->actingAs($admin)
        ->delete(route('permohonan.destroy', $milikBaim->nomor_surat))
        ->assertRedirect(route('permohonan.index'))
        ->assertSessionHas('success');

    expect(PermohonanPengadaan::where('nomor_surat', 'B/PA-10/99')->exists())->toBeFalse();

    // User biasa menghapus permohonannya sendiri yang sudah berjalan: ditolak saat proses masih berjalan, baru diizinkan kembali saat status Selesai.
    Storage::disk('public')->put('permohonan/surat-own.pdf', 'pdf');
    Storage::disk('public')->put('memo/cek-mak.pdf', 'pdf');
    Storage::disk('public')->put('memo/vp-wadir.pdf', 'pdf');
    Storage::disk('public')->put('paket/dokumen-pengadaan.pdf', 'pdf');
    Storage::disk('public')->put('spk/kontrak-own.pdf', 'pdf');
    Storage::disk('public')->put('bast/bast-signed.pdf', 'pdf');
    Storage::disk('public')->put('bast/foto-barang.jpg', 'img');
    Storage::disk('public')->put('pembayaran/spp-own.pdf', 'pdf');
    Storage::disk('public')->put('memo/bayar-ppk.pdf', 'pdf');
    Storage::disk('public')->put('pencairan/sp2d-own.pdf', 'pdf');

    AnggaranMAK::create([
        'kode_mak' => 'MAK-CASCADE-01',
        'uraian_mak' => 'Belanja Peralatan Laboratorium',
        'sumber_dana' => 'RM',
        'tahun_anggaran' => 2026,
        'pagu_anggaran' => 500000000,
        'sisa_pagu_mak' => 350000000,
        'is_locked' => false,
    ]);

    $milikSendiri = PermohonanPengadaan::create([
        'nomor_surat' => '015/PL4.TIK/2026',
        'perihal' => 'Pengadaan Perangkat Praktikum',
        'judul_pengadaan' => 'Pengadaan Perangkat Praktikum',
        'anggaran_diajukan' => 150000000,
        'estimasi_total_pagu' => 150000000,
        'status' => PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK,
        'status_permohonan' => PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK,
        'status_anggaran' => 'tersedia',
        'tanggal_pengajuan' => now(),
        'kode_unit' => $userA->kode_unit,
        'id_user' => $userA->id_user,
        'kode_mak' => 'MAK-CASCADE-01',
        'file_permohonan' => 'permohonan/surat-own.pdf',
        'file_memo_cek_mak' => 'memo/cek-mak.pdf',
    ]);

    $anakRevisi = PermohonanPengadaan::create([
        'nomor_surat' => '015.REV/PL4.TIK/2026',
        'perihal' => 'Turunan Revisi',
        'judul_pengadaan' => 'Turunan Revisi',
        'anggaran_diajukan' => 150000000,
        'estimasi_total_pagu' => 150000000,
        'status' => 'diajukan',
        'status_permohonan' => 'diajukan',
        'tanggal_pengajuan' => now(),
        'kode_unit' => $userA->kode_unit,
        'id_user' => $userA->id_user,
        'nomor_surat_revisi' => $milikSendiri->nomor_surat,
    ]);

    AlokasiMAK::create([
        'nomor_surat' => $milikSendiri->nomor_surat,
        'kode_mak' => 'MAK-CASCADE-01',
        'nominal_alokasi' => 150000000,
        'tanggal_alokasi' => now(),
    ]);

    VerifikasiParalel::create([
        'id_user' => $perencanaan->id_user,
        'nomor_surat' => $milikSendiri->nomor_surat,
        'role_verifikator' => VerifikasiParalel::ROLE_PERENCANAAN,
        'status_keputusan' => VerifikasiParalel::STATUS_DISETUJUI,
        'file_memo' => 'memo/vp-wadir.pdf',
        'tanggal_keputusan' => now(),
    ]);

    // Saat sudah ditetapkan MAK / masih dalam proses verifikasi, tombol hapus tersembunyi dan DELETE ditolak 403.
    $this->actingAs($userA)
        ->get(route('permohonan.show', $milikSendiri->nomor_surat))
        ->assertOk()
        ->assertDontSee('Batalkan Usulan');

    $this->actingAs($userA)
        ->delete(route('permohonan.destroy', $milikSendiri->nomor_surat))
        ->assertStatus(403)
        ->assertSee('Permohonan yang telah diverifikasi atau diproses paket tidak dapat dibatalkan.');

    expect(PermohonanPengadaan::where('nomor_surat', '015/PL4.TIK/2026')->exists())->toBeTrue();

    // Status menengah lain seperti pending_anggaran, perlu_revisi, ditolak, disetujui, dan diproses_ppbj juga dilarang dihapus user biasa.
    foreach ([PermohonanPengadaan::STATUS_PENDING_ANGGARAN, 'perlu_revisi', PermohonanPengadaan::STATUS_DITOLAK, PermohonanPengadaan::STATUS_DISETUJUI, PermohonanPengadaan::STATUS_DIPROSES_PPBJ] as $blockedStatus) {
        $milikSendiri->update(['status_permohonan' => $blockedStatus]);
        $this->actingAs($userA)
            ->deleteJson(route('permohonan.destroy', $milikSendiri->nomor_surat))
            ->assertStatus(403)
            ->assertJsonPath('message', 'Akses Ditolak: Permohonan yang telah diverifikasi atau diproses paket tidak dapat dibatalkan.');
    }
    $milikSendiri->update(['status_permohonan' => PermohonanPengadaan::STATUS_DIPROSES_PPBJ]);

    $paket = PaketPengadaan::create([
        'nomor_paket' => 'PKT-CASC-001',
        'nama_paket' => 'Paket Perangkat Praktikum',
        'jenis_pengadaan' => 'barang',
        'jalur_routing' => PaketPengadaan::ROUTING_PP,
        'nilai_hps' => 150000000,
        'nominal_harga_perkiraan' => 150000000,
        'alokasi_pagu_paket' => 150000000,
        'status_paket' => 'kontrak_aktif',
        'nomor_surat' => $milikSendiri->nomor_surat,
        'tanggal_registrasi_paket' => now(),
        'id_user_ppbj' => $ppbj->id_user,
        'id_user_ppk' => $ppk->id_user,
        'file_dokumen_pengadaan' => 'paket/dokumen-pengadaan.pdf',
    ]);

    ReviuDokumen::create([
        'nomor_paket' => $paket->nomor_paket,
        'id_user' => $ppk->id_user,
        'putaran_ke' => 1,
        'role_reviewer' => 'ppk',
        'status_reviu' => ReviuDokumen::STATUS_DISETUJUI,
        'tanggal_reviu' => now(),
    ]);

    KontrakSPK::create([
        'nomor_spk' => 'SPK-CASC-001',
        'nomor_paket' => $paket->nomor_paket,
        'id_user' => $ppbj->id_user,
        'tipe_dokumen' => KontrakSPK::DOKUMEN_SPK,
        'sifat_kontrak' => KontrakSPK::TIPE_LUMPSUM,
        'status_spk' => KontrakSPK::STATUS_AKTIF,
        'tanggal_spk' => now(),
        'jadwal_pengiriman' => now()->addDays(14),
        'nilai_kontrak' => 150000000,
        'file_spk' => 'spk/kontrak-own.pdf',
    ]);

    $bast = PenerimaanBarang::create([
        'nomor_bast' => 'BAST-CASC-001',
        'nomor_paket' => $paket->nomor_paket,
        'id_user' => $perlengkapan->id_user,
        'tanggal_penerimaan' => now(),
        'status_fisik' => PenerimaanBarang::STATUS_DITERIMA_LENGKAP,
        'kategori_belanja' => PenerimaanBarang::KATEGORI_BELANJA_MODAL,
        'file_bast_signed' => 'bast/bast-signed.pdf',
    ]);

    DokumentasiPenerimaanBarang::create([
        'id_penerimaan' => $bast->id_penerimaan,
        'foto_dokumentasi' => 'bast/foto-barang.jpg',
        'keterangan_foto' => 'Foto serah terima barang',
    ]);

    $pembayaran = Pembayaran::create([
        'nomor_pembayaran' => 'BYR-CASC-001',
        'nomor_paket' => $paket->nomor_paket,
        'id_user_pengaju' => $ppbj->id_user,
        'tahap_termin' => 'lunas',
        'nominal_pengajuan' => 150000000,
        'tanggal_pengajuan' => now(),
        'status_pembayaran' => 'spp_terbit',
        'file_spp' => 'pembayaran/spp-own.pdf',
    ]);

    MemoBayar::create([
        'id_user' => $ppk->id_user,
        'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
        'role_approver' => MemoBayar::ROLE_PPK,
        'status_memo' => MemoBayar::STATUS_DISETUJUI,
        'file_memo' => 'memo/bayar-ppk.pdf',
    ]);

    TransaksiPencairan::create([
        'nomor_transaksi_bank' => 'SP2D-CASC-001',
        'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
        'id_user' => $keuangan->id_user,
        'tanggal_transfer' => now(),
        'nominal_transfer' => 150000000,
        'file_bukti_transfer' => 'pencairan/sp2d-own.pdf',
    ]);

    $reminder = Reminder::create([
        'nomor_paket' => $paket->nomor_paket,
        'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
        'tipe_reminder' => Reminder::TIPE_PENGIRIMAN,
        'waktu_trigger' => now()->addDay(),
        'is_recurring' => false,
        'is_active' => true,
        'judul_alert' => 'Pengingat Uji Kaskade',
        'pesan_alert' => 'Pengingat harus terhapus saat permohonan dibatalkan.',
    ]);

    NotifikasiUser::create([
        'id_user' => $ppbj->id_user,
        'id_reminder' => $reminder->id_reminder,
        'is_read' => false,
        'is_dismissed' => false,
    ]);

    // Tombol Batalkan Usulan tampil di halaman detail meski status sudah selesai.
    $this->actingAs($userA)
        ->get(route('permohonan.show', $milikSendiri->nomor_surat))
        ->assertOk()
        ->assertSee('Batalkan Usulan');

    $this->actingAs($userA)
        ->delete(route('permohonan.destroy', $milikSendiri->nomor_surat))
        ->assertRedirect(route('permohonan.index'))
        ->assertSessionHas('success');

    // Seluruh entitas anak terhapus, saldo MAK kembali penuh, turunan revisi aman, dan file fisik terhapus.
    expect(PermohonanPengadaan::where('nomor_surat', '015/PL4.TIK/2026')->exists())->toBeFalse()
        ->and(AlokasiMAK::where('nomor_surat', '015/PL4.TIK/2026')->exists())->toBeFalse()
        ->and(VerifikasiParalel::where('nomor_surat', '015/PL4.TIK/2026')->exists())->toBeFalse()
        ->and(PaketPengadaan::where('nomor_paket', 'PKT-CASC-001')->exists())->toBeFalse()
        ->and(ReviuDokumen::where('nomor_paket', 'PKT-CASC-001')->exists())->toBeFalse()
        ->and(KontrakSPK::where('nomor_spk', 'SPK-CASC-001')->exists())->toBeFalse()
        ->and(PenerimaanBarang::where('id_penerimaan', $bast->id_penerimaan)->exists())->toBeFalse()
        ->and(DokumentasiPenerimaanBarang::where('id_penerimaan', $bast->id_penerimaan)->exists())->toBeFalse()
        ->and(Pembayaran::where('nomor_pembayaran', 'BYR-CASC-001')->exists())->toBeFalse()
        ->and(MemoBayar::where('nomor_pembayaran', 'BYR-CASC-001')->exists())->toBeFalse()
        ->and(TransaksiPencairan::where('nomor_transaksi_bank', 'SP2D-CASC-001')->exists())->toBeFalse()
        ->and(Reminder::where('id_reminder', $reminder->id_reminder)->exists())->toBeFalse()
        ->and(NotifikasiUser::where('id_reminder', $reminder->id_reminder)->exists())->toBeFalse()
        ->and($anakRevisi->fresh()->nomor_surat_revisi)->toBeNull()
        ->and((float) AnggaranMAK::findOrFail('MAK-CASCADE-01')->sisa_pagu_mak)->toBe(500000000.0);

    foreach ([
        'permohonan/surat-own.pdf',
        'memo/cek-mak.pdf',
        'memo/vp-wadir.pdf',
        'paket/dokumen-pengadaan.pdf',
        'spk/kontrak-own.pdf',
        'bast/bast-signed.pdf',
        'bast/foto-barang.jpg',
        'pembayaran/spp-own.pdf',
        'memo/bayar-ppk.pdf',
        'pencairan/sp2d-own.pdf',
    ] as $path) {
        Storage::disk('public')->assertMissing($path);
    }
});

it('displays validation warning on /profil when uploaded foto_profil exceeds 2048 kb', function (): void {
    Storage::fake('public');

    $user = User::where('username', 'user_pengaju')->firstOrFail();
    $oversizedImage = UploadedFile::fake()->image('gambar_besar.jpg')->size(3584);

    // Pengiriman melalui Modal Edit Profil harus membuka ulang modal dan menampilkan pesan validasi ukuran maksimum.
    $responseFromModal = $this->actingAs($user)
        ->from(route('profile.index'))
        ->put(route('profile.update'), [
            'from_edit_modal' => '1',
            'nama' => $user->nama,
            'email' => $user->email,
            'foto_profil' => $oversizedImage,
        ]);

    $responseFromModal->assertRedirect(route('profile.index'))
        ->assertSessionHasErrors([
            'foto_profil' => 'The foto profil field must not be greater than 2048 kilobytes.',
        ]);

    expect($user->fresh()->foto_profil)->toBeNull();

    $this->actingAs($user)
        ->followingRedirects()
        ->from(route('profile.index'))
        ->put(route('profile.update'), [
            'from_edit_modal' => '1',
            'nama' => $user->nama,
            'email' => $user->email,
            'foto_profil' => UploadedFile::fake()->image('gambar_besar.jpg')->size(3584),
        ])
        ->assertOk()
        ->assertSee('The foto profil field must not be greater than 2048 kilobytes.')
        ->assertSee('data-edit-modal-open="1"', false);

    // Pengiriman langsung dari tombol Ubah Foto pada kartu profil juga menampilkan peringatan di halaman.
    $this->actingAs($user)
        ->followingRedirects()
        ->from(route('profile.index'))
        ->put(route('profile.update'), [
            'nama' => $user->nama,
            'email' => $user->email,
            'foto_profil' => UploadedFile::fake()->image('gambar_besar.jpg')->size(3584),
        ])
        ->assertOk()
        ->assertSee('The foto profil field must not be greater than 2048 kilobytes.')
        ->assertSee('data-edit-modal-open="0"', false);
});
