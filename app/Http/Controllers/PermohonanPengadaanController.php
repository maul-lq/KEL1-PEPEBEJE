<?php

namespace App\Http\Controllers;

use App\Models\AlokasiMAK;
use App\Models\AnggaranMAK;
use App\Models\AuditLog;
use App\Models\PermohonanPengadaan;
use App\Models\Unit;
use App\Models\User;
use App\Models\VerifikasiParalel;
use App\Rules\DokumenUploadRule;
use App\Services\PengadaanNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Controller Permohonan Pengadaan (PermohonanPengadaanController)
 *
 * Mengelola siklus pengajuan usulan surat permohonan pengadaan barang/jasa oleh unit kerja (User).
 * Mencakup pendaftaran usulan baru, revisi pasca penolakan, pelacakan status, dan pembatalan usulan.
 */
class PermohonanPengadaanController extends Controller
{
    public function __construct(
        protected PengadaanNotificationService $notificationService
    ) {}

    /**
     * Menampilkan riwayat usulan permohonan milik user login beserta jejak penolakan.
     */
    public function index(Request $request): View
    {
        return $this->permohonanIndex($request);
    }

    public function permohonanIndex(Request $request): View
    {
        $user = Auth::user();
        $query = PermohonanPengadaan::with([
            'unit',
            'user',
            'verifikasiParalel.user',
            'suratAsalRevisi',
            'alokasiMak.anggaranMak',
            'paketPengadaan.reviuDokumen',
            'paketPengadaan.kontrakSpk',
            'paketPengadaan.penerimaanBarang',
            'paketPengadaan.pembayaran.transaksiPencairan',
            'paketPengadaan.reminders',
        ])
            ->where('status_permohonan', '!=', 'digantikan_oleh_revisi');

        // Jika user yang login hanya memiliki izin melihat usulan sendiri (tanpa view_all), filter berdasarkan ID user
        if ($user && ($user->hasRole(['user', 'pengaju']) || ($user->hasPermission('permohonan.view_own') && ! $user->hasPermission('permohonan.view_all')))) {
            $query->where('id_user', $user->id_user);
        }

        // Terapkan Filter Pencarian (Search / Status / Jenis Pengadaan)
        if ($request->filled('keyword')) {
            $keyword = $request->keyword;
            $query->where(function ($q) use ($keyword) {
                $q->where('nomor_surat', 'like', "%{$keyword}%")
                    ->orWhere('judul_pengadaan', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status_permohonan', $request->status);
        }

        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('jenis_pengadaan_split', $request->type);
        }

        $permohonanList = $query->latest('tanggal_pengajuan')->paginate(15);
        $units = Unit::all();
        $makList = AnggaranMAK::orderBy('kode_mak')->get();
        $ppkUsers = User::where('role', 'ppk')->where('status_aktif', true)->get();

        return view('permohonan.index', compact('permohonanList', 'user', 'units', 'makList', 'ppkUsers'));
    }

    /**
     * Menampilkan detail tracking & informasi permohonan pengadaan.
     */
    public function show(string $id): View
    {
        return $this->permohonanShow($id);
    }

    public function permohonanShow(string $id): View
    {
        $permohonan = PermohonanPengadaan::with([
            'unit',
            'user',
            'verifikasiParalel.user',
            'paket.kontrak.penerimaan',
        ])->where('nomor_surat', $id)
            ->firstOrFail();

        $makList = AnggaranMAK::orderBy('kode_mak')->get();
        $ppkUsers = User::where('role', 'ppk')->where('status_aktif', true)->get();

        return view('permohonan.show', compact('permohonan', 'makList', 'ppkUsers'));
    }

    /**
     * Menampilkan formulir pendaftaran usulan permohonan pengadaan baru berbasis Srikandi.
     */
    public function create(): View
    {
        return $this->permohonanCreate();
    }

    public function permohonanCreate(): View
    {
        $units = Unit::all();

        return view('permohonan.create', compact('units'));
    }

    /**
     * Memproses pengajuan usulan permohonan baru ke Bidang 2 (Alur Persetujuan Awal Berantai).
     */
    public function store(Request $request): RedirectResponse
    {
        return $this->storePermohonan($request);
    }

    public function storePermohonan(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'nomor_surat' => ['required', 'string', 'max:100', 'unique:permohonan_pengadaan,nomor_surat'],
            'perihal' => ['required', 'string', 'max:255'],
            'tujuan_pengadaan_ringkas' => ['nullable', 'string', 'max:500'],
            'anggaran_diajukan' => ['required', 'numeric', 'min:1'],
            'kode_unit' => ['required', 'string', 'exists:units,kode_unit'],
            'tanggal_pengajuan' => ['required', 'date'],
            'file_surat_permohonan' => ['nullable', DokumenUploadRule::pdfOnly()],
            'file_kak' => ['nullable', DokumenUploadRule::pdfOnly()],
            'file_spesifikasi_teknis' => ['nullable', DokumenUploadRule::pdfOnly()],
        ]);

        $filePermohonanPath = $request->hasFile('file_surat_permohonan')
            ? $request->file('file_surat_permohonan')->store('permohonan', 'public')
            : null;

        $fileKakPath = $request->hasFile('file_kak')
            ? $request->file('file_kak')->store('kak', 'public')
            : null;

        $fileSpekPath = $request->hasFile('file_spesifikasi_teknis')
            ? $request->file('file_spesifikasi_teknis')->store('spek', 'public')
            : null;

        $kodeUnit = $user->kode_unit ?? $validated['kode_unit'] ?? 'TIK';

        // Buat baris data permohonan pengadaan pada tahap awal Bidang 2
        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => $validated['nomor_surat'],
            'judul_pengadaan' => $validated['perihal'],
            'tujuan_pengadaan_ringkas' => $validated['tujuan_pengadaan_ringkas'] ?? $validated['perihal'],
            'estimasi_total_pagu' => $validated['anggaran_diajukan'],
            'perihal' => $validated['perihal'],
            'anggaran_diajukan' => $validated['anggaran_diajukan'],
            'kode_unit' => $kodeUnit,
            'id_user' => $user->id_user,
            'tanggal_pengajuan' => $validated['tanggal_pengajuan'],
            'file_permohonan' => $filePermohonanPath ?? 'permohonan/dummy.pdf',
            'file_surat_permohonan' => $filePermohonanPath ?? 'permohonan/dummy.pdf',
            'file_kak' => $fileKakPath,
            'file_spesifikasi_teknis' => $fileSpekPath,
            'status_permohonan' => PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
            'status_anggaran' => 'pending',
            'status' => PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
        ]);

        // Inisialisasi antrean telaah untuk Wadir 2, Perencanaan, dan pemantauan Staf Bidang 2
        VerifikasiParalel::create([
            'id_user' => $user->id_user,
            'nomor_surat' => $permohonan->nomor_surat,
            'role_verifikator' => VerifikasiParalel::ROLE_STAFF_BIDANG_2,
            'status_keputusan' => 'menunggu',
        ]);
        VerifikasiParalel::create([
            'id_user' => $user->id_user,
            'nomor_surat' => $permohonan->nomor_surat,
            'role_verifikator' => VerifikasiParalel::ROLE_WADIR_2,
            'status_keputusan' => 'menunggu',
        ]);
        VerifikasiParalel::create([
            'id_user' => $user->id_user,
            'nomor_surat' => $permohonan->nomor_surat,
            'role_verifikator' => VerifikasiParalel::ROLE_PERENCANAAN,
            'status_keputusan' => 'menunggu',
        ]);

        // Catat ke jejak audit
        AuditLog::catat(
            $user->id_user,
            'AJUKAN_PERMOHONAN',
            'permohonan_pengadaan',
            $permohonan->nomor_surat,
            null,
            PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
            "Pengajuan usulan permohonan pengadaan nomor {$permohonan->nomor_surat} ke Bidang 2 oleh {$user->nama}"
        );

        // Memicu notifikasi siklus: Bidang 2 menerima alarm usulan baru
        $this->notificationService->notifyPengajuanBaru($permohonan);

        return redirect()->route('permohonan.index')->with(
            'success',
            "Usulan permohonan nomor {$permohonan->nomor_surat} berhasil diajukan ke Bidang II untuk pemeriksaan berkas dan penerbitan Memo Cek MAK."
        );
    }

    /**
     * Memproses pengajuan revisi surat usulan permohonan yang sebelumnya ditolak/perlu revisi secara in-place (Opsi A).
     * Mendukung pembaruan in-place maupun pewarisan ke nomor surat baru tanpa pemotongan ganda saldo pagu MAK.
     */
    public function revisi(Request $request): RedirectResponse
    {
        return $this->revisiPermohonan($request);
    }

    public function revisiPermohonan(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'nomor_surat_lama' => ['required', 'exists:permohonan_pengadaan,nomor_surat'],
            'nomor_surat_baru' => ['nullable', 'string', 'max:100'],
            'perihal' => ['required', 'string', 'max:255'],
            'tujuan_pengadaan_ringkas' => ['nullable', 'string', 'max:500'],
            'anggaran_diajukan' => ['required', 'numeric', 'min:1'],
            'keterangan_revisi' => ['nullable', 'string'],
            'file_kak' => ['nullable', DokumenUploadRule::pdfOnly()],
        ]);

        $induk = PermohonanPengadaan::findOrFail($validated['nomor_surat_lama']);
        $oldNomorSurat = $induk->nomor_surat;
        $targetNomorSurat = trim($validated['nomor_surat_baru'] ?? '');
        $isSameSurat = empty($targetNomorSurat) || ($targetNomorSurat === $oldNomorSurat);

        if (! $isSameSurat) {
            $isUsed = PermohonanPengadaan::where('nomor_surat', $targetNomorSurat)->exists();
            if ($isUsed) {
                return back()->withErrors(['nomor_surat_baru' => "Nomor surat {$targetNomorSurat} sudah terdaftar di sistem."])->withInput();
            }
        }

        $fileKakPath = $request->hasFile('file_kak')
            ? $request->file('file_kak')->store('kak', 'public')
            : $induk->file_kak;

        $nominalBaru = (float) $validated['anggaran_diajukan'];
        $nominalLama = (float) ($induk->anggaran_diajukan ?? $induk->estimasi_total_pagu ?? 0);
        $selisih = $nominalBaru - $nominalLama;
        $revisiKe = ($induk->revisi_ke ?? 0) + 1;

        $hasMak = ! empty($induk->kode_mak);
        $targetStatus = $hasMak
            ? PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN
            : PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2;

        // Ambil riwayat verifikasi paralel sebelum revisi
        $oldVerifWadir = VerifikasiParalel::where('nomor_surat', $oldNomorSurat)
            ->where('role_verifikator', VerifikasiParalel::ROLE_WADIR_2)
            ->first();
        $oldVerifPpk = VerifikasiParalel::where('nomor_surat', $oldNomorSurat)
            ->where('role_verifikator', VerifikasiParalel::ROLE_PPK)
            ->first();

        // Cek dan kelola saldo MAK agar tidak terjadi pemotongan ganda
        $mak = $induk->kode_mak ? AnggaranMAK::find($induk->kode_mak) : null;
        $alokasiLama = $induk->kode_mak
            ? AlokasiMAK::where('nomor_surat', $oldNomorSurat)->where('kode_mak', $induk->kode_mak)->first()
            : null;

        if ($mak && $alokasiLama) {
            if ($selisih > 0) {
                if ((float) $mak->sisa_pagu_mak < $selisih) {
                    return back()->with('error', "Penyesuaian anggaran melebihi sisa pagu MAK {$mak->kode_mak} yang tersedia (tersisa Rp ".number_format($mak->sisa_pagu_mak, 0, ',', '.').').')->withInput();
                }
                $mak->sisa_pagu_mak = max(0, (float) $mak->sisa_pagu_mak - $selisih);
                $mak->save();
            } elseif ($selisih < 0) {
                $mak->sisa_pagu_mak = (float) $mak->sisa_pagu_mak + abs($selisih);
                $mak->save();
            }
        }

        if ($isSameSurat) {
            // ==========================================
            // KASUS 1: In-Place Update pada Surat Sama
            // ==========================================
            DB::transaction(function () use ($induk, $validated, $nominalBaru, $fileKakPath, $revisiKe, $alokasiLama, $targetStatus) {
                if ($alokasiLama) {
                    $alokasiLama->update(['nominal_alokasi' => $nominalBaru]);
                }

                $induk->update([
                    'judul_pengadaan' => $validated['perihal'],
                    'perihal' => $validated['perihal'],
                    'tujuan_pengadaan_ringkas' => $validated['tujuan_pengadaan_ringkas'] ?? $induk->tujuan_pengadaan_ringkas ?? $validated['perihal'],
                    'anggaran_diajukan' => $nominalBaru,
                    'estimasi_total_pagu' => $nominalBaru,
                    'file_kak' => $fileKakPath,
                    'status_permohonan' => $targetStatus,
                    'status' => $targetStatus,
                    'revisi_ke' => $revisiKe,
                    'tanggal_revisi' => now(),
                    'alasan_revisi' => $validated['keterangan_revisi'] ?? 'Perbaikan dokumen KAK dan spesifikasi',
                ]);
            });

            $activePermohonan = $induk;
        } else {
            // ==========================================
            // KASUS 2: Surat Baru dengan Pewarisan MAK
            // ==========================================
            $activePermohonan = DB::transaction(function () use ($induk, $targetNomorSurat, $validated, $nominalBaru, $fileKakPath, $revisiKe, $user, $alokasiLama, $targetStatus) {
                $revisi = PermohonanPengadaan::create([
                    'nomor_surat' => $targetNomorSurat,
                    'judul_pengadaan' => $validated['perihal'],
                    'tujuan_pengadaan_ringkas' => $validated['tujuan_pengadaan_ringkas'] ?? $induk->tujuan_pengadaan_ringkas ?? $validated['perihal'],
                    'estimasi_total_pagu' => $nominalBaru,
                    'perihal' => $validated['perihal'],
                    'anggaran_diajukan' => $nominalBaru,
                    'kode_unit' => $induk->kode_unit,
                    'id_user' => $user->id_user,
                    'tanggal_pengajuan' => now(),
                    'file_permohonan' => $induk->file_permohonan ?? 'permohonan/dummy.pdf',
                    'file_surat_permohonan' => $induk->file_surat_permohonan ?? $induk->file_permohonan ?? 'permohonan/dummy.pdf',
                    'file_kak' => $fileKakPath,
                    'status_permohonan' => $targetStatus,
                    'status' => $targetStatus,
                    'revisi_ke' => $revisiKe,
                    'nomor_surat_revisi' => $induk->nomor_surat,
                    'tanggal_revisi' => now(),
                    'alasan_revisi' => $validated['keterangan_revisi'] ?? 'Perbaikan dokumen KAK dan spesifikasi',
                    'kode_mak' => $induk->kode_mak,
                    'status_anggaran' => $induk->status_anggaran ?? 'pending',
                    'id_user_ppk_awal' => $induk->id_user_ppk_awal,
                    'nomor_memo_ketersediaan_mak' => $induk->nomor_memo_ketersediaan_mak,
                    'catatan_memo_ketersediaan_mak' => $induk->catatan_memo_ketersediaan_mak,
                ]);

                // Transfer alokasi MAK dari surat lama ke surat baru
                if ($alokasiLama) {
                    AlokasiMAK::create([
                        'nomor_surat' => $revisi->nomor_surat,
                        'kode_mak' => $alokasiLama->kode_mak,
                        'nominal_alokasi' => $nominalBaru,
                        'tanggal_alokasi' => now(),
                    ]);
                    $alokasiLama->delete();
                }

                // Tandai surat lama sebagai telah digantikan oleh revisi baru
                $induk->update([
                    'status_permohonan' => 'digantikan_oleh_revisi',
                    'status' => 'digantikan_oleh_revisi',
                    'kode_mak' => null,
                ]);

                return $revisi;
            });
        }

        if ($hasMak) {
            // Staf Bidang II telah menyetujui pada tahapan sebelumnya
            VerifikasiParalel::updateOrCreate(
                ['nomor_surat' => $activePermohonan->nomor_surat, 'role_verifikator' => VerifikasiParalel::ROLE_STAFF_BIDANG_2],
                [
                    'id_user' => $user->id_user,
                    'status_keputusan' => 'disetujui',
                    'catatan_alasan' => 'Diverifikasi Staf Bidang II sebelum siklus revisi',
                    'tanggal_keputusan' => now(),
                ]
            );

            // Antrean masuk ke Bagian Perencanaan untuk konfirmasi MAK
            VerifikasiParalel::updateOrCreate(
                ['nomor_surat' => $activePermohonan->nomor_surat, 'role_verifikator' => VerifikasiParalel::ROLE_PERENCANAAN],
                [
                    'status_keputusan' => 'menunggu',
                    'catatan_alasan' => null,
                    'tanggal_keputusan' => null,
                ]
            );

            // WADIR 2: Jika sudah disetujui, TETAP disetujui beserta memo dan file fisiknya!
            if ($oldVerifWadir && $oldVerifWadir->status_keputusan === 'disetujui') {
                VerifikasiParalel::updateOrCreate(
                    ['nomor_surat' => $activePermohonan->nomor_surat, 'role_verifikator' => VerifikasiParalel::ROLE_WADIR_2],
                    [
                        'id_user' => $oldVerifWadir->id_user,
                        'status_keputusan' => 'disetujui',
                        'nomor_memo' => $oldVerifWadir->nomor_memo,
                        'file_memo' => $oldVerifWadir->file_memo,
                        'catatan_alasan' => $oldVerifWadir->catatan_alasan,
                        'tanggal_keputusan' => $oldVerifWadir->tanggal_keputusan,
                    ]
                );
            } else {
                VerifikasiParalel::updateOrCreate(
                    ['nomor_surat' => $activePermohonan->nomor_surat, 'role_verifikator' => VerifikasiParalel::ROLE_WADIR_2],
                    [
                        'id_user' => $oldVerifWadir?->id_user ?? $user->id_user,
                        'status_keputusan' => $oldVerifWadir?->status_keputusan ?? 'menunggu',
                        'catatan_alasan' => $oldVerifWadir?->catatan_alasan,
                    ]
                );
            }

            // PPK: Jika sudah disetujui, TETAP disetujui beserta memo dan file fisiknya!
            if ($oldVerifPpk && $oldVerifPpk->status_keputusan === 'disetujui') {
                VerifikasiParalel::updateOrCreate(
                    ['nomor_surat' => $activePermohonan->nomor_surat, 'role_verifikator' => VerifikasiParalel::ROLE_PPK],
                    [
                        'id_user' => $oldVerifPpk->id_user,
                        'status_keputusan' => 'disetujui',
                        'nomor_memo' => $oldVerifPpk->nomor_memo,
                        'file_memo' => $oldVerifPpk->file_memo,
                        'catatan_alasan' => $oldVerifPpk->catatan_alasan,
                        'tanggal_keputusan' => $oldVerifPpk->tanggal_keputusan,
                    ]
                );
            } else {
                VerifikasiParalel::updateOrCreate(
                    ['nomor_surat' => $activePermohonan->nomor_surat, 'role_verifikator' => VerifikasiParalel::ROLE_PPK],
                    [
                        'id_user' => $oldVerifPpk?->id_user ?? $activePermohonan->id_user_ppk_awal,
                        'status_keputusan' => $oldVerifPpk?->status_keputusan ?? 'menunggu',
                        'catatan_alasan' => $oldVerifPpk?->catatan_alasan,
                    ]
                );
            }

            // Catat jejak audit revisi
            AuditLog::catat(
                $user->id_user,
                'REVISI_PERMOHONAN_PASCA_MAK',
                'permohonan_pengadaan',
                $activePermohonan->nomor_surat,
                $oldNomorSurat !== $activePermohonan->nomor_surat ? $oldNomorSurat : null,
                PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN,
                "Revisi usulan permohonan ke-{$revisiKe} ({$activePermohonan->nomor_surat}) pasca alokasi MAK diteruskan ke Bagian Perencanaan."
            );

            // Memicu notifikasi ke Bagian Perencanaan
            $this->notificationService->notifyRevisiKePerencanaan($activePermohonan);

            return redirect()->route('permohonan.index')->with(
                'success',
                "Usulan permohonan nomor {$activePermohonan->nomor_surat} berhasil direvisi (Revisi ke-{$activePermohonan->revisi_ke}) dan diajukan kembali ke Bagian Perencanaan untuk konfirmasi MAK."
            );
        }

        // Inisialisasi antrean awal kembali di Staf Bidang 2 jika belum memiliki MAK
        VerifikasiParalel::updateOrCreate(
            ['nomor_surat' => $activePermohonan->nomor_surat, 'role_verifikator' => VerifikasiParalel::ROLE_STAFF_BIDANG_2],
            [
                'id_user' => $user->id_user,
                'status_keputusan' => 'menunggu',
                'catatan_alasan' => null,
                'tanggal_keputusan' => null,
            ]
        );

        // Reset keputusan Wadir 2 dan PPK
        VerifikasiParalel::where('nomor_surat', $activePermohonan->nomor_surat)
            ->whereIn('role_verifikator', [VerifikasiParalel::ROLE_WADIR_2, VerifikasiParalel::ROLE_PPK])
            ->update([
                'status_keputusan' => 'menunggu',
                'catatan_alasan' => null,
                'tanggal_keputusan' => null,
            ]);

        // Catat jejak audit revisi
        AuditLog::catat(
            $user->id_user,
            'REVISI_PERMOHONAN',
            'permohonan_pengadaan',
            $activePermohonan->nomor_surat,
            $oldNomorSurat !== $activePermohonan->nomor_surat ? $oldNomorSurat : null,
            PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
            "Revisi usulan permohonan ke-{$revisiKe} ({$activePermohonan->nomor_surat}) oleh {$user->nama}"
        );

        // Memicu notifikasi siklus revisi berkas usulan ke Bidang 2
        $this->notificationService->notifyPengajuanRevisi($activePermohonan);

        return redirect()->route('permohonan.index')->with(
            'success',
            "Usulan permohonan nomor {$activePermohonan->nomor_surat} berhasil direvisi (Revisi ke-{$activePermohonan->revisi_ke}) dan diajukan kembali ke antrean Bidang II."
        );
    }

    /**
     * Membatalkan dan menghapus usulan permohonan pengadaan status draft/diajukan/ditolak.
     */
    public function destroy(string $id): RedirectResponse
    {
        return $this->destroyPermohonan($id);
    }

    public function destroyPermohonan(string $id): RedirectResponse
    {
        $user = Auth::user();
        $permohonan = PermohonanPengadaan::where('nomor_surat', $id)
            ->orWhere('nomor_surat', urldecode($id))
            ->orWhere('nomor_surat', rawurldecode($id))
            ->firstOrFail();

        $isAdmin = $user->hasRole('admin') || $user->role === User::ROLE_ADMIN || ($user->roleEntity && $user->roleEntity->nama_role === User::ROLE_ADMIN);

        if (! $isAdmin) {
            if ((int) $permohonan->id_user !== (int) $user->id_user) {
                abort(403, 'Akses Ditolak: Anda hanya dapat membatalkan permohonan milik sendiri.');
            }

            if (! in_array($permohonan->status_permohonan, ['draft', 'diajukan', 'ditolak'], true)) {
                return redirect()->route('permohonan.index')->with('error', 'Permohonan yang telah diverifikasi atau diproses paket tidak dapat dibatalkan.');
            }
        }

        $nomor = $permohonan->nomor_surat;

        DB::transaction(function () use ($permohonan) {
            $permohonan->cascadeDelete();
        });

        AuditLog::catat(
            $user->id_user,
            'HAPUS_PERMOHONAN',
            'permohonan_pengadaan',
            $nomor,
            null,
            'dihapus',
            "Pembatalan dan penghapusan usulan permohonan nomor {$nomor} oleh {$user->nama}"
        );

        return redirect()->route('permohonan.index')->with('success', "Usulan permohonan nomor {$nomor} berhasil dibatalkan dan dihapus.");
    }

    /**
     * Sinkronisasi otomatis status seluruh usulan yang telah disetujui penuh secara paralel
     * (MAK teralokasi di Perencanaan, Wadir 2 menyetujui, dan PPK menyetujui)
     * agar otomatis berstatus 'disetujui' dan siap diproses oleh PPBJ.
     */
    public function syncAllPermohonanDisetujuiParalel(): void
    {
        $kandidat = PermohonanPengadaan::with(['verifikasiParalel'])
            ->whereNotIn('status_permohonan', [
                PermohonanPengadaan::STATUS_DISETUJUI,
                PermohonanPengadaan::STATUS_DIPROSES_PPBJ,
                PermohonanPengadaan::STATUS_DITOLAK,
                PermohonanPengadaan::STATUS_SELESAI,
                'digantikan_oleh_revisi',
            ])
            ->whereNotNull('kode_mak')
            ->get();

        foreach ($kandidat as $p) {
            $perencanaanAcc = $p->verifikasiParalel
                ->where('role_verifikator', VerifikasiParalel::ROLE_PERENCANAAN)
                ->where('status_keputusan', 'disetujui')
                ->isNotEmpty() || ! empty($p->kode_mak);

            $wadirAcc = $p->verifikasiParalel
                ->where('role_verifikator', VerifikasiParalel::ROLE_WADIR_2)
                ->where('status_keputusan', 'disetujui')
                ->isNotEmpty();

            // Evaluasi kelulusan paralel (AND-join): Perencanaan (MAK) + Wadir 2 (ACC) + PPK (ACC)
            $ppkAcc = $p->verifikasiParalel
                ->where('role_verifikator', VerifikasiParalel::ROLE_PPK)
                ->where('status_keputusan', 'disetujui')
                ->isNotEmpty();

            if ($perencanaanAcc && $wadirAcc && $ppkAcc) {
                $statusLama = $p->status_permohonan;
                $p->status = 'disetujui';
                $p->status_permohonan = PermohonanPengadaan::STATUS_DISETUJUI;
                $p->save();

                AuditLog::catat(
                    Auth::id() ?? $p->id_user,
                    'PERSETUJUAN_PENUH_AWAL_KE_PPBJ',
                    'permohonan_pengadaan',
                    $p->nomor_surat,
                    $statusLama,
                    'disetujui',
                    'Sinkronisasi otomatis: Usulan disetujui penuh oleh Perencanaan, Wadir 2, dan PPK terpilih; diteruskan ke PPBJ.'
                );
            }
        }
    }
}
