<?php

namespace App\Http\Controllers;

use App\Models\Pengadaan;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();

        // 1. Hitung statistik umum
        $totalPengadaan = Pengadaan::count();
        $totalSelesai = Pengadaan::where('status', Pengadaan::STATUS_SELESAI)->count();
        $totalBerjalan = Pengadaan::whereNotIn('status', [Pengadaan::STATUS_SELESAI, Pengadaan::STATUS_DITOLAK_WADIR2, Pengadaan::STATUS_DIBATALKAN])->count();

        // 2. Query pengadaan yang membutuhkan aksi spesifik dari role pengguna saat ini
        $queryAksi = Pengadaan::query();

        switch ($user->role) {
            case User::ROLE_USER:
                $queryAksi->where('user_id', $user->id);
                break;
            case User::ROLE_WADIR2:
                $queryAksi->whereIn('status', [
                    Pengadaan::STATUS_DIAJUKAN,
                    Pengadaan::STATUS_MEMO_PEMBAYARAN_PPK,
                ]);
                break;
            case User::ROLE_PERENCANAAN:
                $queryAksi->where('status', Pengadaan::STATUS_DISETUJUI_WADIR2);
                break;
            case User::ROLE_PPBJ:
                $queryAksi->whereIn('status', [
                    Pengadaan::STATUS_MAK_DITETAPKAN,
                    Pengadaan::STATUS_REVISI_PPK,
                    Pengadaan::STATUS_DISETUJUI_PPK,
                    Pengadaan::STATUS_BARANG_DITERIMA,
                    Pengadaan::STATUS_MEMO_PEMBAYARAN_WADIR2,
                ]);
                break;
            case User::ROLE_PPK_PP:
                $queryAksi->whereIn('status', [
                    Pengadaan::STATUS_TEREGISTRASI_PPBJ,
                    Pengadaan::STATUS_PENGAJUAN_PEMBAYARAN,
                ]);
                break;
            case User::ROLE_PERLENGKAPAN:
                $queryAksi->whereIn('status', [
                    Pengadaan::STATUS_SPK_DITERBITKAN,
                    Pengadaan::STATUS_BARANG_PENDING,
                ]);
                break;
            case User::ROLE_KEUANGAN:
                $queryAksi->where('status', Pengadaan::STATUS_SP_PEMBAYARAN_TERBIT);
                break;
        }

        $pendingTasks = (clone $queryAksi)->with(['user', 'vendor'])->latest()->take(5)->get();
        $pendingTasksCount = (clone $queryAksi)->count();

        // 3. Riwayat terbaru
        $recentPengadaans = Pengadaan::with(['user', 'vendor'])->latest()->take(6)->get();

        // 4. Pengingat / Alarm Popup untuk role ini (FR 12.0)
        $activeReminders = Reminder::where('is_dismissed', false)
            ->where(function ($q) use ($user) {
                $q->where('target_role', $user->role)
                    ->orWhere('user_id', $user->id)
                    ->orWhereNull('target_role');
            })
            ->with('pengadaan')
            ->latest()
            ->get();

        return view('dashboard.index', compact(
            'totalPengadaan',
            'totalSelesai',
            'totalBerjalan',
            'pendingTasksCount',
            'pendingTasks',
            'recentPengadaans',
            'activeReminders'
        ));
    }

    /**
     * Dashboard monitoring status posisi berkas secara real-time bagi seluruh role (FR 7.0 & 19.0)
     */
    public function monitoring(Request $request): View
    {
        $search = $request->input('search');
        $jalur = $request->input('jalur');
        $status = $request->input('status');

        $query = Pengadaan::with(['user', 'vendor', 'perencanaan', 'ppbj', 'ppk', 'perlengkapan', 'keuangan']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_pengadaan', 'like', "%{$search}%")
                    ->orWhere('nomor_pengadaan', 'like', "%{$search}%")
                    ->orWhere('nomor_mak', 'like', "%{$search}%")
                    ->orWhere('nomor_spk', 'like', "%{$search}%");
            });
        }

        if ($jalur) {
            $query->where('jalur_pengadaan', $jalur);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $pengadaans = $query->latest()->paginate(10)->withQueryString();

        return view('dashboard.monitoring', compact('pengadaans'));
    }
}
