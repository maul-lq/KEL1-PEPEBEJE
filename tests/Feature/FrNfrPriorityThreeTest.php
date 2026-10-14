<?php

use App\Models\PaketPengadaan;
use App\Models\Pembayaran;
use App\Models\PermohonanPengadaan;
use App\Models\Unit;
use App\Models\User;
use App\Models\VerifikasiParalel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    Storage::fake('public');
});

test('FR 3.1 & 3.2: store permohonan with Srikandi metadata, PDF validation, and auto-queues parallel verification', function () {
    $user = User::where('role', 'user')->first();
    $this->actingAs($user);

    // 1. Validasi file non-PDF ditolak
    $invalidFile = UploadedFile::fake()->create('dokumen.docx', 500, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    $response = $this->postJson(route('permohonan-pengadaan.store'), [
        'nomor_surat' => 'B/001/PL3.TIK/PG/2026',
        'judul_pengadaan' => 'Pengadaan Komputer Lab TIK',
        'tujuan_pengadaan_ringkas' => 'Kerangka Acuan Kerja Laboratorium Komputer',
        'file_permohonan' => $invalidFile,
        'estimasi_total_pagu' => 150000000,
    ]);
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['file_permohonan']);

    // 2. Berkas PDF valid (<10MB) berhasil disimpan
    $pdfFile = UploadedFile::fake()->create('surat_srikandi.pdf', 2048, 'application/pdf');
    $response = $this->postJson(route('permohonan-pengadaan.store'), [
        'nomor_surat' => 'B/001/PL3.TIK/PG/2026',
        'judul_pengadaan' => 'Pengadaan Komputer Lab TIK',
        'tujuan_pengadaan_ringkas' => 'Kerangka Acuan Kerja Laboratorium Komputer',
        'file_permohonan' => $pdfFile,
        'estimasi_total_pagu' => 150000000,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.nomor_surat', 'B/001/PL3.TIK/PG/2026')
        ->assertJsonPath('data.status_permohonan', PermohonanPengadaan::STATUS_REVIU_PARALEL);

    // Verifikasi database permohonan
    $this->assertDatabaseHas('permohonan_pengadaan', [
        'nomor_surat' => 'B/001/PL3.TIK/PG/2026',
        'judul_pengadaan' => 'Pengadaan Komputer Lab TIK',
        'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
    ]);

    // Verifikasi otomatisasi 2 verifikator paralel (Perencanaan & Wadir 2)
    $this->assertDatabaseHas('verifikasi_paralel', [
        'nomor_surat' => 'B/001/PL3.TIK/PG/2026',
        'role_verifikator' => 'perencanaan',
        'status_keputusan' => 'menunggu',
    ]);
    $this->assertDatabaseHas('verifikasi_paralel', [
        'nomor_surat' => 'B/001/PL3.TIK/PG/2026',
        'role_verifikator' => 'wadir_2',
        'status_keputusan' => 'menunggu',
    ]);

    // Verifikasi audit log
    $this->assertDatabaseHas('audit_log', [
        'jenis_aksi' => 'PENGAJUAN_PERMOHONAN_BARU',
        'id_entitas' => 'B/001/PL3.TIK/PG/2026',
    ]);
});

test('FR 3.3: parallel verification requires alasan when rejecting and cascades approval when both approve', function () {
    $user = User::where('role', 'user')->first();
    $perencanaanUser = User::where('role', 'perencanaan')->first();
    $wadirUser = User::where('role', 'wadir_2')->first();

    $permohonan = PermohonanPengadaan::create([
        'nomor_surat' => 'B/002/PL3.TIK/PG/2026',
        'judul_pengadaan' => 'Pengadaan Switch Core Kampus',
        'tujuan_pengadaan_ringkas' => 'Upgrade infrastruktur jaringan',
        'file_permohonan' => 'permohonan/switch.pdf',
        'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
        'status_anggaran' => 'pending',
        'revisi_ke' => 0,
        'estimasi_total_pagu' => 90000000,
        'tanggal_pengajuan' => now(),
        'kode_unit' => $user->kode_unit ?? Unit::first()->kode_unit,
        'id_user' => $user->id_user,
    ]);

    $verifPerencanaan = VerifikasiParalel::create([
        'id_user' => $perencanaanUser->id_user,
        'nomor_surat' => $permohonan->nomor_surat,
        'role_verifikator' => 'perencanaan',
        'status_keputusan' => 'menunggu',
    ]);

    $verifWadir = VerifikasiParalel::create([
        'id_user' => $wadirUser->id_user,
        'nomor_surat' => $permohonan->nomor_surat,
        'role_verifikator' => 'wadir_2',
        'status_keputusan' => 'menunggu',
    ]);

    // 1. Menolak tanpa catatan alasan harus ditolak (HTTP 422)
    $this->actingAs($perencanaanUser);
    $response = $this->putJson(route('verifikasi-paralel.update', $verifPerencanaan->id), [
        'status_keputusan' => 'ditolak',
        'catatan_alasan' => '',
    ]);
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['catatan_alasan']);

    // 2. Pending Anggaran mengubah status permohonan menjadi pending_anggaran (Hold)
    $response = $this->putJson(route('verifikasi-paralel.update', $verifPerencanaan->id), [
        'status_keputusan' => 'pending_anggaran',
        'catatan_alasan' => 'Menunggu revisi DIPA triwulan II',
    ]);
    $response->assertStatus(200);
    expect($permohonan->fresh()->status_permohonan)->toBe('pending_anggaran');

    // 3. Perencanaan menyetujui
    $response = $this->putJson(route('verifikasi-paralel.update', $verifPerencanaan->id), [
        'status_keputusan' => 'disetujui',
        'catatan_alasan' => 'Pagu anggaran tersedia pada MAK 521111',
    ]);
    $response->assertStatus(200);

    // Permohonan masih belum disetujui penuh karena Wadir 2 belum menyetujui
    expect($permohonan->fresh()->status_permohonan)->not->toBe(PermohonanPengadaan::STATUS_DISETUJUI);

    // 4. Wadir 2 menyetujui -> Status permohonan otomatis cascade ke disetujui
    $this->actingAs($wadirUser);
    $response = $this->putJson(route('verifikasi-paralel.update', $verifWadir->id), [
        'status_keputusan' => 'disetujui',
        'catatan_alasan' => 'Disetujui untuk diproses ke PPBJ',
    ]);
    $response->assertStatus(200);
    expect($permohonan->fresh()->status_permohonan)->toBe(PermohonanPengadaan::STATUS_DISETUJUI);
});

test('FR 3.4: revisi permohonan creates new row with new nomor_surat, unary relation, and new parallel queue', function () {
    $user = User::where('role', 'user')->first();
    $this->actingAs($user);

    $induk = PermohonanPengadaan::create([
        'nomor_surat' => 'B/003/PL3.TIK/PG/2026',
        'judul_pengadaan' => 'Pengadaan Server Cloud Kampus',
        'tujuan_pengadaan_ringkas' => 'Server komputasi mahasiswa',
        'file_permohonan' => 'permohonan/server_old.pdf',
        'status_permohonan' => PermohonanPengadaan::STATUS_DITOLAK,
        'status_anggaran' => 'pending',
        'revisi_ke' => 0,
        'estimasi_total_pagu' => 250000000,
        'tanggal_pengajuan' => now(),
        'kode_unit' => $user->kode_unit ?? Unit::first()->kode_unit,
        'id_user' => $user->id_user,
    ]);

    $revisiPdf = UploadedFile::fake()->create('server_revisi_kak.pdf', 3000, 'application/pdf');

    $response = $this->postJson(route('permohonan-pengadaan.revisi', $induk->nomor_surat), [
        'nomor_surat_baru' => 'B/003-REV/PL3.TIK/PG/2026',
        'judul_pengadaan' => 'Pengadaan Server Cloud Kampus (Revisi KAK Spesifikasi)',
        'tujuan_pengadaan_ringkas' => 'Penyesuaian spesifikasi prosesor dan memori sesuai arahan Wadir 2',
        'file_permohonan' => $revisiPdf,
        'alasan_revisi' => 'Mengubah spesifikasi CPU dari 32 core menjadi 64 core',
        'estimasi_total_pagu' => 240000000,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.nomor_surat', 'B/003-REV/PL3.TIK/PG/2026')
        ->assertJsonPath('data.nomor_surat_revisi', 'B/003/PL3.TIK/PG/2026')
        ->assertJsonPath('data.revisi_ke', 1);

    // Cek di DB
    $this->assertDatabaseHas('permohonan_pengadaan', [
        'nomor_surat' => 'B/003-REV/PL3.TIK/PG/2026',
        'nomor_surat_revisi' => 'B/003/PL3.TIK/PG/2026',
        'revisi_ke' => 1,
        'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
    ]);

    // Cek verifikasi paralel baru telah dibuat
    $this->assertDatabaseHas('verifikasi_paralel', [
        'nomor_surat' => 'B/003-REV/PL3.TIK/PG/2026',
        'role_verifikator' => 'perencanaan',
        'status_keputusan' => 'menunggu',
    ]);
    $this->assertDatabaseHas('verifikasi_paralel', [
        'nomor_surat' => 'B/003-REV/PL3.TIK/PG/2026',
        'role_verifikator' => 'wadir_2',
        'status_keputusan' => 'menunggu',
    ]);

    // Cek audit log
    $this->assertDatabaseHas('audit_log', [
        'jenis_aksi' => 'REVISI_PERMOHONAN_DITOLAK',
        'id_entitas' => 'B/003-REV/PL3.TIK/PG/2026',
    ]);
});

test('NFR 7: tracking endpoint returns 8 stages in under 5 seconds with detailed timeline', function () {
    $user = User::where('role', 'user')->first();
    $this->actingAs($user);

    $permohonan = PermohonanPengadaan::first();

    $startTime = microtime(true);
    $response = $this->getJson(route('permohonan-pengadaan.tracking', $permohonan->nomor_surat));
    $duration = (microtime(true) - $startTime);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'response_time_ms',
            'data' => [
                'nomor_surat',
                'judul_pengadaan',
                'overall_status',
                'current_stage',
                'total_stages',
                'timeline',
            ],
        ])
        ->assertJsonPath('data.total_stages', 8);

    // SLA di bawah 5 detik
    expect($duration)->toBeLessThan(5.0);
    expect($response->json('response_time_ms'))->toBeLessThan(5000);

    // Cek nama 8 tahapan terdefinisi secara berurutan
    $timeline = $response->json('data.timeline');
    expect(count($timeline))->toBe(8);
    expect($timeline[0]['stage_name'])->toBe('Pengajuan Usulan Srikandi');
    expect($timeline[1]['stage_name'])->toBe('Verifikasi Paralel Pimpinan & Perencanaan');
    expect($timeline[2]['stage_name'])->toBe('Penetapan & Alokasi Pagu MAK');
    expect($timeline[3]['stage_name'])->toBe('Registrasi Paket & Auto-Routing PP/PPK');
    expect($timeline[4]['stage_name'])->toBe('Reviu Dokumen Pengadaan & HPS');
    expect($timeline[5]['stage_name'])->toBe('Penerbitan Kontrak / Surat Perintah Kerja (SPK)');
    expect($timeline[6]['stage_name'])->toBe('Penerimaan Fisik & BAST Digital');
    expect($timeline[7]['stage_name'])->toBe('Pembayaran SPP & Pencairan Dana SP2D');

    // Monitoring agregat juga harus cepat (<5s)
    $responseMonitoring = $this->getJson(route('monitoring.status'));
    $responseMonitoring->assertStatus(200)
        ->assertJsonStructure(['status', 'response_time_ms', 'data']);
});

test('NFR 8: anti-duplicate disbursement prevents duplicate payment and auto-cascades status to selesai', function () {
    $keuanganUser = User::where('role', 'keuangan')->first();
    $this->actingAs($keuanganUser);

    $paket = PaketPengadaan::first();
    $permohonan = $paket->permohonanPengadaan;

    $pembayaran = Pembayaran::create([
        'nomor_pembayaran' => 'SPP-TEST-NFR8-001',
        'nomor_paket' => $paket->nomor_paket,
        'id_user_pengaju' => $keuanganUser->id_user,
        'tahap_termin' => 1,
        'nominal_pengajuan' => 75000000,
        'tanggal_pengajuan' => now(),
        'jatuh_tempo' => now()->addDays(14),
        'status_pembayaran' => 'diajukan',
        'nomor_spp' => 'SPP/2026/001',
        'nomor_spm' => 'SPM/2026/001',
    ]);

    $buktiTransfer = UploadedFile::fake()->create('slip_transfer_bank.pdf', 1024, 'application/pdf');

    // 1. Pencairan pertama berhasil
    $response = $this->postJson(route('transaksi-pencairan.store'), [
        'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
        'nomor_transaksi_bank' => 'TRX-BANK-2026-0001',
        'tanggal_transfer' => '2026-10-14',
        'nominal_transfer' => 75000000,
        'bank_asal' => 'Bank Mandiri Kampus UI Depok',
        'bank_tujuan' => 'Bank BNI',
        'nomor_rekening_tujuan' => '0123456789',
        'file_bukti_transfer' => $buktiTransfer,
        'id_user' => $keuanganUser->id_user,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.nomor_pembayaran', 'SPP-TEST-NFR8-001');

    // Cek auto-cascade status lunas / selesai
    expect($pembayaran->fresh()->status_pembayaran)->toBe('lunas');
    expect($paket->fresh()->status_paket)->toBe('selesai');
    if ($permohonan) {
        expect($permohonan->fresh()->status_permohonan)->toBe('selesai');
    }

    // Cek audit log dicatat
    $this->assertDatabaseHas('audit_log', [
        'jenis_aksi' => 'PENCAIRAN_SP2D_SELESAI',
        'id_entitas' => 'TRX-BANK-2026-0001',
    ]);

    // 2. Pencairan kedua pada nomor pembayaran yang sama harus DITOLAK (Anti-duplicate HTTP 422)
    $buktiTransfer2 = UploadedFile::fake()->create('slip_kedua.pdf', 1024, 'application/pdf');
    $responseDuplicate = $this->postJson(route('transaksi-pencairan.store'), [
        'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
        'nomor_transaksi_bank' => 'TRX-BANK-2026-0002',
        'tanggal_transfer' => '2026-10-14',
        'nominal_transfer' => 75000000,
        'bank_asal' => 'Bank Mandiri Kampus UI Depok',
        'bank_tujuan' => 'Bank BNI',
        'nomor_rekening_tujuan' => '0123456789',
        'file_bukti_transfer' => $buktiTransfer2,
        'id_user' => $keuanganUser->id_user,
    ]);

    $responseDuplicate->assertStatus(422)
        ->assertJsonValidationErrors(['nomor_pembayaran']);

    $errorMessage = $responseDuplicate->json('errors.nomor_pembayaran.0');
    expect($errorMessage)->toContain('NFR 8 Keandalan Pembayaran');
});

test('Frontend simulation views: /tes-permohonan and /tes-monitoring render properly', function () {
    $user = User::where('role', 'user')->first();
    $this->actingAs($user);

    $permohonan = PermohonanPengadaan::first();

    // Halaman simulasi permohonan
    $response = $this->get(route('permohonan.web'));
    $response->assertStatus(200)
        ->assertSee('Status Usulan Permohonan Pengadaan');

    // Halaman simulasi monitoring
    $response = $this->get(route('monitoring.web'));
    $response->assertStatus(200)
        ->assertSee('Pelacakan 8 Tahapan Siklus Pengadaan');

    // Lacak berkas spesifik pada halaman simulasi monitoring
    if ($permohonan) {
        $response = $this->get(route('monitoring.web', ['nomor_surat' => $permohonan->nomor_surat]));
        $response->assertStatus(200)
            ->assertSee($permohonan->nomor_surat)
            ->assertSee('Pelacakan 8 Tahapan Siklus Pengadaan');
    }
});
