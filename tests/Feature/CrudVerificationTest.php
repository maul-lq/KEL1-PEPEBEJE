<?php

use App\Models\AnggaranMAK;
use App\Models\KontrakSPK;
use App\Models\MemoBayar;
use App\Models\PaketPengadaan;
use App\Models\Pembayaran;
use App\Models\PenerimaanBarang;
use App\Models\PengadaanLog;
use App\Models\PermohonanPengadaan;
use App\Models\Reminder;
use App\Models\ReviuPengadaan;
use App\Models\User;
use App\Models\VerifikasiParalel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user CRUD works via API', function () {
    $createResponse = $this->postJson('/api/users', [
        'nip' => '199001012020121001',
        'email' => 'admin@kampus.ac.id',
        'nama' => 'Budi Santoso',
        'password' => 'secret123',
        'jabatan' => 'Kepala Unit',
        'unit_kerja' => 'UPT Komputer',
        'role' => User::ROLE_USER_PENGAJU,
        'status_aktif' => true,
        'no_hp' => '081234567890',
        'no_telp_kantor' => '021-1234567',
    ]);

    $createResponse->assertStatus(201)
        ->assertJsonPath('data.nama', 'Budi Santoso');

    $userId = $createResponse->json('data.id_user');

    $this->getJson("/api/users/{$userId}")
        ->assertStatus(200)
        ->assertJsonPath('data.nip', '199001012020121001');

    $this->putJson("/api/users/{$userId}", [
        'nama' => 'Budi Santoso M.Kom',
    ])->assertStatus(200)
        ->assertJsonPath('data.nama', 'Budi Santoso M.Kom');

    $this->deleteJson("/api/users/{$userId}")
        ->assertStatus(200);

    expect(User::find($userId))->toBeNull();
});

test('vendor and permohonan pengadaan CRUD works', function () {
    $user = User::create([
        'nip' => '199001012020121002',
        'email' => 'pengaju@kampus.ac.id',
        'nama' => 'Staf Pengaju',
        'password' => 'secret123',
        'jabatan' => 'Staf',
        'unit_kerja' => 'Teknik Elektro',
        'role' => User::ROLE_USER_PENGAJU,
        'no_hp' => '081234567891',
    ]);

    $vendorResponse = $this->postJson('/api/vendors', [
        'npwp' => '01.234.567.8-901.000',
        'nib' => '9120001234567',
        'nama_perusahaan' => 'PT Maju Bersama',
        'alamat' => 'Jl. Merdeka No. 10',
        'file_legalitas' => 'legalitas_pt_maju.pdf',
        'is_active' => true,
        'nama_pic' => 'Andi Wijaya',
        'telepon_pic' => '0811223344',
        'email_pic' => 'andi@maju.com',
        'nama_bank' => 'Bank Mandiri',
        'nomor_rekening' => '1234567890',
        'atas_nama' => 'PT Maju Bersama',
    ]);

    $vendorResponse->assertStatus(201)
        ->assertJsonPath('data.npwp', '01.234.567.8-901.000');

    $permohonanResponse = $this->postJson('/api/permohonan-pengadaan', [
        'nomor_surat' => '001/UN/PBJ/2026',
        'judul_pengadaan' => 'Pengadaan Komputer Lab',
        'asal_unit' => 'Teknik Elektro',
        'tujuan_ringkas' => 'Untuk praktikum mahasiswa',
        'file_pdf_srikandi' => 'srikandi_001.pdf',
        'status_permohonan' => PermohonanPengadaan::STATUS_DIAJUKAN,
        'id_user' => $user->id_user,
    ]);

    $permohonanResponse->assertStatus(201)
        ->assertJsonPath('data.nomor_surat', '001/UN/PBJ/2026');

    $this->getJson('/api/permohonan-pengadaan/001~UN~PBJ~2026')
        ->assertStatus(404); // without exact key

    $this->getJson('/api/permohonan-pengadaan/001/UN/PBJ/2026')
        ->assertStatus(200);
});

