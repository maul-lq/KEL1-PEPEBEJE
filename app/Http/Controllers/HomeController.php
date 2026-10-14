<?php

namespace App\Http\Controllers;

use App\Models\AnggaranMAK;
use App\Models\NotifikasiUser;
use App\Models\PaketPengadaan;
use App\Models\PermohonanPengadaan;
use App\Models\Unit;
use App\Models\Vendor;
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

        // Daftar data terbaru
        $recentPermohonan = PermohonanPengadaan::with(['unit', 'user'])
            ->latest('tanggal_pengajuan')
            ->take(5)
            ->get();

        $recentPaket = PaketPengadaan::with(['permohonan', 'ppkUser', 'ppUser'])
            ->latest('tanggal_registrasi_paket')
            ->take(5)
            ->get();

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
}
