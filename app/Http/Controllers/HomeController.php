<?php

namespace App\Http\Controllers;

use App\Models\AnggaranMAK;
use App\Models\AuditLog;
use App\Models\KontrakSPK;
use App\Models\NotifikasiUser;
use App\Models\PaketPengadaan;
use App\Models\PenerimaanBarang;
use App\Models\PermohonanPengadaan;
use App\Models\RiwayatPenugasanPPK;
use App\Models\TransaksiPencairan;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VerifikasiParalel;
use App\Rules\DokumenUploadRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class HomeController extends Controller
{
    /**
     * Tampilkan halaman tes beranda (home) yang terhubung dengan database.
     */
    public function index(): View
    {
        $user = Auth::user();
        if ($user) {
            $user->load('unit');
        }

        // Statistik real-time dari database
        $stats = [
            'total_units' => Unit::count(),
            'total_permohonan' => PermohonanPengadaan::count(),
            'total_anggaran' => AnggaranMAK::sum('pagu_anggaran'),
            'total_paket' => PaketPengadaan::count(),
            'total_vendor' => Vendor::count(),
        ];

        // Daftar data terbaru (NFR RBAC: Berkas terisolasi sesuai wewenang dan akun PPK)
        $permohonanQuery = PermohonanPengadaan::with(['unit', 'user']);
        if ($user && $user->role === 'user') {
            $permohonanQuery->where('id_user', $user->id_user);
        }
        $recentPermohonan = $permohonanQuery->latest('tanggal_pengajuan')->take(5)->get();

        $paketQuery = PaketPengadaan::with(['permohonan', 'ppkUser', 'ppUser']);
        if ($user) {
            $paketQuery->forUser($user);
        }
        $recentPaket = $paketQuery->latest('tanggal_registrasi_paket')->take(5)->get();

        $notifications = $user ? NotifikasiUser::with('reminder.paket')
            ->where('id_user', $user->id_user)
            ->where('is_dismissed', false)
            ->latest('id')
            ->take(5)
            ->get() : collect();

        $units = Unit::all();

        return view('tes_home', compact('user', 'stats', 'recentPermohonan', 'recentPaket', 'notifications', 'units'));
    }

    /**
     * Tampilkan halaman tes API backend.
     */
    public function testApi(): View
    {
        return view('tes_api');
    }

    /**
     * Tampilkan antarmuka simulasi permohonan pengadaan & revisi Srikandi (FR 3).
     */
    public function tesPermohonan(Request $request): View
    {
        $user = Auth::user();
        $query = PermohonanPengadaan::with(['unit', 'user', 'verifikasiParalel', 'suratAsalRevisi']);

        if ($user) {
            $query->forUser($user);
        }

        $permohonanList = $query->latest('tanggal_pengajuan')->paginate(10);
        $permohonanDitolak = PermohonanPengadaan::where('status_permohonan', PermohonanPengadaan::STATUS_DITOLAK)
            ->when($user && $user->role === 'user', fn ($q) => $q->where('id_user', $user->id_user))
            ->get();
        $units = Unit::all();

        return view('tes_permohonan', compact('user', 'permohonanList', 'units', 'permohonanDitolak'));
    }

    /**
     * Simpan pengajuan permohonan baru dari antarmuka web simulasi (FR 3.1 - FR 3.3).
     */
    public function storePermohonan(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'nomor_surat' => 'required|string|max:100|unique:permohonan_pengadaan,nomor_surat',
            'judul_pengadaan' => 'required|string|max:255',
            'tujuan_pengadaan_ringkas' => 'required|string',
            'file_permohonan' => ['required', DokumenUploadRule::pdfOnly()],
            'estimasi_total_pagu' => 'nullable|numeric|min:0',
        ]);

        $filePath = $request->file('file_permohonan')->store('permohonan', 'public');

        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => $validated['nomor_surat'],
            'judul_pengadaan' => $validated['judul_pengadaan'],
            'tujuan_pengadaan_ringkas' => $validated['tujuan_pengadaan_ringkas'],
            'file_permohonan' => $filePath,
            'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
            'status_anggaran' => 'pending',
            'estimasi_total_pagu' => $validated['estimasi_total_pagu'],
            'tanggal_pengajuan' => now(),
            'kode_unit' => $user->kode_unit ?? Unit::first()->kode_unit,
            'id_user' => $user->id_user,
        ]);

        VerifikasiParalel::create([
            'id_user' => $user->id_user,
            'nomor_surat' => $permohonan->nomor_surat,
            'role_verifikator' => 'perencanaan',
            'status_keputusan' => 'menunggu',
        ]);

        VerifikasiParalel::create([
            'id_user' => $user->id_user,
            'nomor_surat' => $permohonan->nomor_surat,
            'role_verifikator' => 'wadir_2',
            'status_keputusan' => 'menunggu',
        ]);

        return redirect()->route('permohonan.web')->with('success', 'Permohonan pengadaan nomor '.$permohonan->nomor_surat.' berhasil diajukan dan diteruskan ke Verifikasi Paralel (FR 3.1 - FR 3.3)!');
    }

    /**
     * Simpan pengajuan revisi permohonan yang ditolak dari antarmuka web simulasi (FR 3.4).
     */
    public function revisiPermohonan(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'nomor_surat_lama' => 'required|exists:permohonan_pengadaan,nomor_surat',
            'nomor_surat_baru' => 'required|string|max:100|unique:permohonan_pengadaan,nomor_surat',
            'judul_pengadaan' => 'required|string|max:255',
            'tujuan_pengadaan_ringkas' => 'required|string',
            'file_permohonan' => ['required', DokumenUploadRule::pdfOnly()],
            'estimasi_total_pagu' => 'nullable|numeric|min:0',
            'alasan_revisi' => 'required|string',
        ]);

        $induk = PermohonanPengadaan::findOrFail($validated['nomor_surat_lama']);
        $filePath = $request->file('file_permohonan')->store('permohonan', 'public');

        $revisi = PermohonanPengadaan::create([
            'nomor_surat' => $validated['nomor_surat_baru'],
            'judul_pengadaan' => $validated['judul_pengadaan'],
            'tujuan_pengadaan_ringkas' => $validated['tujuan_pengadaan_ringkas'],
            'file_permohonan' => $filePath,
            'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
            'status_anggaran' => 'pending',
            'revisi_ke' => $induk->revisi_ke + 1,
            'estimasi_total_pagu' => $validated['estimasi_total_pagu'] ?? $induk->estimasi_total_pagu,
            'tanggal_pengajuan' => now(),
            'kode_unit' => $induk->kode_unit,
            'id_user' => $user->id_user,
            'nomor_surat_revisi' => $induk->nomor_surat,
            'tanggal_revisi' => now(),
            'alasan_revisi' => $validated['alasan_revisi'],
        ]);

        VerifikasiParalel::create([
            'id_user' => $revisi->id_user,
            'nomor_surat' => $revisi->nomor_surat,
            'role_verifikator' => 'perencanaan',
            'status_keputusan' => 'menunggu',
        ]);

        VerifikasiParalel::create([
            'id_user' => $revisi->id_user,
            'nomor_surat' => $revisi->nomor_surat,
            'role_verifikator' => 'wadir_2',
            'status_keputusan' => 'menunggu',
        ]);

        return redirect()->route('permohonan.web')->with('success', 'Revisi permohonan berhasil diterbitkan dengan nomor baru: '.$revisi->nomor_surat.' (FR 3.4)');
    }

    /**
     * Tampilkan antarmuka simulasi monitoring dashboard status real-time (NFR 7).
     */
    public function tesMonitoring(Request $request): View
    {
        $user = Auth::user();
        $selectedNomor = $request->query('nomor_surat');
        $trackingData = null;

        if ($selectedNomor) {
            $permohonan = PermohonanPengadaan::find($selectedNomor);
            if ($permohonan) {
                $controller = app(PermohonanPengadaanController::class);
                $trackingData = $controller->tracking($permohonan)->getData(true);
            }
        }

        $allPermohonan = PermohonanPengadaan::with('unit')->latest('tanggal_pengajuan')->take(20)->get();

        return view('tes_monitoring', compact('user', 'selectedNomor', 'trackingData', 'allPermohonan'));
    }

    /**
     * Tampilkan antarmuka simulasi paket pengadaan, threshold routing & reassign PPK (NFR 4 & FR 7).
     */
    public function tesPaket(Request $request): View
    {
        $user = Auth::user();
        $query = PaketPengadaan::with(['permohonan.unit', 'ppbjUser', 'ppkUser', 'ppUser', 'riwayatPpk.ppkBaru']);

        if ($user) {
            $query->forUser($user);
        }

        $paketList = $query->latest('tanggal_registrasi_paket')->paginate(10);
        $permohonanList = PermohonanPengadaan::whereIn('status_permohonan', [
            PermohonanPengadaan::STATUS_DISETUJUI,
            PermohonanPengadaan::STATUS_DIPROSES_PPBJ,
            PermohonanPengadaan::STATUS_REVIU_PARALEL,
        ])->get();

        $ppkUsers = User::where('role', 'ppk')->where('status_aktif', true)->get();
        $ppUsers = User::where('role', 'pp')->where('status_aktif', true)->get();

        return view('tes_paket', compact('user', 'paketList', 'permohonanList', 'ppkUsers', 'ppUsers'));
    }

    /**
     * Simpan paket pengadaan baru dari form simulasi (NFR 4 & FR 8).
     */
    public function storePaket(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'nomor_paket' => 'required|string|max:100|unique:paket_pengadaan,nomor_paket',
            'nama_paket' => 'required|string|max:255',
            'jenis_pengadaan' => 'required|string|in:barang,jasa_lainnya,konstruksi,jasa_konsultansi',
            'platform_pengadaan' => 'required|string|max:50',
            'mekanisme_pengadaan' => 'required|string|max:50',
            'nilai_hps' => 'required|numeric|gt:0',
            'alokasi_pagu_paket' => 'required|numeric|gt:0',
            'nomor_surat' => 'required|exists:permohonan_pengadaan,nomor_surat',
            'id_user_ppk' => 'nullable|exists:users,id_user',
            'id_user_pp' => 'nullable|exists:users,id_user',
        ]);

        $validated['id_user_ppbj'] = $user?->id_user ?? User::where('role', 'ppbj')->first()->id_user;
        $validated['jalur_routing'] = PaketPengadaan::tentukanRouting((float) $validated['nilai_hps']);
        $validated['tanggal_registrasi_paket'] = now();
        $validated['status_paket'] = 'draft';

        if (! empty($validated['id_user_ppk'])) {
            $validated['tanggal_penugasan_ppk'] = now();
            $validated['status_penugasan_ppk'] = 'ditugaskan';
        }

        if (! empty($validated['id_user_pp'])) {
            $validated['tanggal_penugasan_pp'] = now();
        }

        $paket = PaketPengadaan::create($validated);

        return redirect()->route('paket.web')->with('success', 'Paket pengadaan '.$paket->nomor_paket.' berhasil didaftarkan dengan jalur routing otomatis: '.$paket->jalur_routing.' (NFR 4)');
    }

    /**
     * Alih tugas PPK dari antarmuka simulasi web (NFR 4 & FR 7.3).
     */
    public function reassignPpkWeb(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'nomor_paket' => 'required|exists:paket_pengadaan,nomor_paket',
            'ppk_baru_user_id' => 'required|exists:users,id_user',
            'alasan_perubahan' => 'required|string',
        ]);

        $ppkBaru = User::find($validated['ppk_baru_user_id']);
        if (! $ppkBaru || $ppkBaru->role !== 'ppk') {
            return redirect()->back()->withErrors(['ppk_baru_user_id' => 'Akun tujuan pengalihan harus merupakan salah satu dari 4 akun PPK aktif (NFR 4).']);
        }

        $paket = PaketPengadaan::findOrFail($validated['nomor_paket']);
        $ppkLama = $paket->id_user_ppk;

        $paket->id_user_ppk = $validated['ppk_baru_user_id'];
        $paket->tanggal_penugasan_ppk = now();
        $paket->alasan_pengalihan_ppk = $validated['alasan_perubahan'];
        $paket->save();

        RiwayatPenugasanPPK::create([
            'nomor_paket' => $paket->nomor_paket,
            'ppk_lama_user_id' => $ppkLama,
            'ppk_baru_user_id' => $validated['ppk_baru_user_id'],
            'diubah_oleh_user_id' => $user?->id_user ?? User::first()->id_user,
            'alasan_perubahan' => $validated['alasan_perubahan'],
            'tanggal_penugasan' => now(),
        ]);

        AuditLog::catat(
            $user?->id_user ?? User::first()->id_user,
            'REASSIGN_PPK',
            'paket_pengadaan',
            $paket->nomor_paket,
            $ppkLama ? (string) $ppkLama : 'belum_ada',
            (string) $validated['ppk_baru_user_id'],
            'Pengalihan tugas PPK paket pengadaan (NFR 4 & FR 7.3): '.$validated['alasan_perubahan']
        );

        return redirect()->route('paket.web')->with('success', 'Penugasan PPK untuk paket '.$paket->nomor_paket.' berhasil dialihkan ke '.$ppkBaru->nama.' (NFR 4)');
    }

    /**
     * Tampilkan simulasi alur pemeriksaan Staff B2 dan persetujuan mutlak Wadir 2 (FR 5).
     */
    public function tesVerifikasiB2(Request $request): View
    {
        $user = Auth::user();

        $permohonanList = PermohonanPengadaan::with(['unit', 'user', 'verifikasiParalel.user'])
            ->whereIn('status_permohonan', [
                PermohonanPengadaan::STATUS_REVIU_PARALEL,
                PermohonanPengadaan::STATUS_DISETUJUI,
                PermohonanPengadaan::STATUS_DITOLAK,
                PermohonanPengadaan::STATUS_PENDING_ANGGARAN,
            ])
            ->latest('tanggal_pengajuan')
            ->paginate(10);

        return view('tes_verifikasi_b2', compact('user', 'permohonanList'));
    }

    /**
     * Staff B2 meneruskan berkas masuk ke Wadir 2 (FR 5.1).
     */
    public function teruskanStaffB2(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'nomor_surat' => 'required|exists:permohonan_pengadaan,nomor_surat',
            'catatan_pemeriksaan' => 'nullable|string',
        ]);

        $permohonan = PermohonanPengadaan::findOrFail($validated['nomor_surat']);

        AuditLog::catat(
            $user?->id_user ?? User::first()->id_user,
            'STAFF_B2_TERUSKAN_KE_WADIR',
            'permohonan_pengadaan',
            $permohonan->nomor_surat,
            $permohonan->status_permohonan,
            $permohonan->status_permohonan,
            'Staff Bidang 2 memeriksa berkas dan menyampaikan usulan permohonan ke Wadir 2 (FR 5.1): '.($validated['catatan_pemeriksaan'] ?? 'Berkas lengkap')
        );

        return redirect()->route('verifikasi-b2.web')->with('success', 'Permohonan '.$permohonan->nomor_surat.' berhasil disampaikan ke Wadir 2 untuk persetujuan eksekutif (FR 5.1)!');
    }

    /**
     * Wadir 2 membubuhkan keputusan persetujuan mutlak ACC / Tolak / Revisi (FR 5.2 - FR 5.4).
     */
    public function keputusanWadir2(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'nomor_surat' => 'required|exists:permohonan_pengadaan,nomor_surat',
            'status_keputusan' => 'required|in:disetujui,ditolak,revisi',
            'catatan_alasan' => 'required_if:status_keputusan,ditolak,revisi|nullable|string',
        ], [
            'catatan_alasan.required_if' => 'Catatan alasan wajib diisi saat Wadir 2 menolak atau meminta revisi usulan (FR 5.3).',
        ]);

        $verifWadir = VerifikasiParalel::firstOrCreate(
            ['nomor_surat' => $validated['nomor_surat'], 'role_verifikator' => 'wadir_2'],
            ['id_user' => $user?->id_user ?? User::where('role', 'wadir_2')->first()->id_user, 'status_keputusan' => 'menunggu']
        );

        $verifWadir->id_user = $user?->id_user ?? $verifWadir->id_user;
        $verifWadir->status_keputusan = $validated['status_keputusan'];
        $verifWadir->catatan_alasan = $validated['catatan_alasan'];
        $verifWadir->tanggal_keputusan = now();
        $verifWadir->save();

        // Cek kelulusan verifikasi paralel (Dual approval dengan Perencanaan)
        $permohonan = PermohonanPengadaan::find($validated['nomor_surat']);
        if ($permohonan) {
            if ($validated['status_keputusan'] === 'ditolak') {
                $permohonan->status_permohonan = PermohonanPengadaan::STATUS_DITOLAK;
                $permohonan->save();
            } elseif ($validated['status_keputusan'] === 'disetujui') {
                // Cek apakah Perencanaan sudah menyetujui
                $perencanaanOk = VerifikasiParalel::where('nomor_surat', $permohonan->nomor_surat)
                    ->where('role_verifikator', 'perencanaan')
                    ->where('status_keputusan', 'disetujui')
                    ->exists();

                if ($perencanaanOk) {
                    $permohonan->status_permohonan = PermohonanPengadaan::STATUS_DISETUJUI;
                    $permohonan->status_anggaran = 'disetujui';
                    $permohonan->save();

                    AuditLog::catat(
                        $user?->id_user ?? User::first()->id_user,
                        'PERMOHONAN_DISETUJUI_PENUH_KE_PPBJ',
                        'permohonan_pengadaan',
                        $permohonan->nomor_surat,
                        PermohonanPengadaan::STATUS_REVIU_PARALEL,
                        PermohonanPengadaan::STATUS_DISETUJUI,
                        'Permohonan disetujui penuh oleh Wadir 2 dan Perencanaan, otomatis diteruskan ke PPBJ (FR 5.4)'
                    );
                }
            }
        }

        return redirect()->route('verifikasi-b2.web')->with('success', 'Keputusan Wadir 2 untuk usulan '.$validated['nomor_surat'].' berhasil disimpan dengan status: '.strtoupper($validated['status_keputusan']).' (FR 5.2 - FR 5.4)');
    }

    /**
     * Unduh berkas PDF resmi dari antarmuka simulasi web (NFR 12).
     */
    public function unduhDokumenPdf(Request $request, string $jenis, string $id): Response
    {
        return match ($jenis) {
            'permohonan' => app(PermohonanPengadaanController::class)->unduhPdf(PermohonanPengadaan::findOrFail($id)),
            'spk' => app(KontrakSPKController::class)->unduhPdf(KontrakSPK::findOrFail($id)),
            'bast' => app(PenerimaanBarangController::class)->unduhPdf(PenerimaanBarang::findOrFail($id)),
            'transfer' => app(TransaksiPencairanController::class)->unduhPdf(TransaksiPencairan::findOrFail($id)),
            default => abort(404, 'Jenis dokumen PDF tidak dikenali.'),
        };
    }
}