test('anggaran mak, paket pengadaan, dan alokasi mak CRUD works', function () {
    $user = User::create([
        'nip' => '199001012020121003',
        'email' => 'perencanaan@kampus.ac.id',
        'nama' => 'Staf Perencanaan',
        'password' => 'secret123',
        'jabatan' => 'Perencana',
        'unit_kerja' => 'Biro Perencanaan',
        'role' => User::ROLE_PERENCANAAN,
        'no_hp' => '081234567892',
    ]);

    $makResponse = $this->postJson('/api/anggaran-mak', [
        'kode_mak' => 'MAK-2026-001',
        'uraian_mak' => 'Belanja Modal Peralatan Komputer',
        'pagu_anggaran' => 500000000.00,
        'sumber_dana' => 'BOPTN',
        'tahun_anggaran' => 2026,
        'is_locked' => false,
        'id_user' => $user->id_user,
    ]);

    $makResponse->assertStatus(201)
        ->assertJsonPath('data.kode_mak', 'MAK-2026-001');

    $permohonan = PermohonanPengadaan::create([
        'nomor_surat' => '002/UN/PBJ/2026',
        'judul_pengadaan' => 'Pengadaan Server Kampus',
        'asal_unit' => 'UPT Komputer',
        'tujuan_ringkas' => 'Upgrade server database',
        'file_pdf_srikandi' => 'srikandi_002.pdf',
        'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
        'id_user' => $user->id_user,
    ]);

    $alokasiResponse = $this->postJson('/api/alokasi-mak', [
        'nomor_surat' => $permohonan->nomor_surat,
        'kode_mak' => 'MAK-2026-001',
        'nominal_alokasi' => 250000000.00,
    ]);

    $alokasiResponse->assertStatus(201)
        ->assertJsonPath('data.nominal_alokasi', '250000000.00');

    $paketResponse = $this->postJson('/api/paket-pengadaan', [
        'nomor_paket' => 'PKT-2026-001',
        'nama_paket' => 'Server High Availability',
        'jenis_pengadaan' => PaketPengadaan::JENIS_BARANG,
        'metode_pengadaan' => PaketPengadaan::METODE_TENDER,
        'nilai_hps' => 245000000.00,
        'status_paket' => PaketPengadaan::STATUS_REGISTRASI_PPBJ,
        'jalur_routing' => PaketPengadaan::JALUR_PPK,
        'nomor_surat' => $permohonan->nomor_surat,
        'kode_mak' => 'MAK-2026-001',
        'ppbj_user_id' => $user->id_user,
    ]);

    $paketResponse->assertStatus(201)
        ->assertJsonPath('data.nomor_paket', 'PKT-2026-001');
});

test('verifikasi paralel, reviu, dan penugasan ppk CRUD works', function () {
    $user = User::create([
        'nip' => '199001012020121004',
        'email' => 'wadir2@kampus.ac.id',
        'nama' => 'Wakil Direktur II',
        'password' => 'secret123',
        'jabatan' => 'Wadir II',
        'unit_kerja' => 'Pimpinan',
        'role' => User::ROLE_WADIR_2,
        'no_hp' => '081234567893',
    ]);

    $permohonan = PermohonanPengadaan::create([
        'nomor_surat' => '003/UN/PBJ/2026',
        'judul_pengadaan' => 'Gedung Kuliah Baru',
        'asal_unit' => 'Jurusan Sipil',
        'tujuan_ringkas' => 'Pembangunan lab sipil',
        'file_pdf_srikandi' => 'srikandi_003.pdf',
        'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
        'id_user' => $user->id_user,
    ]);

    $verifResponse = $this->postJson('/api/verifikasi-paralel', [
        'id_user' => $user->id_user,
        'nomor_surat' => $permohonan->nomor_surat,
        'role_verifikator' => 'wadir_2',
        'status_keputusan' => VerifikasiParalel::STATUS_DISETUJUI,
        'catatan_alasan' => 'Disetujui untuk diteruskan ke PBJ',
    ]);

    $verifResponse->assertStatus(201)
        ->assertJsonPath('data.status_keputusan', 'disetujui');

    $mak = AnggaranMAK::create([
        'kode_mak' => 'MAK-2026-002',
        'uraian_mak' => 'Belanja Modal Gedung',
        'pagu_anggaran' => 1000000000.00,
        'sumber_dana' => 'RM',
        'tahun_anggaran' => 2026,
        'id_user' => $user->id_user,
    ]);

    $paket = PaketPengadaan::create([
        'nomor_paket' => 'PKT-2026-002',
        'nama_paket' => 'Pembangunan Lab Gedung A',
        'jenis_pengadaan' => PaketPengadaan::JENIS_KONSTRUKSI,
        'metode_pengadaan' => PaketPengadaan::METODE_TENDER,
        'nilai_hps' => 950000000.00,
        'status_paket' => PaketPengadaan::STATUS_REGISTRASI_PPBJ,
        'jalur_routing' => PaketPengadaan::JALUR_PPK,
        'nomor_surat' => $permohonan->nomor_surat,
        'kode_mak' => $mak->kode_mak,
        'ppbj_user_id' => $user->id_user,
    ]);

    $reviuResponse = $this->postJson('/api/reviu-pengadaan', [
        'nomor_paket' => $paket->nomor_paket,
        'id_user' => $user->id_user,
        'status_reviu' => ReviuPengadaan::STATUS_DISETUJUI,
        'catatan_perbaikan' => 'Dokumen spesifikasi teknis lengkap',
    ]);

    $reviuResponse->assertStatus(201)
        ->assertJsonPath('data.status_reviu', 'disetujui');

    $riwayatResponse = $this->postJson('/api/riwayat-penugasan-ppk', [
        'nomor_paket' => $paket->nomor_paket,
        'ppk_baru_user_id' => $user->id_user,
        'diubah_oleh_user_id' => $user->id_user,
        'alasan_perubahan' => 'Penugasan PPK definitif',
    ]);

    $riwayatResponse->assertStatus(201)
        ->assertJsonPath('data.alasan_perubahan', 'Penugasan PPK definitif');
});

