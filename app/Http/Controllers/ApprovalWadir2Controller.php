<?php

namespace App\Http\Controllers;

use App\Models\Pengadaan;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ApprovalWadir2Controller extends Controller
{
    public function showReview(Pengadaan $pengadaan): View
    {
        $pengadaan->load(['user', 'items']);

        return view('roles.wadir2.review', compact('pengadaan'));
    }

    public function approve(Request $request, Pengadaan $pengadaan): RedirectResponse
    {
        $request->validate([
            'catatan_wadir2' => ['nullable', 'string'],
        ]);

        $oldStatus = $pengadaan->status;
        $pengadaan->status = Pengadaan::STATUS_DISETUJUI_WADIR2;
        $pengadaan->wadir2_user_id = Auth::id();
        $pengadaan->catatan_wadir2 = $request->input('catatan_wadir2', 'Disetujui tanpa catatan.');
        $pengadaan->tanggal_persetujuan_wadir2 = now();
        $pengadaan->save();

        AuditLogService::log(
            $pengadaan,
            'Persetujuan Wadir 2',
            'Wadir 2 menyetujui surat permohonan pengadaan. Berkas otomatis diteruskan ke Bagian Perencanaan untuk penentuan MAK dan PPBJ.',
            $oldStatus,
            $pengadaan->status
        );

        return redirect()->route('pengadaan.show', $pengadaan)
            ->with('success', 'Permohonan berhasil disetujui dan diteruskan ke Bagian Perencanaan.');
    }

    public function reject(Request $request, Pengadaan $pengadaan): RedirectResponse
    {
        $request->validate([
            'catatan_wadir2' => ['required', 'string'],
        ]);

        $oldStatus = $pengadaan->status;
        $pengadaan->status = Pengadaan::STATUS_DITOLAK_WADIR2;
        $pengadaan->wadir2_user_id = Auth::id();
        $pengadaan->catatan_wadir2 = $request->input('catatan_wadir2');
        $pengadaan->tanggal_persetujuan_wadir2 = now();
        $pengadaan->save();

        AuditLogService::log(
            $pengadaan,
            'Penolakan Wadir 2',
            'Wadir 2 menolak permohonan pengadaan dengan catatan: '.$request->input('catatan_wadir2'),
            $oldStatus,
            $pengadaan->status
        );

        return redirect()->route('pengadaan.show', $pengadaan)
            ->with('warning', 'Permohonan pengadaan ditolak dengan catatan.');
    }
}
