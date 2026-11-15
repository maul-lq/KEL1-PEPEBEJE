<?php

use App\Models\PermohonanPengadaan;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

it('renders dynamic dokumen_lampiran list on paket create page instead of hardcoded 3 labels', function (): void {
    $pengaju = User::where('username', 'user_pengaju')->firstOrFail();
    $ppbj = User::where('username', 'ppbj')->firstOrFail();

    $lampiran = [
        [
            'name' => 'surat-usulan-tik.pdf',
            'path' => 'lampiran_permohonan/surat-usulan-tik.pdf',
            'url' => asset('storage/lampiran_permohonan/surat-usulan-tik.pdf'),
            'type' => 'PDF',
            'size' => '120 KB',
            'label' => 'surat-usulan-tik.pdf',
        ],
        [
            'name' => 'kak-server-2026.pdf',
            'path' => 'lampiran_permohonan/kak-server-2026.pdf',
            'url' => asset('storage/lampiran_permohonan/kak-server-2026.pdf'),
            'type' => 'PDF',
            'size' => '240 KB',
            'label' => 'kak-server-2026.pdf',
        ],
        [
            'name' => 'rab-rincian.xlsx',
            'path' => 'lampiran_permohonan/rab-rincian.xlsx',
            'url' => asset('storage/lampiran_permohonan/rab-rincian.xlsx'),
            'type' => 'XLSX',
            'size' => '45 KB',
            'label' => 'rab-rincian.xlsx',
        ],
        [
            'name' => 'brosur-teknis.png',
            'path' => 'lampiran_permohonan/brosur-teknis.png',
            'url' => asset('storage/lampiran_permohonan/brosur-teknis.png'),
            'type' => 'PNG',
            'size' => '310 KB',
            'label' => 'brosur-teknis.png',
        ],
    ];

    $permohonan = PermohonanPengadaan::create([
        'nomor_surat' => 'SRT-LAMP-0001',
        'perihal' => 'Pengadaan 4 Lampiran Dinamis',
        'judul_pengadaan' => 'Pengadaan 4 Lampiran Dinamis',
        'anggaran_diajukan' => 45000000,
        'estimasi_total_pagu' => 45000000,
        'status' => 'disetujui',
        'status_permohonan' => 'disetujui',
        'tanggal_pengajuan' => now(),
        'kode_unit' => $pengaju->kode_unit,
        'id_user' => $pengaju->id_user,
        'dokumen_lampiran' => $lampiran,
    ]);

    $this->actingAs($ppbj)
        ->get(route('paket.create', ['selected' => $permohonan->nomor_surat]))
        ->assertOk()
        ->assertSee('surat-usulan-tik.pdf')
        ->assertSee('kak-server-2026.pdf')
        ->assertSee('rab-rincian.xlsx')
        ->assertSee('brosur-teknis.png')
        ->assertDontSee('<span>Surat Srikandi</span>', false)
        ->assertDontSee('<span>Kerangka Acuan</span>', false)
        ->assertDontSee('<span>Spek Teknis</span>', false);
});

it('allows multi-upload on permohonan revision and replaces previous attachments', function (): void {
    Storage::fake('public');

    $pengaju = User::where('username', 'user_pengaju')->firstOrFail();

    $oldFile1 = UploadedFile::fake()->create('lama-1.pdf', 100, 'application/pdf')->store('lampiran_permohonan', 'public');
    $oldFile2 = UploadedFile::fake()->create('lama-2.pdf', 120, 'application/pdf')->store('lampiran_permohonan', 'public');

    $permohonan = PermohonanPengadaan::create([
        'nomor_surat' => 'SRT-REV-0001',
        'perihal' => 'Usulan Sebelum Revisi',
        'judul_pengadaan' => 'Usulan Sebelum Revisi',
        'anggaran_diajukan' => 25000000,
        'estimasi_total_pagu' => 25000000,
        'status' => 'perlu_revisi',
        'status_permohonan' => 'perlu_revisi',
        'tanggal_pengajuan' => now(),
        'kode_unit' => $pengaju->kode_unit,
        'id_user' => $pengaju->id_user,
        'file_permohonan' => $oldFile1,
        'file_surat_permohonan' => $oldFile1,
        'file_kak' => $oldFile2,
        'dokumen_lampiran' => [
            ['name' => 'lama-1.pdf', 'path' => $oldFile1, 'url' => asset('storage/'.$oldFile1), 'type' => 'PDF', 'size' => '100 KB', 'label' => 'lama-1.pdf'],
            ['name' => 'lama-2.pdf', 'path' => $oldFile2, 'url' => asset('storage/'.$oldFile2), 'type' => 'PDF', 'size' => '120 KB', 'label' => 'lama-2.pdf'],
        ],
    ]);

    $this->actingAs($pengaju)
        ->get(route('permohonan.show', $permohonan->nomor_surat))
        ->assertOk()
        ->assertSee('name="dokumen_lampiran[]"', false);

    $newFile1 = UploadedFile::fake()->create('revisi-surat.pdf', 150, 'application/pdf');
    $newFile2 = UploadedFile::fake()->create('revisi-kak.docx', 200, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    $newFile3 = UploadedFile::fake()->create('revisi-rab.xlsx', 90, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $this->actingAs($pengaju)
        ->post(route('permohonan.revisi'), [
            'nomor_surat_lama' => $permohonan->nomor_surat,
            'nomor_surat_baru' => $permohonan->nomor_surat,
            'perihal' => 'Usulan Sesudah Revisi',
            'anggaran_diajukan' => 26000000,
            'keterangan_revisi' => 'Perbaikan seluruh dokumen lampiran',
            'dokumen_lampiran' => [$newFile1, $newFile2, $newFile3],
        ])
        ->assertRedirect();

    $updated = $permohonan->fresh();

    expect($updated->dokumen_lampiran)->toBeArray()->toHaveCount(3)
        ->and(collect($updated->dokumen_lampiran)->pluck('name')->all())
        ->toBe(['revisi-surat.pdf', 'revisi-kak.docx', 'revisi-rab.xlsx'])
        ->and($updated->file_surat_permohonan)->toBeNull()
        ->and($updated->file_kak)->toBeNull();

    Storage::disk('public')->assertMissing($oldFile1);
    Storage::disk('public')->assertMissing($oldFile2);

    foreach ($updated->dokumen_lampiran as $doc) {
        Storage::disk('public')->assertExists($doc['path']);
    }

    // Revisi kedua tanpa upload file baru harus mempertahankan 3 lampiran yang ada
    $updated->update([
        'status' => 'perlu_revisi',
        'status_permohonan' => 'perlu_revisi',
    ]);

    $this->actingAs($pengaju)
        ->post(route('permohonan.revisi'), [
            'nomor_surat_lama' => $updated->nomor_surat,
            'nomor_surat_baru' => $updated->nomor_surat,
            'perihal' => 'Usulan Revisi Teks Saja',
            'anggaran_diajukan' => 26500000,
            'keterangan_revisi' => 'Penyesuaian nominal saja',
        ])
        ->assertRedirect();

    $updatedAgain = $updated->fresh();
    expect($updatedAgain->dokumen_lampiran)->toBeArray()->toHaveCount(3)
        ->and(collect($updatedAgain->dokumen_lampiran)->pluck('name')->all())
        ->toBe(['revisi-surat.pdf', 'revisi-kak.docx', 'revisi-rab.xlsx']);
});