test('kontrak spk, bast penerimaan, foto, dan pembayaran CRUD works', function () {
    $user = User::create([
        'nip' => '199001012020121005',
        'email' => 'ppk@kampus.ac.id',
        'nama' => 'Pejabat Pembuat Komitmen',
        'password' => 'secret123',
        'jabatan' => 'PPK',
        'unit_kerja' => 'Bagian Umum',
        'role' => User::ROLE_PPK,
        'no_hp' => '081234567894',
    ]);

    $permohonan = PermohonanPengadaan::create([
        'nomor_surat' => '004/UN/PBJ/2026',
        'judul_pengadaan' => 'Pengadaan AC Ruang Kelas',
        'asal_unit' => 'Bagian Umum',
        'tujuan_ringkas' => 'Peremajaan AC rusak',
        'file_pdf_srikandi' => 'srikandi_004.pdf',
        'status_permohonan' => PermohonanPengadaan::STATUS_DIPROSES_PPBJ,
        'id_user' => $user->id_user,
    ]);

    $mak = AnggaranMAK::create([
        'kode_mak' => 'MAK-2026-003',
        'uraian_mak' => 'Belanja Modal Peralatan Pendingin',
        'pagu_anggaran' => 100000000.00,
        'sumber_dana' => 'RM',
        'tahun_anggaran' => 2026,
        'id_user' => $user->id_user,
    ]);

    $paket = PaketPengadaan::create([
        'nomor_paket' => 'PKT-2026-003',
        'nama_paket' => 'Pengadaan AC 2 PK',
        'jenis_pengadaan' => PaketPengadaan::JENIS_BARANG,
        'metode_pengadaan' => PaketPengadaan::METODE_PENGADAAN_LANGSUNG,
        'nilai_hps' => 45000000.00,
        'status_paket' => PaketPengadaan::STATUS_SPK_TERBIT,
        'jalur_routing' => PaketPengadaan::JALUR_LANGSUNG,
        'nomor_surat' => $permohonan->nomor_surat,
        'kode_mak' => $mak->kode_mak,
        'ppbj_user_id' => $user->id_user,
    ]);

    $kontrakResponse = $this->postJson('/api/kontrak-spk', [
        'nomor_spk' => 'SPK-001/2026',
        'nomor_paket' => $paket->nomor_paket,
        'tipe_kontrak' => KontrakSPK::TIPE_SEKALI_SELESAI,
        'tanggal_spk' => '2026-10-01',
        'jadwal_pengiriman' => '2026-10-15',
        'nilai_kontrak' => 44000000.00,
        'file_spk' => 'spk_ac_001.pdf',
    ]);

    $kontrakResponse->assertStatus(201)
        ->assertJsonPath('data.nomor_spk', 'SPK-001/2026');

    $bastResponse = $this->postJson('/api/penerimaan-barang', [
        'nomor_bast' => 'BAST-001/2026',
        'nomor_paket' => $paket->nomor_paket,
        'tanggal_penerimaan' => '2026-10-14',
        'status_fisik' => PenerimaanBarang::STATUS_DITERIMA_LENGKAP,
        'ttd_digital_bast' => 'base64_encoded_signature_hash',
        'file_bast_signed' => 'bast_signed_001.pdf',
        'id_user' => $user->id_user,
    ]);

    $bastResponse->assertStatus(201)
        ->assertJsonPath('data.status_fisik', 'diterima_lengkap');

    $fotoResponse = $this->postJson('/api/dokumentasi-penerimaan-barang', [
        'nomor_bast' => 'BAST-001/2026',
        'nomor_paket' => $paket->nomor_paket,
        'foto_dokumentasi' => 'foto_unit_ac_1.jpg',
        'keterangan_foto' => 'Unit outdoor dan indoor terpasang',
    ]);

    $fotoResponse->assertStatus(201)
        ->assertJsonPath('data.foto_dokumentasi', 'foto_unit_ac_1.jpg');

    $bayarResponse = $this->postJson('/api/pembayaran', [
        'nomor_pembayaran' => 'BYR-001/2026',
        'nomor_paket' => $paket->nomor_paket,
        'tahap_termin' => 1,
        'nominal_pengajuan' => 44000000.00,
        'status_pembayaran' => Pembayaran::STATUS_PERINTAH_BAYAR,
    ]);

    $bayarResponse->assertStatus(201)
        ->assertJsonPath('data.nomor_pembayaran', 'BYR-001/2026');

    $memoResponse = $this->postJson('/api/memo-bayar', [
        'id_user' => $user->id_user,
        'nomor_pembayaran' => 'BYR-001/2026',
        'nomor_paket' => $paket->nomor_paket,
        'role_approver' => MemoBayar::ROLE_PPK,
        'status_memo' => MemoBayar::STATUS_DISETUJUI,
        'catatan_memo' => 'Barang sudah diterima lengkap dan terpasang baik',
    ]);

    $memoResponse->assertStatus(201)
        ->assertJsonPath('data.status_memo', 'disetujui');

    $cairResponse = $this->postJson('/api/transaksi-pencairan', [
        'nomor_transaksi_bank' => 'TRX-BNI-987654321',
        'nomor_pembayaran' => 'BYR-001/2026',
        'nomor_paket' => $paket->nomor_paket,
        'nominal_transfer' => 44000000.00,
        'file_bukti_transfer' => 'bukti_transfer_spk001.pdf',
        'catatan_keuangan' => 'Transfer selesai via CMS BNI',
        'id_user' => $user->id_user,
    ]);

    $cairResponse->assertStatus(201)
        ->assertJsonPath('data.nomor_transaksi_bank', 'TRX-BNI-987654321');

    $reminderResponse = $this->postJson('/api/reminders', [
        'nomor_paket' => $paket->nomor_paket,
        'target_role' => Reminder::ROLE_KEUANGAN,
        'tipe_reminder' => Reminder::TIPE_PENGIRIMAN,
        'judul_alert' => 'Jadwal Pengiriman Barang',
        'pesan_alert' => 'Vendor akan mengirimkan barang pada tanggal 15',
        'tanggal_pemicu' => '2026-10-15 09:00:00',
    ]);

    $reminderResponse->assertStatus(201)
        ->assertJsonPath('data.target_role', 'keuangan');

    $logResponse = $this->postJson('/api/pengadaan-logs', [
        'nomor_paket' => $paket->nomor_paket,
        'id_user' => $user->id_user,
        'aksi' => PengadaanLog::AKSI_ASSIGN_PPK,
        'keterangan' => 'Penugasan PPK untuk paket pengadaan AC',
    ]);

    $logResponse->assertStatus(201)
        ->assertJsonPath('data.aksi', 'ASSIGN_PPK');
});
