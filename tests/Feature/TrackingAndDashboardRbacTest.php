<?php

use App\Models\AlokasiMAK;
use App\Models\AnggaranMAK;
use App\Models\MemoBayar;
use App\Models\PaketPengadaan;
use App\Models\Pembayaran;
use App\Models\Permission;
use App\Models\PermohonanPengadaan;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

it('shows ppk level 2 memo bayar on dashboard only after wadir 2 level 1 approves', function (): void {
    $pengaju = User::where('username', 'user_pengaju')->firstOrFail();
    $ppk = User::where('username', 'ppk_1')->firstOrFail();
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

    $memoWadir = MemoBayar::create([
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

    $this->actingAs($wadir)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('BYR-2026-0002')
        ->assertSee('Memo Lvl 1');

    $this->actingAs($ppk)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('BYR-2026-0002');

    $memoWadir->update([
        'status_memo' => MemoBayar::STATUS_DISETUJUI,
        'tanggal_acc' => now(),
    ]);

    $this->actingAs($ppk)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('BYR-2026-0002');
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
    $viewOnlyRole->permissions()->attach($makViewPerm->id_permission);

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
