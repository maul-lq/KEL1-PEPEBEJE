<?php

namespace App\Http\Controllers;

use App\Models\Pengadaan;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ReviewPpkController extends Controller
{
    public function showReview(Pengadaan $pengadaan): View
    {
        $pengadaan->load(['user', 'items', 'wadir2', 'perencanaan', 'ppbj']);

        return view('roles.ppk.review', compact('pengadaan'));
    }

    public function approve(Request $request, Pengadaan $pengadaan): RedirectResponse
    {
        $request->validate([
            'catatan_ppk' => ['nullable', 'string'],
        ]);

        $oldStatus = $pengadaan->status;
        $pengadaan->ppk_user_id = Auth::id();
        $pengadaan->status_reviu_ppk = 'disetujui';
        $pengadaan->catatan_ppk = $request->input('catatan_ppk', 'Dokumen spesifikasi teknis dan HPS disetujui.');
        $pengadaan->tanggal_reviu_ppk = now();
        $pengadaan->status = Pengadaan::STATUS_DISETUJUI_PPK;
        $pengadaan->save();

        AuditLogService::log(
            $pengadaan,
            'Persetujuan Reviu PPK/PP',
            'PPK/PP menyetujui dokumen pengadaan. PPBJ diizinkan melanjutkan ke pembuatan SPK dan kontrak penyedia.',
            $oldStatus,
            $pengadaan->status
        );

        return redirect()->route('pengadaan.show', $pengadaan)
            ->with('success', 'Dokumen pengadaan disetujui oleh PPK/PP. Silakan PPBJ menerbitkan SPK.');
    }

    public function mintaPerbaikan(Request $request, Pengadaan $pengadaan): RedirectResponse
    {
        $request->validate([
            'catatan_ppk' => ['required', 'string'],
        ]);

        $oldStatus = $pengadaan->status;
        $pengadaan->ppk_user_id = Auth::id();
        $pengadaan->status_reviu_ppk = 'perbaikan';
        $pengadaan->catatan_ppk = $request->input('catatan_ppk');
        $pengadaan->tanggal_reviu_ppk = now();
        $pengadaan->status = Pengadaan::STATUS_REVISI_PPK;
        $pengadaan->save();

        AuditLogService::log(
            $pengadaan,
            'Catatan Perbaikan Dokumen oleh PPK/PP',
            'PPK/PP mengembalikan dokumen pengadaan ke Koordinator PPBJ untuk diperbaiki. Catatan: '.$request->input('catatan_ppk'),
            $oldStatus,
            $pengadaan->status
        );

        return redirect()->route('pengadaan.show', $pengadaan)
            ->with('warning', 'Dokumen dikembalikan ke PPBJ untuk perbaikan.');
    }
}
