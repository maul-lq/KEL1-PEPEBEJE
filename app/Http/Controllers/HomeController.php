<?php

namespace App\Http\Controllers;

use App\Models\AnggaranMAK;
use App\Models\NotifikasiUser;
use App\Models\PaketPengadaan;
use App\Models\PermohonanPengadaan;
use App\Models\Unit;
use App\Models\Vendor;
use App\Models\VerifikasiParalel;
use App\Rules\DokumenUploadRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

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
}
